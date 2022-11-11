<?php

namespace Drupal\ghi_base_objects\Plugin\views\field;

use Drupal\Core\Form\FormStateInterface;
use Drupal\views\Plugin\views\field\BulkForm;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines a base object operations bulk form element.
 *
 * @ViewsField("base_object_bulk_form")
 */
class BaseObjectBulkForm extends BulkForm {

  /**
   * The user account service.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->currentUser = $container->get('current_user');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  protected function emptySelectedMessage() {
    return $this->t('No content selected.');
  }

  /**
   * {@inheritdoc}
   */
  public function viewsForm(&$form, FormStateInterface $form_state) {
    // Only allow access to the bulk form to user 1.
    if ($this->currentUser->id() != 1) {
      // Remove the default actions build array.
      unset($form['actions']);
    }
    else {
      parent::viewsForm($form, $form_state);
    }
  }

}
