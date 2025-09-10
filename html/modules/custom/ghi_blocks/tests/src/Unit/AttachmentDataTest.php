<?php

namespace Drupal\Tests\ghi_blocks\Unit;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\ghi_blocks\Plugin\ConfigurationContainerItem\AttachmentData;
use Drupal\ghi_plans\Plugin\EndpointQuery\AttachmentQuery;
use Drupal\hpc_api\Query\EndpointQueryManager;
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
    $attachment_query = $this->prophesize(AttachmentQuery::class);
    $entity_type_manager = $this->prophesize(EntityTypeManagerInterface::class);
    $endpoint_query_manager = $this->prophesize(EndpointQueryManager::class);
    $string_translation = $this->getStringTranslationStub();

    $container = new Container();
    $container->set('attachment_query', $attachment_query->reveal());
    $container->set('entity_type.manager', $entity_type_manager->reveal());
    $container->set('plugin.manager.endpoint_query_manager', $endpoint_query_manager->reveal());
    $container->set('string_translation', $string_translation);
    \Drupal::setContainer($container);

    $attachment_data = AttachmentData::create($container, [], 'attachment_data', []);
    $errors = $attachment_data->getConfigurationErrors();
    $this->assertIsArray($errors);
    $this->assertEquals([
      'No attachment configured',
    ], $errors);
  }

}
