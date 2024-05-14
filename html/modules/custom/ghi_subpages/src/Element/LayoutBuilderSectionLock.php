<?php

namespace Drupal\ghi_subpages\Element;

use Drupal\Core\Render\Element;
use Drupal\Core\Security\TrustedCallbackInterface;
use Drupal\ghi_subpages\Helpers\SubpageHelper;

/**
 * Restrict the configuration, adding and removing of sections.
 */
class LayoutBuilderSectionLock implements TrustedCallbackInterface {

  /**
   * Pre-render callback: Removes access to section admin links.
   */
  public static function preRenderRestrictSectionConfiguration($element) {
    if (\Drupal::currentUser()->hasPermission('configure any layout')) {
      // Don't interfere with the default behavior.
      return $element;
    }
    /** @var \Drupal\Core\Entity\ContentEntityInterface $entity */
    $entity = $element['#section_storage']->getContextValue('entity');
    if (!$entity || $entity->getEntityTypeId() != 'node') {
      return $element;
    }
    $subpage_manager = SubpageHelper::getSubpageManager();
    if (!$subpage_manager->isBaseTypeNode($entity) && !$subpage_manager->isSubpageTypeNode($entity)) {
      return $element;
    }

    foreach (Element::children($element['layout_builder']) as $key) {
      $container = &$element['layout_builder'][$key];
      if (array_key_exists('section_label', $container)) {
        // This is the container for the section configure and remove links.
        $container['remove']['#access'] = FALSE;
        $container['configure']['#access'] = FALSE;
      }
      if (array_key_exists('link', $container)) {
        $container['link']['#access'] = FALSE;
      }
    }
    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public static function trustedCallbacks() {
    return [
      'preRenderRestrictSectionConfiguration',
    ];
  }

}
