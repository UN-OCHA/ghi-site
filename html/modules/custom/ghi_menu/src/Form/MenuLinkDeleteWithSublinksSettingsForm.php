<?php

namespace Drupal\ghi_menu\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Settings form for the menu links with sublinks deletion module.
 */
class MenuLinkDeleteWithSublinksSettingsForm extends ConfigFormBase {

  const CONFIG_NAME = 'menu_links_with_sublinks_deletion.settings';

  /**
   * Entity Type Manager.
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * Constructs the form.
   */
  public function __construct(ConfigFactoryInterface $config_factory, EntityTypeManagerInterface $entity_type_manager) {
    parent::__construct($config_factory);
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'menu_links_with_sublinks_deletion_settings_form';
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
