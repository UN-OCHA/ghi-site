<?php

namespace Drupal\ghi_menu\Plugin\Block;

use Drupal\Component\Plugin\Exception\PluginException;
use Drupal\Component\Utility\Html;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Form\FormState;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\SubformStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Render\Element;
use Drupal\Core\Render\Element\RenderElementBase;
use Drupal\Core\Render\Element\VerticalTabs;
use Drupal\ghi_blocks\Traits\VerticalTabsTrait;
use Drupal\menu_link_content\Plugin\Menu\MenuLinkContent;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a 'SectionsByTerm' block.
 *
 * @Block(
 *  id = "mega_menu_block",
 *  admin_label = @Translation("Show a menu as a single mega menu"),
 *  category = @Translation("Menus"),
 * )
 */
class MegaMenuBlock extends BlockBase implements ContainerFactoryPluginInterface {

  use VerticalTabsTrait;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The entity field manager.
   *
   * @var \Drupal\Core\Entity\EntityFieldManager
   */
  protected $entityFieldManager;

  /**
   * The module handler service.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * The menu tree service.
   *
   * @var \Drupal\Core\Menu\MenuLinkTreeInterface
   */
  protected $menuTree;

  /**
   * The menu tree manipulators.
   *
   * @var \Drupal\Core\Menu\DefaultMenuLinkTreeManipulators
   */
  protected $menuTreeManipulators;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    /** @var \Drupal\ghi_menu\Plugin\Block\MegaMenuBlock $instance */
    $instance = new static($configuration, $plugin_id, $plugin_definition);
    $instance->entityTypeManager = $container->get('entity_type.manager');
    $instance->entityFieldManager = $container->get('entity_field.manager');
    $instance->moduleHandler = $container->get('module_handler');
    $instance->menuTree = $container->get('menu.link_tree');
    $instance->menuTreeManipulators = $container->get('menu.default_tree_manipulators');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function label() {
    $label = parent::label();
    return $label;
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $build = [];

    $menu_tree = $this->getMenuItems();
    if (empty($menu_tree)) {
      return $build;
    }

    $cache_contexts = [];
    $cache_tags = [];

    $tabs = [
      '#theme' => 'item_list',
    ];

    $tab_items = [];
    foreach ($menu_tree as $plugin_id => $item) {
      if (!$item->count()) {
        continue;
      }

      $_tree = $this->menuTree->build($item->subtree);
      $cache_contexts = Cache::mergeContexts($cache_contexts, $_tree['#cache']['contexts']);
      $cache_tags = Cache::mergeTags($cache_tags, $_tree['#cache']['tags']);
      unset($_tree['#cache']);

      $tab_items[$plugin_id] = $_tree;
      $tab_items[$plugin_id]['#title'] = $item->link->getTitle();
      $tab_items[$plugin_id]['#group'] = 'tabs';
      $tab_items[$plugin_id]['#weight'] = $item->link->getWeight();

      // Add classes to child items that themselves do not have child items.
      foreach ($tab_items[$plugin_id]['#items'] ?? [] as &$_item) {
        $_item['attributes']['class'] = $_item['attributes']['class'] ?? [];
        if (empty($_item['below'])) {
          $_item['attributes']['class'][] = 'leaf';
        }
      }
    }

    if (empty($tab_items)) {
      return $build;
    }

    $tabs = [
      '#theme' => 'item_list',
    ] + $tab_items;

    $build = [
      '#type' => 'container',
      '#attributes' => [
        'class' => [
          $this->configuration['label_display'] ? Html::getClass('label-visible') : NULL,
          'mega-menu',
          'mega-menu--' . $this->configuration['menu'],
        ],
      ],
    ];

    $build['tabs'] = [
      '#type' => 'vertical_tabs',
      '#default_tab' => array_key_first($tab_items),
      '#attributes' => [
        'class' => [$this->configuration['label_display'] ? Html::getClass('label-visible') : NULL],
      ],
      '#parents' => ['tabs'],
      '#attached' => [
        'drupalSettings' => ['widthBreakpoint' => 1024],
        'library' => [
          'ghi_menu/mega_menu',
        ],
      ],
    ];

    $build['tab_content'] = $tabs;
    $build['tab_content']['#tree'] = TRUE;

    $form_state = new FormState();
    $complete_form = [];
    VerticalTabs::processVerticalTabs($build['tabs'], $form_state, $complete_form);
    RenderElementBase::processGroup($build['tabs']['group'], $form_state, $complete_form);

    // Default tab is the first one. We have to set #value instead of the
    // #default_value, because this is not a real form and the normal form
    // processing doesn't work.
    $build['tabs']['tabs__active_tab']['#value'] = array_key_first($tab_items);

    foreach (Element::children($build['tab_content']) as $element_key) {
      $build['tab_content'][$element_key] = [
        '#type' => 'details',
        '#title' => $build['tab_content'][$element_key]['#title'],
        '#open' => TRUE,
        '#group' => 'tabs',
        '#id' => Html::getId($build['tab_content'][$element_key]['#title']),
        '#parents' => [
          'tab_content',
          $element_key,
        ],
        'content' => $build['tab_content'][$element_key],
      ];
      RenderElementBase::processGroup($build['tab_content'][$element_key], $form_state, $complete_form);
    }
    $this->processVerticalTabs($build, $form_state);

    $build['#cache'] = [
      'contexts' => $cache_contexts,
      'tags' => $cache_tags,
    ];

    return $build;
  }

