<?php

namespace Drupal\Tests\ghi_blocks\FunctionalJavascript;

use Behat\Mink\Element\NodeElement;
use Drupal\Core\Plugin\Context\EntityContext;
use Drupal\file\Entity\File;
use Drupal\ghi_blocks\Plugin\Block\Generic\LinkCarousel;
use Drupal\image\Entity\ImageStyle;
use Drupal\layout_builder\Plugin\SectionStorage\OverridesSectionStorage;
use Drupal\layout_builder\Section;
use Drupal\layout_builder\SectionComponent;
use Drupal\responsive_image\Entity\ResponsiveImageStyle;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the draggable display boundary in the actual block editing dialog.
 */
#[Group('ghi_blocks')]
class LinkCarouselPlacementControlTest extends BlockUiBase {

  /**
   * Tests the display boundary survives pending edits, order, and Update.
   */
  public function testPlacementControlsPreservePendingConfiguration(): void {
    ImageStyle::create(['name' => 'link_carousel_feature_thumbnail', 'label' => 'Feature thumbnail'])->save();
    ResponsiveImageStyle::create([
      'id' => 'link_carousel_feature',
      'label' => 'Feature image',
      'breakpoint_group' => 'common_design',
      'fallback_image_style' => 'link_carousel_feature_thumbnail',
    ])->save();
    $uri = $this->container->get('file_system')->copy(DRUPAL_ROOT . '/core/tests/fixtures/files/image-test.png', 'public://placement-control.png');
    $file = File::create(['uri' => $uri, 'status' => TRUE]);
    $file->save();
    $items = [];
    foreach (range(0, 5) as $id) {
      $items[$id] = [
        'id' => $id,
        'pid' => NULL,
        'item_type' => 'carousel_item',
        'weight' => $id,
        'config' => [
          'label' => 'Article ' . $id,
          'value' => [
            'image' => [$file->id()],
            'url' => 'https://example.org/article-' . $id,
          ],
        ],
      ];
    }
    $items[99] = [
      'id' => 99,
      'pid' => NULL,
      'item_type' => 'carousel_item',
      'weight' => -1,
      'config' => ['label' => 'Incomplete article', 'value' => []],
    ];
    $settings = [
      'links' => ['items' => $items, 'display_end_weight' => NULL],
      'cta' => [
        'items' => [
          [
            'id' => 1,
            'pid' => NULL,
            'item_type' => 'call_to_action',
            'weight' => 0,
            'config' => [
              'label' => 'Original support title',
              'value' => [
                'description' => 'Original support description',
                'url' => 'https://example.org/support',
                'button_label' => 'Support now',
                'show_heart' => TRUE,
              ],
            ],
          ],
        ],
        'active_item_id' => NULL,
        'source_items' => [],
      ],
    ];
    $uuid = $this->container->get('uuid')->generate();
    $component = new SectionComponent($uuid, 'content', [
      'id' => 'generic_link_carousel',
      'label' => '<none>',
      'label_display' => FALSE,
      'provider' => 'ghi_blocks',
      'hpc' => $settings,
    ]);
    $node = $this->createNode();
    // A legacy component remains editable without permitting new placements.
    $node->get(OverridesSectionStorage::FIELD_NAME)->setValue([new Section('layout_onecol', [], [$component])]);
    $node->save();
    $saved_settings = $this->getSavedSettings($node->id(), $uuid);
    $storage = $this->container->get('plugin.manager.layout_builder.section_storage')->load('overrides', ['entity' => EntityContext::fromEntity($node)]);
    $tempstore_key = $storage->getTempstoreKey();
    $this->loginEditor(['configure any layout']);
    $this->drupalGet($node->toUrl());
    $this->clickCustomize();
    $this->waitForAjaxToFinish();
    $this->clickElement('.layout-builder-block-update a');
    $this->waitForAjaxToFinish();
    $before_controls = $this->getTemporarySettings($tempstore_key, $uuid);

    $this->assertSession()->elementNotExists('css', '[data-link-carousel-placement-control]');
    $this->assertSession()->elementNotExists('css', '[data-link-carousel-last]');
    $this->assertStringContainsString('Displayed news ends here', $this->getSeparator()->getText());
    $this->assertSame([], $this->getSeparator()->findAll('css', 'input[type="submit"]'));
    $this->assertSame([99, 0, 1, 2, 3, 4, '_display_end', 5], $this->getRowOrder());
    $this->assertPlacement(99, 'Not available');
    $this->assertPlacement(0, 'Main news');
    $this->assertPlacement(4, 'Featured 4');
    $this->assertPlacement(5, 'Not displayed');

    $table = $this->assertSession()->elementExists('css', '#layout-builder-modal table[data-link-carousel-placements]');
    $maximum = LinkCarousel::MAX_FEATURED_ITEMS + 1;
    $this->assertSame((string) $maximum, $table->getAttribute('data-link-carousel-max-items'));
    $this->getSession()->executeScript("document.querySelector('table[data-link-carousel-placements]').setAttribute('data-link-carousel-max-items', '3');");
    // Inspect a repair step before drop: badges must already respect the
    // supplied limit even while the physical boundary still exceeds it.
    $handle = $this->getSeparator()->find('css', 'a.tabledrag-handle');
    $handle->focus();
    $handle->keyDown(38);
    $handle->keyUp(38);
    $this->assertSame([99, 0, 1, 2, 3, '_display_end', 4, 5], $this->getRowOrder());
    $this->assertPlacement(2, 'Featured 2');
    $this->assertPlacement(3, 'Not displayed');
    $this->assertSame('2', $table->getAttribute('data-link-carousel-featured-count'));
    $handle->blur();
    $this->assertSame([99, 0, 1, 2, '_display_end', 3, 4, 5], $this->getRowOrder());
    $this->assertSession()->elementTextContains('css', '[data-link-carousel-placement-feedback]', 'one to 3 valid news items');
    $this->moveRow($this->getSeparator(), 'down');
    $this->moveRow($this->getRow(3), 'up');
    $this->assertSame([99, 0, 1, 2, '_display_end', 3, 4, 5], $this->getRowOrder());
    $this->assertSession()->elementTextContains('css', '[data-link-carousel-placement-feedback]', 'up to 2 Featured items');

    // The numeric-weight fallback uses the same limit, not a second constant.
    $this->getSession()->executeScript("const table = document.querySelector('table[data-link-carousel-placements]'); Drupal.tableDrag[table.id].displayColumns(true);");
    $this->getSeparator()->find('css', '.row-weight')->setValue('8');
    $this->assertSame([99, 0, 1, 2, '_display_end', 3, 4, 5], $this->getRowOrder());
    $this->assertSession()->elementTextContains('css', '[data-link-carousel-placement-feedback]', 'one to 3 valid news items');
    $this->assertSame('2', $table->getAttribute('data-link-carousel-featured-count'));
    $this->getSession()->executeScript("const table = document.querySelector('table[data-link-carousel-placements]'); Drupal.tableDrag[table.id].displayColumns(false); table.setAttribute('data-link-carousel-max-items', '$maximum');");
    $this->moveRow($this->getSeparator(), 'down', 2);
    $this->assertSame([99, 0, 1, 2, 3, 4, '_display_end', 5], $this->getRowOrder());

    // A later addition must not displace the existing five displayed items.
    $this->clickButtonWithText('Add new news item');
    $this->waitForAjaxToFinish();
    $page = $this->getSession()->getPage();
    $page->findField('links[items][item_config][plugin_config][label]')->setValue('A later news item');
    $page->findField('links[items][item_config][plugin_config][value][url]')->setValue('https://example.org/later-news');
    $page->attachFileToField('files[links_items_item_config_plugin_config_value_image]', DRUPAL_ROOT . '/core/tests/fixtures/files/image-test.png');
    $this->waitForAjaxToFinish();
    $this->clickButtonWithText('Add news item');
    $this->waitForAjaxToFinish();
    $this->assertSame([99, 0, 1, 2, 3, 4, '_display_end', 5, 100], $this->getRowOrder());
    $this->assertPlacement(4, 'Featured 4');
    $this->assertPlacement(100, 'Not displayed');
    $this->clickElement('#layout-builder-modal input[name="remove-100"]');
    $this->waitForAjaxToFinish();
    $this->assertSame([99, 0, 1, 2, 3, 4, '_display_end', 5], $this->getRowOrder());

    // Mink's W3C pointer actions exercise real mouse drag/drop, not simulated
    // DOM reordering. Return to the original order before the keyboard checks.
    $this->dragRow($this->getSeparator(), $this->getRow(3));
    $this->assertSame([99, 0, 1, 2, '_display_end', 3, 4, 5], $this->getRowOrder());
    $this->assertPlacement(2, 'Featured 2');
    $this->assertPlacement(3, 'Not displayed');
    $this->moveRow($this->getSeparator(), 'up', 2);
    $this->dragRow($this->getSeparator(), $this->getRow(0));
    $this->assertSame([99, 0, '_display_end', 1, 2, 3, 4, 5], $this->getRowOrder());
    $this->assertSession()->elementTextContains('css', '[data-link-carousel-placement-feedback]', 'Keep at least one valid news item above the display boundary.');
    $this->moveRow($this->getSeparator(), 'down', 4);
    $this->assertSame([99, 0, 1, 2, 3, 4, '_display_end', 5], $this->getRowOrder());

    // The same core keyboard interaction moves the separator and news rows.
    $this->moveRow($this->getSeparator(), 'up', 4);
    $this->assertSame([99, 0, '_display_end', 1, 2, 3, 4, 5], $this->getRowOrder());
    $this->assertPlacement(0, 'Main news');
    $this->assertPlacement(1, 'Not displayed');

    // Neither the separator nor the last main item may leave zero valid news.
    $this->moveRow($this->getSeparator(), 'up');
    $this->assertSame([99, 0, '_display_end', 1, 2, 3, 4, 5], $this->getRowOrder());
    $this->moveRow($this->getRow(0), 'down');
    $this->assertSame([99, 0, '_display_end', 1, 2, 3, 4, 5], $this->getRowOrder());

    $this->moveRow($this->getSeparator(), 'down', 4);
    $this->assertSame([99, 0, 1, 2, 3, 4, '_display_end', 5], $this->getRowOrder());
    $this->assertPlacement(4, 'Featured 4');
    $this->moveRow($this->getSeparator(), 'down');
    $this->assertSame([99, 0, 1, 2, 3, 4, '_display_end', 5], $this->getRowOrder());
    $this->moveRow($this->getRow(5), 'up');
    $this->assertSame([99, 0, 1, 2, 3, 4, '_display_end', 5], $this->getRowOrder());

    $this->moveRow($this->getSeparator(), 'up', 2);
    $this->assertPlacement(2, 'Featured 2');
    $this->assertPlacement(3, 'Not displayed');
    $this->moveRow($this->getRow(5), 'up', 3);
    $this->assertSame([99, 0, 1, 2, 5, '_display_end', 3, 4], $this->getRowOrder());
    $this->assertPlacement(5, 'Featured 3');

    // Crossing the boundary changes display membership, including item ID zero.
    $this->moveRow($this->getRow(0), 'down', 4);
    $this->assertSame([99, 1, 2, 5, '_display_end', 0, 3, 4], $this->getRowOrder());
    $this->assertPlacement(1, 'Main news');
    $this->assertPlacement(5, 'Featured 2');
    $this->assertPlacement(0, 'Not displayed');
    $boundary_weight = $this->getSeparator()->find('css', '.row-weight')->getValue();

    // Making a displayed item unavailable must not move the physical boundary
    // or pull a previously excluded item into the displayed group.
    $this->clickElement('#layout-builder-modal input[name="edit-1"]');
    $this->waitForAjaxToFinish();
    $this->getSession()->getPage()->findField('links[items][item_config][plugin_config][label]')->setValue('Pending edited article 1');
    $this->getSession()->getPage()->findField('links[items][item_config][plugin_config][value][url]')->setValue('');
    $this->clickButtonWithText('Update news item');
    $this->waitForAjaxToFinish();
    $this->assertSame($boundary_weight, $this->getSeparator()->find('css', '.row-weight')->getValue());
    $this->assertSame([99, 1, 2, 5, '_display_end', 0, 3, 4], $this->getRowOrder());
    $this->assertPlacement(1, 'Not available');
    $this->assertPlacement(2, 'Main news');
    $this->assertPlacement(5, 'Featured 1');
    $this->assertPlacement(0, 'Not displayed');
    $this->clickElement('#layout-builder-modal input[name="cta-button"]');
    $this->waitForAjaxToFinish();
    $this->clickElement('#layout-builder-modal input[name="edit-1"]');
    $this->waitForAjaxToFinish();
    $this->getSession()->getPage()->findField('cta[items][item_config][plugin_config][label]')->setValue('Pending support title');
    $this->waitForAjaxToFinish();
    $this->clickElement('#layout-builder-modal input[name="links-button"]');
    $this->waitForAjaxToFinish();
    $this->assertSame([99, 1, 2, 5, '_display_end', 0, 3, 4], $this->getRowOrder());
    $this->assertStringContainsString('Pending edited article 1', $this->getRow(1)->getText());
    $this->clickButtonWithText('Save order');
    $this->waitForAjaxToFinish();
    $this->assertSame([99, 1, 2, 5, '_display_end', 0, 3, 4], $this->getRowOrder());
    $this->assertPlacement(2, 'Main news');
    $this->assertPlacement(5, 'Featured 1');
    $this->assertStringContainsString('Pending edited article 1', $this->getRow(1)->getText());
    $this->assertSame($saved_settings, $this->getSavedSettings($node->id(), $uuid));
    $this->assertSame($before_controls, $this->getTemporarySettings($tempstore_key, $uuid));

    $this->clickElement('#layout-builder-modal input[name="cta-button"]');
    $this->waitForAjaxToFinish();
    $this->assertSession()->fieldValueEquals('cta[items][item_config][plugin_config][label]', 'Pending support title');
    $this->clickElement('#layout-builder-update-block input[data-drupal-selector="edit-actions-submit"]');
    $this->waitForAjaxToFinish();
    $this->assertSession()->waitForElementRemoved('css', '#layout-builder-modal');
    $pending = $this->getTemporarySettings($tempstore_key, $uuid);
    $this->assertNotNull($pending);
    $this->assertIsInt($pending['links']['display_end_weight']);
    $this->assertSame([99, 1, 2, 5, 0, 3, 4], array_values(array_column($pending['links']['items'], 'id')));
    $weights = array_column($pending['links']['items'], 'weight');
    $weights[] = $pending['links']['display_end_weight'];
    $this->assertCount(8, array_unique($weights));
    $above = array_filter($pending['links']['items'], fn(array $item) => $item['weight'] < $pending['links']['display_end_weight']);
    $this->assertSame([99, 1, 2, 5], array_values(array_column($above, 'id')));
    $by_id = array_column($pending['links']['items'], NULL, 'id');
    $this->assertSame('Pending edited article 1', $by_id[1]['config']['label']);
    $this->assertSame('', $by_id[1]['config']['value']['url']);
    $cta_by_id = array_column($pending['cta']['items'], NULL, 'id');
    $this->assertSame('Pending support title', $cta_by_id[1]['config']['label']);
    $this->assertSame($saved_settings, $this->getSavedSettings($node->id(), $uuid));
  }

