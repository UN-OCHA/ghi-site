<?php

namespace Drupal\ghi_sections\Plugin\Block;

use Drupal\Component\Render\FormattableMarkup;
use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\SubformStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\ghi_base_objects\Helpers\BaseObjectHelper;
use Drupal\ghi_subpages\SubpageTrait;
use Drupal\taxonomy\Entity\Term;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a 'SectionsByTerm' block.
 *
 * @Block(
 *  id = "sections_by_term",
 *  admin_label = @Translation("Sections by base object term"),
 *  category = @Translation("Menus"),
 * )
 */
class SectionsByTerm extends BlockBase implements ContainerFactoryPluginInterface {

  use SubpageTrait;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The entity field manager.
   *
   * @var \Drupal\Core\Entity\EntityFieldManager
   */
  protected $entityFieldManager;

  /**
   * The module handler service.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * The section manager.
   *
   * @var \Drupal\ghi_sections\SectionManager
   */
  protected $sectionManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    /** @var \Drupal\ghi_subpages\Plugin\Block\SubpageNavigation $instance */
    $instance = new static($configuration, $plugin_id, $plugin_definition);
    $instance->entityTypeManager = $container->get('entity_type.manager');
    $instance->entityFieldManager = $container->get('entity_field.manager');
    $instance->moduleHandler = $container->get('module_handler');
    $instance->sectionManager = $container->get('ghi_sections.manager');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function label() {
    $label = parent::label();
    if (empty($label)) {
      return $label;
    }
    $year = $this->configuration['year'] ?? date('Y');
    return str_replace('{year}', $year, $label);
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $build = [];

    $terms = $this->getTerms();
    $sections_by_terms = $this->getSectionsByTerms();
    if (empty($sections_by_terms)) {
      return $build;
    }

    $list_build = [
      '#theme' => 'item_list',
      '#items' => [],
    ];

    foreach ($sections_by_terms as $term_id => $section_nodes) {
      if (empty($section_nodes)) {
        continue;
      }
      $term_build = [
        '#markup' => new FormattableMarkup('<span>@term_label</span>', [
          '@term_label' => $terms[$term_id]->label(),
        ]),
      ];

      $section_links = [];
      /** @var \Drupal\node\NodeInterface[] $section_nodes */
      foreach ($section_nodes as $section_node) {
        $base_object = BaseObjectHelper::getBaseObjectFromNode($section_node);
        $section_label = $section_node->label();
        if ($base_object && $base_object->hasField('field_short_name') && !$base_object->get('field_short_name')->isEmpty()) {
          $section_label = $base_object->get('field_short_name')->value;
        }
        $section_links[(string) $section_label . '__' . $section_node->id()] = $section_node->toLink($section_label)->toRenderable();
      }

      if (!empty($section_links)) {
        ksort($section_links);
        $term_build[] = [
          '#theme' => 'item_list',
          '#items' => array_values($section_links),
        ];
        $list_build['#items'][] = $term_build;
      }
    }

    $build = [
      '#type' => 'container',
      '#attributes' => [
        'class' => [$this->configuration['label_display'] ? Html::getClass('label-visible') : NULL],
      ],
      0 => [
        '#type' => 'html_tag',
        '#tag' => 'nav',
        '#attributes' => [
          'role' => 'navigation',
          'aria-labelledby' => $this->getAriaId(),
          'class' => ['cd-container'],
        ],
        0 => $list_build,
      ],
    ];

    return $build;
  }

  /**
   * Get the id used for aria attributes.
   *
   * @return string
   *   An id to be used in aria attributes.
   */
  public function getAriaId() {
    return Html::getId('section-menu-' . $this->getPluginId());
  }

  /**
   * Get the terms for this block.
   *
   * @return \Drupal\taxonomy\TermInterface[]
   *   An array of term entities.
   */
  private function getTerms() {
    $conf = $this->getConfiguration();
    $vocabulary_id = $conf['vocabulary_id'] ?? NULL;
    if (!$vocabulary_id) {
      return NULL;
    }

    /** @var \Drupal\taxonomy\Entity\Term[] $terms */
    $terms = $this->entityTypeManager->getStorage('taxonomy_term')->loadByProperties([
      'vid' => $vocabulary_id,
    ]);
    uasort($terms, function (Term $term_1, Term $term_2) {
      return $term_1->getWeight() - $term_2->getWeight();
    });
    return $terms ?? [];
  }

