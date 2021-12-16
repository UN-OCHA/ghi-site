<?php

namespace Drupal\ghi_plans\Helpers;

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
   * @return array
   *   An array of processed attachments.
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
   * @return object
   *   A single processed attachment.
   *
   * @throws \Symfony\Component\Config\Definition\Exception\InvalidTypeException
   *   For unsupported attachment types, an Exception is thrown.
   */
  public static function processAttachment($attachment) {
    switch (strtolower($attachment->type)) {
      case 'caseload':
      case 'indicator':
        return self::processDataAttachment($attachment);

      case 'filewebcontent':
        return self::processFileAttachment($attachment);

      case 'textwebcontent':
        return self::processTextAttachment($attachment);

      default:
        throw new InvalidTypeException(sprintf('Unknown attachment type: %s', $attachment->type));
    }
  }

  /**
   * Process a text attachment.
   *
   * @param object $attachment
   *   The input attachment.
   *
   * @return object
   *   The processed text attachment.
   */
  private static function processTextAttachment($attachment) {
    return (object) [
      'id' => $attachment->id,
      'type' => strtolower($attachment->type),
      'title' => $attachment->attachmentVersion->value->name,
      'content' => html_entity_decode($attachment->attachmentVersion->value->content ?? ''),
    ];
  }

  /**
   * Process a file attachment.
   *
   * @param object $attachment
   *   The input attachment.
   *
   * @return object
   *   The processed file attachment.
   */
  private static function processFileAttachment($attachment) {
    return (object) [
      'id' => $attachment->id,
      'type' => strtolower($attachment->type),
      'url' => $attachment->attachmentVersion->value->file->url,
      'title' => $attachment->attachmentVersion->value->file->title ?? '',
      'file_name' => $attachment->attachmentVersion->value->name ?? '',
    ];
  }

  /**
   * Process a data attachment.
   *
   * @param object $attachment
   *   The input attachment.
   *
   * @return object
   *   The processed data attachment.
   */
  private static function processDataAttachment($attachment) {
    $metrics = $attachment->attachmentVersion->value->metrics;
    $unit = property_exists($metrics, 'unit') ? $metrics->unit : NULL;
    $measure_fields = $metrics->measureFields ?? NULL;
    $prototype = self::getPrototypeData($attachment);

    $processed = (object) [
      'id' => $attachment->id,
      'type' => strtolower($attachment->type),
      'source' => (object) [
        'entity_type' => $attachment->objectType ?? NULL,
        'entity_id' => $attachment->objectId ?? NULL,
        'plan_id' => $attachment->planId ?? NULL,
      ],
      'composed_reference' => $attachment->composedReference,
      'description' => $attachment->attachmentVersion->value->description,
      'values' => array_pad(array_merge(
        array_map(function ($item) {
          return $item->value;
        }, $metrics->values->totals),
        $measure_fields ? array_map(function ($item) {
          return $item->value ?? NULL;
        }, $measure_fields) : []
      ), count($prototype->fields), NULL),
      'prototype' => $prototype,
      'unit' => $unit ? (object) [
        'label' => $unit->label ?? NULL,
        'type' => $unit->type ?? NULL,
        'group' => property_exists($unit, 'isGender') && $unit->isGender == 1 ? 'people' : 'amount',
      ] : NULL,
    ];

    // Cleanup the values.
    $processed->values = array_map(function ($value) {
      return $value === "" ? NULL : $value;
    }, $processed->values);

    return $processed;
  }

  /**
   * Extract prototype information from an attachment.
   *
   * Not all endpoints include the prototype in the response, which is why we
   * provide a work around to infer the prototype from the attachment object
   * itself. This does not contain all the prototype information. Calling
   * classes should assure that they use the correct endpoint if full prototype
   * data is required.
   *
   * @param object $attachment
   *   The attachment object as returned from the API.
   *
   * @return object
   *   An object with at least the relevant parts of an attachment prototype.
   *
   * @throws \Exception
   *   If the prototype cannot be inferred.
   */
  private static function getPrototypeData($attachment) {
    if (property_exists($attachment, 'attachmentPrototype')) {
      $prototype = $attachment->attachmentPrototype;
    }
    else {
      $prototype = self::fetchPrototypeForAttachment($attachment);
    }

    if (!$prototype) {
      throw new \Exception(sprintf('Failed to extract prototype for attachment %s', $attachment->id));
    }

    return (object) [
      'id' => $prototype->id,
      'name' => $prototype->value->name->en,
      'ref_code' => $prototype->refCode,
      'type' => strtolower($prototype->type),
      'fields' => array_merge(
        array_map(function ($item) {
          return $item->name->en;
        }, $prototype->value->metrics),
        array_map(function ($item) {
          return $item->name->en;
        }, $prototype->value->measureFields)
      ),
    ];
  }

  /**
   * Fetch prototype data from the API.
   *
   * @param object $attachment
   *   The attachment object from the API.
   *
   * @return object
   *   An attachment prototype object.
   */
  private static function fetchPrototypeForAttachment($attachment) {
    /** @var \Drupal\ghi_plans\Query\AttachmentPrototypeQuery $query */
    $query_handler = \Drupal::service('ghi_plans.attachment_prototype_query');
    return $query_handler->getPrototypeByPlanAndId($attachment->planId, $attachment->attachmentPrototypeId);
  }

}