  /**
   * Get the id used for aria attributes.
   *
   * @return string
   *   An id to be used in aria attributes.
   */
  public function getAriaId() {
    return Html::getId('menu-item--' . $this->getPluginId());
  }

  /**
   * Get the menu items to display in this block.
   *
   * @return \Drupal\Core\Menu\MenuLinkTreeElement[]
   *   An array of menu link tree elements.
   */
  public function getMenuItems() {
    if (empty($this->configuration['menu'])) {
      return [];
    }
    $menu = $this->entityTypeManager->getStorage('menu')->load($this->configuration['menu']);
    if (!$menu) {
      return [];
    }
    $parameters = $this->menuTree->getCurrentRouteMenuTreeParameters('main');
    $parameters->expandedParents = [];
    $menu_tree = $this->menuTree->load($menu->id(), $parameters);
    $this->filterBrokenItems($menu_tree);

    // Transform the tree using the manipulators you want.
    $manipulators = [
      // Only show links that are accessible for the current user.
      ['callable' => 'menu.default_tree_manipulators:checkAccess'],
      // Use the default sorting of menu links.
      ['callable' => 'menu.default_tree_manipulators:generateIndexAndSort'],
    ];
    $menu_tree = $this->menuTree->transform($menu_tree, $manipulators);
    return $menu_tree;
  }

  /**
   * Filter broken menu links from the tree.
   *
   * @param \Drupal\Core\Menu\MenuLinkTreeElement[] $menu_tree
   *   The menu tree to filter.
   */
  private function filterBrokenItems(array &$menu_tree) {
    foreach ($menu_tree as $key => &$item) {
      $link = $item->link;
      if (!$link instanceof MenuLinkContent) {
        continue;
      }
      try {
        $this->menuTreeManipulators->checkAccess([$item]);
      }
      catch (PluginException $e) {
        unset($menu_tree[$key]);
        continue;
      }
      if ($item->hasChildren) {
        $this->filterBrokenItems($item->subtree);
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'menu' => NULL,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);
    $form['label']['#default_value'] = $this->configuration['label'];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state) {
    parent::blockForm($form, $form_state);

    $wrapper_id = Html::getId('form-wrapper-' . $this->getPluginId());
    $form['#prefix'] = '<div id="' . $wrapper_id . '">';
    $form['#suffix'] = '</div>';

    $values = [];
    if ($form_state instanceof SubformStateInterface) {
      $values = $form_state->getCompleteFormState()->cleanValues()->getValue(['settings']);
    }

    /** @var \Drupal\system\Entity\Menu[] $menus */
    $menus = $this->entityTypeManager->getStorage('menu')->loadMultiple();

    $conf = $this->configuration;
    $default_menu = $values['menu'] ?? ($conf['menu'] ?? array_key_first($menus));

    $form['menu'] = [
      '#type' => 'select',
      '#title' => $this->t('Menu'),
      '#description' => $this->t('Select the menu that you want to show as a mega menu'),
      '#options' => array_map(function ($menu) {
        return $menu->label();
      }, $menus),
      '#default_value' => $default_menu,
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state) {
    $this->configuration['menu'] = $form_state->getValue('menu');
  }

}
