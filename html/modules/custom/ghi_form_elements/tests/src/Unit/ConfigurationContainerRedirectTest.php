<?php

namespace Drupal\Tests\ghi_form_elements\Unit;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Form\FormState;
use Drupal\ghi_blocks\Plugin\ConfigurationContainerItem\CompositeMap;
use Drupal\ghi_form_elements\ConfigurationContainerItemManager;
use Drupal\ghi_form_elements\Element\ConfigurationContainer;
use Drupal\Tests\UnitTestCase;

/**
 * Tests follow-up actions after adding configuration items.
 *
 * @group ghi_form_elements
 */
class ConfigurationContainerRedirectTest extends UnitTestCase {

  /**
   * Tests redirects, normal editing, and saving/cancelling the follow-up step.
   *
   * @dataProvider redirectProvider
   */
  public function testSubmitRedirect(string $mode, ?string $redirect, bool $valid, bool $expected_redirect, string $follow_up): void {
    $plugin = $this->createMock(CompositeMap::class);
    $plugin->method('getCustomActions')->willReturn(['dataset_form' => 'Datasets']);
    $plugin->method('isValidAction')->willReturn($valid);
    $manager = $this->createMock(ConfigurationContainerItemManager::class);
    $manager->method('createInstance')->willReturn($plugin);
    $container = new ContainerBuilder();
    $container->set('plugin.manager.configuration_container_item_manager', $manager);
    \Drupal::setContainer($container);

    $element = [
      '#parents' => ['maps'],
      '#allowed_item_types' => ['composite_map' => []],
      'item_config' => [
        'plugin_config' => ['#submit_redirect_custom_action' => $redirect],
      ],
    ];
    $existing_item = [
      'id' => 4,
      'item_type' => 'composite_map',
      'config' => ['label' => 'Existing map'],
      'weight' => 0,
      'pid' => NULL,
    ];
    $form_state = new FormState();
    ConfigurationContainer::set($element, $form_state, 'items', [$existing_item]);
    ConfigurationContainer::set($element, $form_state, 'mode', $mode);
    ConfigurationContainer::set($element, $form_state, 'edit_item', $mode == 'edit_item' ? 4 : NULL);
    $form_state->setValue(['maps', 'item_config'], [
      'item_type' => 'composite_map',
      'plugin_config' => ['label' => 'New label'],
    ]);
    $trigger = ['#parents' => ['maps', 'item_config', 'actions', 'submit_item']];
    $form_state->setTriggeringElement($trigger);

    ConfigurationContainer::elementSubmit($element, $form_state, []);

    $this->assertSame($expected_redirect ? 'custom_action' : 'list', ConfigurationContainer::get($element, $form_state, 'mode'));
    $items = ConfigurationContainer::get($element, $form_state, 'items');
    $this->assertCount($mode == 'add_item' ? 2 : 1, $items);
    $this->assertSame('New label', end($items)['config']['label']);
    if (!$expected_redirect) {
      return;
    }
    $this->assertSame(5, ConfigurationContainer::get($element, $form_state, 'edit_item'));
    $this->assertSame('dataset_form', ConfigurationContainer::get($element, $form_state, 'custom_action'));
    $this->assertSame($existing_item, $items[0]);

    $datasets = ['datasets' => ['full_pie' => ['attachment' => 52259, 'metric' => 'target']]];
    $form_state->setValue(['maps', 'custom_config', 'dataset_form'], $datasets);
    $trigger = ['#parents' => ['maps', 'custom_config', 'parent_actions', $follow_up]];
    $form_state->setTriggeringElement($trigger);
    ConfigurationContainer::elementSubmit($element, $form_state, []);

    $this->assertNull(ConfigurationContainer::get($element, $form_state, 'mode'));
    $items = ConfigurationContainer::get($element, $form_state, 'items');
    $this->assertCount(2, $items);
    $this->assertSame($existing_item, $items[0]);
    $this->assertSame('New label', $items[1]['config']['label']);
    $this->assertSame($follow_up == 'submit_item' ? $datasets : NULL, $items[1]['config']['dataset_form'] ?? NULL);
  }

  /**
   * Provides redirect and follow-up scenarios.
   */
  public static function redirectProvider(): array {
    return [
      'add and save datasets' => ['add_item', 'dataset_form', TRUE, TRUE, 'submit_item'],
      'add and cancel datasets' => ['add_item', 'dataset_form', TRUE, TRUE, 'cancel'],
      'edit stays in list' => ['edit_item', 'dataset_form', TRUE, FALSE, ''],
      'no redirect requested' => ['add_item', NULL, TRUE, FALSE, ''],
      'unavailable action' => ['add_item', 'dataset_form', FALSE, FALSE, ''],
      'unknown action' => ['add_item', 'unknown', TRUE, FALSE, ''],
    ];
  }

}
