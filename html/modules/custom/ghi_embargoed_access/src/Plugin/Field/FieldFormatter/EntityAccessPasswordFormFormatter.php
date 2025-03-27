<?php

declare(strict_types=1);

namespace Drupal\ghi_embargoed_access\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\entity_access_password\Plugin\Field\FieldFormatter\EntityAccessPasswordFormFormatter as FieldFormatterEntityAccessPasswordFormFormatter;
use Drupal\ghi_content\Traits\ContentPathTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation override of 'entity_access_password_form' formatter.
 */
class EntityAccessPasswordFormFormatter extends FieldFormatterEntityAccessPasswordFormFormatter {

  use ContentPathTrait;

  /**
   * The embargoed access manager service.
   *
   * @var \Drupal\ghi_embargoed_access\EmbargoedAccessManager
   */
  protected $embargoedAccessManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->embargoedAccessManager = $container->get('ghi_embargoed_access.manager');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode): array {
    $elements = parent::viewElements($items, $langcode);
    if (!empty($elements)) {
      return $elements;
    }
    $entity = $items->getEntity();
    if ($parent = $this->embargoedAccessManager->getProtectedParent($entity)) {
      $field_name = $this->fieldDefinition->getName();
      $parent_items = $parent->get($field_name);
      $elements = parent::viewElements($parent_items, $langcode);
    }
    return $elements;
  }

}
