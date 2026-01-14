<?php

namespace Drupal\ghi_plans\Plugin\Field\FieldWidget;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines the 'ghi_plans_plan_caseload' field widget.
 *
 * @FieldWidget(
 *   id = "ghi_plans_plan_caseload",
 *   label = @Translation("Plan caseload"),
 *   field_types = {"ghi_plans_plan_caseload"},
 * )
 */
class PlanCaseloadWidget extends WidgetBase {

  /**
   * The attachment query.
   *
   * @var \Drupal\ghi_plans\Plugin\FabricQuery\AttachmentQuery
   */
  protected $attachmentQuery;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->attachmentQuery = $container->get('plugin.manager.fabric_query_manager')->createInstance('attachment');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    /** @var \Drupal\ghi_base_objects\Entity\BaseObjectInterface $entity */
    $entity = $form['#entity'];
    if (!$entity || $entity->bundle() != 'plan') {
      return $element;
    }
    $plan_id = $entity->field_original_id->value ?? NULL;
    if (!$plan_id) {
      return $element;
    }
    $attachments = $this->attachmentQuery->getAttachmentsByObject('plan', $plan_id, 'caseload');
    $attachment_options = $attachments ? array_map(function ($attachment) {
      /** @var \Drupal\ghi_plans\ApiObjects\Attachments\AttachmentInterface $attachment */
      return ($attachment->getTitle() ?? $attachment->getDescription()) . ' (' . $attachment->id() . ')';
    }, $attachments) : [];

    $element += [
      '#type' => 'select',
      '#options' => [0 => $this->t('Automatic')] + $attachment_options,
      '#default_value' => $items[$delta]->attachment_id,
    ];
    return ['attachment_id' => $element];
  }

}
