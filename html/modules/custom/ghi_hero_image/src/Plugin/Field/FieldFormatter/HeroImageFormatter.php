<?php

namespace Drupal\ghi_hero_image\Plugin\Field\FieldFormatter;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Link;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Render\Markup;
use Drupal\Core\Url;
use Drupal\ghi_subpages\Entity\SubpageNodeInterface;
use Drupal\hpc_common\Helpers\ThemeHelper;
use Drupal\responsive_image\Plugin\Field\FieldFormatter\ResponsiveImageFormatter;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of the 'ghi_hero_image' formatter.
 *
 * @FieldFormatter(
 *   id = "ghi_hero_image",
 *   label = @Translation("Hero image"),
 *   field_types = {"ghi_hero_image"}
 * )
 */
class HeroImageFormatter extends ResponsiveImageFormatter implements ContainerFactoryPluginInterface {

  /**
   * The hero image manager class.
   *
   * @var \Drupal\ghi_hero_image\HeroImageManager
   */
  public $heroImageManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->heroImageManager = $container->get('hero_image.manager');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return [
      'include_credits' => FALSE,
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
    $link_file = FALSE;
    // Check if the formatter involves a link.
    if ($this->getSetting('image_link') == 'content') {
      if (!$entity->isNew()) {
        $url = $entity->toUrl();
      }
    }
    elseif ($this->getSetting('image_link') == 'file') {
      $link_file = TRUE;
    }

    $entity = $items->getEntity();
    $item = !$items->isEmpty() ? (object) $items->get(0)->getValue() ?? NULL : NULL;
    $item_source = $item ? $item->source : NULL;
    if (!$item_source) {
      $item_source = $this->heroImageManager->getDefaultItemSource($items);
    }

    if ($item_source == 'inherit') {
      $build_settings = [
        'label' => 'hidden',
        'settings' => $this->getSettings(),
      ];
      if ($entity instanceof SubpageNodeInterface && $parent_image = $entity->getParentBaseNode()?->getImage()) {
        return $parent_image->view($build_settings);
      }
      elseif ($parent_image = $this->getParentImage($entity)) {
        return $parent_image->view($build_settings);
      }
      return;
    }

    $image_properties = $this->heroImageManager->getImageProperties($items, $this);
    $image_url = $image_properties['image_url'] ?? NULL;
    $caption = $image_properties['caption'] ?? NULL;
    $credit = $image_properties['credit'] ?? NULL;

    if ($image_url) {
      $image_build = [
        '#theme' => 'ghi_image',
        '#responsive_image_style' => $responsive_image_style,
        '#url' => $image_url,
        '#caption' => $caption ? Markup::create($caption) : NULL,
        '#credit' => $include_credits ? $credit : NULL,
      ];

      if ($link_file) {
        $url = Url::fromUri($image_url);
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
