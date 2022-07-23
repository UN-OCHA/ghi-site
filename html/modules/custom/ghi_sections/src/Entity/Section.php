<?php

namespace Drupal\ghi_sections\Entity;

use Drupal\Component\Render\FormattableMarkup;
use Drupal\ghi_base_objects\Helpers\BaseObjectHelper;
use Drupal\ghi_base_objects\Traits\ShortNameTrait;
use Drupal\node\Entity\Node;

/**
 * Bundle class for section nodes.
 */
class Section extends Node implements SectionNodeInterface {

  use ShortNameTrait;

  /**
   * {@inheritdoc}
   */
  public function label() {
    $label = parent::label();
    $base_object = $this->get('field_base_object')->entity;
    return $this->getShortName($base_object, TRUE) ?? $label;
  }

  /**
   * {@inheritdoc}
   */
  public function getPageTitle() {
    $base_object = BaseObjectHelper::getBaseObjectFromNode($this);
    if (!$base_object->needsYear()) {
      return new FormattableMarkup('@label <sup>@year</sup>', [
        '@label' => $this->label(),
        '@year' => $base_object->get('field_year')->value,
      ]);
    }
    return $this->label();
  }

  /**
   * {@inheritdoc}
   */
  public function getImage() {
    return $this->get('field_hero_image');
  }

}
