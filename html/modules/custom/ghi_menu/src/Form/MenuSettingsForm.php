<?php

namespace Drupal\ghi_menu\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Settings form for the menu links with sublinks deletion module.
 */
class MenuSettingsForm extends ConfigFormBase {

  const CONFIG_NAME = 'ghi_menu.settings';

  /**
   * Entity Type Manager.
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    // Instantiates this form class.
    $instance = parent::create($container);
    $instance->entityTypeManager = $container->get('entity_type.manager');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'ghi_menu_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [self::CONFIG_NAME];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config(self::CONFIG_NAME);
    $selected_menus = $config->get('enabled_menus') ?? [];

    // Load all menus.
    $menus = $this->entityTypeManager->getStorage('menu')->loadMultiple();
    $options = [];
    foreach ($menus as $menu) {
      $options[$menu->id()] = $menu->label();
    }

    $form['enabled_menus'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Select menus with automatic sublinks deletion:'),
      '#options' => $options,
      '#default_value' => $selected_menus,
      '#description' => $this->t('When deleting a parent menu item, all sublinks will also be deleted automatically for selected menus.'),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $selected = array_filter($form_state->getValue('enabled_menus'));
    $this->configFactory->getEditable(self::CONFIG_NAME)
      ->set('enabled_menus', $selected)
      ->save();

    parent::submitForm($form, $form_state);
  }

}
