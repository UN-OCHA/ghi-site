<?php

/**
 * @file
 * Contains deploy functions for the GHI Blocks module.
 */

use Drupal\layout_builder\Plugin\SectionStorage\OverridesSectionStorage;

/**
 * Remove plan webcontent text blocks from revisions.
 */
function ghi_blocks_deploy_9014_remove_plan_webcontent_text_blocks(&$sandbox) {
  $count_revisions = 0;
  $result = \Drupal::database()->select('node_revision__layout_builder__layout')
    ->fields('node_revision__layout_builder__layout', ['revision_id'])
    ->condition('layout_builder__layout_section', '%plan_webcontent_text%', 'LIKE')
    ->orderBy('entity_id')
    ->execute();

  foreach ($result as $row) {
    /** @var \Drupal\node\NodeInterface $revision */
    $revision = \Drupal::entityTypeManager()->getStorage('node')->loadRevision($row->revision_id);
    if (!$revision || !$revision->hasField(OverridesSectionStorage::FIELD_NAME)) {
      continue;
    }
    $sections = $revision->get(OverridesSectionStorage::FIELD_NAME)->getValue();
    foreach (array_keys($sections) as $delta) {
      /** @var \Drupal\layout_builder\Section $section */
      $components = $sections[$delta]['section']->getComponents();
      /** @var \Drupal\layout_builder\SectionComponent[] $components */
      foreach ($components as $component) {
        if ($component->getPluginId() != 'plan_webcontent_text') {
          continue;
        }
        $sections[$delta]['section']->removeComponent($component->getUuid());
      }
    }

    $revision->get(OverridesSectionStorage::FIELD_NAME)->setValue($sections);
    $revision->setNewRevision(FALSE);
    $revision->setSyncing(TRUE);
    $revision->save();
    $count_revisions++;
  }
  return t('Updated @count_nodes nodes', [
    '@count_nodes' => $count_revisions,
  ]);
}