  /**
   * Tests the first incomplete news item starts above the physical boundary.
   */
  public function testFirstDraftAddedToEmptyCarouselStartsAboveBoundary(): void {
    $uuid = $this->container->get('uuid')->generate();
    $component = new SectionComponent($uuid, 'content', [
      'id' => 'generic_link_carousel',
      'label' => '<none>',
      'label_display' => FALSE,
      'provider' => 'ghi_blocks',
      'hpc' => ['links' => ['items' => []], 'cta' => ['items' => [], 'active_item_id' => NULL]],
    ]);
    $node = $this->createNode();
    $node->get(OverridesSectionStorage::FIELD_NAME)->setValue([new Section('layout_onecol', [], [$component])]);
    $node->save();
    $storage = $this->container->get('plugin.manager.layout_builder.section_storage')->load('overrides', ['entity' => EntityContext::fromEntity($node)]);
    $this->loginEditor(['configure any layout']);
    $this->drupalGet($node->toUrl());
    $this->clickCustomize();
    $this->waitForAjaxToFinish();
    $this->clickElement('.layout-builder-block-update a');
    $this->waitForAjaxToFinish();
    $this->clickButtonWithText('Add new news item');
    $this->waitForAjaxToFinish();
    $this->getSession()->getPage()->findField('links[items][item_config][plugin_config][label]')->setValue('An incomplete first draft');
    $this->clickButtonWithText('Add news item');
    $this->waitForAjaxToFinish();
    $this->assertSame([1, '_display_end'], $this->getRowOrder());
    $this->assertPlacement(1, 'Not available');
    $this->clickElement('#layout-builder-modal input[name="cta-button"]');
    $this->waitForAjaxToFinish();
    $this->clickElement('#layout-builder-update-block input[data-drupal-selector="edit-actions-submit"]');
    $this->waitForAjaxToFinish();
    $this->assertSession()->waitForElementRemoved('css', '#layout-builder-modal');
    $pending = $this->getTemporarySettings($storage->getTempstoreKey(), $uuid);
    $this->assertSame(1, $pending['links']['display_end_weight']);
    $this->assertSame(0, $pending['links']['items'][0]['weight']);
    $this->assertSame('An incomplete first draft', $pending['links']['items'][0]['config']['label']);
  }

