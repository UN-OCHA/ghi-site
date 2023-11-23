<?php

namespace Drupal\ghi_sections;

/**
 * Methods for running the section creation in a batch.
 *
 * @see \Drupal\ghi_sections
 */
class SectionCreateBatch {

  /**
   * Processes the section create batch.
   *
   * @param \Drupal\ghi_sections\SectionManager $section_manager
   *   The section manager to use.
   * @param array $bundle
   *   The bundles to process.
   * @param int $team
   *   The id of the team term object.
   * @param array $context
   *   The batch context.
   */
  public static function process(SectionManager $section_manager, array $bundle, $team, array &$context) {
    if (!isset($context['sandbox']['section_manager'])) {
      $context['sandbox']['section_manager'] = $section_manager;

      // The basic query to retrieve base object ids.
      $query = \Drupal::entityQuery('base_object')
        ->condition('type', $bundle, 'IN')
        ->accessCheck(FALSE);

      $result = $query->execute();

      $context['sandbox']['ids'] = array_values($result);

      $context['sandbox']['total'] = count($context['sandbox']['ids']);
      $context['results']['processed'] = 0;
      $context['results']['skipped'] = 0;
      $context['results']['total'] = $context['sandbox']['total'];
      $context['results']['errors'] = [];
    }

    /** @var \Drupal\ghi_sections\SectionManager $section_manager */
    $section_manager = $context['sandbox']['section_manager'];
    $base_object = \Drupal::entityTypeManager()->getStorage('base_object')->load(array_shift($context['sandbox']['ids']));

    // Check for existing section.
    $section = $section_manager->loadSectionForBaseObject($base_object);

    $messenger = \Drupal::messenger();

    if (!$section) {
      $values = [
        'team' => $team,
      ];
      try {
        $section = $section_manager->createSectionForBaseObject($base_object, $values);
        if ($section) {
          $context['results']['processed']++;
        }
        else {
          $context['results']['skipped']++;
        }
      }
      catch (\Exception $e) {
        $context['results']['errors'][] = t('@bundle @original_id: @message', [
          '@bundle' => $base_object->type->entity->label(),
          '@original_id' => $base_object->field_original_id->value,
          '@message' => $e->getMessage(),
        ]);
        $context['results']['skipped']++;
      }
    }

    $messenger->deleteAll();

    // Set progress.
    $context['finished'] = ($context['sandbox']['total'] - count($context['sandbox']['ids'])) / $context['sandbox']['total'];
  }

  /**
   * Finish batch.
   *
   * @param bool $success
   *   Indicate that the batch API tasks were all completed successfully.
   * @param array $results
   *   An array of all the results that were updated in update_do_one().
   * @param array $operations
   *   A list of the operations that had not been completed by the batch API.
   */
  public static function finish($success, array $results, array $operations) {
    $messenger = \Drupal::messenger();
    if ($success) {
      if (!empty($results['errors'])) {
        foreach ($results['errors'] as $error) {
          $messenger->addError($error);
        }
        $messenger->addWarning(t('The section creation had errors.'));
      }
      $messenger->addStatus(t('Successfully processed @processed objects, skipped @skipped of a total of @total objects.', [
        '@processed' => $results['processed'],
        '@skipped' => $results['skipped'],
        '@total' => $results['total'],
      ]));
    }
    else {
      // An error occurred.
      $message = t('An error occurred. Please check the logs.');
      $messenger->addError($message);
    }
  }

}
