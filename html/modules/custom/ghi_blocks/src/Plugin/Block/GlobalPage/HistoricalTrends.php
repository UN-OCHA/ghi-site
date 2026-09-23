<?php

namespace Drupal\ghi_blocks\Plugin\Block\GlobalPage;

use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Block\BlockManagerInterface;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\Context\ContextDefinition;
use Drupal\Core\Plugin\Context\EntityContextDefinition;
use Drupal\Core\Session\AnonymousUserSession;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ghi_blocks\Interfaces\OverrideDefaultTitleBlockInterface;
use Drupal\ghi_blocks\Plugin\Block\GHIBlockBase;
use Drupal\ghi_blocks\Traits\HomepageBlockTrait;
use Drupal\ghi_blocks\Traits\TableSoftLimitTrait;
use Drupal\ghi_blocks\Traits\TableTrait;
use Drupal\ghi_homepage\Entity\Homepage;
use Drupal\hpc_common\Plugin\HPCBlockMetadata;
use Drupal\hpc_downloads\Interfaces\HPCDownloadExcelInterface;
use Drupal\hpc_downloads\Interfaces\HPCDownloadPNGInterface;
use Drupal\layout_builder\LayoutEntityHelperTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Compares the figures displayed on published homepages across years.
 */
#[Block(
  id: 'global_historical_trends',
  admin_label: new TranslatableMarkup('Historical trends'),
  category: new TranslatableMarkup('Global'),
  context_definitions: [
    'node' => new EntityContextDefinition('entity:node', new TranslatableMarkup('Node'), required: FALSE),
    'year' => new ContextDefinition(data_type: 'integer', label: new TranslatableMarkup('Year'), required: FALSE),
  ],
)]
class HistoricalTrends extends GHIBlockBase implements OverrideDefaultTitleBlockInterface, HPCDownloadExcelInterface, HPCDownloadPNGInterface {

  use HomepageBlockTrait;
  use LayoutEntityHelperTrait;
  use TableSoftLimitTrait;
  use TableTrait;

  /**
   * The block plugin manager.
   */
  protected BlockManagerInterface $blockManager;

