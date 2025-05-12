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
    $sublink_ids = [];
    $entity = $this->getEntity();
    $enabled_menus = $this->configFactory->get('ghi_menu.settings')->get('enabled_menus') ?? [];

    // First, collect sublinks IDs.
    if ($entity instanceof MenuLinkContent && in_array($entity->getMenuName(), $enabled_menus)) {
      $sublink_ids = $this->getSublinkIds($entity->getPluginId(), $entity->getMenuName());
    }

    // Let Drupal handle parent deletion.
    parent::submitForm($form, $form_state);

    // Delete the sublinks if any.
    if (!empty($sublink_ids)) {
      $this->deleteSublinksByIds($sublink_ids);
    }
  }

  /**
   * Recursively collects all sublinks IDs of a given link.
   *
   * @param string $plugin_id
   *   The plugin id of the menu link entity.
   * @param string $menu_name
   *   The menu name for which to get the sublink ids.
   *
   * @return int[]
   *   An array of menu link entity ids.
   */
  protected function getSublinkIds(string $plugin_id, string $menu_name): array {
    /** @var \Drupal\menu_link_content\Entity\MenuLinkContent $sublinks */
    $sublinks = $this->entityTypeManager->getStorage('menu_link_content')->loadByProperties([
      'menu_name' => $menu_name,
      'parent' => $plugin_id,
    ]);

    $sublinks_ids = [];
    if (!empty($sublinks)) {
      $sublinks_ids = array_keys($sublinks);
      foreach ($sublinks as $sublink) {
        $sublinks_ids = array_merge($sublinks_ids, $this->getSublinkIds($sublink->getPluginId(), $menu_name));
      }
    }
    return $sublinks_ids;
  }

  /**
   * Deletes menu link entities by id.
   *
   * @param int[] $ids
   *   An array of entity ids for the menu links to be deleted.
   */
  protected function deleteSublinksByIds(array $ids) {
    $storage = $this->entityTypeManager->getStorage('menu_link_content');
    $entities = $storage->loadMultiple($ids);

    if (!empty($entities)) {
      $storage->delete($entities);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription(): string {
    $entity = $this->getEntity();
    $description = parent::getDescription();
    $enabled_menus = $this->configFactory->get('ghi_menu.settings')->get('enabled_menus') ?? [];

    if (!$entity instanceof MenuLinkContent) {
      return $description;
    }

    if (!in_array($entity->getMenuName(), $enabled_menus)) {
      return $description;
    }

    if (empty($this->getSublinkIds($entity->getPluginId(), $entity->getMenuName()))) {
      return $description;
    }

    // If there are sublinks will be deleted when deleting the current menu
    // item, tell the user.
    return $this->t('This will also delete all child items.') . ' ' . $description;
  }

}
