<?php

/**
 * @file
 * Hooks for GHI Content.
 */

/**
 * @addtogroup hooks
 * @{
 */

/**
 * Alter the normalized entity data for an entity.
 *
 * @param array $data
 *   The data array representing the entity. This data is already normalized.
 *
 * @see BaseContentManager->normalizeContentNodeData()
 */
function hook_normalize_content_alter(&$data) {
  unset($data['field_my_field'][0]['property']);
}
