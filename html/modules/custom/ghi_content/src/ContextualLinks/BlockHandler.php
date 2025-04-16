<?php

namespace Drupal\ghi_content\ContextualLinks;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\ghi_content\Entity\ContentBase;
use Drupal\ghi_content\Plugin\Block\Paragraph;
use Drupal\layout_builder\LayoutEntityHelperTrait;
use Drupal\layout_builder\LayoutTempstoreRepositoryInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Sync element service class.
 */
class BlockHandler implements ContainerInjectionInterface {

  use LayoutEntityHelperTrait;

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Layout tempstore repository.
   *
   * @var \Drupal\layout_builder\LayoutTempstoreRepositoryInterface
   */
  protected $layoutTempstoreRepository;

  /**
   * Public constructor.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, LayoutTempstoreRepositoryInterface $layout_tempstore_repository) {
    $this->entityTypeManager = $entity_type_manager;
    $this->layoutTempstoreRepository = $layout_tempstore_repository;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('layout_builder.tempstore_repository'),
    );
  }

  /**
   * Alter contextual links.
   *
   * We look for the block remove link. For paragraphs synced from a source, we
   * want to remove that link, because synced blocks can't be deleted, only
   * hidden.
   *
   * @param array $links
   *   An associative array containing contextual links for the given $group,
   *   as described above. The array keys are used to build CSS class names for
   *   contextual links and must therefore be unique for each set of contextual
   *   links.
   * @param string $group
   *   The group of contextual links being rendered.
   * @param array $route_parameters
   *   The route parameters passed to each route_name of the contextual links.
   *
   * @see ghi_content_contextual_links_alter()
   * @see hook_contextual_links_alter()
   */
  public function alterLinks(array &$links, $group, array $route_parameters) {
    if (empty($route_parameters['section_storage_type']) || $route_parameters['section_storage_type'] != 'overrides') {
      return;
    }
    $remove_link = 'layout_builder_block_remove';
    if (empty($links[$remove_link])) {
      // Remove link is not present, se we can skip the rest.
      return;
    }

    [$entity_type_id, $id] = explode('.', $route_parameters['section_storage']);
    $uuid = $route_parameters['uuid'];
    $delta = $route_parameters['delta'];

    $entity = $this->entityTypeManager->getStorage($entity_type_id)->load($id);
    if (!$entity instanceof ContentBase) {
      // We only want to prevent removing a paragraph on article or document
      // pages.
      return;
    }
    $section_storage = $this->getSectionStorageForEntity($entity, 'default');
    $section_storage = $this->layoutTempstoreRepository->get($section_storage);

    $component_keys = array_keys($section_storage->getSection($delta)->getComponents());
    if (!in_array($uuid, $component_keys)) {
      // Unknown uuid, keep going.
      return;
    }
    $component = $section_storage->getSection($delta)->getComponent($uuid);
    $plugin = $component->getPlugin();
    if (!$plugin || !$plugin instanceof Paragraph) {
      // Plugin not found or not the right one.
      return;
    }
    /** @var \Drupal\ghi_content\Plugin\Block\Paragraph $plugin */
    $configuration = $component->get('configuration');
    if ($plugin->lockArticle() && !empty($configuration['sync']) && !empty($configuration['sync']['source_uuid'])) {
      // Article locked and synced from a source. Remove the "remove" link.
      unset($links[$remove_link]);
    }

  }

}
