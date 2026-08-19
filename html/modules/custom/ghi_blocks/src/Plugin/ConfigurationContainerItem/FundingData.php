<?php

namespace Drupal\ghi_blocks\Plugin\ConfigurationContainerItem;

use Drupal\Component\Utility\Html;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Logger\LoggerChannelTrait;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ghi_base_objects\Entity\BaseObjectInterface;
use Drupal\ghi_blocks\Traits\ConfigurationItemClusterRestrictTrait;
use Drupal\ghi_blocks\Traits\ConfigurationItemValuePreviewTrait;
use Drupal\ghi_blocks\Traits\PlanFootnoteTrait;
use Drupal\ghi_form_elements\Attribute\ConfigurationContainerItem;
use Drupal\ghi_form_elements\ConfigurationContainerItemPluginBase;
use Drupal\ghi_plans\ApiObjects\Attachments\CostAttachment;
use Drupal\ghi_plans\Entity\GoverningEntity;
use Drupal\ghi_plans\Entity\Plan;
use Drupal\ghi_plans\Traits\FtsLinkTrait;
use Drupal\hpc_api\ApiObjects\ApiObjectInterface;
use Drupal\hpc_common\Helpers\ThemeHelper;
use Drupal\hpc_common\Traits\RenderArrayTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides an funding data item for configuration containers.
 *
 * This item type allows the following options when using as part of a
 * configuration container:
 * - cluster_restrict: When set and set to FALSE, this disables the additional
 *   cluster restriction form element in configuration.
 *
 * @todo This is still missing support for special requirements logic.
 */
#[ConfigurationContainerItem(
  id: 'funding_data',
  label: new TranslatableMarkup('Financial data'),
  description: new TranslatableMarkup('Using the Financial data item, you can add funding and requirements data to this block. You can choose between different ways of displaying the data and do calculations. You can also override the default label.'),
)]
class FundingData extends ConfigurationContainerItemPluginBase {

  use ConfigurationItemClusterRestrictTrait;
  use ConfigurationItemValuePreviewTrait;
  use FtsLinkTrait;
  use PlanFootnoteTrait;
  use RenderArrayTrait;
  use LoggerChannelTrait;

  /**
   * The flow search query.
   *
   * @var \Drupal\ghi_plans\Plugin\EndpointQuery\FlowSearchQuery
   */
  public $flowSearchQuery;

  /**
   * The cluster query.
   *
   * @var \Drupal\ghi_plans\Plugin\FabricQuery\GoverningEntityQuery
   */
  public $clusterQuery;

  /**
   * The attachment query.
   *
   * @var \Drupal\ghi_plans\Plugin\FabricQuery\AttachmentQuery
   */
  public $attachmentQuery;

