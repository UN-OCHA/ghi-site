<?php

namespace Drupal\ghi_menu\Form;

use Drupal\Core\Entity\ContentEntityDeleteForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\menu_link_content\Entity\MenuLinkContent;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Deletes a menu link with its Sublinks.
 */
class MenuLinkDeleteWithSublinksForm extends ContentEntityDeleteForm {

  /**
   * The config factory service.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * Entity Type Manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    // Instantiates this form class.
    $instance = parent::create($container);
    $instance->entityTypeManager = $container->get('entity_type.manager');
    $instance->configFactory = $container->get('config.factory');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $entity = $this->getEntity();
    $enabled_menus = $this->configFactory->get('ghi_menu.settings')->get('enabled_menus') ?? [];

    if ($entity instanceof MenuLinkContent && in_array($entity->getMenuName(), $enabled_menus)) {
      $this->deleteSublinks($entity);
    }

    parent::submitForm($form, $form_state);
  }

  /**
   * Recursively deletes all sublinks of a given link.
   */
  protected function deleteSublinks(MenuLinkContent $link): void {
    $storage = $this->entityTypeManager->getStorage('menu_link_content');
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

  /**
   * {@inheritdoc}
   */
  public function getDescription(): string {
    $entity = $this->getEntity();
    $modal_text = parent::getDescription();
    $enabled_menus = $this->configFactory->get('ghi_menu.settings')->get('enabled_menus') ?? [];

    // Concatenate a new text only when automatically deleting sublinks.
    if ($entity instanceof MenuLinkContent && in_array($entity->getMenuName(), $enabled_menus)) {
      return $this->t('This will also delete all child items.') . ' ' . $modal_text;
    }

    return $modal_text;
  }

}
