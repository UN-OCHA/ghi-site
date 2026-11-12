<?php

namespace Drupal\ghi_gin_test\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\State\StateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides native tree tables without custom drag handlers or markup changes.
 */
class TableDragTestForm extends FormBase {

  /**
   * Constructs the native tabledrag fixture.
   */
  public function __construct(protected StateInterface $state) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static($container->get('state'));
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'ghi_gin_tabledrag_test_form';
  }

  /**
   * Builds a native tree table shared by page and Layout Builder fixtures.
   */
  public static function buildTable(string $id, array $rows = []): array {
    $rows = $rows ?: [
      1 => ['weight' => 0, 'parent' => '', 'depth' => 0],
      2 => ['weight' => 1, 'parent' => '', 'depth' => 0],
      3 => ['weight' => 2, 'parent' => '', 'depth' => 0],
    ];
    uasort($rows, static fn(array $a, array $b) => $a['weight'] <=> $b['weight']);
    $table = [
      '#type' => 'table',
      '#tree' => TRUE,
      '#header' => [
        ['data' => t('Item'), 'colspan' => 4],
        t('Weight'),
      ],
      '#attributes' => ['id' => $id],
      '#tabledrag' => [
        ['action' => 'order', 'relationship' => 'sibling', 'group' => $id . '-weight'],
        [
          'action' => 'match',
          'relationship' => 'parent',
          'group' => $id . '-parent',
          'source' => $id . '-id',
          'hidden' => TRUE,
          'limit' => 2,
        ],
        ['action' => 'depth', 'relationship' => 'group', 'group' => $id . '-depth', 'hidden' => TRUE],
      ],
    ];
    foreach ($rows as $row_id => $row) {
      $table[$row_id] = [
        '#attributes' => ['class' => ['draggable'], 'data-test-row' => $row_id],
        'title' => [
          'indentation' => ['#theme' => 'indentation', '#size' => $row['depth']],
          'label' => ['#plain_text' => 'Row ' . $row_id],
        ],
        'id' => [
          '#type' => 'hidden',
          '#value' => $row_id,
          '#attributes' => ['class' => [$id . '-id']],
        ],
        'parent' => [
          '#type' => 'hidden',
          '#default_value' => $row['parent'],
          '#attributes' => ['class' => [$id . '-parent']],
        ],
        'depth' => [
          '#type' => 'hidden',
          '#default_value' => $row['depth'],
          '#attributes' => ['class' => [$id . '-depth']],
        ],
        'weight' => [
          '#type' => 'weight',
          '#title' => t('Weight for row @id', ['@id' => $row_id]),
          '#title_display' => 'invisible',
          '#default_value' => $row['weight'],
          '#attributes' => ['class' => [$id . '-weight']],
        ],
      ];
      if ($row_id === 2) {
        // A tall cell exercises guides beyond the height of the native handle.
        $table[$row_id]['title']['label'] = [
          '#type' => 'container',
          '#attributes' => ['style' => 'min-height: 140px'],
          '#plain_text' => 'Row ' . $row_id,
        ];
      }
    }
    return $table;
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $rows = $form_state->getValue('table') ?: $this->state->get('ghi_gin_test.rows', []);
    $form['#prefix'] = '<div id="ghi-gin-tabledrag-form-wrapper">';
    $form['#suffix'] = '</div>';
    $form['table'] = static::buildTable('ghi-gin-test-table', $rows);
    $form['preview'] = [
      '#type' => 'container',
      '#tree' => TRUE,
      '#attributes' => ['data-block-preview' => ''],
      'table' => static::buildTable('ghi-gin-preview-table'),
    ];
    $form['plain'] = [
      '#type' => 'table',
      '#attributes' => ['id' => 'ghi-gin-plain-table'],
      '#rows' => [['Not a draggable table']],
    ];
    $form['actions'] = [
      '#type' => 'actions',
      'save' => ['#type' => 'submit', '#value' => $this->t('Save tree')],
      'rebuild' => [
        '#type' => 'submit',
        '#value' => $this->t('Rebuild tree'),
        '#ajax' => ['callback' => '::rebuild', 'wrapper' => 'ghi-gin-tabledrag-form-wrapper'],
      ],
    ];
    return $form;
  }

  /**
   * Returns the rebuilt form through core AJAX processing.
   */
  public function rebuild(array &$form, FormStateInterface $form_state): array {
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $trigger = $form_state->getTriggeringElement();
    if (end($trigger['#parents']) === 'rebuild') {
      $form_state->setRebuild();
      return;
    }
    $this->state->set('ghi_gin_test.rows', $form_state->getValue('table'));
  }

}
