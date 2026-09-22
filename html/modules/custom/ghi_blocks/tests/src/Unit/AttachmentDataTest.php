<?php

namespace Drupal\Tests\ghi_blocks\Unit;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\ghi_plans\ApiObjects\Attachments\Attachment;
use Drupal\ghi_plans\ApiObjects\Prototypes\AttachmentPrototype;
use Drupal\ghi_plans\Entity\Plan;
use Drupal\ghi_blocks\Plugin\ConfigurationContainerItem\AttachmentData;
use Drupal\ghi_plans\Plugin\FabricQuery\AttachmentPrototypeQuery;
use Drupal\ghi_plans\Plugin\FabricQuery\AttachmentQuery;
use Drupal\hpc_api\Query\EndpointQueryManager;
use Drupal\hpc_api\Query\FabricQueryManager;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\DependencyInjection\Container;

/**
 * Test the attachment data configuration item plugin.
 */
#[Group('AttachmentData')]
class AttachmentDataTest extends UnitTestCase {

  /**
   * Basic test for the validation.
   */
  public function testAttachmentDataValidation() {
    $attachment_data = $this->createAttachmentDataPlugin();
    $errors = $attachment_data->getConfigurationErrors();
    $this->assertIsArray($errors);
    $this->assertEquals([
      'No attachment configured',
    ], $errors);
  }

  /**
   * Tests that plan pages can use child entity attachments from the same plan.
   */
  public function testAttachmentDataValidationAllowsPlanPageChildAttachments() {
    $plan_id = 1158;
    $attachment_id = 123;

    $attachment_query = $this->prophesize(AttachmentQuery::class);
    $attachment = $this->createMock(Attachment::class);
    $attachment->method('getPlanId')->willReturn($plan_id);
    $plan = $this->createMock(Plan::class);
    $plan->method('getSourceId')->willReturn($plan_id);
    $attachment->expects($this->once())->method('belongsToBaseObject')->with($plan)->willReturn(TRUE);
    $attachment_query->getAttachment($attachment_id)->willReturn($attachment);

    $attachment_data = $this->createAttachmentDataPlugin($attachment_query->reveal());
    $attachment_data->set('attachment', ['attachment_id' => $attachment_id]);
    $attachment_data->setContextValue('plan_object', $plan);
    $attachment_data->setContextValue('base_object', $plan);

    $this->assertSame([], $attachment_data->getConfigurationErrors());
  }

  /**
   * Tests that fixing an attachment also converts legacy data point indexes.
   */
  public function testFixConfigurationErrorsConvertsLegacyDataPoints(): void {
    $original_prototype = $this->mockAttachmentPrototype(10, 'BP', ['target', 'cumulative_reach']);
    $new_prototype = $this->mockAttachmentPrototype(20, 'BP', ['cumulative_reach', 'target']);
    $original_attachment = $this->mockAttachment(1, 100, $original_prototype);
    $new_attachment = $this->mockAttachment(2, 200, $new_prototype);

    $attachment_query = $this->prophesize(AttachmentQuery::class);
    $attachment_query->getAttachment(1)->willReturn($original_attachment);
    $attachment_query->getAttachmentsForPlan(200, NULL, [
      'AttachmentType' => [
        'Caseload',
        'Indicator',
      ],
    ])->willReturn([2 => $new_attachment]);

    $prototype_query = $this->prophesize(AttachmentPrototypeQuery::class);
    $prototype_query->getPrototype(10)->willReturn($original_prototype);
    $prototype_query->getPrototype(20)->willReturn($new_prototype);

    $plan = $this->createMock(Plan::class);
    $plan->method('getSourceId')->willReturn(200);
    $plan->method('getPlanCaseloadId')->willReturn(2);

    $attachment_data = $this->createAttachmentDataPlugin($attachment_query->reveal(), $prototype_query->reveal());
    $attachment_data->setConfig([
      'attachment' => ['attachment_id' => 1],
      'data_point' => [
        'processing' => 'calculated',
        'data_points' => [
          ['index' => 1, 'monitoring_period' => 123],
          ['metric_type' => 'target', 'monitoring_period' => 123],
        ],
      ],
    ]);
    $attachment_data->setContextValue('plan_object', $plan);

    $attachment_data->fixConfigurationErrors();

    $config = $attachment_data->getConfig();
    $this->assertSame(2, $config['attachment']['attachment_id']);
    $this->assertSame('cumulative_reach', $config['data_point']['data_points'][0]['metric_type']);
    $this->assertArrayNotHasKey('index', $config['data_point']['data_points'][0]);
    $this->assertSame('target', $config['data_point']['data_points'][1]['metric_type']);
    $this->assertSame('latest', $config['data_point']['data_points'][0]['monitoring_period']);
    $this->assertSame('latest', $config['data_point']['data_points'][1]['monitoring_period']);
  }

