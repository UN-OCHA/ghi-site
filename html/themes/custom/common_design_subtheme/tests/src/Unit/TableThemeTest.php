<?php

namespace Drupal\Tests\common_design_subtheme\Unit;

use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the theme integration for frontend tables.
 */
#[Group('common_design_subtheme')]
class TableThemeTest extends UnitTestCase {

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    require_once __DIR__ . '/../../../common_design_subtheme.theme';
  }

  /**
   * Provides column counts and expected leading-column widths.
   */
  public static function distributedColumnWidths(): array {
    return [
      'one column' => [1, '100%'],
      'two columns' => [2, '66.6667%'],
      'nine columns' => [9, '20%'],
    ];
  }

  /**
   * Adds proportional column styling only when explicitly requested.
   */
  #[DataProvider('distributedColumnWidths')]
  public function testDistributedColumns(int $column_count, string $expected_width): void {
    $variables = $this->tableVariables([
      'distributed_columns' => TRUE,
      'header_columns' => $column_count,
    ]);

    \common_design_subtheme_preprocess_table($variables);

    $this->assertContains('ghi-table--distributed-columns', $variables['attributes']['class']);
    $this->assertSame([
      '--ghi-table-first-column-width: ' . $expected_width . ';',
    ], $variables['attributes']['style']);
  }

  /**
   * Leaves ordinary tables unchanged.
   */
  public function testDistributedColumnsAreOptIn(): void {
    $variables = $this->tableVariables(['header_columns' => 9]);

    \common_design_subtheme_preprocess_table($variables);

    $this->assertNotContains('ghi-table--distributed-columns', $variables['attributes']['class']);
    $this->assertArrayNotHasKey('style', $variables['attributes']);
  }

  /**
   * Preserves table styles when adding the calculated column width.
   */
  public function testDistributedColumnsPreserveExistingStyles(): void {
    $variables = $this->tableVariables([
      'attributes' => ['style' => 'color: red;'],
      'distributed_columns' => TRUE,
      'header_columns' => 4,
    ]);

    \common_design_subtheme_preprocess_table($variables);

    $this->assertSame([
      'color: red;',
      '--ghi-table-first-column-width: 40%;',
    ], $variables['attributes']['style']);
  }

  /**
   * Keeps isolated single-character tokens with an adjacent label word.
   */
  public function testSingleCharacterHeaderTokensDoNotWrapAlone(): void {
    $variables = $this->tableVariables([
      'header' => [
        ['content' => '% Funded'],
        ['content' => 'Phase 1'],
        ['content' => 'Type A response'],
        ['content' => 'Ordinary multi-word label'],
      ],
    ]);

    \common_design_subtheme_preprocess_table($variables);

    $this->assertSame("%\u{00A0}Funded", $variables['header'][0]['content']);
    $this->assertSame("Phase\u{00A0}1", $variables['header'][1]['content']);
    $this->assertSame("Type A\u{00A0}response", $variables['header'][2]['content']);
    $this->assertSame('Ordinary multi-word label', $variables['header'][3]['content']);
  }

  /**
   * Provides labels whose final word or nonbreaking group needs the sort icon.
   */
  public static function sortableHeaderLabels(): array {
    return [
      'multiword' => ['People targeted', 'People ', 'targeted'],
      'single word' => ['Requirements', '', 'Requirements'],
      'leading symbol' => ['% Funded', '', "%\u{00A0}Funded"],
      'trailing character' => ['Phase 1', '', "Phase\u{00A0}1"],
      'internal character' => ['Type A response', 'Type ', "A\u{00A0}response"],
      'translated label' => ['Besoins financiers', 'Besoins ', 'financiers'],
      'trailing whitespace' => ['Plan type ', 'Plan ', 'type '],
    ];
  }

  /**
   * Keeps nonbreaking label groups intact when attaching the sort indicator.
   */
  #[DataProvider('sortableHeaderLabels')]
  public function testSortableHeaderLabelParts(string $label, string $prefix, string $suffix): void {
    $variables = $this->tableVariables([
      'sortable' => TRUE,
      'header' => [['content' => $label]],
    ]);

    \common_design_subtheme_preprocess_table($variables);

    $header = $variables['header'][0];
    $this->assertSame(['prefix' => $prefix, 'suffix' => $suffix], $header['label_parts']);
    $this->assertSame($prefix . $suffix, $header['content']);
  }

  /**
   * Preserves structured headers rather than splitting their markup as text.
   */
  public function testStructuredSortableHeaderIsPreserved(): void {
    $content = ['#markup' => '<strong>People targeted</strong>'];
    $variables = $this->tableVariables([
      'sortable' => TRUE,
      'header' => [['content' => $content]],
    ]);

    \common_design_subtheme_preprocess_table($variables);

    $this->assertSame($content, $variables['header'][0]['content']);
    $this->assertArrayNotHasKey('label_parts', $variables['header'][0]);
  }

  /**
   * Builds the minimum variables expected by the table preprocessor.
   */
  private function tableVariables(array $overrides = []): array {
    return $overrides + [
      'attributes' => [],
      'header' => [],
      'rows' => [],
      'footer' => [],
    ];
  }

}
