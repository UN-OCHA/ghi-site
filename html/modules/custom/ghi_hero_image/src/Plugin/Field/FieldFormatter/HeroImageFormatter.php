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
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->entitiesQuery = $container->get('plugin.manager.endpoint_query_manager')->createInstance('plan_entities_query');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {

    // This all assumes to show web attachments for the moment, which obviously
    // only works for plan sections.
    $element = [];
    $entity = $items->getEntity();
    $base_object = BaseObjectHelper::getBaseObjectFromNode($entity);

    $plan_object = $base_object && $base_object->bundle() == 'plan' ? $base_object : NULL;
    if (!$plan_object) {
      return $element;
    }
    $this->entitiesQuery->setPlaceholder('plan_id', $plan_object->field_original_id->value);
    $attachments = $plan_object ? $this->entitiesQuery->getWebContentFileAttachments() : NULL;
    if (empty($attachments)) {
      return $element;
    }

    if ($items->isEmpty()) {
      $attachment = $attachments ? reset($attachments) : NULL;
      $image = $attachment ? ThemeHelper::theme('image', [
        '#uri' => $attachment->url,
        '#attributes' => [
          'style' => 'width: 100%',
        ],
      ], TRUE, FALSE) : NULL;
      $element[0]['source'] = [
        '#type' => 'item',
        '#markup' => Markup::create($image),
      ];
    }
    else {
      $item = (object) $items->get(0)->getValue();
      if ($item->source == 'hpc_webcontent_file_attachment') {
        // Somehow find the right attachment based on the configuration. For
        // the time being we just take the first one, which is the same effect
        // as having the field empty.
        $attachment = $attachments ? reset($attachments) : NULL;

        $preview_image = $attachment ? ThemeHelper::theme('image', [
          '#uri' => $attachment->url,
          '#attributes' => [
            'style' => 'width: 100%',
          ],
        ], TRUE, FALSE) : NULL;
        $element[0]['source'] = [
          '#type' => 'item',
          '#markup' => Markup::create($preview_image),
        ];
      }
    }

    return $element;
  }

}