  /**
   * Create an attachment data plugin for tests.
   *
   * @param \Drupal\ghi_plans\Plugin\FabricQuery\AttachmentQuery|null $attachment_query
   *   The attachment query to use.
   * @param \Drupal\ghi_plans\Plugin\FabricQuery\AttachmentPrototypeQuery|null $attachment_prototype_query
   *   The attachment prototype query to use.
   *
   * @return \Drupal\ghi_blocks\Plugin\ConfigurationContainerItem\AttachmentData
   *   The attachment data plugin.
   */
  private function createAttachmentDataPlugin(?AttachmentQuery $attachment_query = NULL, ?AttachmentPrototypeQuery $attachment_prototype_query = NULL): AttachmentData {
    $attachment_query ??= $this->prophesize(AttachmentQuery::class)->reveal();
    $attachment_prototype_query ??= $this->prophesize(AttachmentPrototypeQuery::class)->reveal();
    $entity_type_manager = $this->prophesize(EntityTypeManagerInterface::class);
    $endpoint_query_manager = $this->prophesize(EndpointQueryManager::class);
    $fabric_query_manager = $this->prophesize(FabricQueryManager::class);
    $fabric_query_manager->createInstance('attachment')->willReturn($attachment_query);
    $fabric_query_manager->createInstance('attachment_prototype')->willReturn($attachment_prototype_query);
    $fabric_query_manager->hasDefinition('attachment_prototype')->willReturn(TRUE);
    $string_translation = $this->getStringTranslationStub();
    $current_user = $this->prophesize(AccountProxyInterface::class);

    $container = new Container();
    $container->set('entity_type.manager', $entity_type_manager->reveal());
    $container->set('plugin.manager.endpoint_query_manager', $endpoint_query_manager->reveal());
    $container->set('plugin.manager.fabric_query_manager', $fabric_query_manager->reveal());
    $container->set('string_translation', $string_translation);
    $container->set('current_user', $current_user->reveal());
    \Drupal::setContainer($container);

    return AttachmentData::create($container, [], 'attachment_data', []);
  }

  /**
   * Mock an attachment prototype used by attachment matching.
   */
  private function mockAttachmentPrototype(int $id, string $ref_code, array $field_types): AttachmentPrototype {
    $prototype = $this->createMock(AttachmentPrototype::class);
    $prototype->method('id')->willReturn($id);
    $prototype->method('getRefCode')->willReturn($ref_code);
    $prototype->method('getFieldTypes')->willReturn($field_types);
    $prototype->method('resolveMetricType')->willReturnCallback(function ($data_point) use ($field_types): ?string {
      if (is_int($data_point) || (is_string($data_point) && ctype_digit($data_point))) {
        return $field_types[(int) $data_point] ?? NULL;
      }
      return is_string($data_point) && in_array($data_point, $field_types, TRUE) ? $data_point : NULL;
    });
    return $prototype;
  }

  /**
   * Mock a caseload attachment used by attachment matching.
   */
  private function mockAttachment(int $id, int $plan_id, AttachmentPrototype $prototype): Attachment {
    $attachment = $this->createMock(Attachment::class);
    $attachment->method('id')->willReturn($id);
    $attachment->method('getPlanId')->willReturn($plan_id);
    $attachment->method('getAttachmentType')->willReturn('caseload');
    $attachment->method('getSourceEntityType')->willReturn('plan');
    $attachment->method('getPrototype')->willReturn($prototype);
    return $attachment;
  }

}
