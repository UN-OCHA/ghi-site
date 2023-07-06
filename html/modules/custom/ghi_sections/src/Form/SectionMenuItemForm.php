<?php

namespace Drupal\ghi_sections\Form;

use Drupal\Component\Utility\Html;
use Drupal\Core\Ajax\AjaxFormHelperTrait;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\RedirectCommand;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\ghi_blocks\Traits\GinLbModalTrait;
use Drupal\ghi_sections\Entity\Section;
use Drupal\ghi_sections\Menu\SectionMenuPluginManager;
use Drupal\ghi_sections\Menu\SectionMenuStorage;
use Drupal\ghi_sections\SectionManager;
use Drupal\ghi_subpages\Plugin\SectionMenuItem\StandardSubpage;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * A section menu item edit form.
 */
class SectionMenuItemForm extends FormBase {

  use GinLbModalTrait;
  use AjaxFormHelperTrait;

  /**
   * The section manager.
   *
   * @var \Drupal\ghi_sections\SectionManager
   */
  protected $sectionManager;

  /**
   * The section menu plugin manager.
   *
   * @var \Drupal\ghi_sections\Menu\SectionMenuPluginManager
   */
  protected $sectionMenuPluginManager;

  /**
   * The section menu storage.
   *
   * @var \Drupal\ghi_sections\Menu\SectionMenuStorage
   */
  protected $sectionMenuStorage;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * The supported bundles.
   *
   * @var array
   */
  protected $bundles;

  /**
   * Public constructor.
   */
  public function __construct(SectionManager $section_manager, SectionMenuPluginManager $section_menu_plugin_manager, SectionMenuStorage $section_menu_storage, EntityTypeManagerInterface $entity_type_manager, ModuleHandlerInterface $module_handler) {
    $this->sectionManager = $section_manager;
    $this->sectionMenuPluginManager = $section_menu_plugin_manager;
    $this->sectionMenuStorage = $section_menu_storage;
    $this->entityTypeManager = $entity_type_manager;
    $this->moduleHandler = $module_handler;

    $this->bundles = $this->sectionManager->getAvailableBaseObjectTypes();
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('ghi_sections.manager'),
      $container->get('plugin.manager.section_menu'),
      $container->get('ghi_sections.section_menu.storage'),
      $container->get('entity_type.manager'),
      $container->get('module_handler'),
    );
  }

  /**
   * Title callback for the form route.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node object.
   * @param int $delta
   *   The index of the menu item in the item list of the node.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup
   *   The title for the form page.
   */
  public function getTitle(NodeInterface $node, $delta) {
    $menu_item = $this->getMenuItem($delta);
    return $this->t('Menu item for <em>@type</em>: @title', [
      '@type' => $menu_item->getPlugin()->getPluginLabel(),
      '@title' => $menu_item->getLabel(),
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'ghi_sections_section_menu_item_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $node = NULL, $delta = NULL) {
    if (!$node instanceof Section || $delta === NULL) {
      return NULL;
    }

    $form['#title'] = $this->getTitle($node, $delta);
    $form['#node'] = $node;
    $form['#delta'] = $delta;

    $form['settings'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['canvas-form__settings'],
      ],
    ];

    $menu_item = $this->getMenuItem($delta);
    $form['settings']['label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Label'),
      '#default_value' => $menu_item->getLabel(),
    ];
    if ($menu_item->getPlugin() instanceof StandardSubpage) {
      $form['settings']['label']['#disabled'] = TRUE;
      $form['settings']['label']['#description'] = $this->t('Menu items based on standard subpages cannot be renamed.');
    }

    $form['actions'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['canvas-form__actions'],
      ],
    ];

    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Update menu item'),
    ];
    if ($this->isAjax()) {
      $form['actions']['submit']['#ajax']['callback'] = '::ajaxSubmit';
      // @todo static::ajaxSubmit() requires data-drupal-selector to be the same
      //   between the various Ajax requests. A bug in
      //   \Drupal\Core\Form\FormBuilder prevents that from happening unless
      //   $form['#id'] is also the same. Normally, #id is set to a unique HTML
      //   ID via Html::getUniqueId(), but here we bypass that in order to work
      //   around the data-drupal-selector bug. This is okay so long as we
      //   assume that this form only ever occurs once on a page. Remove this
      //   workaround in https://www.drupal.org/node/2897377.
      $form['#id'] = Html::getId($form_state->getBuildInfo()['form_id']);
    }

    // Add a cancel link.
    $form['actions']['cancel'] = [
      '#type' => 'link',
      '#title' => $this->t('Cancel'),
      '#url' => Url::fromRoute('ghi_sections.node.section_navigation', [
        'node' => $node->id(),
      ]),
      '#weight' => -1,
      '#attributes' => [
        'class' => [
          'dialog-cancel',
        ],
      ],
    ];

    $this->makeGinLbForm($form, $form_state);
    return $form;
  }

  /**
   * Get the menu item object for the given delta.
   *
   * @param int $delta
   *   The delta, or position of the item in the item list.
   *
   * @return \Drupal\ghi_sections\Menu\SectionMenuItemInterface|null
   *   The menu item or NULL.
   */
  private function getMenuItem($delta) {
    $menu_items = $this->sectionMenuStorage->getSectionMenuItems();
    /** @var \Drupal\ghi_sections\Menu\SectionMenuItemInterface $menu_item */
    return $menu_items->get($delta)?->menu_item;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $label = $form_state->getValue('label');

    $delta = $form['#delta'];
    $menu_item = $this->getMenuItem($delta);
    $menu_item->setLabel($label);
    $this->sectionMenuStorage->save();

    $form_state->setRebuild();
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
    $redirect_url = Url::fromRoute('ghi_sections.node.section_navigation', [
      'node' => $form['#node']->id(),
    ]);
    $response = new AjaxResponse();
    $response->addCommand(new RedirectCommand($redirect_url->toString()));
    return $response;
  }

}