  /**
   * Returns the current row using its stable item ID, including zero.
   */
  private function getRow(int $id): NodeElement {
    foreach ($this->assertSession()->elementExists('css', '#layout-builder-modal table[data-link-carousel-placements]')->findAll('css', 'tbody > tr.draggable') as $row) {
      $identity = $row->find('css', '.row-id');
      if ($identity && (string) $identity->getValue() === (string) $id) {
        return $row;
      }
    }
    $this->fail('The news row with ID ' . $id . ' was not found.');
  }

  /**
   * Returns the synthetic boundary row, which is never a configured news item.
   */
  private function getSeparator(): NodeElement {
    return $this->assertSession()->elementExists('css', '#layout-builder-modal tr[data-link-carousel-display-end]');
  }

  /**
   * Drags a row onto another row through WebDriver's native mouse actions.
   */
  private function dragRow(NodeElement $row, NodeElement $target): void {
    $handle = $row->find('css', 'a.tabledrag-handle');
    $target_handle = $target->find('css', 'a.tabledrag-handle');
    $this->assertNotNull($handle);
    $this->assertNotNull($target_handle);
    $handle->dragTo($target_handle);
  }

  /**
   * Moves either kind of row using core tabledrag's actual keyboard handlers.
   */
  private function moveRow(NodeElement $row, string $direction, int $repeat = 1): void {
    $handle = $row->find('css', 'a.tabledrag-handle');
    $this->assertNotNull($handle);
    $handle->focus();
    $key = $direction === 'up' ? 38 : 40;
    for ($move = 0; $move < $repeat; $move++) {
      $handle->keyDown($key);
      $handle->keyUp($key);
    }
    $handle->blur();
  }