  /**
   * Flag fro disabling the FTS link for an instance.
   *
   * @var bool
   */
  private $ftsLinkDisabled = FALSE;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): FundingData {
    /** @var self $instance */
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->flowSearchQuery = $instance->endpointQueryManager->createInstance('flow_search_query');
    $instance->clusterQuery = $instance->fabricQueryManager->createInstance('governing_entity');
    $instance->attachmentQuery = $instance->fabricQueryManager->createInstance('attachment');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm($element, FormStateInterface $form_state) {
    $element = parent::buildForm($element, $form_state);

    $plan_object = $this->getContextValue('plan_object');

    $data_type_options = $this->getDataTypeOptions();
    $data_type_key = $this->getSubmittedOptionsValue($element, $form_state, 'data_type', $data_type_options);
    $scale = $this->getSubmittedValue($element, $form_state, 'scale', 'auto');
    $cluster_restrict = $this->getSubmittedValue($element, $form_state, 'cluster_restrict', [
      'type' => NULL,
      'tag' => NULL,
    ]);

    $element['data_type'] = [
      '#type' => 'select',
      '#title' => $this->t('Data type'),
      '#options' => $data_type_options,
      '#default_value' => $data_type_key,
      '#weight' => 0,
      '#ajax' => [
        'event' => 'change',
        'callback' => [static::class, 'updateAjax'],
        'wrapper' => $this->wrapperId,
      ],
    ];
    $element['label']['#weight'] = 1;

    $data_type = $this->getDataType($data_type_key);
    if ($data_type && !empty($data_type['default_label'])) {
      $element['label']['#description'] = $this->t('Leave empty to use the default label: <em>%default_label</em>', [
        '%default_label' => (string) $data_type['default_label'],
      ]);
      $element['label']['#placeholder'] = (string) $data_type['default_label'];
    }
    else {
      $element['label']['#required'] = TRUE;
    }

    if ($plan_object && $data_type['cluster_restrict'] && !$this->clusterRestrictDisabled()) {
      $element['cluster_restrict'] = $this->buildClusterRestrictFormElement($cluster_restrict);
    }

    $element['scale'] = [
      '#type' => 'select',
      '#title' => $this->t('Scale'),
      '#options' => [
        'auto' => $this->t('Automatic'),
        'full' => $this->t('Full value'),
      ],
      '#default_value' => $scale,
      '#ajax' => [
        'event' => 'change',
        'callback' => [static::class, 'updateAjax'],
        'wrapper' => $this->wrapperId,
      ],
      '#weight' => 2,
    ];

    if ($data_type && !empty($data_type['scale'])) {
      $element['scale']['#type'] = 'hidden';
      $element['scale']['#value'] = $data_type['scale'];
      $element['scale']['#default_value'] = $data_type['scale'];
    }

    // Add a preview.
    if ($this->shouldDisplayPreview()) {
      $preview_value = $this->getRenderArray($data_type_key, $scale, $cluster_restrict);
      $element['value_preview'] = $this->buildValuePreviewFormElement($preview_value);
    }

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function getLabel() {
    if (!empty($this->config['label'])) {
      return $this->config['label'];
    }
    $data_type = $this->getDataType();
    return $data_type['default_label'] ?? NULL;
  }

  /**
   * Get the cost attachment for the given entity type and entity id.
   *
   * @param string $entity_type
   *   The entity type.
   * @param int $entity_id
   *   The entity id.
   *
   * @return float|null
   *   The requirements.
   */
  private function getCostAttachment(string $entity_type, int $entity_id): ?CostAttachment {
    $attachments = $this->attachmentQuery->getAttachmentsByObject($entity_type, [$entity_id], 'cost');
    $attachment = count($attachments) > 0 ? reset($attachments) : NULL;
    assert($attachment === NULL || $attachment instanceof CostAttachment);
    return $attachment;
  }

  /**
   * Get the requirements for the given entity type and entity id.
   *
   * @param string $entity_type
   *   The entity type.
   * @param int $entity_id
   *   The entity id.
   *
   * @return float|null
   *   The requirements.
   */
  private function getRequirements(string $entity_type, int $entity_id): ?float {
    return $this->getCostAttachment($entity_type, $entity_id)?->getRequirements() ?? 0.0;
  }

  /**
   * Get the requirements for the given entity type and entity id.
   *
   * @param string $entity_type
   *   The entity type.
   * @param int $entity_id
   *   The entity id.
   *
   * @return float|null
   *   The original requirements.
   */
  private function getOriginalRequirements(string $entity_type, int $entity_id): ?float {
    return $this->getCostAttachment($entity_type, $entity_id)?->getOriginalRequirements() ?? 0.0;
  }

  /**
   * Get the value of an item.
   *
   * @param string $data_type_key
   *   The data type key.
   * @param array $cluster_restrict
   *   An array describing how to restrict by cluster..
   *
   * @return string|\Drupal\Component\Render\MarkupInterface
   *   Return the rendered value.
   */
  public function getValue(?string $data_type_key = NULL, ?array $cluster_restrict = NULL) {
    $values = &drupal_static(__CLASS__ . '::' . __METHOD__, []);

    $data_type_key = $data_type_key ?: $this->get('data_type');
    $cluster_restrict = $cluster_restrict ?: ($this->get('cluster_restrict') ?: NULL);

    /** @var \Drupal\ghi_plans\ApiObjects\Entities\EntityObjectInterface $entity */
    $entity = $this->getContextValue('entity');
    /** @var \Drupal\ghi_plans\Entity\Plan $plan_object */
    $plan_object = $this->getContextValue('plan_object');
    $base_object = $this->getContextValue('base_object');
    $cluster_context = $base_object && $base_object instanceof GoverningEntity ? $base_object : NULL;

    $data_type = $this->getDataType($data_type_key);
    if (!$data_type) {
      return NULL;
    }
    $property = $data_type['property'];
    $cluster_restrict_cache_key = $this->getClusterRestrictCacheKey($cluster_restrict);

    // Allow raw data to be passed in so that callers can take advantage of the
    // formatting options of this plugin in getRenderArray, without the need to
    // mock complete objects. Used for example for the funding and
    // not-specified clusters in the GVE overview tables.
    $raw_data = $this->getContextValue('raw_data');
    if (is_object($raw_data) && property_exists($raw_data, $property)) {
      return $raw_data->$property;
    }

    $cache_key = NULL;
    if ($entity && $entity instanceof ApiObjectInterface) {
      $cache_key = 'cluster::' . $entity->id() . '::' . $data_type_key;
    }
    elseif ($plan_object && !$cluster_context) {
      $cache_key = implode('::', [
        'plan' . $plan_object->id(),
        $data_type_key,
        'cluster_restrict',
        $cluster_restrict_cache_key,
      ]);
    }
    elseif ($cluster_context) {
      $cache_key = 'cluster::' . $cluster_context->getSourceId() . '::' . $data_type_key;
    }

    if ($cache_key && array_key_exists($cache_key, $values)) {
      return $values[$cache_key];
    }

    $value = NULL;
    if ($entity && $entity instanceof ApiObjectInterface) {
      $value = $this->getValueForCluster($entity->id(), $property);
    }
    elseif ($plan_object && !$cluster_context) {
      if (!empty($cluster_restrict) && !empty($cluster_restrict['type']) && $cluster_restrict['type'] != 'none') {
        $value = $this->getValueWithClusterRestrict($data_type, $cluster_restrict);
      }
      else {
        $value = $this->getValueForPlan($plan_object, $property);
      }
    }
    elseif ($cluster_context) {
      $value = $this->getValueForCluster($cluster_context->getSourceId(), $property);
    }
    if ($cache_key) {
      $values[$cache_key] = $value;
    }
    return $value;
  }

  /**
   * Get a value from a specific plan.
   *
   * @param \Drupal\ghi_plans\Entity\Plan $plan
   *   The plan entity object.
   * @param string $property
   *   The property to retrieve.
   *
   * @return float|mixed|null
   *   The value.
   */
  public function getValueForPlan(Plan $plan, $property) {
    $values = &drupal_static(__CLASS__ . '::' . __METHOD__, []);
    $plan_id = $plan->getSourceId();
    $values[$plan_id] = $values[$plan_id] ?? [];

    if (array_key_exists($property, $values[$plan_id])) {
      return $values[$plan_id][$property];
    }
    switch ($property) {
      case 'current_requirements':
        $value = $plan->getRequirements();
        break;

      case 'original_requirements':
        $value = $plan->getOriginalRequirements();
        break;

      case 'total_funding':
        $value = $plan->getTotalFunding();
        break;

      case 'outside_funding':
        $value = $plan->getOutsideFunding();
        break;

      case 'funding_gap':
        $value = $plan->getFundingGap();
        break;

      case 'funding_coverage':
        $value = $plan->getCoverage();
        break;

      default:
        $this->getLogger('ghi')->warning('Requested unknown property ' . $property);
        $value = 0;
        break;
    }

    $values[$plan_id][$property] = $value;
    return $value;
  }

  /**
   * Get a value from a specific cluster.
   *
   * @param int $cluster_id
   *   The cluster id.
   * @param string $property
   *   The property to retrieve.
   *
   * @return float|mixed|null
   *   The value.
   */
  public function getValueForCluster($cluster_id, $property) {
    $values = &drupal_static(__CLASS__ . '::' . __METHOD__, []);
    $plan_object = $this->getContextValue('plan_object');
    $plan_id = $plan_object instanceof Plan ? $plan_object->getSourceId() : 0;
    $cache_key = $plan_id . '::' . $cluster_id;
    $values[$cache_key] = $values[$cache_key] ?? [];
    if (array_key_exists($property, $values[$cache_key])) {
      return $values[$cache_key][$property];
    }
    switch ($property) {
      case 'current_requirements':
        $value = $this->getRequirements('governingEntity', $cluster_id);
        break;

      case 'original_requirements':
        $value = $this->getOriginalRequirements('governingEntity', $cluster_id);
        break;

      case 'total_funding':
        $value = $this->flowSearchQuery->getClusterTotalFunding($cluster_id);
        break;

      case 'funding_gap':
        $requirements = $this->getRequirements('governingEntity', $cluster_id);
        $value = $this->flowSearchQuery->getClusterFundingGap($cluster_id, $requirements);
        break;

      case 'funding_coverage':
        $requirements = $this->getRequirements('governingEntity', $cluster_id);
        $value = $this->flowSearchQuery->getClusterFundingCoverage($cluster_id, $requirements);
        break;

      default:
        $value = $this->flowSearchQuery->getClusterPropertyById($cluster_id, $property);
        break;
    }

    $values[$cache_key][$property] = $value;
    return $value;
  }

  /**
   * Get a value using the configured cluster restrict.
   *
   * @param array $data_type
   *   A data type definition.
   * @param array $cluster_restrict
   *   A cluster restriction to apply.
   *
   * @return mixed|null
   *   The retrieved value.
   */
  public function getValueWithClusterRestrict(array $data_type, array $cluster_restrict) {
    $values = &drupal_static(__CLASS__ . '::' . __METHOD__, []);
    /** @var \Drupal\ghi_plans\Entity\Plan $plan_object */
    $plan_object = $this->getContextValue('plan_object');
    $plan_id = $plan_object->getSourceId();
    $values[$plan_id] = $values[$plan_id] ?? [];
    $cluster_restrict_cache_key = $this->getClusterRestrictCacheKey($cluster_restrict);
    $values[$plan_id][$cluster_restrict_cache_key] = $values[$plan_id][$cluster_restrict_cache_key] ?? [];

    $property = $data_type['property'];
    if (array_key_exists($property, $values[$plan_id][$cluster_restrict_cache_key])) {
      return $values[$plan_id][$cluster_restrict_cache_key][$property];
    }

    // Extract the actually used cluster from the funding data.
    $cluster_ids = $this->getClusterIdsByClusterRestrict($cluster_restrict, $this->clusterQuery, $this->flowSearchQuery);
    if (empty($cluster_ids)) {
      $values[$plan_id][$cluster_restrict_cache_key][$property] = NULL;
      return $values[$plan_id][$cluster_restrict_cache_key][$property];
    }
    if ($property == 'current_requirements') {
      $attachments = $this->attachmentQuery->getAttachmentsByObject('governingEntity', $cluster_ids, 'cost');
      /** @var \Drupal\ghi_plans\ApiObjects\Attachments\CostAttachment[] $attachments */
      $attachments = array_filter($attachments, fn (CostAttachment $attachment): bool => $attachment instanceof CostAttachment);
      $value = array_sum(array_map(
        fn (CostAttachment $attachment): float => $attachment->getRequirements(),
        $attachments
      ));
    }
    else {
      $cluster_values = array_map(function ($cluster_id) use ($property) {
        return $this->getValueForCluster($cluster_id, $property);
      }, $cluster_ids);
      $numeric_values = array_filter($cluster_values, 'is_numeric');
      $value = count($numeric_values) == count($cluster_values) ? array_sum($numeric_values) : NULL;
    }
    $values[$plan_id][$cluster_restrict_cache_key][$property] = $value;
    return $value;
  }

  /**
   * Get a static cache key for a cluster restriction.
   *
   * @param array|null $cluster_restrict
   *   A cluster restriction to apply.
   *
   * @return string
   *   A static cache key part for the restriction.
   */
  private function getClusterRestrictCacheKey(?array $cluster_restrict = NULL): string {
    if (empty($cluster_restrict) || empty($cluster_restrict['type']) || $cluster_restrict['type'] == 'none') {
      return 'none';
    }
    ksort($cluster_restrict);
    return hash('sha256', serialize($cluster_restrict));
  }

  /**
   * {@inheritdoc}
   */
  public function getRenderArray($data_type_key = NULL, $scale = NULL, $cluster_restrict = NULL) {
    $data_type = $this->getDataType($data_type_key ?: $this->get('data_type'));
    if (!$data_type) {
      return NULL;
    }
    $scale = ($scale ?: $this->get('scale')) ?: (!empty($data_type['scale']) ? $data_type['scale'] : 'auto');
    $cluster_restrict = $cluster_restrict ?: ($this->get('cluster_restrict') ?: NULL);

    $theme_function = !empty($data_type['theme']) ? $data_type['theme'] : 'hpc_currency';
    $theme_options = !empty($data_type['theme_options']) ? $data_type['theme_options'] : [];

    /** @var \Drupal\ghi_plans\Entity\Plan $plan_object */
    $plan_object = $this->getContextValue('plan_object');
    /** @var \Drupal\ghi_base_objects\Entity\BaseObjectInterface $base_object */
    $base_object = $this->getContextValue('base_object');

    $value = $this->getValue($data_type_key, $cluster_restrict);
    $rendered = $this->buildRenderArray($theme_function, $value ?? 0, [
      'scale' => $scale,
      'decimal_format' => $plan_object->getDecimalFormat(),
    ] + $theme_options);

    // See if we need to add a footnote.
    $footnote = NULL;
    if (array_key_exists('footnote_property', $data_type) && $base_object instanceof Plan) {
      $footnotes = $this->getFootnotesForPlanBaseobject($base_object);
      $footnote = $this->buildFootnoteTooltip($footnotes, $data_type['footnote_property']);
    }

    // If this needs an FTS link, lets build and add that.
    $fts_tooltip = NULL;
    if ($this->needsFtsLink()) {
      $link_icon = ThemeHelper::themeFtsIcon();
      $fts_link = $this->needsFtsLink() ? self::buildFtsLink($link_icon, $this->getContextValue('plan_object'), $data_type['fts_link_target'], $this->getContextValue('base_object')) : NULL;
      $fts_tooltip = $fts_link ? [
        '#theme' => 'hpc_tooltip',
        '#tooltip' => $this->t('View this data in FTS', [], ['langcode' => $plan_object?->getPlanLanguage()]),
        '#tag_content' => $fts_link,
      ] : NULL;
    }

    $build = [
      '#type' => 'container',
      'content' => $rendered,
      'tooltips' => [
        '#theme' => 'hpc_tooltip_wrapper',
        '#tooltips' => [
          $footnote,
          $fts_tooltip,
        ],
      ],
    ];

    if ($value === NULL && $this->isClusterFundingDataType($data_type, $cluster_restrict)) {
      $build['#cache']['max-age'] = 0;
    }
    return $build;
  }

  /**
   * {@inheritdoc}
   */
  public function getTableCell() {
    $cell = parent::getTableCell();
    $cell['data-value'] = $this->getSortableValue();
    return $cell;
  }

  /**
   * {@inheritdoc}
   */
  public function getSortableValue() {
    return $this->getValue() ?? 0;
  }

  /**
   * Check if a data type depends on the cluster-scoped FTS summary.
   *
   * @param array $data_type
   *   The data type definition.
   * @param array|null $cluster_restrict
   *   A cluster restriction to apply.
   *
   * @return bool
   *   TRUE if the value is derived from cluster-scoped FTS funding data.
   */
  private function isClusterFundingDataType(array $data_type, ?array $cluster_restrict = NULL): bool {
    $property = $data_type['property'] ?? NULL;
    if (!in_array($property, ['total_funding', 'funding_gap', 'funding_coverage'], TRUE)) {
      return FALSE;
    }

    $entity = $this->getContextValue('entity');
    $base_object = $this->getContextValue('base_object');
    return $entity instanceof ApiObjectInterface || $base_object instanceof GoverningEntity || (!empty($cluster_restrict['type']) && $cluster_restrict['type'] != 'none');
  }

  /**
   * Check if this item needs an FTS link.
   *
   * @return bool
   *   TRUE if a link is needed, FALSE otherwhise.
   */
  private function needsFtsLink() {
    $plugin_configuration = $this->getPluginConfiguration();
    if (array_key_exists('fts_link', $plugin_configuration) && $plugin_configuration['fts_link'] !== TRUE) {
      // Explicitely requested to skip the link.
      return FALSE;
    }
    if ($this->ftsLinkDisabled) {
      return FALSE;
    }
    // All items except the progress bar can have links to FTS.
    $data_type = $this->getDataType();
    return !empty($data_type['fts_link_target']);
  }

  /**
   * {@inheritdoc}
   */
  public function getClasses() {
    // Missing source data remains NULL for cacheability decisions, but funding
    // displays consistently present it as zero rather than as unavailable.
    $classes = array_values(array_diff(parent::getClasses(), ['not-available']));
    $classes[] = Html::getClass($this->getPluginId() . '--' . $this->get('data_type'));
    return $classes;
  }

  /**
   * {@inheritdoc}
   */
  public function getColumnType() {
    return $this->get('data_type') == 'funding_coverage' ? 'percentage' : parent::getColumnType();
  }

  /**
   * Get the data type options.
   *
   * @return array
   *   An array of data types, suitable to use as options in a form element.
   */
  private function getDataTypeOptions() {
    $context = $this->getContext();
    $base_object = $context['base_object'];
    $data_types = array_filter($this->getDataTypes(), function ($type) use ($base_object) {
      return !array_key_exists('valid_context', $type) || ($base_object instanceof BaseObjectInterface && in_array($base_object->bundle(), $type['valid_context']));
    });
    return array_map(function ($type) {
      return $type['title'];
    }, $data_types);
  }

  /**
   * Get the available data types.
   *
   * @return array
   *   An array of defined data types.
   */
  private function getDataTypes() {
    $available_types = [
      'funding_totals' => [
        'title' => $this->t('Funding totals'),
        'default_label' => $this->t('Current funding ($)'),
        'valid_context' => ['plan', 'governing_entity'],
        'cluster_restrict' => TRUE,
        'property' => 'total_funding',
        'footnote_property' => 'funding',
        'scale' => 'auto',
        'fts_link_target' => 'flows',
      ],
      'outside_funding' => [
        'title' => $this->t('Funded outside HRP'),
        'default_label' => $this->t('Funded outside HRP ($)'),
        'valid_context' => ['plan'],
        'cluster_restrict' => FALSE,
        'property' => 'outside_funding',
        'scale' => 'auto',
      ],
      'funding_coverage' => [
        'title' => $this->t('Coverage (%)'),
        'default_label' => $this->t('% Funded'),
        'valid_context' => ['plan', 'governing_entity'],
        'cluster_restrict' => TRUE,
        'property' => 'funding_coverage',
        'theme' => 'hpc_percent',
      ],
      'funding_progress_bar' => [
        'title' => $this->t('Funding coverage progress bar'),
        'default_label' => $this->t('Funding coverage'),
        'valid_context' => ['plan', 'governing_entity'],
        'cluster_restrict' => TRUE,
        'property' => 'funding_coverage',
        'theme' => 'hpc_progress_bar',
        'theme_options' => [
          'hide_value' => TRUE,
        ],
      ],
      'funding_gap' => [
        'title' => $this->t('Funding gap'),
        'default_label' => $this->t('Unmet ($)'),
        'valid_context' => ['plan', 'governing_entity'],
        'cluster_restrict' => TRUE,
        'property' => 'funding_gap',
        'scale' => 'auto',
      ],
      'original_requirements' => [
        'title' => $this->t('Original requirements'),
        'default_label' => $this->t('Original ($)'),
        'valid_context' => ['plan', 'governing_entity'],
        'cluster_restrict' => TRUE,
        'property' => 'original_requirements',
      ],
      'current_requirements' => [
        'title' => $this->t('Current requirements'),
        'default_label' => $this->t('Requirements ($)'),
        'valid_context' => ['plan', 'governing_entity'],
        'cluster_restrict' => TRUE,
        'property' => 'current_requirements',
        'footnote_property' => 'requirements',
        'fts_link_target' => 'clusters',
        // @todo Add support for inclusion of original requirements as a
        // tooltip.
      ],
    ];
    $configuration = $this->getPluginConfiguration();
    if (array_key_exists('item_types', $configuration)) {
      $available_types = array_intersect_key($available_types, array_flip($configuration['item_types']));
    }
    return $available_types;
  }

  /**
   * Get a specific data type definition.
   *
   * @param string $data_type
   *   The key of the data type.
   *
   * @return array|null
   *   A definition array if the data type is found.
   */
  private function getDataType($data_type = NULL) {
    if ($data_type === NULL) {
      $data_type = $this->config['data_type'] ?? $this->config['data_type'];
    }
    $data_types = $this->getDataTypes();
    return array_key_exists($data_type, $data_types) ? $data_types[$data_type] : NULL;
  }

  /**
   * Whether cluster restriction is disabled.
   *
   * @return bool
   *   TRUE if cluster restriction is disabled, FALSE otherwhise.
   */
  private function clusterRestrictDisabled() {
    $plugin_configuration = $this->getPluginConfiguration();
    return array_key_exists('cluster_restrict', $plugin_configuration) && $plugin_configuration['cluster_restrict'] === FALSE;
  }

  /**
   * Explicitely disable the FTS link for an instance of this plugin.
   */
  public function disableFtsLink() {
    $this->ftsLinkDisabled = TRUE;
  }

}
