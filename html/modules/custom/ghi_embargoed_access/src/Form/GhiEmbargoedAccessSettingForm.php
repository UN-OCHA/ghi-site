<?php

namespace Drupal\ghi_embargoed_access\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a settings form for embargoed access.
 */
class GhiEmbargoedAccessSettingForm extends ConfigFormBase {

  /**
   * The embargoed access manager service.
   *
   * @var \Drupal\ghi_embargoed_access\EmbargoedAccessManager
   */
  protected $embargoedAccessManager;

  /**
   * The section manager.
   *
   * @var \Drupal\ghi_subpages\SubpageManager
   */
  protected $subpageManager;

  /**
   * The password hashing service.
   *
   * @var \Drupal\Core\Password\PasswordInterface
   */
  protected $password;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    /** @var static $instance */
    $instance = parent::create($container);
    $instance->embargoedAccessManager = $container->get('ghi_embargoed_access.manager');
    $instance->subpageManager = $container->get('ghi_subpages.manager');
    $instance->password = $container->get('password');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'ghi_embargoed_access.settings',
      'entity_access_password.settings',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'ghi_embargoed_access_settings';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildForm($form, $form_state);
    $config = $this->config('ghi_embargoed_access.settings');

    $form['enabled'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Global protection enabled'),
      '#description' => $this->t('Check to use the global protection for embargoed access.'),
      '#default_value' => $config->get('enabled'),
    ];
    $form['password'] = [
      '#type' => 'password',
      '#title' => $this->t('Global password'),
      '#description' => $this->t('If left empty will not overwrite current password (if any).'),
      '#states' => [
        'visible' => [
          'input[name="enabled"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $rows = [];
    foreach ($this->embargoedAccessManager->getEmbargoedNodeTypes() as $node_type) {
      if ($this->subpageManager->isSubpageType($node_type)) {
        continue;
      }
      $rows[] = [
        $node_type->label(),
        count($this->embargoedAccessManager->getEmbargoedNodesForNodeType($node_type)),
        Link::fromTextAndUrl($this->t('show list'), Url::fromUserInput('/admin/config/ghi/embargoed-access/embargoed-pages/' . $node_type->id())),
      ];
    }

    // Add a summary table with links to backend listings.
    $form['summary'] = [
      '#type' => 'container',
      '#states' => [
        'visible' => [
          'input[name="enabled"]' => ['checked' => TRUE],
        ],
      ],
      [
        '#type' => 'html_tag',
        '#tag' => 'h6',
        '#value' => $this->t('Summary of protected pages by content type'),
      ],
      [
        '#theme' => 'table',
        '#header' => [
          $this->t('Content type'),
          $this->t('# protected pages'),
          '',
        ],
        '#rows' => $rows,
        '#empty' => $this->t('It seems no content type is currently configured for protection. If you think this is an error, please contact an administrator'),
      ],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->config('ghi_embargoed_access.settings');
    $entity_access_config = $this->config('entity_access_password.settings');

    $changed_enabled = $config->get('enabled') != $form_state->getValue('enabled');
    $config->set('enabled', $form_state->getValue('enabled'));
    $config->save();

    $password = $form_state->getValue('password');
    if ($password) {
      $entity_access_config->set('global_password', $this->password->hash($password));
      $entity_access_config->save();
      $this->messenger()->addStatus($this->t('The global password has been updated'));
    }

    // Find all currently protected nodes and mark them for re-index if the
    // global switch has been changed.
    if ($changed_enabled) {
      $this->embargoedAccessManager->markAllForReindex();
      drupal_flush_all_caches();
    }

    return parent::submitForm($form, $form_state);
  }

}
