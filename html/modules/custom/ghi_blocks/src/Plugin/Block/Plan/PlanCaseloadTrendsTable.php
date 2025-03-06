<?php

namespace Drupal\ghi_blocks\Plugin\Block\Plan;

use Drupal\Core\Form\FormStateInterface;
use Drupal\ghi_blocks\Interfaces\OverrideDefaultTitleBlockInterface;
use Drupal\ghi_blocks\Plugin\Block\GHIBlockBase;
use Drupal\ghi_blocks\Traits\TableTrait;
use Drupal\ghi_plans\Entity\Plan;
use Drupal\hpc_downloads\Interfaces\HPCDownloadExcelInterface;
use Drupal\hpc_downloads\Interfaces\HPCDownloadPNGInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a 'PlanEntityTypes' block.
 *
 * @Block(
 *  id = "plan_caseload_trends_table",
 *  admin_label = @Translation("Caseload Trends Table"),
 *  category = @Translation("Plan elements"),
 *  default_title = @Translation("Evolution of the humanitarian response"),
 *  data_sources = {
 *    "attachment_search" = "attachment_search_query",
 *    "plan_funding" = "plan_funding_summary_query"
 *  },
 *  context_definitions = {
 *    "node" = @ContextDefinition("entity:node", label = @Translation("Node")),
 *    "plan" = @ContextDefinition("entity:base_object", label = @Translation("Plan"), constraints = { "Bundle": "plan" })
 *  }
 * )
 */
class PlanCaseloadTrendsTable extends GHIBlockBase implements OverrideDefaultTitleBlockInterface, HPCDownloadExcelInterface, HPCDownloadPNGInterface {

  use TableTrait;

  const DEFAULT_MAX_YEARS = 5;
  const MIN_YEARS = 3;
  const MAX_YEARS = 10;

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
    /** @var \Drupal\ghi_blocks\Plugin\Block\Plan\PlanClusterLogframeLinks $instance */
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->sectionManager = $container->get('ghi_sections.manager');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultTitle() {
    $title = parent::getDefaultTitle();
    $langcode = $this->getCurrentPlanObject()?->getPlanLanguage() ?? 'en';
    // @codingStandardsIgnoreStart
    return $title ? $this->t((string) $title, [], ['langcode' => $langcode]) : $title;
    // @codingStandardsIgnoreEnd
  }

  /**
   * {@inheritdoc}
   */
  public function buildContent() {
    $table = $this->buildTableData();
    if (empty($table)) {
      return NULL;
    }
    return [
      '#theme' => 'table',
      '#header' => $table['header'],
      '#rows' => $table['rows'],
      '#progress_groups' => TRUE,
      '#sortable' => TRUE,
    ];
  }

  /**
   * Build the table data for this element.
   *
   * @return array|null
   *   An array with the keys "header" and "rows".
   */
  private function buildTableData() {
    $data = $this->buildSourceData();
    if (empty($data)) {
      return NULL;
    }

    $langcode = $this->getCurrentPlanObject()?->getPlanLanguage() ?? 'en';
    $t_options = ['langcode' => $langcode];
    $header = [
      $this->buildHeaderColumn($this->t('Year', [], $t_options), 'number'),
      $this->buildHeaderColumn($this->t('People in need', [], $t_options), 'amount'),
      $this->buildHeaderColumn($this->t('People targeted', [], $t_options), 'amount'),
      $this->buildHeaderColumn($this->t('Requirements ($)', [], $t_options), 'currency'),
      $this->buildHeaderColumn($this->t('Funding ($)', [], $t_options), 'currency'),
      $this->buildHeaderColumn($this->t('% Funded', [], $t_options), 'percentage'),
    ];
    $rows = [];

    foreach ($data as $item) {
      $row = [
        [
          'data' => $item['label_link'],
          'data-raw-value' => $item['label'],
          'data-column-type' => 'string',
        ],
        [
          'data' => [
            '#theme' => 'hpc_amount',
            '#amount' => $item['in_need'] ?: '-',
            '#decimals' => 1,
          ],
          'data-raw-value' => $item['in_need'],
          'data-column-type' => 'amount',
          'data-progress-group' => 'people',
        ],
        [
          'data' => [
            '#theme' => 'hpc_amount',
            '#amount' => $item['target'] ?: '-',
            '#decimals' => 1,
          ],
          'data-raw-value' => $item['target'],
          'data-column-type' => 'amount',
          'data-progress-group' => 'people',
        ],
        [
          'data' => [
            '#theme' => 'hpc_currency',
            '#value' => $item['current_requirements'],
          ],
          'data-raw-value' => $item['current_requirements'],
          'data-column-type' => 'currency',
          'data-progress-group' => 'financial',
        ],
        [
          'data' => [
            '#theme' => 'hpc_currency',
            '#value' => $item['total_funding'],
          ],
          'data-raw-value' => $item['total_funding'],
          'data-column-type' => 'currency',
          'data-progress-group' => 'financial',
        ],
        [
          'data' => [
            '#theme' => 'hpc_percent',
            '#percent' => $item['funding_coverage'],
          ],
          'data-raw-value' => $item['funding_coverage'],
          'data-column-type' => 'percentage',
          'data-progress-group' => 'coverage',
        ],
      ];
      $rows[] = $row;
    }

    if (empty($rows)) {
      return NULL;
    }

    return [
      'header' => $header,
      'rows' => $rows,
    ];
  }

