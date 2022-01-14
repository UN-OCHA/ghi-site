<?php

namespace Drupal\ghi_hero_image\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Render\Markup;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\ghi_base_objects\Helpers\BaseObjectHelper;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\hpc_common\Helpers\ThemeHelper;
use Drupal\ghi_plans\Plugin\EndpointQuery\PlanEntitiesQuery;

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
  public function __construct($plugin_id, $plugin_definition, FieldDefinitionInterface $field_definition, array $settings, $label, $view_mode, array $third_party_settings, PlanEntitiesQuery $entities_query) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $label, $view_mode, $third_party_settings);

    $this->entitiesQuery = $entities_query;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $plugin_id,
      $plugin_definition,
      $configuration['field_definition'],
      $configuration['settings'],
      $configuration['label'],
      $configuration['view_mode'],
      $configuration['third_party_settings'],
      $container->get('plugin.manager.endpoint_query_manager')->createInstance('plan_entities_query')
    );
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
        // Somehow fine the right attachment based on the configuration. For
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
