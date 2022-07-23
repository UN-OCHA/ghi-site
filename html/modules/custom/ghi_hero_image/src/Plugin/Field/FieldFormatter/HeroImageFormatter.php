<?php

namespace Drupal\ghi_hero_image\Plugin\Field\FieldFormatter;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Link;
use Drupal\Core\Render\Markup;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\ghi_base_objects\Helpers\BaseObjectHelper;
use Drupal\ghi_subpages\Entity\SubpageNodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\hpc_common\Helpers\ThemeHelper;
use Drupal\responsive_image\Plugin\Field\FieldFormatter\ResponsiveImageFormatter;

/**
 * Plugin implementation of the 'ghi_hero_image' formatter.
 *
 * @FieldFormatter(
 *   id = "ghi_hero_image",
 *   label = @Translation("Default"),
 *   field_types = {"ghi_hero_image"}
 * )
 */
class HeroImageFormatter extends ResponsiveImageFormatter implements ContainerFactoryPluginInterface {

  /**
   * The attachment query.
   *
   * @var \Drupal\ghi_plans\Plugin\EndpointQuery\PlanEntitiesQuery
   */
  public $entitiesQuery;

  /**
   * The SmugMug image service.
   *
   * @var \Drupal\smugmug_api\Service\Image
   */
  public $smugmugImage;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->entitiesQuery = $container->get('plugin.manager.endpoint_query_manager')->createInstance('plan_entities_query');
    $instance->smugmugImage = $container->get('smugmug_api.image');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function prepareView(array $entities_items) {
    // This is only here to prevent EntityReferenceFormatterBase::prepareView()
    // to create errors, as this hero image field is not an actual reference
    // and can't be preloaded in the way that EntityReferenceFormatterBase
    // expects.
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {

    // This all assumes to show web attachments for the moment, which obviously
    // only works for plan sections.
    $element = [];

    $repsonsive_image_style_id = $this->getSetting('responsive_image_style') ?: 'hero';

    // Collect cache tags to be added for each item in the field.
    $responsive_image_style = $this->responsiveImageStyleStorage->load($repsonsive_image_style_id);
    $image_styles_to_load = [];
    $cache_tags = [];
    if ($responsive_image_style) {
      $cache_tags = Cache::mergeTags($cache_tags, $responsive_image_style->getCacheTags());
      $image_styles_to_load = $responsive_image_style->getImageStyleIds();
    }

    $image_styles = $this->imageStyleStorage->loadMultiple($image_styles_to_load);
    foreach ($image_styles as $image_style) {
      $cache_tags = Cache::mergeTags($cache_tags, $image_style->getCacheTags());
    }

    $entity = $items->getEntity();
    $url = NULL;
    // Check if the formatter involves a link.
    if ($this->getSetting('image_link') == 'content') {
      if (!$entity->isNew()) {
        $url = $entity->toUrl();
      }
    }
    elseif ($this->getSetting('image_link') == 'file') {
      $link_file = TRUE;
    }

    $image_url = NULL;
    if ($items->isEmpty() && $attachments = $this->getPlanWebContentAttachments($items)) {
      // If there is noting configured but we managed to get hold of some
      // webcontent attachments, just use the first one.
      $attachment = reset($attachments);
      $image_url = $attachment->url;
    }
    elseif (!$items->isEmpty()) {
      // Otherwise see what's been setup.
      $item = (object) $items->get(0)->getValue();
      if ($item->source == 'hpc_webcontent_file_attachment') {
        // Find the right attachment based on the configuration, or fallback to
        // the first available attachment.
        $attachments = $this->getPlanWebContentAttachments($items);
        $attachment = $attachments ? reset($attachments) : NULL;
        $attachment_id = $item->settings[$item->source]['attachment_id'] ?? array_key_first($attachments);
        if ($attachment_id && !empty($attachments[$attachment_id])) {
          $attachment = $attachments[$attachment_id];
          $image_url = $attachment->url;
        }
      }
      if ($item->source == 'smugmug_api') {
        $image_id = $item->settings[$item->source]['image_id'] ?? NULL;
        $image_urls = $image_id ? $this->smugmugImage->getImageSizes($image_id) : NULL;
        $image_url = $image_urls['X3LargeImageUrl'] ?? NULL;
      }
      if ($item->source == 'inherit') {
        if ($entity instanceof SubpageNodeInterface && $parent_image = $entity->getParentNode()->getImage()) {
          return $parent_image->view();
        }
        elseif ($parent_image = $this->getParentImage($entity)) {
          return $parent_image->view();
        }
      }
    }

    if ($image_url) {
      $image_build = [
        '#theme' => $responsive_image_style ? 'imagecache_external_responsive' : 'image',
        '#uri' => $image_url,
        '#responsive_image_style_id' => $responsive_image_style ? $responsive_image_style->id() : NULL,
        '#attributes' => [
          'style' => 'width: 100%',
        ],
      ];

      if (isset($link_file)) {
        $url = $image_url;
      }

      if ($url) {
        $image_rendered = ThemeHelper::render($image_build, FALSE);
        $element[0] = Link::fromTextAndUrl(Markup::create($image_rendered), $url)->toRenderable();
      }
      else {
        $element[0] = $image_build;
      }
    }

    return $element;
  }

  /**
   * Get the webcontent file attachments of a plan if possible.
   *
   * @param \Drupal\Core\Field\FieldItemListInterface $items
   *   The field values to be rendered.
   *
   * @return \Drupal\ghi_plans\ApiObjects\Attachments\AttachmentInterface[]
   *   An array of attachment objects.
   */
  private function getPlanWebContentAttachments(FieldItemListInterface $items) {
    $entity = $items->getEntity();
    $base_object = BaseObjectHelper::getBaseObjectFromNode($entity, 'plan');
    $plan_object = $base_object && $base_object->bundle() == 'plan' ? $base_object : NULL;
    if (!$plan_object) {
      return [];
    }
    $this->entitiesQuery->setPlaceholder('plan_id', $plan_object->field_original_id->value);
    return $this->entitiesQuery->getWebContentFileAttachments();
  }

  /**
   * Get a non-empty image field from a parent.
   *
   * @param \Drupal\Core\Entity\FieldableEntityInterface $entity
   *   The entity to start.
   *
   * @return \Drupal\Core\Field\FieldItemListInterface
   *   A field item list instance.
   */
  private function getParentImage(FieldableEntityInterface $entity) {
    $field_definitions = $entity->getFieldDefinitions();
    foreach ($field_definitions as $field_definition) {
      if ($field_definition->getType() != 'entity_reference') {
        continue;
      }
      $candidate = $entity->get($field_definition->getName())->entity;
      if (!$candidate || !$candidate instanceof FieldableEntityInterface) {
        continue;
      }
      // Check if there is an image field.
      $_parent_candidates = [];
      foreach ($candidate->getFieldDefinitions() as $_field_definition) {
        if ($_field_definition->getType() == 'entity_reference') {
          $_parent_candidates[] = $candidate;
        }
        if ($_field_definition->getType() != 'ghi_hero_image') {
          continue;
        }
        $image_field = $candidate->get($_field_definition->getName());
        if (!$image_field->isEmpty()) {
          return $image_field;
        }
      }
      // If we didn't find an image, but we did find more potential parents,
      // repeat to see if we can find something up the reference chain.
      if (!empty($_parent_candidates)) {
        foreach ($_parent_candidates as $_parent_candidate) {
          $_parent_image = $this->getParentImage($_parent_candidate);
          if ($_parent_image) {
            return $_parent_image;
          }
        }
      }
    }
  }

}
