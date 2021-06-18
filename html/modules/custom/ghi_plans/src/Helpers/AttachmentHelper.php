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
    $unit = property_exists($attachment->attachmentVersion->value->metrics, 'unit') ? $attachment->attachmentVersion->value->metrics->unit : NULL;
    $measure_fields = $attachment->attachmentVersion->value->metrics->measureFields ?? NULL;
    $processed = (object) [
      'id' => $attachment->id,
      'type' => strtolower($attachment->type),
      'composed_reference' => $attachment->composedReference,
      'description' => $attachment->attachmentVersion->value->description,
      'values' => array_merge(
        array_map(function ($item) {
          return $item->value;
        }, $attachment->attachmentVersion->value->metrics->values->totals),
        $measure_fields ? array_map(function ($item) {
          return $item->value ?? NULL;
        }, $measure_fields) : []
      ),
      'prototype' => (object) [
        'id' => $attachment->attachmentPrototype->id,
        'name' => $attachment->attachmentPrototype->value->name->en,
        'ref_code' => $attachment->attachmentPrototype->refCode,
        'type' => strtolower($attachment->attachmentPrototype->type),
        'fields' => array_merge(array_map(function ($item) {
          return $item->name->en;
        }, $attachment->attachmentPrototype->value->metrics), array_map(function ($item) {
          return $item->name->en;
        }, $attachment->attachmentPrototype->value->measureFields)),
      ],
      'unit' => $unit ? (object) [
        'label' => $unit->label ?? NULL,
        'type' => $unit->type ?? NULL,
        'group' => property_exists($unit, 'isGender') && $unit->isGender == 1 ? 'people' : 'amount',
      ] : NULL,
    ];
    return $processed;
  }

}