  /**
   * Get the sections grouped by term ids.
   *
   * @return array
   *   An array keyed by term ids, each item holding an array of section nodes.
   */
  private function getSectionsByTerms() {
    $conf = $this->getConfiguration();

    $bundle = $conf['bundle'] ?? NULL;
    $reference_field = $conf['reference_field'] ?? NULL;
    $vocabulary_id = $conf['vocabulary_id'] ?? NULL;
    $year = $conf['year'] ?? date('Y');
    $terms = $this->getTerms();

    if (empty($terms) || empty($bundle) || empty($reference_field) || empty($vocabulary_id)) {
      return NULL;
    }

    /** @var \Drupal\ghi_base_objects\Entity\BaseObjectTypeInterface $base_object_type */
    $base_object_type = $this->entityTypeManager->getStorage('base_object_type')->load($bundle);

    $sections_by_terms = [];
    foreach ($terms as $term) {

      // Get the base objects relevant to the term.
      $base_objects = $this->entityTypeManager->getStorage('base_object')->loadByProperties(array_filter([
        'type' => $bundle,
        $reference_field => $term->id(),
        'field_year' => $base_object_type->hasYear() ? $year : NULL,
      ]));
      if (empty($base_objects)) {
        continue;
      }

      // Get the section nodes relevant to the base objects.
      $section_nodes = $this->entityTypeManager->getStorage('node')->loadByProperties(array_filter([
        'type' => 'section',
        'field_base_object' => array_keys($base_objects),
        'field_year' => !$base_object_type->hasYear() ? $year : NULL,
      ]));
      if (empty($section_nodes)) {
        continue;
      }

      $term_nodes = [];
      foreach ($section_nodes as $section_node) {
        if (!$section_node->access('view')) {
          continue;
        }
        $term_nodes[] = $section_node;
      }
      if (!empty($term_nodes)) {
        $sections_by_terms[$term->id()] = $term_nodes;
      }
    }
    return $sections_by_terms;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'bundle' => NULL,
      'reference_field' => NULL,
      'vocabulary_id' => NULL,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);
    $form['label']['#description'] = $this->t('The title for this block. You can use the following placeholders: {year}');
    $form['label']['#default_value'] = $this->configuration['label'];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state) {
    parent::blockForm($form, $form_state);

    $wrapper_id = Html::getId('form-wrapper-' . $this->getPluginId());
    $form['#prefix'] = '<div id="' . $wrapper_id . '">';
    $form['#suffix'] = '</div>';

    $values = [];
    if ($form_state instanceof SubformStateInterface) {
      $values = $form_state->getCompleteFormState()->cleanValues()->getValue(['settings']);
    }

    $conf = $this->configuration;
    $base_object_types = $this->sectionManager->getAvailableBaseObjectTypes();
    $default_bundle = $values['bundle'] ?? ($conf['bundle'] ?? array_key_first($base_object_types));

    $form['bundle'] = [
      '#type' => 'select',
      '#title' => $this->t('Section types'),
      '#options' => array_map(function ($bundle) {
        return $bundle->label();
      }, $base_object_types),
      '#default_value' => $default_bundle,
      '#ajax' => [
        'event' => 'change',
        'callback' => [static::class, 'updateAjax'],
        'wrapper' => $wrapper_id,
      ],
    ];

    $bundle_fields = $this->entityFieldManager->getFieldDefinitions('base_object', $default_bundle);
    $bundle_fields = array_filter($bundle_fields, function (FieldDefinitionInterface $bundle_field) {
      if ($bundle_field->getType() != 'entity_reference') {
        return FALSE;
      }
      if ($bundle_field->getSetting('target_type') != 'taxonomy_term') {
        return FALSE;
      }
      return TRUE;
    });
    $form['reference_field'] = [
      '#type' => 'select',
      '#title' => $this->t('Reference field'),
      '#description' => $this->t('Select the reference field where the term reference is stored.'),
      '#options' => array_map(function (FieldDefinitionInterface $field_definition) {
        return $field_definition->getLabel();
      }, $bundle_fields),
      '#default_value' => $conf['reference_field'] ?? NULL,
    ];

    $vocabularies = $this->entityTypeManager->getStorage('taxonomy_vocabulary')->loadMultiple();
    $form['vocabulary_id'] = [
      '#type' => 'select',
      '#title' => $this->t('Vocabulary'),
      '#options' => array_map(function ($vocabulary) {
        return $vocabulary->label();
      }, $vocabularies),
      '#default_value' => $conf['vocabulary_id'],
    ];

    $form['year'] = [
      '#type' => 'number',
      '#title' => $this->t('Year'),
      '#description' => $this->t('Optional: You can enter a year to further limit the sections to show. If no year is entered, the current year will be used.'),
      '#size' => 4,
      '#maxlength' => 4,
      '#min' => 2017,
      '#max' => date('Y') + 1,
      '#default_value' => $conf['year'],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state) {
    $this->configuration['bundle'] = $form_state->getValue('bundle');
    $this->configuration['reference_field'] = $form_state->getValue('reference_field');
    $this->configuration['vocabulary_id'] = $form_state->getValue('vocabulary_id');
    $this->configuration['year'] = $form_state->getValue('year');
  }

  /**
   * Generic ajax callback to be used by implementing classes.
   *
   * @param array $form
   *   The form element.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state interface.
   *
   * @return array
   *   The part of the form structure that should be replaced.
   */
  public static function updateAjax(array &$form, FormStateInterface $form_state) {
    $response = new AjaxResponse();

    $triggering_element = $form_state->getTriggeringElement();
    $parents = $triggering_element['#parents'];
    array_pop($parents);
    $ajax = $triggering_element['#ajax'];

    if (!empty($ajax['wrapper']) && !empty($parents)) {
      $wrapper_id = $ajax['wrapper'];
      // Just update the full element.
      $response->addCommand(new ReplaceCommand('#' . $wrapper_id, NestedArray::getValue($form, $parents)));
    }

    return $response;
  }

}
