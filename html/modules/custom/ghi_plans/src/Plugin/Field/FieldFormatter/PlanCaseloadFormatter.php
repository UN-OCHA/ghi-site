<?php

namespace Drupal\ghi_plans\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Field\FieldItemListInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of the 'ghi_plans_plan_caseload' formatter.
 *
 * @FieldFormatter(
 *   id = "ghi_plans_plan_caseload",
 *   label = @Translation("Default"),
 *   field_types = {"ghi_plans_plan_caseload"}
 * )
 */
class PlanCaseloadFormatter extends FormatterBase {

  /**
   * The attachment query class.
   *
   * @var \Drupal\ghi_plans\Plugin\EndpointQuery\AttachmentQuery
   */
  protected $attachmentQuery;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->attachmentQuery = $container->get('plugin.manager.endpoint_query_manager')->createInstance('attachment_query');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $element = [];
    foreach ($items as $delta => $item) {
      $value = $this->t('Automatic');
      if ($item->attachment_id) {
        $attachment = $this->attachmentQuery->getAttachment($item->attachment_id);
        $value = $attachment->getTitle();
      }
      $element[$delta]['attachment_id'] = [
        '#plain_text' => $value,
      ];
    }
    return $element;
  }

}
