<?php

namespace Drupal\ghi_base_objects\Entity;

use Drupal\views\EntityViewsData;

/**
 * Provides Views data for Base object entities.
 */
class BaseObjectViewsData extends EntityViewsData {

  /**
   * {@inheritdoc}
   */
  public function getViewsData() {
    $data = parent::getViewsData();

    // Additional information for Views integration, such as table joins, can be
    // put here.
    $data['base_object']['base_object_bulk_form'] = [
      'title' => $this->t('Base object operations bulk form'),
      'help' => $this->t('Add a form element that lets you run operations on multiple base objects.'),
      'field' => [
        'id' => 'base_object_bulk_form',
      ],
    ];

    return $data;
  }

}