  /**
   * {@inheritdoc}
   */
  public static function metadata(): ?HPCBlockMetadata {
    return new HPCBlockMetadata(defaultTitle: 'Evolution of the humanitarian response');
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->sectionStorageManager = $container->get('plugin.manager.layout_builder.section_storage');
    $instance->blockManager = $container->get('plugin.manager.block');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheTags() {
    // Publishing a new homepage must also invalidate an already cached table.
    return Cache::mergeTags(parent::getCacheTags(), ['node_list:homepage']);
  }

  /**
   * {@inheritdoc}
   */
  public function buildContent() {
    $table = $this->buildTableData();
    $build = [
      '#theme' => 'table',
      '#header' => $table['header'],
      '#rows' => $table['rows'],
      '#empty' => $this->t('No data'),
      '#sortable' => TRUE,
      '#progress_groups' => TRUE,
      '#soft_limit' => $this->getBlockConfig()['soft_limit'],
      '#block_id' => $this->getBlockId(),
      '#wrapper_attributes' => ['class' => ['content-width']],
    ];
    $table['cacheability']->applyTo($build);
    return $build;
  }

  /**
   * Builds table cells from the final configured homepage figures.
   *
   * @return array
   *   The table header, rows and cacheability metadata.
   */
  private function buildTableData(): array {
    $config = $this->getBlockConfig();
    $columns = $this->getColumns();
    $enabled = array_filter($config['columns']);
    if ($enabled) {
      $columns = array_intersect_key($columns, $enabled);
    }
    $header = ['year' => $this->buildHeaderColumn($this->t('Year'), 'number')];
    foreach ($columns as $key => $column) {
      $header[$key] = $this->buildHeaderColumn($column['label'], $column['type']);
    }

    $cacheability = (new CacheableMetadata())->setCacheTags($this->getCacheTags());
    $rows = [];
    $end_year = $config['end_year'] ?: $this->getContextValue('year');
    foreach ($this->getHomepages() as $year => $homepage) {
      if (($config['start_year'] && $year < $config['start_year']) || ($end_year && $year > $end_year)) {
        continue;
      }
      $figures = $this->getHomepageFigures($homepage, array_keys($columns), $cacheability);
      $row = [
        'year' => [
          'data' => $year,
          'data-raw-value' => $year,
          'data-column-type' => 'number',
          'export_value' => $year,
        ],
      ];
      foreach ($columns as $key => $column) {
        // A repeated type could refer to a different population or scope. Do
        // not silently select one, match by editable label, or invent a zero.
        $matches = $figures[$key] ?? [];
        $figure = count($matches) === 1 ? reset($matches) : NULL;
        $value = $figure['value'] ?? NULL;
        $row[$key] = [
          'data' => $figure['render'] ?? ['#markup' => $this->t('No data')],
          'data-raw-value' => $value ?? '',
          'data-column-type' => $column['type'],
          'export_value' => $value,
          'export_commentary' => $figure['footnote'] ?? (string) $this->t('No data'),
        ];
        if ($value !== NULL) {
          $row[$key]['data-progress-group'] = $column['group'];
        }
        else {
          // Prevent the Excel percentage formatter from casting NULL to zero.
          $row[$key]['excel_format'] = 'string';
        }
      }
      $rows[] = $row;
    }
    return ['header' => $header, 'rows' => $rows, 'cacheability' => $cacheability];
  }

  /**
   * Reads visible, typed key figures from a saved homepage layout.
   *
   * @param \Drupal\ghi_homepage\Entity\Homepage $homepage
   *   The published homepage in its default revision.
   * @param string[] $types
   *   The requested figure types.
   * @param \Drupal\Core\Cache\CacheableMetadata $cacheability
   *   Cache metadata to which the source dependencies are added.
   *
   * @return array
   *   Lists of matching figures, keyed by data type.
   */
  private function getHomepageFigures(Homepage $homepage, array $types, CacheableMetadata $cacheability): array {
    $cacheability->addCacheableDependency($homepage);
    // Homepages are publicly embedded but their canonical nodes deliberately
    // deny anonymous access. Use their published layouts, not node access or
    // the editor's Layout Builder tempstore.
    $storage = $this->getSectionStorageForEntity($homepage, 'embed');
    if (!$storage) {
      return [];
    }
    $cacheability->addCacheableDependency($storage);
    $figures = [];
    foreach ($storage->getSections() as $section) {
      foreach ($section->getComponents() as $component) {
        if ($component->getPluginId() !== 'global_key_figures') {
          continue;
        }
        // A fresh instance avoids reusing a query initialized for another year
        // when several homepages were copied from the same layout.
        $configuration = $component->get('configuration');
        $configuration['uuid'] = $component->getUuid();
        /** @var \Drupal\ghi_blocks\Plugin\Block\GlobalPage\KeyFigures $block */
        $block = $this->blockManager->createInstance($component->getPluginId(), $configuration);
        $block->setContextValue('node', $homepage);
        $block->setContextValue('year', (int) $homepage->getYear());
        $access = $block->access(new AnonymousUserSession(), TRUE);
        $cacheability->addCacheableDependency($block)->addCacheableDependency($access);
        if ($block->isHidden() || !$access->isAllowed()) {
          continue;
        }

        $items = $block->getConfiguredItems($block->getBlockConfig()['key_figures']['items']);
        $context = NULL;
        // Match the grouping rules used by KeyFigures::buildContent(): items
        // outside a group are not displayed and must not enter the history.
        foreach ($block->buildTree($items ?? []) as $group) {
          foreach ($group['children'] ?? [] as $item) {
            $type = $item['config']['type'] ?? NULL;
            if ($item['item_type'] !== 'plan_overview_data' || !in_array($type, $types, TRUE)) {
              continue;
            }
            $context = $context ?? $block->getBlockContext();
            $plugin = $block->getItemTypePluginForColumn($item, $context);
            $value = $plugin->getValue();
            $render = $plugin->getRenderArray();
            $cacheability->addCacheableDependency(CacheableMetadata::createFromRenderArray($render));
            $figures[$type][] = [
              'value' => $type === 'funding_progress' ? $value * 100 : $value,
              'render' => $render,
              'footnote' => $plugin->get('footnote') ?? '',
            ];
          }
        }
      }
    }
    return $figures;
  }

  /**
   * Defines the supported columns using stable key-figure data types.
   *
   * @return array
   *   Column labels, formatting types and progress groups.
   */
  private function getColumns(): array {
    return [
      'people_in_need' => ['label' => $this->t('People in need'), 'type' => 'amount', 'group' => 'people'],
      'people_target' => ['label' => $this->t('People targeted'), 'type' => 'amount', 'group' => 'people'],
      'total_requirements' => ['label' => $this->t('Requirements ($)'), 'type' => 'currency', 'group' => 'financial'],
      'total_funding' => ['label' => $this->t('Funding ($)'), 'type' => 'currency', 'group' => 'financial'],
      'funding_progress' => ['label' => $this->t('% Funded'), 'type' => 'percentage', 'group' => 'coverage'],
    ];
  }

  /**
   * {@inheritdoc}
   */
  protected function getConfigurationDefaults() {
    return [
      'columns' => array_combine(array_keys($this->getColumns()), array_keys($this->getColumns())),
      'start_year' => '',
      'end_year' => '',
      'soft_limit' => 10,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getConfigForm(array $form, FormStateInterface $form_state) {
    $form['source'] = [
      '#type' => 'item',
      '#description' => $this->t('Figures come from the published homepage for each year, including write-in values and footnotes. Changes to those homepages also change this table. Missing or repeated data types are shown as "No data"; free-text figures cannot be matched.'),
    ];
    $years = $this->getHomepageSwitcherOptions();
    $form['start_year'] = [
      '#type' => 'select',
      '#title' => $this->t('Start year'),
      '#options' => ['' => $this->t('Earliest available year')] + $years,
      '#default_value' => $this->getDefaultFormValueFromFormState($form_state, 'start_year'),
    ];
    $form['end_year'] = [
      '#type' => 'select',
      '#title' => $this->t('End year'),
      '#options' => ['' => $this->t('Page year, or latest available year')] + $years,
      '#default_value' => $this->getDefaultFormValueFromFormState($form_state, 'end_year'),
      '#element_validate' => [[$this, 'validateYearRange']],
    ];
    $form['columns'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Columns'),
      '#description' => $this->t('Select the columns to display in the table. If no column is checked, all will be displayed'),
      '#options' => array_map(fn (array $column) => $column['label'], $this->getColumns()),
      '#default_value' => $this->getDefaultFormValueFromFormState($form_state, 'columns'),
    ];
    $form['soft_limit'] = $this->buildSoftLimitFormElement($this->getDefaultFormValueFromFormState($form_state, 'soft_limit'), 1);
    return $form;
  }

  /**
   * Validates the configured year range.
   *
   * @param array $element
   *   The end-year form element.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The submitted form state.
   */
  public function validateYearRange(array &$element, FormStateInterface $form_state): void {
    $parents = array_slice($element['#parents'], 0, -1);
    $start = $form_state->getValue(array_merge($parents, ['start_year']));
    $end = $form_state->getValue($element['#parents']) ?: $this->getContextValue('year');
    if ($start && $end && $start > $end) {
      $form_state->setError($element, $this->t('The end year must not be earlier than the start year.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function buildDownloadData() {
    $table = $this->buildTableData();
    return ['header' => $table['header'], 'rows' => $table['rows']];
  }

}
