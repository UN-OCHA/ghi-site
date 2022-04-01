<?php

namespace Drupal\ghi_blocks\ContextualLinks;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\ghi_blocks\Plugin\Block\GHIBlockBase;
use Drupal\layout_builder\LayoutEntityHelperTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\layout_builder\LayoutTempstoreRepositoryInterface;

/**
 * Sync element service class.
 */
class BlockHandler implements ContainerInjectionInterface {

  use LayoutEntityHelperTrait;
  use StringTranslationTrait;

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

    unset($links['layout_builder_block_move']);

    [$entity_type_id, $id] = explode('.', $route_parameters['section_storage']);
    $uuid = $route_parameters['uuid'];
    $delta = $route_parameters['delta'];

    $entity = $this->entityTypeManager->getStorage($entity_type_id)->load($id);
    $section_storage = $this->getSectionStorageForEntity($entity);
    $section_storage = $this->layoutTempstoreRepository->get($section_storage);

    $section_storage = $this->getSectionStorageForEntity($entity);
    $section_storage = $this->layoutTempstoreRepository->get($section_storage);

    $component_keys = array_keys($section_storage->getSection($delta)->getComponents());
    if (!in_array($uuid, $component_keys)) {
      // Unknown uuid, keep going.
      return;
    }
    $component = $section_storage->getSection($delta)->getComponent($uuid);
    $plugin = $component->getPlugin();
    if (!$plugin || !$plugin instanceof GHIBlockBase) {
      // Plugin not found or not the right one.
      return;
    }

    $localized_options = [
      'attributes' => [
        'class' => ['use-ajax'],
        'data-dialog-type' => 'dialog',
        'data-dialog-renderer' => 'off_canvas',
      ],
    ];
    $metadata = [
      'operations' => 'update:remove:hide:unhide',
      'langcode' => 'en',
    ];

    $links['layout_builder_block_remove']['title'] = $this->t('Remove');

    $links['layout_builder_block_hide'] = [
      'route_name' => 'ghi_blocks.hide_block',
      'route_parameters' => $route_parameters,
      'title' => $this->t('Hide'),
      'weight' => NULL,
      'localized_options' => $localized_options,
      'metadata' => $metadata,
    ];
    $links['layout_builder_block_unhide'] = [
      'route_name' => 'ghi_blocks.unhide_block',
      'route_parameters' => $route_parameters,
      'title' => $this->t('Unhide'),
      'weight' => NULL,
      'localized_options' => $localized_options,
      'metadata' => $metadata,
    ];

  }

}
