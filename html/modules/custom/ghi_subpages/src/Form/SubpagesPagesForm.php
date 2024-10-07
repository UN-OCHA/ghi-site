<?php

namespace Drupal\ghi_subpages\Form;

use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Access\CsrfTokenGenerator;
use Drupal\Core\Datetime\DateFormatter;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\Render\Element;
use Drupal\Core\Render\Markup;
use Drupal\Core\Routing\RedirectDestinationInterface;
use Drupal\Core\Security\TrustedCallbackInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Url;
use Drupal\ghi_base_objects\Entity\BaseObjectAwareEntityInterface;
use Drupal\ghi_sections\Entity\Section;
use Drupal\ghi_sections\Entity\SectionNodeInterface;
use Drupal\ghi_subpages\SubpageManager;
use Drupal\ghi_subpages\SubpageTrait;
use Drupal\layout_builder\LayoutEntityHelperTrait;
use Drupal\node\NodeInterface;
use Drupal\node\NodeTypeInterface;
use Drupal\publishcontent\Access\PublishContentAccess;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a form for managing subpages of a base entity.
 */
class SubpagesPagesForm extends FormBase implements TrustedCallbackInterface {

  use SubpageTrait;
  use LayoutEntityHelperTrait;

  /**
   * The date formatter service.
   *
   * @var \Drupal\Core\Datetime\DateFormatter
   */
  protected $dateFormatter;

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The publish content access service.
   *
   * @var \Drupal\publishcontent\Access\PublishContentAccess
   */
  protected $publishContentAccess;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * The CSRF token generator.
   *
   * @var \Drupal\Core\Access\CsrfTokenGenerator
   */
  protected $csrfToken;

  /**
   * The module handler to invoke hooks on.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * The redirect destination service.
   *
   * @var \Drupal\Core\Routing\RedirectDestinationInterface
   */
  protected $redirectDestination;

  /**
   * The section manager.
   *
   * @var \Drupal\ghi_subpages\SubpageManager
   */
  protected $subpageManager;