  /**
   * Build the source data for this element.
   *
   * @return array|null
   *   An array with data or NULL.
   */
  private function buildSourceData() {
    $related_sections = $this->getRelatedSections();
    if (empty($related_sections)) {
      return NULL;
    }
    /** @var \Drupal\ghi_plans\Plugin\EndpointQuery\AttachmentSearchQuery $attachments_query */
    $attachments_query = $this->getQueryHandler('attachment_search');

    /** @var \Drupal\ghi_plans\Plugin\EndpointQuery\PlanFundingSummaryQuery $funding_query */
    $funding_query = $this->getQueryHandler('plan_funding');

    $data = [];
    $years = [];
    foreach ($related_sections as $section) {
      /** @var \Drupal\ghi_plans\Entity\Plan $plan */
      $plan = $section->getBaseObject();
      $years[$plan->getYear()] = !empty($years[$plan->getYear()]) ? $years[$plan->getYear()] + 1 : 1;
    }

    foreach ($related_sections as $section) {
      /** @var \Drupal\ghi_plans\Entity\Plan $plan */
      $plan = $section->getBaseObject();

      /** @var \Drupal\ghi_plans\ApiObjects\Attachments\CaseloadAttachment[] $caseloads */
      $caseloads = $attachments_query->getAttachmentsByObject('plan', $plan->getSourceId(), ['type' => 'caseload']);
      $caseload = count($caseloads) > 1 ? $plan->getPlanCaseload($caseloads) : (!empty($caseloads) ? reset($caseloads) : NULL);
      $funding_data = $funding_query->getData(['plan_id' => $plan->getSourceId()]);

      $label = $years[$plan->getYear()] > 1 ? $plan->getYear() . ' - ' . $plan->getShortName() : $plan->getYear();

      $data[] = [
        'label' => $label,
        'label_link' => $section->access('view') ? $section->toLink($label)->toRenderable() : ['#markup' => $label],
        'in_need' => $caseload?->getFieldByType('inNeed')?->value,
        'target' => $caseload?->getFieldByType('target')?->value,
        'current_requirements' => $funding_data['current_requirements'],
        'total_funding' => $funding_data['total_funding'],
        'funding_coverage' => $funding_data['funding_coverage'],
      ];
    }
    return $data;
  }

  /**
   * Get the related sections for this element.
   *
   * @return \Drupal\ghi_sections\Entity\SectionNodeInterface[]
   *   An array of section nodes associated to plan base objects.
   */
  private function getRelatedSections() {
    $section = $this->getCurrentSectionNode();
    $plan = $this->getCurrentPlanObject();
    if (!$plan || !$section) {
      return [];
    }
    $related_sections = $this->sectionManager->getRelatedSections($section);
    if (empty($related_sections)) {
      return [];
    }
    $config = $this->getBlockConfig();
    $max_year = date('Y');
    $min_year = $plan->getYear() - $config['years'] + 1;
    $plan_type_id = $plan->getPlanType()->id();
    $related_sections = array_filter($related_sections, function ($_section) use ($min_year, $max_year, $plan_type_id) {
      $_base_object = $_section->getBaseObject();
      if (!$_base_object instanceof Plan || $_base_object->getPlanType()->id() != $plan_type_id) {
        return FALSE;
      }
      return in_array($_base_object->getYear(), range($min_year, $max_year));
    });
    return $related_sections;
  }

  /**
   * {@inheritdoc}
   */
  public function getConfigurationDefaults() {
    return [
      'years' => NULL,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getConfigForm(array $form, FormStateInterface $form_state) {
    $years = range(self::MIN_YEARS, self::MAX_YEARS);
    $form['years'] = [
      '#type' => 'select',
      '#title' => $this->t('Number of years to show'),
      '#options' => array_combine($years, $years),
      '#default_value' => $this->getDefaultFormValueFromFormState($form_state, 'years') ?: self::DEFAULT_MAX_YEARS,
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function buildDownloadData() {
    return $this->buildTableData();
  }

}
