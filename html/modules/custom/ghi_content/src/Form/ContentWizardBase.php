<?php

namespace Drupal\ghi_content\Form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\ghi_form_elements\Form\WizardBase;
use Drupal\ghi_form_elements\Traits\AjaxElementTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a wizard form for creating article nodes.
 */
abstract class ContentWizardBase extends WizardBase {

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
   * The attachment query.
   *
   * @var \Drupal\ghi_content\RemoteSource\RemoteSourceManager
   */
  public $remoteSourceManager;

  /**
   * The wrapper id for ajax.
   *
   * @var string
   */
  protected $ajaxWrapperId;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    /** @var \Drupal\ghi_content\Form\ContentWizardBase $instance */
    $instance = parent::create($container);
    $instance->entityTypeManager = $container->get('entity_type.manager');
    $instance->entityFieldManager = $container->get('entity_field.manager');
    $instance->remoteSourceManager = $container->get('plugin.manager.remote_source');
    return $instance;
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
