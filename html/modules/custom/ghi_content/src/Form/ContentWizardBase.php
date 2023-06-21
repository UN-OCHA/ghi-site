<?php

namespace Drupal\ghi_content\Form;

use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\ghi_content\RemoteSource\RemoteSourceManager;
use Drupal\ghi_form_elements\Traits\AjaxElementTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a wizard form for creating article nodes.
 */
abstract class ContentWizardBase extends FormBase {

  use AjaxElementTrait;

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected $entityFieldManager;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * The attachment query.
   *
   * @var \Drupal\ghi_content\RemoteSource\RemoteSourceManager
   */
  public $remoteSourceManager;

  /**
   * Constructs a document create form.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, EntityFieldManagerInterface $entity_field_manager, ModuleHandlerInterface $module_handler, AccountProxyInterface $user, RemoteSourceManager $remote_source_manager) {
    $this->entityTypeManager = $entity_type_manager;
    $this->entityFieldManager = $entity_field_manager;
    $this->moduleHandler = $module_handler;
    $this->currentUser = $user;
    $this->remoteSourceManager = $remote_source_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('entity_field.manager'),
      $container->get('module_handler'),
      $container->get('current_user'),
      $container->get('plugin.manager.remote_source'),
    );
  }

  /**
   * Get options for the remote source.
   *
   * @return array|null
   *   The remote source options or NULL.
   */
  protected function getSourceOptions() {
    $definitions = $this->remoteSourceManager->getDefinitions();
    if (empty($definitions)) {
      return NULL;
    }
    return array_map(function ($remote_source) {
      return $this->remoteSourceManager->createInstance($remote_source);
    }, array_keys($definitions));
  }

  /**
   * Retrieve the team options for the team select field.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state object.
   *
   * @return array
   *   An array of team names, keyed by tid.
   */
  protected function getTeamOptions(FormStateInterface $form_state) {
    $terms = $this->entityTypeManager->getStorage('taxonomy_term')->loadTree('team');
    if (empty($terms)) {
      return [];
    }
    $options = [];
    foreach ($terms as $term) {
      $options[$term->tid] = $term->name;
    }
    return $options;
  }

  /**
   * Get the submitted article.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state object.
   *
   * @return \Drupal\ghi_content\RemoteSource\RemoteSourceInterface
   *   The remote source of the article.
   */
  protected function getSubmittedSource(FormStateInterface $form_state) {
    $remote_source = $form_state->getValue('source');
    if (empty($remote_source)) {
      return NULL;
    }
    $instance = $this->remoteSourceManager->createInstance($remote_source);
    return $instance;
  }

}
