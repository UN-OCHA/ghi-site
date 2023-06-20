<?php

namespace Drupal\ghi_hero_image\Plugin\Field\FieldFormatter;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Link;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Render\Markup;
use Drupal\ghi_base_objects\Helpers\BaseObjectHelper;
use Drupal\ghi_subpages\Entity\SubpageNodeInterface;
use Drupal\hpc_common\Helpers\ThemeHelper;
use Drupal\responsive_image\Plugin\Field\FieldFormatter\ResponsiveImageFormatter;
use Symfony\Component\DependencyInjection\ContainerInterface;

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
  public static function defaultSettings() {
    return [
      'include_credits' => FALSE,
      'crop_image' => TRUE,
    ] + parent::defaultSettings();
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

    $include_credits = $this->getSetting('include_credits');
    $repsonsive_image_style_id = $this->getSetting('responsive_image_style') ?: 'hero';

    // Collect cache tags to be added for each item in the field.
    /** @var \Drupal\responsive_image\Entity\ResponsiveImageStyle $responsive_image_style */
    $responsive_image_style = $this->responsiveImageStyleStorage->load($repsonsive_image_style_id);
    $image_styles_to_load = [];
    $cache_tags = [];
    if ($responsive_image_style) {
      $cache_tags = Cache::mergeTags($cache_tags, $responsive_image_style->getCacheTags());
      $image_styles_to_load = $responsive_image_style->getImageStyleIds();
    }

    /** @var \Drupal\image\Entity\ImageStyle[] $image_styles */
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
    $credit = NULL;
    $caption = NULL;

    $item = !$items->isEmpty() ? (object) $items->get(0)->getValue() ?? NULL : NULL;
    $item_source = $item ? $item->source : NULL;

    if (!$item_source && $attachments = $this->getPlanWebContentAttachments($items)) {
      $item_source = 'hpc_webcontent_file_attachment';
    }

    $item_settings = $item && property_exists($item, 'settings') && is_array($item->settings) ? ($item->settings[$item_source] ?? []) : [];
    switch ($item_source) {
      case 'hpc_webcontent_file_attachment':
        // Find the right attachment based on the configuration, or fallback to
        // the first available attachment.
        $attachments = $this->getPlanWebContentAttachments($items);
        $attachment_id = $item_settings['attachment_id'] ?? array_key_first($attachments);
        if ($attachment_id && !empty($attachments[$attachment_id])) {
          /** @var \Drupal\ghi_plans\ApiObjects\Attachments\FileAttachment $attachment */
          $attachment = $attachments[$attachment_id];
          $image_url = $attachment->getUrl();
          $credit = $attachment->getCredit();
        }
        break;

      case 'smugmug_api':
        $image_id = $item_settings['image_id'] ?? NULL;
        $image_urls = $image_id ? $this->smugmugImage->getImageSizes($image_id) : NULL;
        $image_url = $image_urls['X3LargeImageUrl'] ?? ($image_urls['LargestImageUrl'] ?? NULL);
        $image = $image_id ? $this->smugmugImage->getImage($image_id) : NULL;
        if ($image) {
          $caption_format_parents = ['FormattedValues', 'Caption', 'html'];
          $caption = NestedArray::getValue($image, $caption_format_parents) ?? ($image['Caption'] ?? NULL);
        }
        break;

      case 'inherit':
        $build_settings = [
          'label' => 'hidden',
          'settings' => $this->getSettings(),
        ];
        if ($entity instanceof SubpageNodeInterface && $parent_image = $entity->getParentNode()?->getImage()) {
          return $parent_image->view($build_settings);
        }
        elseif ($parent_image = $this->getParentImage($entity)) {
          return $parent_image->view($build_settings);
        }
        break;
    }

    if ($image_url) {
      $image_build = [
        '#theme' => 'ghi_image',
        '#responsive_image_style' => $responsive_image_style,
        '#url' => $image_url,
        '#caption' => $caption ? Markup::create($caption) : NULL,
        '#credit' => $include_credits ? $credit : NULL,
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
   * @return \Drupal\ghi_plans\ApiObjects\Attachments\FileAttachment[]
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