  /**
   * Asserts the live placement label for a news row.
   */
  private function assertPlacement(int $id, string $label): void {
    $row = $this->getRow($id);
    $labels = array_filter($row->findAll('css', '[data-link-carousel-placement]'), fn(NodeElement $element) => $element->isVisible());
    $this->assertCount(1, $labels);
    $this->assertSame($label, reset($labels)->getText());
  }

  /**
   * Returns news IDs and the separator in their visible table order.
   */
  private function getRowOrder(): array {
    $table = $this->assertSession()->elementExists('css', '#layout-builder-modal table[data-link-carousel-placements]');
    $order = [];
    foreach ($table->findAll('css', 'tbody > tr.draggable') as $row) {
      $order[] = $row->hasAttribute('data-link-carousel-display-end') ? '_display_end' : (int) $row->find('css', '.row-id')->getValue();
    }
    return $order;
  }

  /**
   * Returns persisted component settings without entity static caches.
   */
  private function getSavedSettings(int|string $nid, string $uuid): array {
    $node = $this->container->get('entity_type.manager')->getStorage('node')->loadUnchanged($nid);
    return $node->get(OverridesSectionStorage::FIELD_NAME)->getSections()[0]->getComponent($uuid)->toArray()['configuration']['hpc'];
  }

  /**
   * Returns pending component settings without populating repository caches.
   */
  private function getTemporarySettings(string $key, string $uuid): ?array {
    $entry = $this->container->get('tempstore.shared')->get('layout_builder.section_storage.overrides')->get($key);
    return $entry ? $entry['section_storage']->getSection(0)->getComponent($uuid)->toArray()['configuration']['hpc'] : NULL;
  }

}
