<?php

namespace Drupal\Tests\ghi_blocks\Unit;

use Drupal\ghi_blocks\Services\PlanHeadlineFiguresConfigMigrator;
use Drupal\ghi_plans\ApiObjects\Attachments\CaseloadAttachment;
use Drupal\ghi_plans\ApiObjects\PlanEntityInterface;
use Drupal\ghi_plans\Entity\Plan;
use Drupal\ghi_plans\Plugin\FabricQuery\AttachmentQuery;
use Drupal\hpc_api\Query\FabricQueryManager;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests migration of legacy plan headline figure items.
 */
#[Group('ghi_blocks')]
final class PlanHeadlineFiguresConfigMigratorTest extends UnitTestCase {

  /**
   * Tests attachment migration and the effective-caseload safeguard.
   */
  public function testAttachmentDataMigration(): void {
    $attachment = $this->createMock(CaseloadAttachment::class);
    $attachment->method('id')->willReturn(123);

    $attachment_query = $this->createMock(AttachmentQuery::class);
    $attachment_query->expects($this->once())
      ->method('getAttachmentsByObject')
      ->with(PlanEntityInterface::ENTITY_TYPE_PLAN, 10, 'caseload')
      ->willReturn([123 => $attachment]);

    $plan = $this->createMock(Plan::class);
    $plan->method('getSourceId')->willReturn(10);
    $plan->method('getPlanCaseload')->willReturn($attachment);

    $configuration = $this->buildConfiguration([
      $this->attachmentItem(123),
      $this->attachmentItem(456),
      $this->attachmentItem(123, '42'),
    ]);
    $this->assertTrue($this->createMigrator($attachment_query)->migrateConfiguration($configuration, $plan));

    $items = $configuration['hpc']['key_figures']['items'];
    $this->assertSame('plan_metric_data', $items[0]['item_type']);
    $this->assertSame([
      'metric' => 'latest_reach',
      'processing' => 'calculated',
      'formatting' => 'percent',
      'label' => 'People reached',
      'metric_2' => 'target',
      'calculation' => 'percentage',
    ], $items[0]['config']);
    $this->assertSame('attachment_data', $items[1]['item_type']);
    $this->assertSame('attachment_data', $items[2]['item_type']);
  }

  /**
   * Tests financial mappings and safeguards for incompatible options.
   */
  public function testFundingDataMigration(): void {
    $attachment_query = $this->createMock(AttachmentQuery::class);
    $plan = $this->createMock(Plan::class);
    $configuration = $this->buildConfiguration([
      $this->fundingItem('funding_totals'),
      $this->fundingItem('funding_gap'),
      $this->fundingItem('funding_coverage'),
      $this->fundingItem('original_requirements'),
      $this->fundingItem('outside_funding'),
      $this->fundingItem('current_requirements'),
      $this->fundingItem('current_requirements', ['type' => 'tag', 'tag' => 'COVID-19']),
      $this->fundingItem('current_requirements', scale: 'full'),
    ]);

    $this->assertTrue($this->createMigrator($attachment_query)->migrateConfiguration($configuration, $plan));
    $items = $configuration['hpc']['key_figures']['items'];
    $this->assertSame([
      'metric' => 'funding',
      'processing' => 'single',
      'formatting' => 'auto',
      'label' => 'Funding',
    ], $items[0]['config']);
    $this->assertSame([
      'metric' => 'funding_gap',
      'processing' => 'single',
      'formatting' => 'auto',
      'label' => 'Gap',
    ], $items[1]['config']);
    $this->assertSame('coverage', $items[2]['config']['metric']);
    $this->assertSame('original_requirements', $items[3]['config']['metric']);
    $this->assertSame('outside_funding', $items[4]['config']['metric']);
    $this->assertSame('requirements', $items[5]['config']['metric']);
    $this->assertSame('funding_data', $items[6]['item_type']);
    $this->assertSame('plan_metric_data', $items[7]['item_type']);
    $this->assertSame('auto', $items[7]['config']['formatting']);
  }

  /**
   * Creates a migrator using the controlled attachment query.
   */
  private function createMigrator(AttachmentQuery $attachment_query): PlanHeadlineFiguresConfigMigrator {
    $manager = $this->createMock(FabricQueryManager::class);
    $manager->expects($this->once())
      ->method('createInstance')
      ->with('attachment')
      ->willReturn($attachment_query);
    return new PlanHeadlineFiguresConfigMigrator($manager);
  }

  /**
   * Builds a headline figures configuration around the supplied items.
   */
  private function buildConfiguration(array $items): array {
    return [
      'hpc' => [
        'key_figures' => [
          'items' => $items,
        ],
      ],
    ];
  }

  /**
   * Builds a legacy attachment item.
   */
  private function attachmentItem(int $attachment_id, string $monitoring_period = 'latest'): array {
    return [
      'item_type' => 'attachment_data',
      'config' => [
        'attachment' => ['attachment_id' => $attachment_id],
        'label' => 'People reached',
        'data_point' => [
          'processing' => 'calculated',
          'calculation' => 'percentage',
          'data_points' => [
            ['metric_type' => 'latest_reach', 'monitoring_period' => $monitoring_period],
            ['metric_type' => 'target', 'monitoring_period' => 'latest'],
          ],
          'formatting' => 'percent',
          'widget' => 'none',
        ],
      ],
    ];
  }

  /**
   * Builds a legacy financial item.
   */
  private function fundingItem(string $data_type, array $cluster_restrict = ['type' => 'none'], string $scale = 'auto'): array {
    return [
      'item_type' => 'funding_data',
      'config' => [
        'data_type' => $data_type,
        'cluster_restrict' => $cluster_restrict,
        'scale' => $scale,
        'label' => $data_type === 'funding_gap' ? 'Gap' : 'Funding',
      ],
    ];
  }

}
