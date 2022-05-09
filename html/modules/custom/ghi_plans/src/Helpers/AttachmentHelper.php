<?php

namespace Drupal\ghi_plans\Helpers;

use Drupal\ghi_plans\ApiObjects\Attachments\DataAttachment;
use Drupal\ghi_plans\ApiObjects\Attachments\FileAttachment;
use Drupal\ghi_plans\ApiObjects\Attachments\TextAttachment;
use Symfony\Component\Config\Definition\Exception\InvalidTypeException;

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
      $processed[$attachment->id] = self::processAttachment($attachment);
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
   * @throws \Symfony\Component\Config\Definition\Exception\InvalidTypeException
   *   For unsupported attachment types, an Exception is thrown.
   */
  public static function processAttachment($attachment) {
    switch (strtolower($attachment->type)) {
      case 'caseload':
      case 'indicator':
        return new DataAttachment($attachment);

      case 'filewebcontent':
        return new FileAttachment($attachment);

      case 'textwebcontent':
        return new TextAttachment($attachment);

      default:
        throw new InvalidTypeException(sprintf('Unknown attachment type: %s', $attachment->type));
    }
  }

}
