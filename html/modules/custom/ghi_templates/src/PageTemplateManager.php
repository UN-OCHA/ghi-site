<?php

namespace Drupal\ghi_templates;

use Drupal\Component\Serialization\Yaml;
use Drupal\Component\Uuid\UuidInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\Context\ContextHandlerInterface;
use Drupal\Core\Plugin\Context\EntityContext;
use Drupal\ghi_base_objects\Helpers\BaseObjectHelper;
use Drupal\ghi_blocks\Interfaces\ConfigValidationInterface;
use Drupal\ghi_blocks\Plugin\Block\GHIBlockBase;
use Drupal\ghi_templates\Entity\PageTemplateInterface;
use Drupal\hpc_common\Helpers\ArrayHelper;
use Drupal\layout_builder\LayoutEntityHelperTrait;
use Drupal\layout_builder\Plugin\SectionStorage\DefaultsSectionStorage;
use Drupal\layout_builder\Section;
use Drupal\layout_builder\SectionComponent;
use Drupal\layout_builder\SectionStorageInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Manager class for page templates.
 */
class PageTemplateManager implements ContainerInjectionInterface {

  use LayoutEntityHelperTrait;

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The plugin context handler.
   *
   * @var \Drupal\Core\Plugin\Context\ContextHandlerInterface
   */
  protected $contextHandler;

  /**
   * The UUID service.
   *
   * @var \Drupal\Component\UuidInterface
   */
  protected $uuid;

  /**
   * Public constructor.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, ContextHandlerInterface $context_handler, UuidInterface $uuid) {
    $this->entityTypeManager = $entity_type_manager;
    $this->contextHandler = $context_handler;
    $this->uuid = $uuid;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('context.handler'),
      $container->get('uuid'),
    );
  }

  /**
   * Load a page template by it's id.
   *
   * @param int $id
   *   The id of the page template to load.
   *
   * @return \Drupal\ghi_templates\Entity\PageTemplateInterface
   *   The page template entity.
   */
  public function loadPageTemplate($id) {
    return $this->entityTypeManager->getStorage('page_template')->load($id);
  }

  /**
   * Load page templates available for the given entity.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity for which to load page templates.
   *
   * @return \Drupal\ghi_templates\Entity\PageTemplateInterface[]
   *   An array of page tenmplate entities.
   */
  public function loadAvailableTemplatesForEntity(EntityInterface $entity) {
    $page_templates = $this->entityTypeManager->getStorage('page_template')->loadByProperties([
      'status' => TRUE,
    ]);
    $page_templates = array_filter($page_templates, function (PageTemplateInterface $page_template) use ($entity) {
      $source = $page_template->getSourceEntity();
      if (!$source) {
        return FALSE;
      }
      if ($source->getEntityTypeId() != $entity->getEntityTypeId()) {
        return FALSE;
      }
      if ($entity->getEntityType()->hasKey('bundle')) {
        return $source->bundle() == $entity->bundle();
      }
      return TRUE;
    });
    return $page_templates;
  }

  /**
   * Build a section storage from the given page configuration.
   *
   * @param \Drupal\layout_builder\SectionStorageInterface $section_storage
   *   The section storage to extend or rebuild.
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity associated with the section storage.
   * @param array $page_config
   *   The page config.
   * @param bool $overwrite
   *   Whether the section storage should be completely overwritten.
   * @param array $selected_elements
   *   Optional: An array of selected elements. If given, then only the
   *   elements specified will be added to the section storage.
   * @param bool $fix_element_configuration
   *   Whether the element configuration should be fixed if necessary.
   *
   * @return \Drupal\layout_builder\SectionStorageInterface
   *   The update section storage.
   */
  public function buildSectionStorageFromPageConfig(SectionStorageInterface $section_storage, EntityInterface $entity, array $page_config, bool $overwrite, array $selected_elements = [], $fix_element_configuration = TRUE) {
    // First, make sure we have an overridden section storage.
    if ($section_storage instanceof DefaultsSectionStorage) {
      // Overide the section storage in the tempstore.
      $section_storage = $this->sectionStorageManager()->load('overrides', [
        'entity' => EntityContext::fromEntity($entity),
      ]);
    }

    $delta = 0;
    $section = $section_storage->getSection($delta);

    // Clear the current storage.
    if ($overwrite && $section->getComponents()) {
      foreach ($section->getComponents() as $component) {
        $plugin = $component->getPlugin();
        if ($plugin instanceof GHIBlockBase && !$plugin->canBeRemoved()) {
          // Don't remove components that could not have been removed manually
          // via the UI. In this case, the paragraph is locked, so it can't be
          // removed.
          continue;
        }
        $section->removeComponent($component->getUuid());
      }
    }

    // Now setup each section according to the imported config.
    foreach ($page_config as $section_key => $section_config) {
      if (empty($section_config['components'])) {
        continue;
      }
      $components = [];
      foreach ($section_config['components'] as $component_key => $component_config) {
        if (empty($selected_elements[$section_key . '--' . $component_key])) {
          continue;
        }
        // Set a new UUID to prevent UUID collisions.
        $component_config['uuid'] = $this->uuid->generate();
        $component = SectionComponent::fromArray($component_config);

        // Make sure that context objects are updated too.
        $this->updateComponentContext($component, $entity);

        // Fix configuration issues if possible.
        $plugin = $component->getPlugin();
        $configuration = $component->toArray()['configuration'];
        if ($plugin instanceof GHIBlockBase) {
          $plugin->alterImportedConfiguration($configuration);
        }
        if ($fix_element_configuration && $plugin instanceof GHIBlockBase && $plugin instanceof ConfigValidationInterface) {
          $plugin->setContext('entity', EntityContext::fromEntity($entity, $entity->type->entity->label()));
          $plugin->setConfiguration($configuration);
          $plugin->fixConfigErrors();
          $configuration = $plugin->getConfiguration();
        }
        $component->setConfiguration($configuration);
        $components[$component->getUuid()] = $component->toArray();
      }
      if ($overwrite || empty($section_storage->getSections())) {
        $section_config['components'] = $components;
        $section = Section::fromArray($section_config);
        $section_storage->appendSection($section);
      }
      else {
        $section = $section_storage->getSection(0);
        foreach ($components as $component) {
          $section->appendComponent(SectionComponent::fromArray($component));
        }
      }
    }
    return $section_storage;
  }

