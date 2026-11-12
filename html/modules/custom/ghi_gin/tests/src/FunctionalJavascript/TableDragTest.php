<?php

namespace Drupal\Tests\ghi_gin\FunctionalJavascript;

use Behat\Mink\Element\NodeElement;
use Drupal\Tests\ghi_blocks\FunctionalJavascript\BlockUiBase;
use Drupal\file\Entity\File;
use Drupal\image\Entity\ImageStyle;
use Drupal\layout_builder\Plugin\SectionStorage\OverridesSectionStorage;
use Drupal\layout_builder\Section;
use Drupal\layout_builder\SectionComponent;
use Drupal\responsive_image\Entity\ResponsiveImageStyle;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the shared styling preserves native tabledrag in both editing themes.
 */
#[Group('ghi_gin')]
class TableDragTest extends BlockUiBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['ghi_gin_test'];

  /**
   * Tests tree dragging, keyboard movement, weight fallback, and AJAX rebuilds.
   */
  public function testAdministrativeNativeTableDrag(): void {
    $this->loginEditor(['administer site configuration']);
    $this->drupalGet('/admin/ghi-gin/tabledrag-test');
    $this->assertSession()->elementExists('css', 'form[data-ghi-tabledrag] #ghi-gin-test-table.ghi-tabledrag');
    $this->assertSession()->elementNotExists('css', '#ghi-gin-preview-table.ghi-tabledrag');
    $this->assertSession()->elementNotExists('css', '#ghi-gin-plain-table.ghi-tabledrag');
    $this->assertSingleHandles('ghi-gin-test-table');
    $hierarchy = $this->assertSession()->waitForElement('css', '#ghi-gin-test-table.ghi-tabledrag--hierarchy');
    $this->assertNotNull($hierarchy, $this->getHierarchyState('#ghi-gin-test-table'));
    $this->assertSession()->elementNotExists('css', '#ghi-gin-test-table .ghi-tabledrag__group');
    $this->assertSame([1, 2, 3], $this->getRowOrder('ghi-gin-test-table'));

    $this->getRow('ghi-gin-test-table', 3)->find('css', 'a.tabledrag-handle')->dragTo($this->getRow('ghi-gin-test-table', 1)->find('css', 'a.tabledrag-handle'));
    $this->assertSame([3, 1, 2], $this->getRowOrder('ghi-gin-test-table'));
    $this->moveRow($this->getRow('ghi-gin-test-table', 1), 39);
    $this->assertSame('3', $this->getRow('ghi-gin-test-table', 1)->find('css', 'input[name$="[parent]"]')->getValue());
    $this->assertSame('1', $this->getRow('ghi-gin-test-table', 1)->find('css', 'input[name$="[depth]"]')->getValue());
    $this->assertHierarchyGuides('#ghi-gin-test-table', [1 => 3], [1]);
    $this->assertFocusedHandleFitsCell($this->getRow('ghi-gin-test-table', 3));
    $this->moveRow($this->getRow('ghi-gin-test-table', 2), 39);
    $this->moveRow($this->getRow('ghi-gin-test-table', 2), 39);
    $this->assertSame('1', $this->getRow('ghi-gin-test-table', 2)->find('css', 'input[name$="[parent]"]')->getValue());
    $this->assertSame('2', $this->getRow('ghi-gin-test-table', 2)->find('css', 'input[name$="[depth]"]')->getValue());
    $this->assertHierarchyGuides('#ghi-gin-test-table', [1 => 3, 2 => 1], [1, 2]);
    $this->assertFocusedHandleFitsCell($this->getRow('ghi-gin-test-table', 1));
    $this->assertFocusedHandleFitsCell($this->getRow('ghi-gin-test-table', 2));
    $nested = $this->getSession()->evaluateScript(<<<JS
(function () {
  const row = document.querySelector('#ghi-gin-test-table [data-test-row="2"]');
  return {height: row.getBoundingClientRect().height,
    lanes: Array.from(row.cells[0].querySelectorAll('.js-indentation')).map((indent) => ({
      line: indent.classList.contains('ghi-tabledrag__indent--line'),
      branch: indent.classList.contains('ghi-tabledrag__indent--branch')}))};
})()
JS);
    $this->assertGreaterThan(140, $nested['height']);
    $this->assertSame([['branch' => FALSE, 'line' => FALSE], ['branch' => TRUE, 'line' => TRUE]], $nested['lanes']);
    $this->moveRow($this->getRow('ghi-gin-test-table', 2), 37);
    $this->moveRow($this->getRow('ghi-gin-test-table', 2), 37);
    $this->assertHierarchyGuides('#ghi-gin-test-table', [1 => 3], [1]);
    $this->moveRow($this->getRow('ghi-gin-test-table', 1), 37);
    $this->assertSame('', $this->getRow('ghi-gin-test-table', 1)->find('css', 'input[name$="[parent]"]')->getValue());
    $this->assertHierarchyGuides('#ghi-gin-test-table', [], []);
    $this->moveRow($this->getRow('ghi-gin-test-table', 1), 38);
    $this->assertSame([1, 3, 2], $this->getRowOrder('ghi-gin-test-table'));

    $page = $this->getSession()->getPage();
    $this->assertFalse($page->findField('table[1][weight]')->isVisible());
    $page->findButton('Show row weights')->click();
    $this->assertTrue($page->findField('table[1][weight]')->isVisible());
    $page->findField('table[3][weight]')->setValue(-9);
    $page->findField('table[1][weight]')->setValue(-8);
    $page->findField('table[2][weight]')->setValue(-7);
    $this->clickButtonWithText('Save tree');
    $this->assertSame([3, 1, 2], $this->getRowOrder('ghi-gin-test-table'));
    $this->container->get('state')->resetCache();
    $this->assertSame('-9', $this->container->get('state')->get('ghi_gin_test.rows')[3]['weight']);

    for ($rebuild = 0; $rebuild < 2; $rebuild++) {
      $this->clickButtonWithText('Rebuild tree');
      $this->waitForAjaxToFinish();
      $this->assertSession()->elementExists('css', 'form[data-ghi-tabledrag] #ghi-gin-test-table.ghi-tabledrag');
      $this->assertSingleHandles('ghi-gin-test-table');
      $this->assertSame([3, 1, 2], $this->getRowOrder('ghi-gin-test-table'));
    }
    $this->assertTrue($this->getSession()->evaluateScript(<<<JS
(function () {
  const native = Drupal.tableDrag['ghi-gin-test-table'];
  Drupal.attachBehaviors(document, drupalSettings);
  Drupal.attachBehaviors(document, drupalSettings);
  return native === Drupal.tableDrag['ghi-gin-test-table'];
})()
JS));
    $this->assertSingleHandles('ghi-gin-test-table');
    $this->assertSession()->elementNotExists('css', '#ghi-gin-preview-table.ghi-tabledrag');

    $this->drupalGet('/ghi-gin/tabledrag-test');
    $this->assertSession()->elementNotExists('css', '#ghi-gin-tabledrag-test-form[data-ghi-tabledrag]');
    $this->assertSession()->elementNotExists('css', '#ghi-gin-test-table.ghi-tabledrag');
    $this->assertSingleHandles('ghi-gin-test-table');
  }

  /**
   * Tests the shared library is discovered in the real frontend editor modal.
   */
  public function testFrontendLayoutBuilderModal(): void {
    $uuid = $this->container->get('uuid')->generate();
    $component = new SectionComponent($uuid, 'content', [
      'id' => 'ghi_gin_tabledrag_test',
      'label' => 'Native tabledrag test',
      'label_display' => FALSE,
      'provider' => 'ghi_gin_test',
    ]);
    $node = $this->createNode();
    $node->get(OverridesSectionStorage::FIELD_NAME)->setValue([new Section('layout_onecol', [], [$component])]);
    $node->save();
    $this->loginEditor(['configure any layout']);
    $this->drupalGet($node->toUrl());
    $this->clickCustomize();
    $this->waitForAjaxToFinish();
    $this->clickElement('.layout-builder-block-update a');
    $this->waitForAjaxToFinish();
    $this->assertSession()->elementExists('css', '#layout-builder-modal form[data-ghi-tabledrag] #ghi-gin-modal-table.ghi-tabledrag');
    $this->assertSingleHandles('ghi-gin-modal-table');
    $this->moveRow($this->getRow('ghi-gin-modal-table', 2), 39);
    $this->assertSame('1', $this->getRow('ghi-gin-modal-table', 2)->find('css', 'input[name$="[parent]"]')->getValue());
    $this->assertHierarchyGuides('#ghi-gin-modal-table', [2 => 1], [2]);
    $this->assertFocusedHandleFitsCell($this->getRow('ghi-gin-modal-table', 1));
    $this->moveRow($this->getRow('ghi-gin-modal-table', 2), 37);
    $this->assertHierarchyGuides('#ghi-gin-modal-table', [], []);
    $this->moveRow($this->getRow('ghi-gin-modal-table', 2), 38);
    $this->assertSame([2, 1, 3], $this->getRowOrder('ghi-gin-modal-table'));
    $this->assertSingleHandles('ghi-gin-modal-table');

    $this->getSession()->resizeWindow(390, 844);
    $this->assertLessThanOrEqual(575, $this->getSession()->evaluateScript('window.innerWidth'));
    $this->assertSession()->elementNotExists('css', '#ghi-gin-modal-table.cd-table--responsive');
    $geometry = $this->getSession()->evaluateScript(<<<JS
(function () {
  const table = document.getElementById('ghi-gin-modal-table');
  const row = table.querySelector('tbody > tr');
  const handle = row.querySelector('a.tabledrag-handle').getBoundingClientRect();
  return [getComputedStyle(table).display, getComputedStyle(row).display,
    getComputedStyle(row.firstElementChild).display,
    getComputedStyle(row.lastElementChild).display, handle.width, handle.height];
})()
JS);
    $this->assertSame(['table', 'table-row', 'table-cell', 'none'], array_slice($geometry, 0, 4));
    $this->assertEquals([44, 44], array_slice($geometry, 4));
    $weight = $this->getRow('ghi-gin-modal-table', 1)->find('css', 'select[name$="[weight]"]');
    $this->assertFalse($weight->isVisible());
    $this->getSession()->getPage()->findButton('Show row weights')->click();
    $this->assertTrue($weight->isVisible());
    $this->getSession()->getPage()->findButton('Hide row weights')->click();
    $this->assertFalse($weight->isVisible());
  }

  /**
   * Tests the real custom container, including its legacy action-cell sizing.
   */
  public function testConfigurationContainerModal(): void {
    ImageStyle::create(['name' => '16x9_480', 'label' => 'Link preview'])->save();
    ResponsiveImageStyle::create([
      'id' => 'card_hero',
      'label' => 'Card hero',
      'breakpoint_group' => 'common_design',
      'fallback_image_style' => '16x9_480',
    ])->save();
    $uri = $this->container->get('file_system')->copy(DRUPAL_ROOT . '/core/tests/fixtures/files/image-test.png', 'public://custom-link-preview.png');
    $file = File::create(['uri' => $uri, 'status' => TRUE]);
    $file->save();
    $items = [];
    foreach ([1, 2, 3] as $id) {
      $items[$id] = [
        'id' => $id,
        'pid' => NULL,
        'weight' => $id,
        'item_type' => 'link',
        'config' => [
          'label' => 'Custom item ' . $id,
          'link' => ['link' => ['link_type' => 'custom', 'link_custom' => ['url' => 'https://example.org/item-' . $id]]],
          'content' => ['date' => '2026-11-05', 'description_toggle' => FALSE],
          'image' => ['image' => [$file->id()], 'crop_type' => '16x9'],
        ],
      ];
    }
    $uuid = $this->container->get('uuid')->generate();
    $component = new SectionComponent($uuid, 'content', [
      'id' => 'links',
      'label' => 'Custom links',
      'label_display' => FALSE,
      'provider' => 'ghi_blocks',
      'hpc' => ['links' => ['links' => $items]],
    ]);
    $node = $this->createNode();
    $node->get(OverridesSectionStorage::FIELD_NAME)->setValue([new Section('layout_onecol', [], [$component])]);
    $node->save();
    $this->loginEditor(['configure any layout']);
    $this->drupalGet($node->toUrl());
    $this->clickCustomize();
    $this->waitForAjaxToFinish();
    $this->clickElement('.layout-builder-block-update a');
    $this->waitForAjaxToFinish();
    $this->assertSession()->elementExists('css', '#layout-builder-modal table.summary-table.ghi-tabledrag');
    $this->assertSession()->elementNotExists('css', '#layout-builder-modal table.summary-table.ghi-tabledrag--hierarchy');
    $this->assertSession()->elementNotExists('css', '#layout-builder-modal table.summary-table .ghi-tabledrag__group, #layout-builder-modal table.summary-table .ghi-tabledrag__indent');
    $geometry = $this->getSession()->evaluateScript(<<<JS
(function () {
  const table = document.querySelector('#layout-builder-modal table.summary-table');
  const row = table.querySelector('tbody > tr');
  const handle = row.querySelector('a.tabledrag-handle');
  const handleRect = handle.getBoundingClientRect();
  const contentRect = row.firstElementChild.querySelector('.cell-wrapper-inner').getBoundingClientRect();
  const buttons = Array.from(row.querySelectorAll('.operation-buttons input[type="submit"]'));
  return {handlePosition: getComputedStyle(handle).position,
    handleWidth: handleRect.width, handleHeight: handleRect.height,
    handleTop: handleRect.top, handleBottom: handleRect.bottom, handleRight: handleRect.right,
    contentTop: contentRect.top, contentBottom: contentRect.bottom, contentLeft: contentRect.left,
    buttonTops: buttons.map((button) => button.getBoundingClientRect().top),
    rowHeight: row.getBoundingClientRect().height};
})()
JS);
    $this->assertSame('relative', $geometry['handlePosition']);
    $this->assertEquals(44, $geometry['handleWidth']);
    $this->assertEquals(44, $geometry['handleHeight']);
    $this->assertCount(2, $geometry['buttonTops']);
    $this->assertGreaterThanOrEqual($geometry['handleRight'], $geometry['contentLeft'], json_encode($geometry));
    $this->assertGreaterThan(max($geometry['handleTop'], $geometry['contentTop']), min($geometry['handleBottom'], $geometry['contentBottom']), json_encode($geometry));
    $this->assertEqualsWithDelta($geometry['buttonTops'][0], $geometry['buttonTops'][1], 1, json_encode($geometry));
    $this->clickButtonWithText('Save order');
    $this->waitForAjaxToFinish();
    $this->assertSession()->elementsCount('css', '#layout-builder-modal table.summary-table.ghi-tabledrag a.tabledrag-handle', 3);
    $this->assertSession()->elementNotExists('css', '#layout-builder-modal table.summary-table .ghi-tabledrag__group, #layout-builder-modal table.summary-table .ghi-tabledrag__indent');
  }

  /**
   * Tests Key figures' real grouped editor and its wrapped indentation markup.
   */
  public function testGroupedConfigurationContainerModal(): void {
    $items = [
      ['id' => 0, 'pid' => NULL, 'weight' => 0, 'item_type' => 'item_group', 'config' => ['label' => 'Population']],
      [
        'id' => 1,
        'pid' => 0,
        'weight' => 1,
        'item_type' => 'label_value',
        'config' => ['label' => 'People in need', 'value' => 100, 'formatting' => 'raw'],
      ],
      [
        'id' => 2,
        'pid' => 0,
        'weight' => 2,
        'item_type' => 'label_value',
        'config' => ['label' => 'People targeted', 'value' => 80, 'formatting' => 'raw'],
      ],
      ['id' => 3, 'pid' => NULL, 'weight' => 3, 'item_type' => 'item_group', 'config' => ['label' => 'Response']],
      [
        'id' => 4,
        'pid' => 3,
        'weight' => 4,
        'item_type' => 'label_value',
        'config' => ['label' => 'People reached', 'value' => 70, 'formatting' => 'raw'],
      ],
    ];
    $uuid = $this->container->get('uuid')->generate();
    $component = new SectionComponent($uuid, 'content', [
      'id' => 'ghi_gin_grouped_tabledrag_test',
      'label' => 'Key figures',
      'label_display' => FALSE,
      'provider' => 'ghi_gin_test',
      'hpc' => ['key_figures' => ['items' => $items]],
    ]);
    $node = $this->createNode();
    $node->get(OverridesSectionStorage::FIELD_NAME)->setValue([new Section('layout_onecol', [], [$component])]);
    $node->save();
    $this->loginEditor(['configure any layout']);
    $this->drupalGet($node->toUrl());
    $this->clickCustomize();
    $this->waitForAjaxToFinish();
    $this->clickElement('.layout-builder-block-update a');
    $this->waitForAjaxToFinish();
    $this->assertSession()->elementExists('css', '#layout-builder-modal table.summary-table.ghi-tabledrag');
    $this->assertSame([0, 1, 2, 3, 4], $this->getContainerRowOrder());
    $this->assertTrue($this->getContainerRow(0)->hasClass('tabledrag-root'));
    $this->assertTrue($this->getContainerRow(1)->hasClass('tabledrag-leaf'));
    $this->assertSession()->elementTextContains('css', '#layout-builder-modal table.summary-table', 'Population');
    $this->assertHierarchyGuides('#layout-builder-modal table.summary-table', [1 => 0, 2 => 0, 4 => 3], [2, 4]);
    $this->assertFocusedHandleFitsCell($this->getContainerRow(0));
    $this->assertFocusedHandleFitsCell($this->getContainerRow(1));
    $geometry = $this->getSession()->evaluateScript(<<<JS
Array.from(document.querySelectorAll('#layout-builder-modal table.summary-table tbody > tr')).map((row) => {
  const cell = row.firstElementChild;
  const handle = cell.querySelector('a.tabledrag-handle').getBoundingClientRect();
  const wrapper = cell.querySelector('.value-wrapper');
  const text = Array.from(wrapper.childNodes).find((node) => node.nodeType === Node.TEXT_NODE && node.textContent.trim());
  const range = document.createRange();
  range.selectNode(text);
  const label = range.getBoundingClientRect();
  const indentation = cell.querySelector('.js-indentation')?.getBoundingClientRect();
  return {id: Number(row.querySelector('.row-id').value), width: handle.width, height: handle.height,
    labelLeft: label.left, handleRight: handle.right,
    overlaps: Math.min(handle.bottom, label.bottom) > Math.max(handle.top, label.top),
    indentationWidth: indentation?.width || 0, indentationRight: indentation?.right || 0, handleLeft: handle.left};
})
JS);
    foreach ($geometry as $row) {
      $this->assertEquals(44, $row['width'], json_encode($row));
      $this->assertEquals(44, $row['height'], json_encode($row));
      $this->assertGreaterThanOrEqual($row['handleRight'], $row['labelLeft'], json_encode($row));
      $this->assertTrue($row['overlaps'], json_encode($row));
      if (in_array($row['id'], [1, 2, 4])) {
        $this->assertGreaterThan(0, $row['indentationWidth'], json_encode($row));
        $this->assertGreaterThanOrEqual($row['indentationRight'], $row['handleLeft'], json_encode($row));
      }
    }
    $parent = $this->getContainerRow(2)->find('css', '.row-pid');
    $this->assertSame('0', $parent->getValue());
    $this->assertFalse($parent->isVisible());
    $this->moveRow($this->getContainerRow(2), 38);
    $this->assertSame([0, 2, 1, 3, 4], $this->getContainerRowOrder());
    $this->assertSame('0', $parent->getValue());
    $this->assertHierarchyGuides('#layout-builder-modal table.summary-table', [1 => 0, 2 => 0, 4 => 3], [1, 4]);
    $this->clickButtonWithText('Save order');
    $this->waitForAjaxToFinish();
    $this->assertSame([0, 2, 1, 3, 4], $this->getContainerRowOrder());
    $this->assertSame('0', $this->getContainerRow(2)->find('css', '.row-pid')->getValue());
    $this->assertSession()->elementsCount('css', '#layout-builder-modal table.summary-table.ghi-tabledrag a.tabledrag-handle', 5);
    $this->assertHierarchyGuides('#layout-builder-modal table.summary-table', [1 => 0, 2 => 0, 4 => 3], [1, 4]);
  }

  /**
   * Asserts guide paint follows native indentation and the current DOM order.
   */
  private function assertHierarchyGuides(string $table_selector, array $parents, array $last_children): void {
    $selector = json_encode($table_selector);
    $expected_parents = json_encode($parents, JSON_FORCE_OBJECT);
    $expected_last = json_encode($last_children);
    $decorated = $this->getSession()->wait(5000, <<<JS
(function () {
  const table = document.querySelector($selector);
  if (!table?.classList.contains('ghi-tabledrag--hierarchy')) return false;
  const rows = Array.from(table.tBodies[0].rows);
  const parents = $expected_parents;
  const last = $expected_last;
  return rows.every((row) => {
    const id = Number(row.dataset.testRow ?? row.querySelector('.row-id').value);
    const indents = Array.from(row.cells[0].querySelectorAll('.js-indentation'));
    const branch = indents.at(-1);
    const group = Object.values(parents).includes(id);
    return row.classList.contains('ghi-tabledrag__group') === (group || row.classList.contains('tabledrag-root')) &&
      (Object.hasOwn(parents, id)
        ? branch?.classList.contains('ghi-tabledrag__indent--branch') && branch.classList.contains('ghi-tabledrag__indent--end') === last.includes(id)
        : indents.length === 0);
  });
})()
JS);
    $this->assertTrue($decorated, 'Hierarchy decoration follows native row and indentation changes: ' . $this->getHierarchyState($table_selector));
    $geometry = $this->getSession()->evaluateScript(<<<JS
Array.from(document.querySelector($selector).tBodies[0].rows).map((row) => {
  const handle = row.cells[0].querySelector('a.tabledrag-handle').getBoundingClientRect();
  const indent = Array.from(row.cells[0].querySelectorAll('.js-indentation')).at(-1);
  let guide = null;
  if (indent) {
    const rect = indent.getBoundingClientRect();
    const before = getComputedStyle(indent, '::before');
    const after = getComputedStyle(indent, '::after');
    guide = {axis: rect.left + parseFloat(before.left), top: rect.top + parseFloat(before.top),
      bottom: rect.top + parseFloat(before.top) + parseFloat(before.height),
      branchY: rect.top + parseFloat(after.top), branchWidth: parseFloat(after.width),
      line: indent.classList.contains('ghi-tabledrag__indent--line'),
      verticalWidth: parseFloat(before.borderInlineStartWidth), horizontalWidth: parseFloat(after.borderBlockStartWidth),
      color: before.borderInlineStartColor,
      verticalContent: before.content, horizontalContent: after.content};
  }
  const rect = row.getBoundingClientRect();
  return {id: Number(row.dataset.testRow ?? row.querySelector('.row-id').value),
    center: handle.left + handle.width / 2, middle: handle.top + handle.height / 2,
    top: rect.top, bottom: rect.bottom, guide};
})
JS);
    $rows = array_column($geometry, NULL, 'id');
    foreach ($parents as $id => $parent) {
      $row = $rows[$id];
      $guide = $row['guide'];
      $message = json_encode($row);
      $this->assertTrue($guide['line'], $message);
      $this->assertNotContains($guide['verticalContent'], ['none', 'normal'], $message);
      $this->assertNotContains($guide['horizontalContent'], ['none', 'normal'], $message);
      $this->assertGreaterThan(0, $guide['verticalWidth'], $message);
      $this->assertGreaterThan(0, $guide['horizontalWidth'], $message);
      $this->assertNotContains($guide['color'], ['transparent', 'rgba(0, 0, 0, 0)'], $message);
      $this->assertGreaterThan(0, $guide['branchWidth'], $message);
      $this->assertEqualsWithDelta($rows[$parent]['center'], $guide['axis'], 1, $message);
      $this->assertEqualsWithDelta($row['middle'], $guide['branchY'], 1, $message);
      $this->assertEqualsWithDelta($row['top'], $guide['top'], 1, $message);
      $this->assertEqualsWithDelta(in_array($id, $last_children) ? $row['middle'] : $row['bottom'], $guide['bottom'], 1, $message);
    }
  }

  /**
   * Returns native and decorative state to explain failed synchronization.
   */
  private function getHierarchyState(string $table_selector): string {
    $selector = json_encode($table_selector);
    return json_encode($this->getSession()->evaluateScript(<<<JS
(function () {
  const table = document.querySelector($selector);
  return {classes: table.className, settings: drupalSettings.tableDrag[table.id],
    nativeIndent: Drupal.tableDrag[table.id]?.indentEnabled,
    rows: Array.from(table.tBodies[0].rows).map((row) => ({classes: row.className,
      id: row.dataset.testRow ?? row.querySelector('.row-id')?.value,
      indents: Array.from(row.cells[0].querySelectorAll('.js-indentation')).map((indent) => indent.className)}))};
})()
JS));
  }

  /**
   * Asserts the whole focusable target and its focus halo fit the first cell.
   */
  private function assertFocusedHandleFitsCell(NodeElement $row): void {
    $handle = $row->find('css', 'a.tabledrag-handle');
    $handle->focus();
    $xpath = json_encode($row->getXpath());
    $geometry = $this->getSession()->evaluateScript(<<<JS
(function () {
  const row = document.evaluate($xpath, document, null, XPathResult.FIRST_ORDERED_NODE_TYPE, null).singleNodeValue;
  const handle = row.querySelector('a.tabledrag-handle');
  const target = handle.getBoundingClientRect();
  const cell = row.cells[0].getBoundingClientRect();
  const ring = getComputedStyle(handle, '::before');
  return {focused: document.activeElement === handle, width: target.width, height: target.height,
    inside: target.left - 2 >= cell.left && target.right + 2 <= cell.right && target.top - 2 >= cell.top && target.bottom + 2 <= cell.bottom,
    content: ring.content, shadow: ring.boxShadow};
})()
JS);
    $this->assertTrue($geometry['focused']);
    $this->assertEquals(44, $geometry['width']);
    $this->assertEquals(44, $geometry['height']);
    $this->assertTrue($geometry['inside'], json_encode($geometry));
    $this->assertNotContains($geometry['content'], ['none', 'normal']);
    $this->assertNotSame('none', $geometry['shadow']);
    $handle->blur();
  }

  /**
   * Returns a real configuration-container row by its immutable item ID.
   */
  private function getContainerRow(int $id): NodeElement {
    return $this->assertSession()->elementExists('css', '#layout-builder-modal table.summary-table .row-id[value="' . $id . '"]')->find('xpath', 'ancestor::tr');
  }

  /**
   * Returns the item order from a real configuration-container summary table.
   */
  private function getContainerRowOrder(): array {
    return array_map(static fn(NodeElement $row) => (int) $row->find('css', '.row-id')->getValue(), $this->getSession()->getPage()->findAll('css', '#layout-builder-modal table.summary-table tbody > tr'));
  }

  /**
   * Returns the row with the requested fixture identity.
   */
  private function getRow(string $table_id, int $row_id): NodeElement {
    return $this->assertSession()->elementExists('css', '#' . $table_id . ' [data-test-row="' . $row_id . '"]');
  }

  /**
   * Returns the native DOM order without interpreting weights or parent IDs.
   */
  private function getRowOrder(string $table_id): array {
    return array_map(static fn(NodeElement $row) => (int) $row->getAttribute('data-test-row'), $this->getSession()->getPage()->findAll('css', '#' . $table_id . ' > tbody > tr'));
  }

  /**
   * Sends arrow keys to core's existing focusable handle.
   */
  private function moveRow(NodeElement $row, int $key): void {
    $handle = $row->find('css', 'a.tabledrag-handle');
    $handle->focus();
    $handle->keyDown($key);
    $handle->keyUp($key);
    $handle->blur();
  }

  /**
   * Asserts repeated behavior attachment did not duplicate native controls.
   */
  private function assertSingleHandles(string $table_id): void {
    foreach ([1, 2, 3] as $row_id) {
      $this->assertCount(1, $this->getRow($table_id, $row_id)->findAll('css', 'a.tabledrag-handle'));
    }
  }

}
