<?php

/**
 * @file
 * Contains deploy functions for the GHI Blocks module.
 */

use Drupal\ghi_blocks\Helpers\FundingDataConfigurationUpdateHelper;
use Drupal\ghi_blocks\Helpers\LinkConfigurationUpdateHelper;
use Drupal\layout_builder\Plugin\SectionStorage\OverridesSectionStorage;
use Drupal\layout_builder\SectionComponent;

/**
 * Remove sections without components if multiple sections exists for a node.
 */
function ghi_blocks_deploy_9001_remove_empty_layout_builder_sections(&$sandbox) {
  $count_nodes = 0;
  $count_sections = 0;
  $result = \Drupal::database()->select('node__layout_builder__layout')
    ->fields('node__layout_builder__layout', ['entity_id'])
    ->condition('delta', 0, '>')
    ->orderBy('entity_id')
    ->execute();

  foreach ($result as $row) {
    /** @var \Drupal\node\NodeInterface $node */
    $node = \Drupal::entityTypeManager()->getStorage('node')->load($row->entity_id);
    if (!$node || !$node->hasField(OverridesSectionStorage::FIELD_NAME)) {
      continue;
    }
    $sections = $node->get(OverridesSectionStorage::FIELD_NAME)->getValue();
    if (empty($sections) || count($sections) == 1) {
      continue;
    }
    $changed = FALSE;
    foreach (array_keys($sections) as $delta) {
      /** @var \Drupal\layout_builder\Section $section */
      $section = &$sections[$delta]['section'];
      $components = $section->getComponents();
      if (empty($components)) {
        unset($sections[$delta]);
        $count_sections++;
        $count_nodes++;
        $changed = TRUE;
      }
    }
    if ($changed) {
      $node->get(OverridesSectionStorage::FIELD_NAME)->setValue($sections);
      $node->setSyncing(TRUE);
      $node->save();
    }

  }
  return t('Removed @count_sections sections from @count_nodes nodes', [
    '@count_sections' => $count_sections,
    '@count_nodes' => $count_nodes,
  ]);
}

/**
 * Merge multiple layout builder sections into one.
 */
function ghi_blocks_deploy_9002_merge_layout_builder_sections(&$sandbox) {
  $count_nodes = 0;
  $result = \Drupal::database()->select('node__layout_builder__layout')
    ->fields('node__layout_builder__layout', ['entity_id'])
    ->condition('delta', 0, '>')
    ->orderBy('entity_id')
    ->execute();

  foreach ($result as $row) {
    /** @var \Drupal\node\NodeInterface $node */
    $node = \Drupal::entityTypeManager()->getStorage('node')->load($row->entity_id);
    if (!$node || !$node->hasField(OverridesSectionStorage::FIELD_NAME)) {
      continue;
    }
    $sections = $node->get(OverridesSectionStorage::FIELD_NAME)->getValue();
    if (empty($sections) || count($sections) == 1) {
      continue;
    }
    $merged_components = [];
    foreach (array_keys($sections) as $delta) {
      /** @var \Drupal\layout_builder\Section $section */
      $components = $sections[$delta]['section']->getComponents();
      uasort($components, function (SectionComponent $a, SectionComponent $b) {
        return $a->getWeight() <=> $b->getWeight();
      });
      /** @var \Drupal\layout_builder\SectionComponent[] $components */
      foreach ($components as $component) {
        $sections[$delta]['section']->removeComponent($component->getUuid());
        $component->setWeight(count($merged_components));
        $merged_components[$component->getUuid()] = $component;
      }
    }
    foreach (array_keys($sections) as $delta) {
      if ($delta == 0) {
        continue;
      }
      unset($sections[$delta]);
    }
    uasort($merged_components, function (SectionComponent $a, SectionComponent $b) {
      return $a->getWeight() <=> $b->getWeight();
    });
    foreach ($merged_components as $component) {
      $sections[0]['section']->appendComponent($component);
    }

    $node->get(OverridesSectionStorage::FIELD_NAME)->setValue($sections);
    $node->setSyncing(TRUE);
    $node->save();
    $count_nodes++;
  }
  return t('Updated @count_nodes nodes', [
    '@count_nodes' => $count_nodes,
  ]);
}

/**
 * Enqueue nodes for the replacement of the plan_entity_types plugin.
 */
