<?php

namespace Drupal\ghi_sections\Form;

use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\TypedData\TypedDataManagerInterface;
use Drupal\ghi_form_elements\Traits\AjaxElementTrait;
use Drupal\ghi_sections\SectionManager;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a wizard form for creating section nodes.
 */
abstract class WizardBase extends FormBase {

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
   * @var \Drupal\Core\TypedData\TypedDataManagerInterface
   */
  protected $typedDataManager;

  /**
   * The module handler service.
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
   * The section manager.
   *
   * @var \Drupal\ghi_sections\Import\SectionManager
   */
  protected $sectionManager;

  /**
   * The wrapper id for ajax.
   *
   * @var string
   */
  protected $ajaxWrapperId;

  /**
   * Constructs a section create form.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, EntityFieldManagerInterface $entity_field_manager, TypedDataManagerInterface $typed_data_manager, ModuleHandlerInterface $module_handler, AccountProxyInterface $user, SectionManager $section_manager) {
    $this->entityTypeManager = $entity_type_manager;
    $this->entityFieldManager = $entity_field_manager;
    $this->typedDataManager = $typed_data_manager;
    $this->moduleHandler = $module_handler;
    $this->currentUser = $user;
    $this->sectionManager = $section_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('entity_field.manager'),
      $container->get('typed_data_manager'),
      $container->get('module_handler'),
      $container->get('current_user'),
      $container->get('ghi_sections.manager')
    );
  }

  /**
   * Define the node bundle for this wizard.
   *
   * @return string
   *   The bundle id used for new nodes created using this wizard.
   */
  abstract protected function getBundle();

  /**
   * Get the help text for the given field name.
   *
   * @param string $field_name
   *   The field name.
   *
   * @return string|null
   *   The help text or NULL.
   */
  protected function getFieldHelp($field_name) {
    $field_definitions = $this->entityFieldManager->getFieldDefinitions('node', $this->getBundle());
    /** @var \Drupal\field\FieldConfigInterface $field_config */
    $field_config = $field_definitions[$field_name] ?? NULL;
    return $field_config?->getDescription();
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, NodeInterface $node = NULL) {
    self::prepareAjaxForm($form, $form_state);
    $this->ajaxWrapperId = self::getWrapperId($form);
    $form['#prefix'] = '<div id="' . $this->ajaxWrapperId . '">';
    $form['#suffix'] = '</div>';

    /** @var \Drupal\node\NodeTypeInterface $node_type */
    $node_type = $this->entityTypeManager->getStorage('node_type')->load($this->getBundle());
    $help = $node_type->getHelp();

    if (!empty($help)) {
      $form['help'] = [
        '#theme' => 'status_messages',
        '#message_list' => [
          'info' => [$help],
        ],
        '#status_headings' => [
          'info' => $this->t('Help'),
        ],
      ];
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    // We need to prepare the ajax form, because validation is called before
    // form building, and in case of errors doesn't reach the buildForm method.
    self::prepareAjaxForm($form, $form_state);
  }

  /**
   * Get the entity reference field item list for the given bundle and field.
   *
   * @param string $bundle
   *   The bundle name.
   * @param string $field_name
   *   The field name.
   * @param array $values
   *   The values.
   *
   * @return \Drupal\Core\Field\EntityReferenceFieldItemList
   *   An instance of EntityReferenceFieldItemList.
   */
  protected function getEntityReferenceFieldItemList($bundle, $field_name, array $values) {
    $node_storage = $this->entityTypeManager->getStorage('node');
    $node = $node_storage->create(['type' => $bundle]);
    $tags = $this->typedDataManager->getPropertyInstance($node->getTypedData(), $field_name, $values);
    return $tags;
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
    // @todo Ideally, this should fetch teams that have access to the base
    // object, but for now we fetch all teams.
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

}
