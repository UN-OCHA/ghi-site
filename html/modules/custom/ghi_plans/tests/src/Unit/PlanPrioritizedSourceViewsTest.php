<?php

namespace Drupal\Tests\ghi_plans\Unit;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\ghi_plans\Entity\Plan;
use Drupal\ghi_plans\Plugin\views\field\PlanPrioritizedSource as PlanPrioritizedSourceField;
use Drupal\Tests\UnitTestCase;
use Drupal\views\ResultRow;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the effective prioritized source Views field.
 */
#[Group('ghi_plans')]
class PlanPrioritizedSourceViewsTest extends UnitTestCase {

  /**
   * Tests that the field shows the effective source and inheritance state.
   */
  public function testFieldRender(): void {
    $container = new ContainerBuilder();
    $container->set('string_translation', $this->getStringTranslationStub());
    \Drupal::setContainer($container);

    $handler = new PlanPrioritizedSourceField([], 'plan_prioritized_source', []);
    $handler->setStringTranslation($this->getStringTranslationStub());
    $handler->options['relationship'] = 'none';

    $inherited = $this->createMock(Plan::class);
    $inherited->method('getEffectivePrioritizedDataSource')->willReturn(Plan::DATA_SOURCE_GHO);
    $inherited->method('getPrioritizedDataSourceOverride')->willReturn(NULL);
    $this->assertSame('GHO (inherited)', (string) $handler->render(new ResultRow(['_entity' => $inherited])));

    $explicit = $this->createMock(Plan::class);
    $explicit->method('getEffectivePrioritizedDataSource')->willReturn(Plan::DATA_SOURCE_PLAN);
    $explicit->method('getPrioritizedDataSourceOverride')->willReturn(Plan::DATA_SOURCE_PLAN);
    $this->assertSame('Country Module', (string) $handler->render(new ResultRow(['_entity' => $explicit])));
  }

}
