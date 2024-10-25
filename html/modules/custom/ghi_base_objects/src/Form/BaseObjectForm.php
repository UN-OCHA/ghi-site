<?php

namespace Drupal\ghi_base_objects\Form;

use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\Messenger;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Form controller for Base object edit forms.
 *
 * @ingroup ghi_base_objects
 */
class BaseObjectForm extends ContentEntityForm {

  /**
   * The current user account.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $account;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    // Instantiates this form class.
    $instance = parent::create($container);
    $instance->account = $container->get('current_user');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    // Just add the entity to the form array, so that modules can easily access
    // the entity in form_later hooks.
    $form['#entity'] = $this->entity;
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $entity = $this->entity;
    $status = parent::save($form, $form_state);

    // Delete warnings first.
    $this->messenger()->deleteByType(Messenger::TYPE_WARNING);

    switch ($status) {
      case SAVED_NEW:
        $this->messenger()->addMessage($this->t('Created the %label Base object.', [
          '%label' => $entity->label(),
        ]));
        break;

      default:
        $this->messenger()->addMessage($this->t('Saved the %label Base object.', [
          '%label' => $entity->label(),
        ]));
    }
    $form_state->setRedirect('entity.base_object.canonical', ['base_object' => $entity->id()]);
  }

}
