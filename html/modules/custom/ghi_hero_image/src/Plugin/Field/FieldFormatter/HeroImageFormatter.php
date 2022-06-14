<?php

namespace Drupal\ghi_hero_image\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Render\Markup;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\ghi_base_objects\Helpers\BaseObjectHelper;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\hpc_common\Helpers\ThemeHelper;

/**
 * Plugin implementation of the 'ghi_hero_image' formatter.
 *
 * @FieldFormatter(
 *   id = "ghi_hero_image",
 *   label = @Translation("Default"),
 *   field_types = {"ghi_hero_image"}
 * )
 */
class HeroImageFormatter extends FormatterBase implements ContainerFactoryPluginInterface {

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
  public function viewElements(FieldItemListInterface $items, $langcode) {

    // This all assumes to show web attachments for the moment, which obviously
    // only works for plan sections.
    $element = [];

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
        // Somehow find the right attachment based on the configuration.
        $attachments = $this->getPlanWebContentAttachments($items);
        $attachment = $attachments ? reset($attachments) : NULL;
        $attachment_id = $item->settings[$item->source]['attachment_id'] ?? NULL;
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
    }

    if ($image_url) {
      $preview_image = ThemeHelper::theme('imagecache_external', [
        '#uri' => $image_url,
        '#style_name' => 'hero_image',
        '#attributes' => [
          'style' => 'width: 100%',
        ],
      ], TRUE, FALSE);
      $element[0]['source'] = [
        '#type' => 'item',
        '#markup' => Markup::create($preview_image),
      ];
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
    $base_object = BaseObjectHelper::getBaseObjectFromNode($entity);
    $plan_object = $base_object && $base_object->bundle() == 'plan' ? $base_object : NULL;
    if (!$plan_object) {
      return [];
    }
    $this->entitiesQuery->setPlaceholder('plan_id', $plan_object->field_original_id->value);
    return $this->entitiesQuery->getWebContentFileAttachments();
  }

}