  /**
   * Constructs a SubpagesPages form.
   */
  public function __construct(DateFormatter $date_formatter, EntityTypeManagerInterface $entity_type_manager, PublishContentAccess $publish_content_access, AccountProxyInterface $user, CsrfTokenGenerator $csrf_token, ModuleHandlerInterface $module_handler, RedirectDestinationInterface $redirect_destination, SubpageManager $subpage_manager) {
    $this->dateFormatter = $date_formatter;
    $this->entityTypeManager = $entity_type_manager;
    $this->publishContentAccess = $publish_content_access;
    $this->currentUser = $user;
    $this->csrfToken = $csrf_token;
    $this->moduleHandler = $module_handler;
    $this->redirectDestination = $redirect_destination;
    $this->subpageManager = $subpage_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('date.formatter'),
      $container->get('entity_type.manager'),
      $container->get('publishcontent.access'),
      $container->get('current_user'),
      $container->get('csrf_token'),
      $container->get('module_handler'),
      $container->get('redirect.destination'),
      $container->get('ghi_subpages.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    // Have "views_form" in here will improve the styling of this form if the
    // GIN theme is used. See gin_form_alter().
    return 'ghi_subpages_admin_views_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, NodeInterface $node = NULL) {

    $form['#attached']['library'][] = 'ghi_subpages/admin.subpages_form';
    $form['#after_build'][] = [self::class, 'afterBuild'];

    /** @var \Drupal\ghi_sections\Entity\SectionNodeInterface $node */
    $node = $this->getBaseTypeNode($node);
    $form['#node'] = $node;

    $header = [
      $this->t('Page title'),
      $this->t('Status'),
      $this->t('Team'),
      $this->t('Created'),
      $this->t('Updated'),
      $this->t('Operations'),
    ];

    $rows = [];

    if (!$node->isPublished()) {
      $this->messenger()->addWarning($this->t('This @type is currently unpublished. The subpages listed on this page can only be published once the @type itself is published.', [
        '@type' => $this->entityTypeManager->getStorage('node_type')->load($node->getType())->get('name'),
      ]));
    }

    $form['description'] = [
      '#prefix' => '<p>',
      '#suffix' => '</p>',
      '#markup' => $this->t('On this page you can see all subpages that are directly linked to this @page_type.', [
        '@page_type' => $node instanceof Section ? $this->t('@type section', [
          '@type' => strtolower($node->getSectionType()),
        ]) : $this->t('section'),
      ]),
    ];

    $section_team = $node->field_team->entity->getName();

    // First create a table with the subpage types directly supported by this
    // module.
    foreach (SubpageManager::SUPPORTED_SUBPAGE_TYPES as $subpage_type) {
      $subpages = $this->entityTypeManager->getStorage('node')->loadByProperties([
        'type' => $subpage_type,
        'field_entity_reference' => $node->id(),
      ]);

      $row = [];
      $subpage_type_label = $this->entityTypeManager->getStorage('node_type')->load($subpage_type)->get('name');

      if (count($subpages) && count($subpages) == 1) {
        /** @var \Drupal\node\NodeInterface $subpage */
        $subpage = reset($subpages);

        /** @var \Drupal\taxonomy\Entity\Term $subpage_team */
        $subpage_team = !$subpage->field_team->isEmpty() ? $subpage->field_team->entity : NULL;

        $row[] = Link::createFromRoute($subpage_type_label, 'entity.node.canonical', ['node' => $subpage->id()]);
        $row[] = $subpage->isPublished() ? $this->t('Published') : $this->t('Unpublished');
        $row[] = $subpage_team ? $subpage_team->getName() : $section_team . ' (' . $this->t('Inherit from section') . ')';
        $row[] = $this->dateFormatter->format($subpage->getCreatedTime(), 'custom', 'F j, Y h:ia');
        $row[] = $this->dateFormatter->format($subpage->getChangedTime(), 'custom', 'F j, Y h:ia');
        $row[] = $this->getOperationLinks($subpage, $node);
        $rows[$subpage->id()] = $row;
      }
      elseif (empty($subpages)) {
        $row[] = $subpage_type_label;
        $row[] = $this->t('Missing');
        $row[] = '';
        $row[] = '';
        $row[] = '';
        $rows[] = $row;
      }
    }

    $form['subpages_header'] = [
      '#type' => 'html_tag',
      '#tag' => 'h2',
      '#value' => $this->t('Standard subpages'),
    ];
    $form['subpages_standard'] = [
      '#type' => 'tableselect',
      '#header' => $header,
      '#options' => $rows,
      '#empty' => $this->t('No subpages exist for this item.'),
    ];

    // Now show one table per additional subpage type.
    $node_types = $this->entityTypeManager->getStorage('node_type')->loadMultiple();
    foreach ($node_types as $node_type) {
      if ($this->subpageManager->isStandardSubpageType($node_type) || !$this->subpageManager->isSubpageType($node_type)) {
        continue;
      }

      $subpage_nodes = $this->subpageManager->getCustomSubpagesForBaseNode($node, $node_type) ?? [];

      // See if this subpage type has a base object associated.
      $bundle_class = $this->entityTypeManager->getStorage('node')->getEntityClass($node_type->id());
      $has_base_object = $bundle_class && is_subclass_of($bundle_class, BaseObjectAwareEntityInterface::class);
      $header = array_values(array_filter([
        $this->t('Page title'),
        $has_base_object ? $bundle_class::getBaseObjectType()->label() : NULL,
        $this->t('Status'),
        $this->t('Team'),
        $this->t('Created'),
        $this->t('Updated'),
        $this->t('Operations'),
      ]));
      $rows = [];
      foreach ($subpage_nodes as $subpage_node) {
        /** @var \Drupal\taxonomy\Entity\Term $subpage_team */
        $subpage_team = !$subpage_node->field_team->isEmpty() ? $subpage_node->field_team->entity : NULL;
        $parent_subpage = $subpage_node->getParentNode()?->bundle() == $node_type->id() ? $subpage_node->getParentNode() : NULL;
        $row_classes = [];
        $row = [];
        $row[] = $subpage_node->toLink();
        if ($has_base_object) {
          /** @var \Drupal\ghi_base_objects\Entity\BaseObjectAwareEntityInterface $subpage_node */
          $base_object = $subpage_node instanceof BaseObjectAwareEntityInterface ? $subpage_node->getBaseObject() : NULL;
          $row[] = $base_object ? $this->t('@label (<a href="@url">@id</a>)', [
            '@label' => $base_object->label(),
            '@url' => $base_object->toUrl('edit-form')->toString(),
            '@id' => $base_object->getSourceId(),
          ]) : ($parent_subpage ? FALSE : $this->t('Missing'));
          if (!$parent_subpage && !$base_object) {
            $row_classes[] = 'missing-base-object';
          }
          if (!$parent_subpage && $base_object && (string) $base_object->label() != (string) $subpage_node->label()) {
            $row_classes[] = 'title-mismatch';
          }
        }
        $row[] = $subpage_node->isPublished() ? (string) $this->t('Published') : (string) $this->t('Unpublished');
        $row[] = $subpage_team ? $subpage_team->getName() : $section_team . ' (' . $this->t('Inherit from section') . ')';
        $row[] = $this->dateFormatter->format($subpage_node->getCreatedTime(), 'custom', 'F j, Y h:ia');
        $row[] = $this->dateFormatter->format($subpage_node->getChangedTime(), 'custom', 'F j, Y h:ia');
        $row[] = $this->getOperationLinks($subpage_node, $node);
        $row['#attributes'] = [
          'class' => $row_classes,
        ];
        if ($parent_subpage && array_key_exists($parent_subpage->id(), $rows)) {
          $pos = array_search($parent_subpage->id(), array_keys($rows)) + 1;
          $row['#disabled'] = TRUE;
          $row['#attributes'] = ['class' => ['dependent-subpage-row']];
          $rows = array_slice($rows, 0, $pos, TRUE) + [$subpage_node->id() => $row] + array_slice($rows, $pos, NULL, TRUE);
        }
        else {
          $rows[$subpage_node->id()] = $row;
        }
      }

      $header_links = $this->getHeaderLinks($node_type, $node);
      $form['subpages_' . $node_type->id() . '_header'] = [
        '#type' => 'container',
        '#attributes' => [
          'class' => ['subpages-form-header-wrapper'],
        ],
        'label' => [
          '#type' => 'html_tag',
          '#tag' => 'h2',
          '#value' => $this->t('@label subpages', [
            '@label' => $node_type->label(),
          ]),
        ],
      ] + $header_links;

      $form['subpages_' . $node_type->id()] = [
        '#type' => 'tableselect',
        '#header' => $header,
        '#options' => $rows,
        '#attributes' => [
          'class' => [Html::getClass('subpages_' . $node_type->id() . '_bulk_form')],
        ],
        '#wrapper_attributes' => [
          'class' => [Html::getClass('subpages_' . $node_type->id() . '_bulk_form')],
        ],
        '#empty' => Markup::create(implode(' ', array_filter([
          $this->t('There is no <em>@type</em> content yet.', [
            '@type' => strtolower($node_type->label()),
          ]),
          $this->addLink($node_type, $node)?->toString() ?? NULL,
        ]))),
      ];
    }

    $bulk_form_actions = $this->getBulkFormActions();
    if (!empty($bulk_form_actions)) {
      $this->buildBulkForm($form);
    }

    return $form;
  }

  /**
   * After build callback for the form.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state object.
   *
   * @return array
   *   The form array.
   */
  public static function afterBuild(array $form, FormStateInterface $form_state) {
    foreach (Element::children($form) as $element_key) {
      $element = &$form[$element_key];
      if (empty($element['#type']) || $element['#type'] != 'tableselect') {
        continue;
      }

      $element['#pre_render'][] = [self::class, 'tableselectPreRender'];
    }
    return $form;
  }

  /**
   * Pre render callback for tableselect form elements used in the form.
   *
   * Add a class to the checkbox column of each row, so that the logic in
   * core/themes/claro/js/tableselect.js can find the checkboxes.
   *
   * @param array $element
   *   The element array.
   *
   * @return array
   *   The element array.
   */
  public static function tableselectPreRender($element) {
    foreach ($element['#rows'] as &$row) {
      $row['data'][0] = [
        'data' => $row['data'][0],
        'class' => 'subpages-bulk-form',
      ];
    }
    return $element;
  }

  /**
   * Build the bulk form.
   *
   * Do this in a way that plays nice with Gin.
   *
   * @param array $form
   *   The form array.
   */
  private function buildBulkForm(array &$form) {
    // Build the bulk form. This is mainly done in a way to be compatible with
    // the gin theme, see gin_form_alter() and gin/styles/base/_views.scss.
    $form['#prefix'] = Markup::create('<div class="view-content"><div class="views-form">');
    $form['#suffix'] = Markup::create('</div></div>');
    $form['header'] = [
      '#type' => 'container',
      '#id' => 'edit-header',
      'subpages_bulk_form' => [
        '#type' => 'container',
        '#id' => 'edit-node-bulk-form',
        'action' => [
          '#type' => 'select',
          '#title' => $this->t('Action'),
          '#options' => $this->getBulkFormActions(),
        ],
        'actions' => [
          '#type' => 'actions',
          'submit' => [
            '#type' => 'submit',
            '#name' => 'bulk_submit',
            '#value' => $this->t('Apply to selected items'),
          ],
        ],
      ],
    ];

    self::imitateViewBulkForm($form, 'subpages_bulk_form', $this->t('Section subpages'));
  }

  /**
   * Pretend to be a views bulk form.
   *
   * This has been copied 1:1 from claro_form_alter, which applies this logic
   * only to views forms unfortunately.
   * Because a strict copy it's been added here as a separate function.
   *
   * @param array $form
   *   The form array.
   * @param string $key
   *   The string key of the bulk actions form.
   * @param string|\Drupal\Component\Render\MarkupInterface $view_title
   *   The pretended title of the view.
   */
  private static function imitateViewBulkForm(array &$form, $key, $view_title) {
    // Move the bulk actions form from the header to its own container.
    $form['bulk_actions_container'] = $form['header'][$key];
    unset($form['header'][$key]);

    // Remove the supplementary bulk operations submit button as it appears
    // in the same location the form was moved to.
    unset($form['actions']);

    $form['bulk_actions_container']['#attributes']['data-drupal-views-bulk-actions'] = '';
    $form['bulk_actions_container']['#attributes']['class'][] = 'views-bulk-actions';
    $form['bulk_actions_container']['actions']['submit']['#button_type'] = 'primary';
    $form['bulk_actions_container']['actions']['submit']['#attributes']['class'][] = 'button--small';
    $label = t('Perform actions on the selected items in the %view_title view', ['%view_title' => $view_title]);
    $label_id = $key . '_group_label';

    // Group the bulk actions select and submit elements, and add a label
    // that makes the purpose of these elements more clear to
    // screen readers.
    $form['bulk_actions_container']['#attributes']['role'] = 'group';
    $form['bulk_actions_container']['#attributes']['aria-labelledby'] = $label_id;
    $form['bulk_actions_container']['group_label'] = [
      '#type' => 'container',
      '#markup' => $label,
      '#attributes' => [
        'id' => $label_id,
        'class' => ['visually-hidden'],
      ],
      '#weight' => -1,
    ];

    // Add a status label for counting the number of items selected.
    $form['bulk_actions_container']['status'] = [
      '#type' => 'container',
      '#markup' => t('No items selected'),
      '#weight' => -1,
      '#attributes' => [
        'class' => [
          'js-views-bulk-actions-status',
          'views-bulk-actions__item',
          'views-bulk-actions__item--status',
          'js-show',
        ],
        'data-drupal-views-bulk-actions-status' => '',
      ],
    ];

    // Loop through bulk actions items and add the needed CSS classes.
    $bulk_action_item_keys = Element::children($form['bulk_actions_container'], TRUE);
    $bulk_last_key = NULL;
    $bulk_child_before_actions_key = NULL;
    foreach ($bulk_action_item_keys as $bulk_action_item_key) {
      if (!empty($form['bulk_actions_container'][$bulk_action_item_key]['#type'])) {
        if ($form['bulk_actions_container'][$bulk_action_item_key]['#type'] === 'actions') {
          // We need the key of the element that precedes the actions
          // element.
          $bulk_child_before_actions_key = $bulk_last_key;
          $form['bulk_actions_container'][$bulk_action_item_key]['#attributes']['class'][] = 'views-bulk-actions__item';
        }

        if (!in_array($form['bulk_actions_container'][$bulk_action_item_key]['#type'], ['hidden', 'actions'])) {
          $form['bulk_actions_container'][$bulk_action_item_key]['#wrapper_attributes']['class'][] = 'views-bulk-actions__item';
          $bulk_last_key = $bulk_action_item_key;
        }
      }
    }

    if ($bulk_child_before_actions_key) {
      $form['bulk_actions_container'][$bulk_child_before_actions_key]['#wrapper_attributes']['class'][] = 'views-bulk-actions__item--preceding-actions';
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    if ($form_state->getTriggeringElement()['#name'] != 'bulk_submit') {
      return;
    }
    $action = $form_state->getValue('action');
    if (!array_key_exists($action, $this->getBulkFormActions())) {
      return;
    }
    $values = $form_state->getValues();
    $node_ids = [];
    foreach ($values as $key => $subpages_values) {
      if (strpos($key, 'subpages_') !== 0) {
        continue;
      }
      $node_ids = $node_ids + array_filter($subpages_values);
    }
    if (empty($node_ids)) {
      return;
    }
    /** @var \Drupal\node\NodeInterface[] $nodes */
    $nodes = $this->entityTypeManager->getStorage('node')->loadMultiple(array_keys($node_ids));
    foreach ($nodes as $node) {
      if ($action == 'publish') {
        $node->setPublished();
      }
      if ($action == 'unpublish') {
        $node->setUnpublished();
      }
      $node->save();
    }

    // Stay on the subpages form page.
    $form_state->setIgnoreDestination();
  }

  /**
   * Get the add node link for the given node type.
   *
   * @param \Drupal\node\NodeTypeInterface $node_type
   *   The node type in question.
   * @param \Drupal\ghi_sections\Entity\SectionNodeInterface $section_node
   *   The section node being the parent of the subpages.
   *
   * @return \Drupal\Core\Link|null
   *   A link object or NULL.
   */
  protected function addLink(NodeTypeInterface $node_type, SectionNodeInterface $section_node) {
    $add_url = Url::fromRoute('node.add', [
      'node_type' => $node_type->id(),
    ], [
      'query' => ['section' => $section_node->id()],
    ]);
    $add_label = $this->t('Create a @type', [
      '@type' => strtolower($node_type->label()),
    ]);
    return $add_url->access() ? Link::fromTextAndUrl($add_label, $add_url) : NULL;
  }

  /**
   * Get the header links for the given node type.
   *
   * @param \Drupal\node\NodeTypeInterface $node_type
   *   The node type for which to get the header links.
   * @param \Drupal\ghi_sections\Entity\SectionNodeInterface $section_node
   *   The section node being the parent of the subpages.
   *
   * @return array
   *   A render array for links.
   */
  protected function getHeaderLinks(NodeTypeInterface $node_type, SectionNodeInterface $section_node) {
    $header_links = [];
    if ($add_link = $this->addLink($node_type, $section_node)) {
      $header_links['add_link'] = [
        '#type' => 'html_tag',
        '#tag' => 'a',
        'markup' => [
          '#markup' => $add_link->getText(),
        ],
        '#attributes' => [
          'href' => $add_link->getUrl()->toString(),
          'class' => [
            'button',
            'button--primary',
            'button--action',
          ],
        ],
      ];
    }
    foreach ($this->moduleHandler->invokeAll('subpage_admin_form_header_links', [$node_type, $section_node]) as $key => $link) {
      /** @var \Drupal\Core\Link $link */
      $header_links[$key] = [
        '#type' => 'html_tag',
        '#tag' => 'a',
        'markup' => [
          '#markup' => $link->getText(),
        ],
        '#attributes' => NestedArray::mergeDeep([
          'href' => $link->getUrl()->toString(),
          'class' => [
            'button',
            'button--primary',
            'button--action',
          ],
        ], $link->getUrl()->getOption('attributes')),
      ];
    }
    return $header_links;
  }

  /**
   * Get the operations links for the given subpage.
   *
   * @param \Drupal\node\NodeInterface $subpage
   *   The subpage node.
   * @param \Drupal\node\NodeInterface $section
   *   The section node.
   *
   * @return array
   *   A render array with the operations links dropbutton.
   */
  protected function getOperationLinks(NodeInterface $subpage, NodeInterface $section) {
    $links = [];

    // The token for the publishing links need to be generated manually here.
    $token = $this->csrfToken->get('node/' . $subpage->id() . '/toggleStatus');

    $destination = $this->redirectDestination->getAsArray();

    if ($subpage->access('view')) {
      $links['view'] = [
        'title' => $this->t('View'),
        'url' => $subpage->toUrl(),
      ];
    }
    if ($subpage->access('update')) {
      $links['edit'] = [
        'title' => $this->t('Edit'),
        'url' => $subpage->toUrl('edit-form'),
        'query' => $destination,
      ];
      if ($this->moduleHandler->moduleExists('layout_builder_operation_link')) {
        $links += layout_builder_operation_link_entity_operation($subpage);
      }
    }

    if ($this->publishContentAccess->access($this->currentUser, $subpage)->isAllowed() && $section->isPublished()) {
      $route_args = ['node' => $subpage->id()];
      $options = [
        'query' => [
          'token' => $token,
        ] + $destination,
      ];
      $links['toggle_status'] = [
        'title' => $subpage->isPublished() ? $this->t('Unpublish') : $this->t('Publish'),
        'url' => Url::fromRoute('entity.node.publish', $route_args, $options),
      ];
    }

    $this->moduleHandler->alter('entity_operation', $links, $subpage);

    return [
      'data' => [
        '#type' => 'dropbutton',
        '#links' => $links,
        '#attributes' => [
          'class' => [
            'dropbutton--extrasmall',
          ],
        ],
      ],
    ];
  }

  /**
   * Get the bulk form actions.
   *
   * @return array
   *   An array of action key - label pairs.
   */
  private function getBulkFormActions() {
    return [
      'publish' => $this->t('Publish'),
      'unpublish' => $this->t('Unpublish'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public static function trustedCallbacks() {
    return [
      'tableselectPreRender',
    ];
  }

}
