<?php

/**
 * @file
 * Contains deploy functions for the GHI image module.
 */

/**
 * Fix some mime types after applying core patch from #3487488.
 */
function ghi_image_deploy_fix_mime_types(&$sandbox) {
  /** @var \Drupal\file\FileInterface[] $files */
  $files = \Drupal::entityTypeManager()->getStorage('file')->loadByProperties([
    'filemime' => 'application/octet-stream',
  ]);
  /** @var \Drupal\Core\File\MimeType\MimeTypeGuesser $mime_type_guesser */
  $mime_type_guesser = \Drupal::service('file.mime_type.guesser');
  foreach ($files as $file) {
    $mime_type = $mime_type_guesser->guessMimeType($file->getFileUri());
    if ($file->getMimeType() != $mime_type) {
      $file->setMimeType($mime_type);
      $file->save();
    }
  }
}
