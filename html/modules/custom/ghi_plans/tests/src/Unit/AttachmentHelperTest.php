<?php

namespace Drupal\Tests\ghi_plans\Unit;

use Drupal\ghi_plans\ApiObjects\Attachments\DataAttachment;
use Drupal\ghi_plans\Exceptions\InvalidAttachmentTypeException;
use Drupal\ghi_plans\Helpers\AttachmentHelper;

/**
 * Tests for the AttachmentHelper class.
 */
class AttachmentHelperTest extends ApiObjectTestBase {

  /**
   * Test the processAttachments method.
   */
  public function testProcessAttachments() {
    // Test with empty attachments.
    $processed_attachments = AttachmentHelper::processAttachments([]);
    $this->assertIsArray($processed_attachments);
    $this->assertCount(0, $processed_attachments);

    // Test with 2 valid attachments.
    $attachments = [
      (object) [
        'id' => 38529,
        'type' => 'caseLoad',
        'attachmentPrototype' => $this->getApiObjectFixture('AttachmentPrototype', 'caseload'),
      ],
      (object) [
        'id' => 38544,
        'type' => 'indicator',
        'attachmentPrototype' => $this->getApiObjectFixture('AttachmentPrototype', 'indicator'),
      ],
    ];
    $processed_attachments = AttachmentHelper::processAttachments($attachments);
    $this->assertIsArray($processed_attachments);
    $this->assertCount(2, $processed_attachments);

    // Test with an invalid attachment.
    $attachments[] = (object) [
      'id' => 38999,
      'type' => 'INVALID_ATTACHMENT_TYPE',
    ];
    $processed_attachments = AttachmentHelper::processAttachments($attachments);
    $this->assertIsArray($processed_attachments);
    $this->assertCount(2, $processed_attachments);
  }

  /**
   * Test exception for the processAttachment method.
   */
  public function testInvalidAttachmentType() {
    $this->expectException(InvalidAttachmentTypeException::class);
    AttachmentHelper::processAttachment((object) [
      'id' => 38529,
      'type' => 'INVALID_ATTACHMENT_TYPE',
    ]);
  }

  /**
   * Test the idTypes method.
   */
  public function testIdTypes() {
    $id_types = AttachmentHelper::idTypes();
    $this->assertIsArray($id_types);
    $expected_types = ['custom_id', 'custom_id_prefixed_refcode', 'composed_reference'];
    $this->assertEquals($expected_types, array_keys($id_types));
  }

  /**
   * Test the getCustomAttachmentId method.
   */
  public function testGetCustomAttachmentId() {
    $attachment = $this->getMockBuilder(DataAttachment::class)->disableOriginalConstructor()->getMock();
    $attachment->method('__get')->with('custom_id')->willReturn('custom_id_VALUE');
    /** @var \Drupal\ghi_plans\ApiObjects\Attachments\AttachmentInterface $attachment */
    $this->assertEquals('custom_id_VALUE', AttachmentHelper::getCustomAttachmentId($attachment, 'custom_id'));

    $attachment = $this->getMockBuilder(DataAttachment::class)->disableOriginalConstructor()->getMock();
    $attachment->method('__get')->with('custom_id_prefixed_refcode')->willReturn('custom_id_prefixed_refcode_VALUE');
    /** @var \Drupal\ghi_plans\ApiObjects\Attachments\AttachmentInterface $attachment */
    $this->assertEquals('custom_id_prefixed_refcode_VALUE', AttachmentHelper::getCustomAttachmentId($attachment, 'custom_id_prefixed_refcode'));

    $attachment = $this->getMockBuilder(DataAttachment::class)->disableOriginalConstructor()->getMock();
    $attachment->method('__get')->with('composed_reference')->willReturn('composed_reference_VALUE');
    /** @var \Drupal\ghi_plans\ApiObjects\Attachments\AttachmentInterface $attachment */
    $this->assertEquals('composed_reference_VALUE', AttachmentHelper::getCustomAttachmentId($attachment, 'composed_reference'));

    $this->assertEquals(NULL, AttachmentHelper::getCustomAttachmentId($attachment, 'INVALID_ID_TYPE'));
  }

}
