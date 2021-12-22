<?php

namespace Drupal\ghi_element_sync\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\ghi_subpages\Helpers\SubpageHelper;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Form class for the element sync configuration form.
 */
class ConfigForm extends ConfigFormBase {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Public constructor.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'ghi_element_sync_config_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    $form = parent::buildForm($form, $form_state);
    $config = $this->config('ghi_element_sync.settings');

    $form['sync_source'] = [
      '#type' => 'url',
      '#title' => $this->t('Sync source URL'),
      '#description' => $this->t('Enter the url for this sync source'),
      '#default_value' => $config->get('sync_source') ?? NULL,
    ];

    $form['access_key'] = [
      '#type' => 'password',
      '#title' => $this->t('Access key'),
      '#description' => $this->t('Enter the access key for this remote source'),
      '#default_value' => $config->get('access_key') ?? NULL,
    ];

    $basic_auth = $config->get('basic_auth') ?? [
      'user' => NULL,
      'pass' => NULL,
    ];

    $form['basic_auth'] = [
      '#type' => 'details',
      '#title' => $this->t('Basic auth'),
      '#open' => !empty($basic_auth),
      '#tree' => TRUE,
    ];
    $form['basic_auth']['user'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Username'),
      '#description' => $this->t('Enter the basic auth username'),
      '#default_value' => $basic_auth['user'] ?? NULL,
    ];
    $form['basic_auth']['pass'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Password'),
      '#description' => $this->t('Enter the basic auth password'),
      '#default_value' => $basic_auth['pass'] ?? NULL,
    ];

    $available_node_types = array_merge(['section'], SubpageHelper::SUPPORTED_SUBPAGE_TYPES);
    $form['node_types'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Node types'),
      '#description' => $this->t('Select all node types that should allow to import plan elements from the defined source.'),
      '#options' => array_map(function ($item) {
        return $this->entityTypeManager->getStorage('node_type')->load($item)->get('name');
      }, array_combine($available_node_types, $available_node_types)),
      '#default_value' => $config->get('node_types') ?? NULL,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->config('ghi_element_sync.settings');
    $config->set('sync_source', $form_state->getValue('sync_source'));
    $config->set('access_key', $form_state->getValue('access_key'));
    $config->set('basic_auth', [
      'user' => $form_state->getValue(['basic_auth', 'user']),
      'pass' => $form_state->getValue(['basic_auth', 'pass']),
    ]);
    $config->set('node_types', array_values(array_filter($form_state->getValue('node_types'))));
    $config->save();
    return parent::submitForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'ghi_element_sync.settings',
    ];
  }

}
