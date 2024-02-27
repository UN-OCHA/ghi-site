<?php

namespace Drupal\ghi_templates\Entity;

use Drupal\views\EntityViewsData;

/**
 * Provides Views data for page template entities.
 */
class PageTemplateViewsData extends EntityViewsData {

  /**
   * {@inheritdoc}
   */
  public function getViewsData() {
    $data = parent::getViewsData();

    // Additional information for Views integration, such as table joins, can be
    // put here.
    $data['page_template']['bulk_form'] = [
      'title' => $this->t('Page template operations bulk form'),
      'help' => $this->t('Add a form element that lets you run operations on multiple page templates.'),
      'field' => [
        'id' => 'bulk_form',
      ],
    ];

    return $data;
  }

}
