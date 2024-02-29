<?php

namespace Drupal\ghi_templates\LayoutBuilder;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\CloseDialogCommand;
use Drupal\Core\Ajax\RedirectCommand;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\Context\EntityContext;
use Drupal\ghi_base_objects\Helpers\BaseObjectHelper;
use Drupal\ghi_blocks\Traits\GinLbModalTrait;
use Drupal\layout_builder\LayoutEntityHelperTrait;
use Drupal\layout_builder\Plugin\SectionStorage\DefaultsSectionStorage;
use Drupal\layout_builder\Section;
use Drupal\layout_builder\SectionComponent;
use Drupal\layout_builder\SectionStorageInterface;
use Drupal\layout_builder_ipe\Controller\EntityEditController;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Base class for template forms.
 */
abstract class TemplateFormBase extends FormBase {

  use LayoutEntityHelperTrait;
  use GinLbModalTrait;

  /**
   * The controller resolver.
   *
   * @var \Drupal\Core\Controller\ControllerResolverInterface
   */
  protected $controllerResolver;

  /**
   * The plugin context handler.
   *
   * @var \Drupal\Core\Plugin\Context\ContextHandlerInterface
   */
  protected $contextHandler;

  /**
   * The UUID generator service.
   *
   * @var \Drupal\Component\UuidInterface
   */
  protected $uuidGenerator;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $instance = new static();
    $instance->controllerResolver = $container->get('controller_resolver');
    $instance->contextHandler = $container->get('context.handler');
    $instance->uuidGenerator = $container->get('uuid');
    return $instance;
  }

  /**
   * Build form callback.
   */
  public function buildForm(array $form, FormStateInterface $form_state, EntityInterface $entity = NULL, SectionStorageInterface $section_storage = NULL) {
    $form['settings'] = [
      '#type' => 'container',
    ];
    $form['actions'] = [
      '#type' => 'container',
    ];
    $form['#attached']['library'][] = 'ghi_templates/template_modal_form';
    $this->makeGinLbForm($form, $form_state);
    return $form;
  }

  /**
   * Build and send an ajax response after successfull form submission.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   An AJAX response.
   */
  protected function successfulAjaxSubmit(array $form, FormStateInterface $form_state) {
    $query = $this->getRequest()->query;
    $redirect_to_entity = $query->get('redirect_to_entity', FALSE);
    if ($redirect_to_entity) {
      /** @var \Drupal\Core\Url $entity_url */
      $entity_url = $form_state->get('entity')->toUrl();
      $query = $entity_url->getOption('query');
      $query['openIpe'] = TRUE;
      $entity_url->setOption('query', $query);
      $response = new AjaxResponse();
      $response->addCommand(new RedirectCommand($entity_url->toString()));
    }
    else {
      $callable = $this->controllerResolver->getControllerFromDefinition(EntityEditController::class . '::edit');
      $response = $callable($form_state->get('section_storage'));
      $response->addCommand(new CloseDialogCommand('#layout-builder-modal'));
    }
    return $response;
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
   *
   * @return \Drupal\layout_builder\SectionStorageInterface
   *   The update section storage.
   */
  protected function buildSectionStorageFromPageConfig(SectionStorageInterface $section_storage, EntityInterface $entity, array $page_config, bool $overwrite, array $selected_elements = []) {
    // First, make sure we have an overridden section storage.
    if ($section_storage instanceof DefaultsSectionStorage) {
      // Overide the section storage in the tempstore.
      $section_storage = $this->sectionStorageManager()->load('overrides', [
        'entity' => EntityContext::fromEntity($entity),
      ]);
    }

    // Clear the current storage.
    if ($overwrite) {
      $section_storage->removeAllSections();
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
        $component_config['uuid'] = $this->uuidGenerator->generate();
        $component = SectionComponent::fromArray($component_config);
        $plugin = $component->getPlugin();
        $definition = $plugin->getPluginDefinition();
        $context_mapping = [
          'context_mapping' => array_intersect_key([
            'node' => 'layout_builder.entity',
          ], $definition['context_definitions']),
        ];
        // And make sure that base objects are mapped too.
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
            $context_mapping['context_mapping'][$context_key] = $_base_object->getUniqueIdentifier();
          }
        }
        $configuration = $context_mapping + $component->get('configuration');
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

}
