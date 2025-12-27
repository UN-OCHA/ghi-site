<?php

namespace Drupal\hpc_api\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\hpc_api\ApiObjects\Types\EntityType;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Relationship lookup form.
 */
class RelationshipLookupForm extends FormBase {

  /**
   * The endpoint query to retrieve API data.
   *
   * @var \Drupal\hpc_api\Query\FabricQueryManager
   */
  protected $fabricQueryManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    /** @var \Drupal\ghi_blocks\Form\TableSettingsForm $instance */
    $instance = parent::create($container);
    $instance->fabricQueryManager = $container->get('plugin.manager.fabric_query_manager');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'hpc_api_relationship_lookup_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    /** @var \Drupal\hpc_api\Plugin\FabricQuery\RelationshipQuery $query */
    $query = $this->fabricQueryManager->createInstance('relationship');
    $source_type = $form_state->getValue('source_type') ?: NULL;
    $source_id = $form_state->getValue('source_id') ?: NULL;
    $target_type = $form_state->getValue('target_type') ?: NULL;
    $target_id = $form_state->getValue('target_id') ?: NULL;

    $form['filter'] = [
      '#type' => 'container',
      '#tree' => FALSE,
      '#attributes' => [
        'style' => 'display: flex; gap: 1rem; flex-wrap: wrap; align-items: anchor-center;',
      ],
    ];
    $form['filter']['source_type'] = [
      '#type' => 'select',
      '#title' => $this->t('Source type'),
      '#default_value' => $source_type,
      '#options' => $this->getEntityTypeOptions(),
    ];
    $form['filter']['source_id'] = [
      '#type' => 'number',
      '#title' => $this->t('Source id'),
      '#default_value' => $source_id,
    ];
    $form['filter']['target_type'] = [
      '#type' => 'select',
      '#title' => $this->t('Target type'),
      '#default_value' => $target_type,
      '#options' => $this->getEntityTypeOptions(),
    ];
    $form['filter']['target_id'] = [
      '#type' => 'number',
      '#title' => $this->t('Target id'),
      '#default_value' => $target_id,
    ];
    $form['filter']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Apply'),
      '#attributes' => [
        'style' => 'height: fit-content;',
      ],
    ];

    $entity_types = $query->getEntityTypes();
    $rows = [];
    if ($source_type || $target_type || $source_id || $target_id) {
      foreach ($query->getRelationshipItems($source_type, $target_type, $source_id, $target_id) as $item) {
        $_source_type_id = $item->getSourceTypeId();
        $_source_id = $item->getSourceId();
        $_target_type_id = $item->getTargetTypeId();
        $_target_id = $item->getTargetId();
        $source_label = $query->lookupEntityLabel($_source_type_id, $_source_id);
        $target_label = $query->lookupEntityLabel($_target_type_id, $_target_id);

        $source_label = $source_label ? ($source_label . ' (' . $_source_id . ')') : $_source_id;
        $target_label = $target_label ? ($target_label . ' (' . $_target_id . ')') : $_target_id;

        $source_url = Url::fromRoute('hpc_api.reports.fabric.entity_lookup', [
          'entity_type_id' => $_source_type_id,
          'entity_id' => $_source_id,
        ]);
        if ($source_url) {
          $source_label = Link::fromTextAndUrl($source_label, $source_url)->toString();
        }
        $target_url = Url::fromRoute('hpc_api.reports.fabric.entity_lookup', [
          'entity_type_id' => $_target_type_id,
          'entity_id' => $_target_id,
        ]);
        if ($target_url) {
          $target_label = Link::fromTextAndUrl($target_label, $target_url)->toString();
        }

        $rows[] = [
          count($rows) + 1,
          // $item->id(),
          // $item->getType(),
          $entity_types[$_source_type_id]->getName(),
          $source_label,
          $entity_types[$_target_type_id]->getName(),
          $target_label,
        ];
      }
    }

    $form['table'] = [
      '#type' => 'table',
      '#header' => [
        '#',
        // $this->t('Id'),
        // $this->t('Type'),
        $this->t('Source type'),
        $this->t('Source (id)'),
        $this->t('Target type'),
        $this->t('Target (id)'),
      ],
      '#rows' => $rows,
      '#empty' => $this->t('No relationships found'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $form_state->setRebuild();
  }

  /**
   * Get the options for the entity type selector.
   *
   * @return array
   *   An array to be used in form select elements.
   */
  public function getEntityTypeOptions() {
    /** @var \Drupal\hpc_api\Plugin\FabricQuery\RelationshipQuery $query */
    $query = $this->fabricQueryManager->createInstance('relationship');
    $types = array_map(fn(EntityType $item) => $item->getLabel(), $query->getEntityTypes());
    ksort($types);
    return [0 => $this->t('Any')] + $types;
  }

}
