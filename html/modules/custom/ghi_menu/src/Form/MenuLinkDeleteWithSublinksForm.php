<?php

namespace Drupal\ghi_menu\Form;

use Drupal\Core\Entity\ContentEntityDeleteForm;
use Drupal\menu_link_content\Entity\MenuLinkContent;
use Drupal\Core\Form\FormStateInterface;

/**
 * Deletes a menu link with its Sublinks.
 */
class MenuLinkDeleteWithSublinksForm extends ContentEntityDeleteForm {

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $entity = $this->getEntity();
    $config_name = MenuLinkDeleteWithSublinksSettingsForm::CONFIG_NAME;
    $enabled_menus = \Drupal::config($config_name)->get('enabled_menus') ?? [];

    if ($entity instanceof MenuLinkContent && in_array($entity->getMenuName(), $enabled_menus)) {
      $this->deleteSublinks($entity);
    }

    parent::submitForm($form, $form_state);
  }

  /**
   * Recursively deletes all sublinks of a given link.
   */
  protected function deleteSublinks(MenuLinkContent $link): void {
    $storage = \Drupal::entityTypeManager()->getStorage('menu_link_content');
    $sublinks = $storage->loadByProperties([
      'menu_name' => $link->getMenuName(),
      'parent' => $link->getPluginId(),
    ]);

    foreach ($sublinks as $sublink) {
      if ($sublink instanceof MenuLinkContent) {
        $this->deleteSublinks($sublink);
        $sublink->delete();
      }
    }
  }

}
