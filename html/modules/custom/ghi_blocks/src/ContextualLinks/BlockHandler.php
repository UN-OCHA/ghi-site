<?php

namespace Drupal\ghi_blocks\ContextualLinks;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\Context\EntityContext;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\ghi_blocks\Plugin\Block\GHIBlockBase;
use Drupal\layout_builder\LayoutEntityHelperTrait;
use Drupal\layout_builder\LayoutTempstoreRepositoryInterface;
use Drupal\layout_builder\Plugin\Block\InlineBlock;
use Drupal\layout_builder\Plugin\SectionStorage\DefaultsSectionStorage;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Block handler service class.
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
   * The user account service.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * Public constructor.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, LayoutTempstoreRepositoryInterface $layout_tempstore_repository, AccountInterface $account) {
    $this->entityTypeManager = $entity_type_manager;
    $this->layoutTempstoreRepository = $layout_tempstore_repository;
    $this->currentUser = $account;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('layout_builder.tempstore_repository'),
      $container->get('current_user'),
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
    $allowed_storage_types = [
      'overrides',
      'page_manager',
    ];
    if (empty($route_parameters['section_storage_type']) || !in_array($route_parameters['section_storage_type'], $allowed_storage_types)) {
      return;
    }

    unset($links['layout_builder_block_move']);

    if ($route_parameters['section_storage_type'] == 'page_manager') {
      $entity = $this->entityTypeManager->getStorage('page_variant')->load($route_parameters['section_storage']);
    }
    else {
      [$entity_type_id, $id] = explode('.', $route_parameters['section_storage']);
      $entity = $this->entityTypeManager->getStorage($entity_type_id)->load($id);
    }
    $uuid = $route_parameters['uuid'];
    $delta = $route_parameters['delta'];
    $section_storage = $this->getSectionStorageForEntity($entity, 'default');

    // Get the overridden section storage if necessary. The default section
    // storage will not have a tempstore version.
    if ($section_storage instanceof DefaultsSectionStorage) {
      $section_storage = $this->sectionStorageManager()->load('overrides', [
        'entity' => EntityContext::fromEntity($entity),
      ]);
    }
    $section_storage = $this->layoutTempstoreRepository->get($section_storage);

    $component_keys = array_keys($section_storage->getSection($delta)->getComponents());
    if (!in_array($uuid, $component_keys)) {
      // Unknown uuid, keep going.
      return;
    }
    $component = $section_storage->getSection($delta)->getComponent($uuid);
    $plugin = $component->getPlugin();
    if (!$plugin || !($plugin instanceof GHIBlockBase || $plugin instanceof InlineBlock)) {
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
      'operations' => 'update:remove:hide:unhide:show_config',
      'langcode' => 'en',
    ];

    $links['layout_builder_block_remove']['title'] = $this->t('Remove');

    if ($this->currentUser->hasPermission('show block configuration code')) {
      $links['layout_builder_block_show_config'] = [
        'route_name' => 'ghi_blocks.show_block_config',
        'route_parameters' => $route_parameters,
        'title' => $this->t('Export config'),
        'weight' => NULL,
        'localized_options' => $localized_options,
        'metadata' => $metadata,
      ];
    }

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

    // Order the links.
    $order = [
      'layout_builder_block_update',
      'layout_builder_block_hide',
      'layout_builder_block_unhide',
      'layout_builder_block_show_config',
      'layout_builder_block_remove',
    ];
    $_links = [];
    foreach ($order as $key) {
      if (!array_key_exists($key, $links)) {
        continue;
      }
      $_links[$key] = $links[$key];
    }
    $links = $_links;
    unset($_links);

  }

}
