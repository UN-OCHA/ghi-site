<?php

namespace Drupal\ghi_plans\Plugin\ConfigurationContainerItem;

use Drupal\Core\Form\FormStateInterface;
use Drupal\ghi_configuration_container\ConfigurationContainerItemPluginBase;
use Drupal\node\NodeInterface;

/**
 * Provides an entity counter item for configuration containers.
 *
 * @todo This needs implementation.
 *
 * @ConfigurationContainerItem(
 *   id = "attachment_data",
 *   label = @Translation("Attachment data"),
 * )
 */
class AttachmentData extends ConfigurationContainerItemPluginBase {

  /**
   * {@inheritdoc}
   */
  public function buildForm($element, FormStateInterface $form_state) {
    $element = parent::buildForm($element, $form_state);
    $element['label']['#description'] = $this->t('Leave empty to use a default label');

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function getValue() {

    return NULL;
  }

  /**
   * Access callback.
   *
   * @param array $context
   *   A context array.
   * @param array $access_requirements
   *   An array with access requirements.
   *
   * @return bool
   *   The access status.
   */
  public function access(array $context, array $access_requirements) {
    $allowed = TRUE;
    if (empty($context['page_node'])) {
      return FALSE;
    }
    if (!empty($access_requirements['node_type'])) {
      $allowed = $allowed && $this->accessByNodeType($context['page_node'], $access_requirements['node_type']);
    }
    return $allowed;
  }

  /**
   * Check access by node type.
   *
   * @param \Drupal\node\NodeInterface $page_node
   *   A node object.
   * @param array $valid_node_types
   *   An array with the valid node types.
   *
   * @return bool
   *   The access status.
   */
  public function accessByNodeType(NodeInterface $page_node, array $valid_node_types) {
    return in_array($page_node->bundle(), $valid_node_types);
  }

}