function ghi_blocks_deploy_9003_queue_nodes_for_plan_entity_type_replacement(&$sandbox) {
  $plugin_id = 'plan_entity_types';
  /** @var \Drupal\node\NodeInterface[] $nodes */
  $nodes = \Drupal::entityTypeManager()->getStorage('node')->loadMultiple();
  foreach ($nodes as $node) {
    if (!$node->hasField(OverridesSectionStorage::FIELD_NAME)) {
      continue;
    }
    \Drupal::queue('ghi_blocks_replace_deprecated_blocks_queue')->createItem((object) [
      'entity_id' => $node->id(),
      'entity_type_id' => $node->getEntityTypeId(),
      'plugin_id' => $plugin_id,
    ]);
  }
  return (string) t('Enqueued @total nodes for replacing of deprecated plan element @plugin_id.', [
    '@total' => \Drupal::queue('ghi_blocks_replace_deprecated_blocks_queue')->numberOfItems(),
    '@plugin_id' => $plugin_id,
  ]);
}

/**
 * Remove obsolete field from layout sections to prevent warnings.
 */
function ghi_blocks_deploy_9004_remove_field_block(&$sandbox) {
  $count_nodes = 0;
  $result = \Drupal::database()->select('node__layout_builder__layout')
    ->fields('node__layout_builder__layout', ['entity_id'])
    ->condition('layout_builder__layout_section', '%field_chapter%', 'LIKE')
    ->orderBy('entity_id')
    ->execute();

  foreach ($result as $row) {
    /** @var \Drupal\node\NodeInterface $node */
    $node = \Drupal::entityTypeManager()->getStorage('node')->load($row->entity_id);
    if (!$node || !$node->hasField(OverridesSectionStorage::FIELD_NAME)) {
      continue;
    }
    $sections = $node->get(OverridesSectionStorage::FIELD_NAME)->getValue();
    foreach (array_keys($sections) as $delta) {
      /** @var \Drupal\layout_builder\Section $section */
      $components = $sections[$delta]['section']->getComponents();
      /** @var \Drupal\layout_builder\SectionComponent[] $components */
      foreach ($components as $component) {
        if ($component->getPluginId() != 'field_block:node:article:field_chapter') {
          continue;
        }
        $sections[$delta]['section']->removeComponent($component->getUuid());
      }
    }

    $node->get(OverridesSectionStorage::FIELD_NAME)->setValue($sections);
    $node->setNewRevision(FALSE);
    $node->setSyncing(TRUE);
    $node->save();
    $count_nodes++;
  }
  return t('Updated @count_nodes nodes', [
    '@count_nodes' => $count_nodes,
  ]);
}

/**
 * Remove obsolete field from layout sections to prevent warnings (revisions).
 */
