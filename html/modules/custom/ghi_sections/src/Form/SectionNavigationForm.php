<?php

namespace Drupal\ghi_sections\Form;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\Render\Markup;
use Drupal\ghi_sections\Entity\Section;
use Drupal\ghi_sections\Menu\OptionalSectionMenuPluginInterface;
use Drupal\ghi_sections\Menu\SectionMenuPluginInterface;
use Drupal\ghi_sections\Menu\SectionMenuPluginManager;
use Drupal\ghi_sections\Menu\SectionMenuStorage;
use Drupal\ghi_sections\SectionManager;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * A form for bulk creating sections.
 */
class SectionNavigationForm extends FormBase {

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
   * The layout builder ipe config object.
   *
   * @var \Drupal\Core\Config\Config
   */
  protected $modalConfig;

  /**
   * The supported bundles.
   *
   * @var array
   */
  protected $bundles;

  /**
   * Public constructor.
   */
  public function __construct(SectionManager $section_manager, SectionMenuPluginManager $section_menu_plugin_manager, SectionMenuStorage $section_menu_storage, EntityTypeManagerInterface $entity_type_manager, ModuleHandlerInterface $module_handler, ConfigFactoryInterface $config_factory) {
    $this->sectionManager = $section_manager;
    $this->sectionMenuPluginManager = $section_menu_plugin_manager;
    $this->sectionMenuStorage = $section_menu_storage;
    $this->entityTypeManager = $entity_type_manager;
    $this->moduleHandler = $module_handler;
    $this->modalConfig = $config_factory->get('layout_builder_modal.settings');

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
      $container->get('config.factory'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'ghi_sections_section_navigation_form';
  }

  /**
   * Title callback for the form route.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node object.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup
   *   The title for the form page.
   */
  public function getTitle(NodeInterface $node) {
    return $this->t('Section navigation for <em>@label</em>', [
      '@label' => $node->label(),
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $node = NULL) {
    if (!$node instanceof Section) {
      return NULL;
    }

    $form['#node'] = $node;
    $form['#attached']['library'][] = 'ghi_sections/admin.section_navigation';

    $form['description'] = [
      '#markup' => '<p>' . $this->t('On this page you can set the order of menu items for this @type section. A menu item for the main landing page is always visible and always the first item in the menu. The other menu items can be rearranged to display in the order best suited for the @type section. Menu items that show as greyed out here are not displayed to public users because their corresponding page is either unpublished or empty. They can still be moved around in order to prepare for a future publication.', [
        '@type' => strtolower($node->getSectionType()),
      ]) . '</p>',
    ];

    $header = [
      $this->t('Label'),
      $this->t('Type'),
      $this->t('Status'),
      $this->t('Operations'),
      $this->t('Weight'),
    ];

    $rows = [];
    $rows[-1] = [
      '#attributes' => [
        'class' => [],
      ],
      '#weight' => -1,
      'label' => [
        '#markup' => $this->t('Overview'),
      ],
      'type' => [
        '#markup' => $this->t('Main landing page'),
      ],
      'status' => [
        '#markup' => $this->t('Always visible'),
      ],
      'operations' => [],
      'weight' => [
        '#type' => 'weight',
        '#title' => $this->t('Weight for @title', ['@title' => $this->t('Overview')]),
        '#title_display' => 'invisible',
        '#default_value' => -1,
        '#attributes' => [
          'class' => ['weight'],
        ],
      ],
    ];
    $menu_items = $this->sectionMenuStorage->getSectionMenuItems();
    foreach (array_values($menu_items->getAll()) as $delta => $menu_item) {
      $plugin = $menu_item->getPlugin();
      if (!$plugin || !$plugin->isValid()) {
        continue;
      }
      $item_label = $menu_item->getLabel();
      $item_status = $plugin->getStatus();
      $row = [
        '#attributes' => [
          'class' => ['draggable'],
        ],
        '#weight' => $delta,
        'label' => [
          '#type' => 'html_tag',
          '#tag' => 'span',
          '#attributes' => [
            'class' => [
              'menu-label',
            ],
          ],
          'value' => [
            '#markup' => Markup::create($item_label),
          ],
        ],
        'type' => [
          '#markup' => $plugin->getPluginLabel(),
        ],
        'status' => [
          '#markup' => $item_status ? $this->t('Visible') : $this->t('Hidden'),
        ],
        'operations' => [
          'edit' => Link::createFromRoute($this->t('Edit'), 'ghi_sections.menu_item.edit', [
            'node' => $node->id(),
            'delta' => $delta,
          ], ['attributes' => $this->getLinkAttributes(['button'])])->toRenderable(),
          'remove' => [
            '#type' => 'submit',
            '#value' => 'remove',
            '#name' => 'operation-remove-' . $delta,
            '#access' => $plugin instanceof OptionalSectionMenuPluginInterface,
          ],
        ],
        'weight' => [
          '#type' => 'weight',
          '#title' => $this->t('Weight for @title', ['@title' => $item_label]),
          '#title_display' => 'invisible',
          '#default_value' => $delta,
          '#attributes' => [
            'class' => ['weight'],
          ],
        ],
      ];
      if (!$item_status) {
        $row['#attributes']['class'][] = 'visually-disabled';
      }
      $rows[$delta] = $row;
    }

    $form['table'] = [
      '#type' => 'table',
      '#header' => $header,
      '#tabledrag' => [
        [
          'action' => 'order',
          'relationship' => 'sibling',
          'group' => 'weight',
        ],
      ],
    ] + $rows;

    $form['actions'] = [
      '#type' => 'actions',
      '#tree' => FALSE,
    ];
    $form['actions']['save_order'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save order'),
      '#gin_action_item' => TRUE,
    ];

    $form['actions']['reset'] = [
      '#type' => 'submit',
      '#value' => $this->t('Reset to defaults'),
    ];

    // Add controles for adding optional menu items.
    $form['add'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Add new menu item'),
      '#tree' => TRUE,
    ];

    $optional_plugins = $this->sectionMenuPluginManager->getOptionalPluginsForSection($node);
    if (!empty($optional_plugins)) {
      $form['add']['plugin_id'] = [
        '#type' => 'select',
        '#title' => $this->t('Menu item type'),
        '#options' => array_map(function (SectionMenuPluginInterface $plugin) {
          return $plugin->getPluginLabel();
        }, $optional_plugins),
      ];
      foreach ($optional_plugins as $plugin_id => $plugin) {
        $form['add'][$plugin_id] = [
          '#type' => 'container',
          '#states' => [
            'visible' => [
              ':input[name="add[plugin_id]"]' => ['value' => $plugin_id],
            ],
          ],
        ];
        $form['add'][$plugin_id]['configuration'] = $plugin->buildForm($form['add'][$plugin_id], $form_state);
      }

      $form['add']['add_item'] = [
        '#type' => 'submit',
        '#value' => $this->t('Add item'),
      ];
    }
    else {
      $form['add']['not_available'] = [
        '#markup' => $this->t('No additonal items found that could be added to the menu.'),
      ];
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $trigger_chain = $form_state->getTriggeringElement()['#parents'];
    $action = array_pop($trigger_chain);

    switch ($action) {
      case 'reset':
        $menu_items = $this->sectionMenuStorage->getSectionMenuItems();
        $menu_items->setValue(NULL);
        $this->sectionMenuStorage->save();
        break;

      case 'save_order':
        $rows = $form_state->getValue('table');
        $menu_items = $this->sectionMenuStorage->getSectionMenuItems();
        $menu_items->setNewOrder(array_keys($rows));
        $this->sectionMenuStorage->save();
        break;

      case 'remove':
        array_pop($trigger_chain);
        $delta = array_pop($trigger_chain);
        $menu_items = $this->sectionMenuStorage->getSectionMenuItems();
        $menu_items->removeItem($delta);
        $this->sectionMenuStorage->save();
        break;

      case 'add_item':
        $add = $form_state->getValue('add');
        $plugin_id = $add['plugin_id'];
        $this->sectionMenuStorage->createMenuItem($plugin_id, $add[$plugin_id]['configuration']);
        break;
    }
  }

  /**
   * Get the common attributes for template links.
   *
   * @param array $classes
   *   An optional set of additional classes for the link.
   *
   * @return array
   *   The link attributes array.
   */
  private function getLinkAttributes(array $classes = []) {
    return [
      'class' => array_merge([
        'use-ajax',
      ], $classes),
      'data-dialog-type' => 'dialog',
      'data-dialog-options' => Json::encode([
        'width' => $this->modalConfig->get('modal_width'),
        'height' => $this->modalConfig->get('modal_height'),
        'target' => 'layout-builder-modal',
        'autoResize' => $this->modalConfig->get('modal_autoresize'),
        'modal' => TRUE,
      ]),
    ];
  }

}
