<?php

namespace Drupal\ghi_form_elements\Element;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Attribute\FormElement;
use Drupal\Core\Render\Element;
use Drupal\Core\Render\Element\FormElementBase;
use Drupal\ghi_plans\Traits\PlanQueryTrait;

/**
 * Provides a webcontent file select element.
 */
#[FormElement('webcontent_file_select')]
class WebcontentFileSelect extends FormElementBase {

  use PlanQueryTrait;

  /**
   * {@inheritdoc}
   */
  public function getInfo() {
    $class = get_class($this);
    return [
      '#default_value' => NULL,
      '#input' => TRUE,
      '#tree' => TRUE,
      '#process' => [
        [$class, 'processWebcontentFileSelect'],
        [$class, 'processGroup'],
      ],
      '#pre_render' => [
        [$class, 'preRenderWebcontentFileSelect'],
        [$class, 'preRenderGroup'],
      ],
      '#theme_wrappers' => ['form_element'],
      '#multiple' => FALSE,
      '#plan_object' => NULL,
      '#base_object' => NULL,
    ];
  }

  /**
   * Process the webcontent file select form element.
   *
   * This is called during form build. Note that it is not possible to store
   * any arbitrary data inside the form_state object.
   */
  public static function processWebcontentFileSelect(array &$element, FormStateInterface $form_state) {
    if (empty($element['#default_value']['file_asset_id']) && !empty($element['#default_value']['attachment_id'])) {
      $element['#default_value']['file_asset_id'] = $element['#default_value']['attachment_id'];
      unset($element['#default_value']['attachment_id']);
    }
    /** @var \Drupal\ghi_base_objects\Entity\BaseObjectInterface $plan_object */
    $plan_object = $element['#plan_object'];
    if (!$plan_object) {
      // This is probably a Fields UI backend page.
      return $element;
    }
    /** @var \Drupal\ghi_base_objects\Entity\BaseObjectInterface $base_object */
    $base_object = $element['#base_object'] ?: $plan_object;
    $file_asset_query = self::getFileAssetQuery();
    $file_assets = $file_asset_query->getFileAssetsByObject($base_object->bundle(), $base_object->getSourceId());
    $states = $element['#states'] ?? [];

    $file_options = [];
    if (!empty($file_assets)) {
      foreach ($file_assets as $file_asset) {
        // @todo Add check for image files before trying to show a preview.
        $file_options[$file_asset->id()] = [
          'id' => $file_asset->id(),
          'title' => $file_asset->getName(),
          'file_name' => $file_asset->getName(),
          'preview' => [
            'data' => [
              '#theme' => 'imagecache_external',
              '#style_name' => 'thumbnail',
              '#uri' => $file_asset->getUrl()->toString(),
              '#attributes' => [
                'title' => $file_asset->getUrl()->toString(),
              ],
            ],
          ],
        ];
      }
    }

    $table_header = [
      'id' => t('File asset ID'),
      'title' => t('Title'),
      'file_name' => t('File name'),
      'preview' => t('Preview'),
    ];

    // Set the defaults.
    $submitted_values = array_filter((array) $form_state->getValue($element['#parents']));
    $values = $submitted_values + (array) $element['#default_value'];
    $default_value = !empty($values['file_asset_id']) ? $values['file_asset_id'] : ($element['#default_value']['file_asset_id'] ?? (count($file_assets) ? array_key_first($file_assets) : NULL));
    $element['file_asset_id'] = [
      '#type' => 'tableselect',
      '#tree' => TRUE,
      '#header' => $table_header,
      '#validated' => TRUE,
      '#options' => $file_options,
      '#default_value' => $default_value,
      '#multiple' => FALSE,
      '#empty' => t('There are no images yet.'),
      '#required' => TRUE,
      '#states' => $states,
    ];
    return $element;
  }

  /**
   * Prerender callback.
   */
  public static function preRenderWebcontentFileSelect(array $element) {
    $element['#attributes']['type'] = 'webcontent_file_select';
    Element::setAttributes($element, ['id', 'name', 'value']);
    // Sets the necessary attributes, such as the error class for validation.
    // Without this line the field will not be hightlighted, if an error
    // occurred.
    static::setAttributes($element, ['form-webcontent-file-select']);
    return $element;
  }

  /**
   * Get the endpoint query manager service.
   *
   * @return \Drupal\hpc_api\Query\EndpointQueryManager
   *   The endpoint query manager service.
   */
  private static function getEndpointQueryManager() {
    return \Drupal::service('plugin.manager.endpoint_query_manager');
  }

}
