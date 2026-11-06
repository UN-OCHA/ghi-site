<?php

namespace Drupal\ghi_templates\LayoutBuilder;

use Drupal\Component\Utility\Html;
use Drupal\Core\Ajax\AjaxHelperTrait;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\CloseDialogCommand;
use Drupal\Core\Ajax\RedirectCommand;
use Drupal\Core\Block\BlockManagerInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\DependencyInjection\ClassResolverInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\Context\EntityContext;
use Drupal\Core\Render\Markup;
use Drupal\ghi_blocks\Interfaces\ConfigValidationInterface;
use Drupal\ghi_blocks\LayoutBuilder\AllowedBlockDefinitions;
use Drupal\ghi_blocks\Traits\GinLbModalTrait;
use Drupal\hpc_common\Traits\AjaxFormTrait;
use Drupal\layout_builder\LayoutEntityHelperTrait;
use Drupal\layout_builder\SectionStorageInterface;
use Drupal\layout_builder_ipe\Controller\EntityEditController;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Base class for template forms.
 */
abstract class TemplateFormBase extends FormBase {

  use LayoutEntityHelperTrait;
  use GinLbModalTrait;
  use AjaxFormTrait;
  use AjaxHelperTrait;

  /**
   * The controller resolver.
   *
   * @var \Drupal\Core\Controller\ControllerResolverInterface
   */
  protected $controllerResolver;

  /**
   * The template manager.
   *
   * @var \Drupal\ghi_templates\PageTemplateManager
   */
  protected $pageTemplateManager;

  /**
   * The class resolver.
   *
   * @var \Drupal\Core\DependencyInjection\ClassResolverInterface
   */
  protected ClassResolverInterface $classResolver;

  /**
   * The block manager.
   */
  protected BlockManagerInterface $blockManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $instance = new static();
    $instance->controllerResolver = $container->get('controller_resolver');
    $instance->pageTemplateManager = $container->get('ghi_templates.manager');
    $instance->classResolver = $container->get('class_resolver');
    $instance->blockManager = $container->get('plugin.manager.block');
    return $instance;
  }

  /**
   * Build form callback.
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?EntityInterface $entity = NULL, ?SectionStorageInterface $section_storage = NULL) {
    $form_state->set('section_storage', $section_storage);
    $form_state->set('entity', $entity);
    $form['settings'] = [
      '#type' => 'container',
    ];
    $form['actions'] = [
      '#type' => 'actions',
    ];
    $form['#attached']['library'][] = 'ghi_templates/template_modal_form';
    $this->makeGinLbForm($form, $form_state);
    $this->setAjaxCompatibleFormId($form, $form_state);
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $section_storage = $form_state->get('section_storage');
    $definitions = $this->classResolver->getInstanceFromDefinition(AllowedBlockDefinitions::class)->getDefinitions($section_storage, $form_state->get('entity'));
    $selected_elements = $form_state->getValue('table', []);
    foreach ($form_state->get('page_config') ?? [] as $section_key => $section_config) {
      foreach ($section_config['components'] ?? [] as $component_key => $component_config) {
        if (!empty($selected_elements[$section_key . '--' . $component_key]) && !isset($definitions[$component_config['configuration']['id']])) {
          $form_state->setErrorByName('table', $this->t('This element is not allowed in this layout.'));
        }
      }
    }
  }

  /**
   * Builds the selectable element summary shared by import and apply workflows.
   *
   * @param array $page_config
   *   The sections and components being imported.
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The destination entity.
   * @param \Drupal\layout_builder\SectionStorageInterface $section_storage
   *   The destination section storage.
   *
   * @return array
   *   A table selecting the allowed components and displaying their issues.
   */
  protected function buildElementSelectionTable(array $page_config, EntityInterface $entity, SectionStorageInterface $section_storage): array {
    $page_config = array_filter($page_config, function ($section) {
      return !empty($section['components']);
    });
    $definitions = $this->classResolver->getInstanceFromDefinition(AllowedBlockDefinitions::class)->getDefinitions($section_storage, $entity);
    $rows = [];
    foreach ($page_config as $section_key => $section) {
      if (count($page_config) > 1) {
        $rows[] = [
          [
            'data' => [
              '#type' => 'html_tag',
              '#tag' => 'h2',
              '#value' => Html::escape((string) $section['layout_settings']['label']),
            ],
            'colspan' => 3,
          ],
          '#disabled' => TRUE,
        ];
      }
      foreach ($section['components'] as $component_key => $component) {
        /** @var \Drupal\Core\Block\BlockBase $block */
        $block = $this->blockManager->createInstance($component['configuration']['id'], $component['configuration']);
        $block->setContext('entity', EntityContext::fromEntity($entity));
        $key = $section_key . '--' . $component_key;
        $rows[$key] = [
          $block->getPluginDefinition()['admin_label'],
          $block->label() ?? $this->t('n/a'),
          $block instanceof ConfigValidationInterface && !$block->validateConfiguration() ? Markup::create(implode('<br />', $block->getConfigErrors())) : '',
        ];
        if (!isset($definitions[$component['configuration']['id']])) {
          $rows[$key][2] = $this->t('This element is not allowed in this layout.');
          $rows[$key]['#disabled'] = TRUE;
        }
      }
    }

    $selectable_rows = array_filter($rows, static fn(array $row) => empty($row['#disabled']));
    return [
      '#type' => 'tableselect',
      '#header' => [
        $this->t('Element type'),
        $this->t('Label'),
        $this->t('Configuration issues'),
      ],
      '#options' => $rows,
      '#default_value' => array_fill_keys(array_keys($selectable_rows), TRUE),
      '#required' => TRUE,
    ];
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
      $response->addCommand(new CloseDialogCommand('#layout-builder-modal'), TRUE);
    }
    return $response;
  }

}
