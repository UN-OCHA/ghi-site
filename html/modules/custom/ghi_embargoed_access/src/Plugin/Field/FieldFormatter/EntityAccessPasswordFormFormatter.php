<?php

declare(strict_types=1);

namespace Drupal\ghi_embargoed_access\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\entity_access_password\Plugin\Field\FieldFormatter\EntityAccessPasswordFormFormatter as FieldFormatterEntityAccessPasswordFormFormatter;
use Drupal\ghi_content\Entity\ContentBase;
use Drupal\ghi_content\Traits\ContentPathTrait;
use Drupal\ghi_sections\Entity\SectionNodeInterface;
use Drupal\ghi_subpages\Entity\SubpageNodeInterface;

/**
 * Plugin implementation override of 'entity_access_password_form' formatter.
 */
class EntityAccessPasswordFormFormatter extends FieldFormatterEntityAccessPasswordFormFormatter {

  use ContentPathTrait;

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode): array {
    $elements = parent::viewElements($items, $langcode);
    if (!empty($elements)) {
      return $elements;
    }
    $entity = $items->getEntity();
    $parent = NULL;
    if ($entity instanceof SubpageNodeInterface) {
      $parent = $entity->getParentBaseNode();
    }
    elseif ($entity instanceof ContentBase) {
      $parent = $this->getCurrentSectionNode();
    }

    if (!$parent instanceof SectionNodeInterface) {
      return $elements;
    }
    $field_name = $this->fieldDefinition->getName();
    $parent_items = $parent->get($field_name);
    $elements = parent::viewElements($parent_items, $langcode);

    return $elements;
  }

}
