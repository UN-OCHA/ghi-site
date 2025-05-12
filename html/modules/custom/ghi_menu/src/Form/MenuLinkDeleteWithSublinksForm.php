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
    $child_ids = [];
    $entity = $this->getEntity();
    $enabled_menus = $this->configFactory->get('ghi_menu.settings')->get('enabled_menus') ?? [];

    // First, collect sublinks IDs.
    if ($entity instanceof MenuLinkContent && in_array($entity->getMenuName(), $enabled_menus)) {
      $child_ids = $this->collectSublinksRecursively($entity->getPluginId(), $entity->getMenuName());
    }

    // Let Drupal handle parent deletion.
    parent::submitForm($form, $form_state);

    // Delete the sublinks if any.
    if (!empty($child_ids)) {
      $this->deleteSublinksByIds($child_ids);
    }
  }

  /**
   * Recursively collects all sublinks IDs of a given link.
   */
  protected function collectSublinksRecursively(string $plugin_id, string $menu_name): array {
    $storage = $this->entityTypeManager->getStorage('menu_link_content');
    $query = $storage->getQuery()
      ->condition('menu_name', $menu_name)
      ->condition('parent', $plugin_id)
      ->accessCheck(FALSE);
    $sublinks_ids = $query->execute();

    $all_sublinks_ids = [];
    if ($sublinks_ids) {
      $all_sublinks_ids = $sublinks_ids;
      foreach ($sublinks_ids as $id) {
        $child = $storage->load($id);
        if ($child) {
          $all_sublinks_ids = array_merge($all_sublinks_ids, $this->collectChildIdsRecursive($child->getPluginId(), $menu_name));
        }
      }
    }
    return $all_sublinks_ids;
  }

  /**
   * Deletes all sublinks of a given link.
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
    $modal_text = parent::getDescription();
    $enabled_menus = $this->configFactory->get('ghi_menu.settings')->get('enabled_menus') ?? [];

    // Concatenate a new text only when automatically deleting sublinks.
    if ($entity instanceof MenuLinkContent && in_array($entity->getMenuName(), $enabled_menus)) {
      return $this->t('This will also delete all child items.') . ' ' . $modal_text;
    }

    return $modal_text;
  }

}