function ghi_blocks_deploy_9005_remove_field_block_revisions(&$sandbox) {
  $count_revisions = 0;
  $result = \Drupal::database()->select('node_revision__layout_builder__layout')
    ->fields('node_revision__layout_builder__layout', ['revision_id'])
    ->condition('layout_builder__layout_section', '%field_chapter%', 'LIKE')
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
        if ($component->getPluginId() != 'field_block:node:article:field_chapter') {
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

/**
 * Update link configuration for various elements on nodes.
 */
function ghi_blocks_deploy_9006_update_link_configuration_nodes(&$sandbox) {
  set_time_limit(30);
  if (!isset($sandbox['nodes'])) {
    $result = \Drupal::database()->select('node__layout_builder__layout')
      ->fields('node__layout_builder__layout', ['entity_id'])
      ->condition('layout_builder__layout_section', "%link%", 'LIKE')
      ->orderBy('entity_id')
      ->execute();
    $sandbox['nodes'] = array_map(function ($row) {
      return $row->entity_id;
    }, $result->fetchAll());
    $sandbox['total'] = count($sandbox['nodes']);
    $sandbox['updated'] = 0;
  }
  for ($i = 0; $i < 25; $i++) {
    if (empty($sandbox['nodes'])) {
      continue;
    }
    $node_id = array_shift($sandbox['nodes']);
    /** @var \Drupal\node\NodeInterface $node */
    $node = \Drupal::entityTypeManager()->getStorage('node')->load($node_id);
    if (!$node) {
      continue;
    }

    $changed = FALSE;
    if (!$node->hasField(OverridesSectionStorage::FIELD_NAME)) {
      continue;
    }
    $sections = $node->get(OverridesSectionStorage::FIELD_NAME)->getValue();
    if (empty($sections)) {
      continue;
    }
    /** @var \Drupal\layout_builder\Section $section */
    $section = &$sections[0]['section'];
    $components = $section->getComponents();
    if (empty($components)) {
      continue;
    }
    foreach ($components as $component) {
      switch ($component->getPluginId()) {
        case 'plan_headline_figures':
          $changed = LinkConfigurationUpdateHelper::updatePlanHeadlineFiguresComponent($component) || $changed;
          break;

        case 'links':
          $changed = LinkConfigurationUpdateHelper::updateLinksComponent($component) || $changed;
          break;

        case 'plan_entity_logframe':
          $changed = LinkConfigurationUpdateHelper::updatePlanEntityTypesComponent($component) || $changed;
          break;
      }
    }

    if ($changed) {
      $node->get(OverridesSectionStorage::FIELD_NAME)->setValue($sections);
      $node->setNewRevision(FALSE);
      $node->setSyncing(TRUE);
      $node->save();
      $sandbox['updated']++;
    }
  }

  $sandbox['#finished'] = 1 / (count($sandbox['nodes']) + 1);
  if ($sandbox['#finished'] === 1) {
    return t('Updated link configurations in @count_changed / @count_total nodes', [
      '@count_changed' => $sandbox['updated'],
      '@count_total' => $sandbox['total'],
    ]);
  }
  else {
    return t('Processed @count_processed / @count_total nodes', [
      '@count_processed' => $sandbox['total'] - count($sandbox['nodes']),
      '@count_total' => $sandbox['total'],
    ]);
  }
}

/**
 * Update link configuration for various elements on page templates.
 */
function ghi_blocks_deploy_9007_update_link_configuration_page_templates(&$sandbox) {
  set_time_limit(30);
  if (!isset($sandbox['page_templates'])) {
    $result = \Drupal::database()->select('page_template__layout_builder__layout')
      ->fields('page_template__layout_builder__layout', ['entity_id'])
      ->condition('layout_builder__layout_section', "%link%", 'LIKE')
      ->orderBy('entity_id')
      ->execute();
    $sandbox['page_templates'] = array_map(function ($row) {
      return $row->entity_id;
    }, $result->fetchAll());
    $sandbox['total'] = count($sandbox['page_templates']);
    $sandbox['updated'] = 0;
  }
  for ($i = 0; $i < 25; $i++) {
    if (empty($sandbox['page_templates'])) {
      continue;
    }
    $page_template_id = array_shift($sandbox['page_templates']);
    /** @var \Drupal\ghi_templates\Entity\PageTemplateInterface $page_template */
    $page_template = \Drupal::entityTypeManager()->getStorage('page_template')->load($page_template_id);
    if (!$page_template) {
      continue;
    }

    $changed = FALSE;
    if (!$page_template->hasField(OverridesSectionStorage::FIELD_NAME)) {
      continue;
    }
    $sections = $page_template->get(OverridesSectionStorage::FIELD_NAME)->getValue();
    if (empty($sections)) {
      continue;
    }
    /** @var \Drupal\layout_builder\Section $section */
    $section = &$sections[0]['section'];
    $components = $section->getComponents();
    if (empty($components)) {
      continue;
    }
    foreach ($components as $component) {
      switch ($component->getPluginId()) {
        case 'plan_headline_figures':
          $changed = LinkConfigurationUpdateHelper::updatePlanHeadlineFiguresComponent($component) || $changed;
          break;

        case 'links':
          $changed = LinkConfigurationUpdateHelper::updateLinksComponent($component) || $changed;
          break;

        case 'plan_entity_logframe':
          $changed = LinkConfigurationUpdateHelper::updatePlanEntityTypesComponent($component) || $changed;
          break;
      }
    }

    if ($changed) {
      $page_template->get(OverridesSectionStorage::FIELD_NAME)->setValue($sections);
      $page_template->save();
      $sandbox['updated']++;
    }
  }

  $sandbox['#finished'] = 1 / (count($sandbox['page_templates']) + 1);
  if ($sandbox['#finished'] === 1) {
    return t('Updated link configurations in @count_changed / @count_total page templates', [
      '@count_changed' => $sandbox['updated'],
      '@count_total' => $sandbox['total'],
    ]);
  }
  else {
    return t('Processed @count_processed / @count_total page templates', [
      '@count_processed' => $sandbox['total'] - count($sandbox['page_templates']),
      '@count_total' => $sandbox['total'],
    ]);
  }
}

/**
 * Update configuration for plan overview map.
 */
function ghi_blocks_deploy_9008_update_plan_overview_map(&$sandbox) {
  $nodes = \Drupal::entityTypeManager()->getStorage('node')->loadByProperties([
    'type' => 'homepage',
  ]);
  /** @var \Drupal\ghi_homepage\Entity\Homepage[] $nodes */
  foreach ($nodes as $node) {
    $changed = FALSE;
    if (!$node->hasField(OverridesSectionStorage::FIELD_NAME)) {
      continue;
    }
    $sections = $node->get(OverridesSectionStorage::FIELD_NAME)->getValue();
    if (empty($sections)) {
      continue;
    }
    /** @var \Drupal\layout_builder\Section $section */
    $section = &$sections[0]['section'];
    $components = $section->getComponents();
    if (empty($components)) {
      continue;
    }
    foreach ($components as $component) {
      if ($component->getPluginId() !== 'global_plan_overview_map') {
        continue;
      }
      $configuration = $component->get('configuration');
      if (array_key_exists('style', $configuration['hpc'])) {
        continue;
      }
      $configuration['hpc'] = [
        'style' => $node->getYear() == date('Y') ? 'circle' : 'donut',
        'search_enabled' => TRUE,
        'disclaimer' => $configuration['hpc']['map']['disclaimer'],
        'plan_select' => $configuration['hpc']['plans']['plan_select'],
        'label' => $configuration['hpc']['label'],
        'label_display' => $configuration['hpc']['label_display'],
      ];
      $component->setConfiguration($configuration);
      $changed = TRUE;
    }
    if ($changed) {
      $node->get(OverridesSectionStorage::FIELD_NAME)->setValue($sections);
      $node->setSyncing(TRUE);
      $node->save();
    }
  }

  return t('Updated map configuration for all homepage nodes');
}

/**
 * Update link configuration for various elements on page templates.
 */
function ghi_blocks_deploy_9009_update_funding_coverage_default_label_nodes(&$sandbox) {
  set_time_limit(30);
  if (!isset($sandbox['nodes'])) {
    $result = \Drupal::database()->select('node__layout_builder__layout')
      ->fields('node__layout_builder__layout', ['entity_id'])
      ->condition('layout_builder__layout_section', '%"Coverage"%', 'LIKE')
      ->orderBy('entity_id')
      ->execute();
    $sandbox['nodes'] = array_map(function ($row) {
      return $row->entity_id;
    }, $result->fetchAll());
    $sandbox['total'] = count($sandbox['nodes']);
    $sandbox['updated'] = 0;
  }
  for ($i = 0; $i < 25; $i++) {
    if (empty($sandbox['nodes'])) {
      continue;
    }
    $node_id = array_shift($sandbox['nodes']);
    if (FundingDataConfigurationUpdateHelper::updateNode($node_id)) {
      $sandbox['updated']++;
    }
  }

  $sandbox['#finished'] = 1 / (count($sandbox['nodes']) + 1);
  if ($sandbox['#finished'] === 1) {
    return t('Updated funding data configurations in @count_changed / @count_total nodes', [
      '@count_changed' => $sandbox['updated'],
      '@count_total' => $sandbox['total'],
    ]);
  }
  else {
    return t('Processed @count_processed / @count_total nodes', [
      '@count_processed' => $sandbox['total'] - count($sandbox['nodes']),
      '@count_total' => $sandbox['total'],
    ]);
  }
}

/**
 * Update link configuration for various elements on page templates.
 */
function ghi_blocks_deploy_90010_update_funding_coverage_default_label_page_templates(&$sandbox) {
  set_time_limit(30);
  if (!isset($sandbox['page_templates'])) {
    $result = \Drupal::database()->select('page_template__layout_builder__layout')
      ->fields('page_template__layout_builder__layout', ['entity_id'])
      ->condition('layout_builder__layout_section', '%"Coverage"%', 'LIKE')
      ->orderBy('entity_id')
      ->execute();
    $sandbox['page_templates'] = array_map(function ($row) {
      return $row->entity_id;
    }, $result->fetchAll());
    $sandbox['total'] = count($sandbox['page_templates']);
    $sandbox['updated'] = 0;
  }
  for ($i = 0; $i < 25; $i++) {
    if (empty($sandbox['page_templates'])) {
      continue;
    }
    $page_template_id = array_shift($sandbox['page_templates']);
    if (FundingDataConfigurationUpdateHelper::updatePageTemplate($page_template_id)) {
      $sandbox['updated']++;
    }
  }

  $sandbox['#finished'] = 1 / (count($sandbox['page_templates']) + 1);
  if ($sandbox['#finished'] === 1) {
    return t('Updated funding data configurations in @count_changed / @count_total page templates', [
      '@count_changed' => $sandbox['updated'],
      '@count_total' => $sandbox['total'],
    ]);
  }
  else {
    return t('Processed @count_processed / @count_total page templates', [
      '@count_processed' => $sandbox['total'] - count($sandbox['page templates']),
      '@count_total' => $sandbox['total'],
    ]);
  }
}
