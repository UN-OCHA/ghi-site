<?php

namespace Drupal\Tests\ghi_blocks\Unit;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\ghi_plans\ApiObjects\Attachments\Attachment;
use Drupal\ghi_plans\Entity\Plan;
use Drupal\ghi_blocks\Plugin\ConfigurationContainerItem\AttachmentData;
use Drupal\ghi_plans\Plugin\FabricQuery\AttachmentPrototypeQuery;
use Drupal\ghi_plans\Plugin\FabricQuery\AttachmentQuery;
use Drupal\hpc_api\Query\EndpointQueryManager;
use Drupal\hpc_api\Query\FabricQueryManager;
use Drupal\Tests\UnitTestCase;
use Symfony\Component\DependencyInjection\Container;

/**
 * Test the attachment data configuration item plugin.
 *
 * @group AttachmentData
 */
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
   * Create an attachment data plugin for tests.
   *
   * @param \Drupal\ghi_plans\Plugin\FabricQuery\AttachmentQuery|null $attachment_query
   *   The attachment query to use.
   *
   * @return \Drupal\ghi_blocks\Plugin\ConfigurationContainerItem\AttachmentData
   *   The attachment data plugin.
   */
  private function createAttachmentDataPlugin(?AttachmentQuery $attachment_query = NULL): AttachmentData {
    $attachment_query ??= $this->prophesize(AttachmentQuery::class)->reveal();
    $attachment_prototype_query = $this->prophesize(AttachmentPrototypeQuery::class);
    $entity_type_manager = $this->prophesize(EntityTypeManagerInterface::class);
    $endpoint_query_manager = $this->prophesize(EndpointQueryManager::class);
    $fabric_query_manager = $this->prophesize(FabricQueryManager::class);
    $fabric_query_manager->createInstance('attachment')->willReturn($attachment_query);
    $fabric_query_manager->createInstance('attachment_prototype')->willReturn($attachment_prototype_query->reveal());
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

}
