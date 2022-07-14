<?php

namespace Drupal\ghi_form_elements\Element;

use Drupal\Core\Render\Element\FormElement;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element;
use Drupal\Core\Link;
use Drupal\Core\Url;

/**
 * Provides an attachment select element.
 *
 * @FormElement("webcontent_file_select")
 */
class WebcontentFileSelect extends FormElement {

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
    ];
  }

  /**
   * Process the attachment select form element.
   *
   * This is called during form build. Note that it is not possible to store
   * any arbitrary data inside the form_state object.
   */
  public static function processWebcontentFileSelect(array &$element, FormStateInterface $form_state) {
    /** @var \Drupal\ghi_base_objects\Entity\BaseObjectInterface $plan_object */
    $plan_object = $element['#plan_object'];
    if (!$plan_object) {
      // This is probably a Fields UI backend page.
      return $element;
    }
    $entity_query = self::getPlanEntitiesQuery($plan_object->getSourceId());
    $attachments = $entity_query->getWebContentFileAttachments($plan_object);
    $states = $element['#states'] ?? [];

    $file_options = [];
    if (!empty($attachments)) {
      foreach ($attachments as $attachment) {
        $file_options[$attachment->id] = [
          'id' => $attachment->id,
          'title' => $attachment->title,
          'file_name' => $attachment->file_name,
          'file_url' => Link::fromTextAndUrl($attachment->url, Url::fromUri($attachment->url, [
            'external' => TRUE,
            'attributes' => [
              'target' => '_blank',
            ],
          ])),
          'preview' => [
            'data' => [
              '#theme' => 'imagecache_external',
              '#style_name' => 'thumbnail',
              '#uri' => $attachment->url,
            ],
          ],
        ];
      }
    }

    $table_header = [
      'id' => t('Attachment ID'),
      'title' => t('Title'),
      'file_name' => t('File name'),
      'file_url' => t('File URL'),
      'preview' => t('Preview'),
    ];

    // Set the defaults.
    $submitted_values = array_filter((array) $form_state->getValue($element['#parents']));
    $values = $submitted_values + (array) $element['#default_value'];
    $default_attachment = !empty($values['attachment_id']) ? $values['attachment_id'] : ($element['#default_value'] ?? (count($attachments) ? array_key_first($attachments) : NULL));

    $element['attachment_id'] = [
      '#type' => 'tableselect',
      '#tree' => TRUE,
      '#header' => $table_header,
      '#validated' => TRUE,
      '#options' => $file_options,
      '#default_value' => $default_attachment,
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

  /**
   * Get the plan entities query service.
   *
   * @param int $plan_id
   *   The plan id for which a query should be build.
   *
   * @return \Drupal\ghi_plans\Plugin\EndpointQuery\PlanEntitiesQuery
   *   The plan entities query plugin.
   */
  public static function getPlanEntitiesQuery($plan_id) {
    $query_handler = self::getEndpointQueryManager()->createInstance('plan_entities_query');
    $query_handler->setPlaceholder('plan_id', $plan_id);
    return $query_handler;
  }

}