  /**
   * Update the contexts for the component.
   *
   * @param \Drupal\layout_builder\SectionComponent $component
   *   The section component.
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity associated with the section storage.
   */
  private function updateComponentContext(SectionComponent $component, EntityInterface $entity) {
    $plugin = $component->getPlugin();
    $definition = $plugin->getPluginDefinition();

    $configuration = [
      'context_mapping' => array_intersect_key([
        'node' => 'layout_builder.entity',
      ], $definition['context_definitions']),
    ] + $component->get('configuration');
    $component->setConfiguration($configuration);

    if (!$plugin instanceof GHIBlockBase) {
      return;
    }

    // Get hold of the base object storage.
    $base_object_storage = $this->entityTypeManager->getStorage('base_object');

    $base_objects = BaseObjectHelper::getBaseObjectsFromNode($entity) ?? [];
    foreach ($base_objects as $_base_object) {
      $contexts = [
        EntityContext::fromEntity($_base_object),
      ];
      foreach ($definition['context_definitions'] as $context_key => $context_definition) {
        $matching_contexts = $this->contextHandler->getMatchingContexts($contexts, $context_definition);
        if (empty($matching_contexts)) {
          continue;
        }
        $configuration['context_mapping'][$context_key] = $_base_object->getUniqueIdentifier();
      }
    }

    if (array_key_exists(GHIBlockBase::CONTEXTS_FORM_KEY, $configuration)) {
      $context = &$configuration[GHIBlockBase::CONTEXTS_FORM_KEY];
      $context['context_mapping'] = $configuration['context_mapping'];

      if (!empty($context['data_object']) && $base_object = $base_object_storage->load($context['data_object'])) {
        // Also map the configured data object.
        /** @var \Drupal\ghi_base_objects\Entity\BaseObjectInterface $base_object */
        $base_object_identifier = $base_object->getUniqueIdentifier();
        foreach ($context['context_mapping'] as $context_key => $unique_identifier) {
          if (!str_contains($unique_identifier, '--')) {
            continue;
          }
          [$bundle, $original_id] = explode('--', $unique_identifier);
          if (!str_starts_with($base_object_identifier, $bundle . '--')) {
            continue;
          }
          $base_object_new = BaseObjectHelper::getBaseObjectFromOriginalId($original_id, $bundle);
          if (!$base_object_new) {
            continue;
          }
          $context['data_object'] = $base_object_new->id();
        }
      }

      if (array_key_exists(GHIBlockBase::CONTEXTS_FORM_KEY, $configuration['hpc'])) {
        $configuration['hpc'][GHIBlockBase::CONTEXTS_FORM_KEY] = $context;
      }
    }

    $component->setConfiguration($configuration);
  }

  /**
   * Export a section storage to an array.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity for which to export the section storage.
   *
   * @return array
   *   An array representing the section storage.
   */
  public function exportSectionStorage(EntityInterface $entity) {
    $section_storage = $this->getSectionStorageForEntity($entity);
    /** @var \Drupal\Core\Entity\EntityInterface $entity */
    // $entity = $section_storage->getContextValue('entity');
    $sections = $section_storage->getSections();
    $config_export = [
      'entity_type' => $entity->getEntityTypeId(),
      'entity_id' => (int) $entity->id(),
      'bundle' => $entity->bundle(),
      'url' => $entity->toUrl()->toString(),
      'validation' => count($sections) == 1,
      'page_config' => [],
    ];
    foreach ($sections as $delta => $section) {
      $config_export['page_config'][$delta] = $section->toArray();
      uasort($config_export['page_config'][$delta]['components'], function ($a, $b) {
        return $a['weight'] <=> $b['weight'];
      });
      foreach ($config_export['page_config'][$delta]['components'] as &$component) {
        $plugin = $section->getComponent($component['uuid'])?->getPlugin();
        if ($plugin instanceof GHIBlockBase) {
          $plugin->alterExportedConfiguration($component['configuration']);
        }
        unset($component['configuration']['uuid']);
        unset($component['configuration']['context_mapping']);
        unset($component['configuration']['data_sources']);
      }
    }

    $config_export['hash'] = md5(Yaml::encode(ArrayHelper::mapObjectsToString($config_export)));
    return $config_export;
  }

}
