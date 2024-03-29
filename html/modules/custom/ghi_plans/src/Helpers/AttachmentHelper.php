<?php

namespace Drupal\ghi_plans\Helpers;

use Drupal\ghi_plans\ApiObjects\Attachments\AttachmentInterface;
use Drupal\ghi_plans\ApiObjects\Attachments\CaseloadAttachment;
use Drupal\ghi_plans\ApiObjects\Attachments\ContactAttachment;
use Drupal\ghi_plans\ApiObjects\Attachments\FileAttachment;
use Drupal\ghi_plans\ApiObjects\Attachments\IndicatorAttachment;
use Drupal\ghi_plans\ApiObjects\Attachments\TextAttachment;
use Drupal\ghi_plans\Exceptions\InvalidAttachmentTypeException;

/**
 * Helper class for mapping attachment objects.
 */
class AttachmentHelper {

  /**
   * Process an array of attachments.
   *
   * @param array $attachments
   *   The input attachments to process.
   *
   * @return \Drupal\ghi_plans\ApiObjects\Attachments\AttachmentInterface[]
   *   An array of attachment objects, keyed by the attachment id.
   */
  public static function processAttachments(array $attachments) {
    $processed = [];
    foreach ($attachments as $attachment) {
      try {
        $processed[$attachment->id] = self::processAttachment($attachment);
      }
      catch (InvalidAttachmentTypeException $e) {
        // Ignore this for the moment.
      }
    }
    return $processed;
  }

  /**
   * Process a single attachment.
   *
   * @param object $attachment
   *   The input attachment to process.
   *
   * @return \Drupal\ghi_plans\ApiObjects\Attachments\AttachmentInterface
   *   A single attachment object.
   *
   * @throws \Drupal\ghi_plans\Exceptions\InvalidAttachmentTypeException
   *   For unsupported attachment types, an Exception is thrown.
   */
  public static function processAttachment(object $attachment) {
    switch (strtolower($attachment->type)) {
      case 'caseload':
        return new CaseloadAttachment($attachment);

      case 'indicator':
        return new IndicatorAttachment($attachment);

      case 'filewebcontent':
        return new FileAttachment($attachment);

      case 'textwebcontent':
        return new TextAttachment($attachment);

      case 'contact':
        return new ContactAttachment($attachment);

      default:
        throw new InvalidAttachmentTypeException(sprintf('Unknown attachment type: %s', $attachment->type));
    }
  }

  /**
   * Get the possible id types for data attachments.
   *
   * @return array
   *   The list of possible id types for formatting.
   */
  public static function idTypes() {
    return [
      'custom_id' => t('Custom ID'),
      'custom_id_prefixed_refcode' => t('Custom ID, prefixed with object type (CA, CO, SO, ...)'),
      'composed_reference' => t('Composed reference'),
    ];
  }

  /**
   * Get a custom attachment id based on the given id type.
   *
   * @param \Drupal\ghi_plans\ApiObjects\Attachments\AttachmentInterface $attachment
   *   The attachment object.
   * @param string $id_type
   *   The id type.
   *
   * @return string
   *   The custom id.
   */
  public static function getCustomAttachmentId(AttachmentInterface $attachment, $id_type) {
    switch ($id_type) {
      case 'custom_id':
        return $attachment->custom_id;

      case 'custom_id_prefixed_refcode':
        return $attachment->custom_id_prefixed_refcode;

      case 'composed_reference':
        return $attachment->composed_reference;
    }
  }

}
