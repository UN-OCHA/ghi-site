<?php

namespace Drupal\ghi_subpages\Form;

use Drupal\Component\Serialization\Json;
use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Access\CsrfTokenGenerator;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Datetime\DateFormatter;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\Render\Markup;
use Drupal\Core\Routing\RedirectDestinationInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Url;
use Drupal\ghi_base_objects\Entity\BaseObjectAwareEntityInterface;
use Drupal\ghi_sections\Entity\Section;
use Drupal\ghi_sections\Entity\SectionNodeInterface;
use Drupal\ghi_subpages\SubpageManager;
use Drupal\ghi_subpages\SubpageTrait;
use Drupal\hpc_api\Traits\BulkFormTrait;
use Drupal\layout_builder\LayoutEntityHelperTrait;
use Drupal\node\NodeInterface;
use Drupal\node\NodeTypeInterface;
use Drupal\publishcontent\Access\PublishContentAccess;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a form for managing subpages of a base entity.
 */
class SubpagesPagesForm extends FormBase {

  use SubpageTrait;
  use LayoutEntityHelperTrait;
  use BulkFormTrait;

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
  public function buildForm(array $form, FormStateInterface $form_state, ?NodeInterface $node = NULL) {

    $form['#attached']['library'][] = 'ghi_subpages/admin.subpages_form';
    $form['#attached']['library'][] = 'core/drupal.dialog.ajax';

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
    // Bulk creation selects missing bundles, while deletion selects node IDs;
    // distinct prefixes prevent either action from interpreting the wrong row.
    foreach (SubpageManager::SUPPORTED_SUBPAGE_TYPES as $subpage_type) {
      $subpage_type_label = (string) $this->entityTypeManager->getStorage('node_type')->load($subpage_type)->get('name');
      $subpage = $this->subpageManager->loadStandardSubpage($node, $subpage_type);
      if ($subpage) {
        $rows['node:' . $subpage->id()] = $this->buildExistingStandardSubpageRow($subpage, $node, $subpage_type_label, $section_team);
        continue;
      }
      $rows['bundle:' . $subpage_type] = $this->buildMissingStandardSubpageRow($node, $subpage_type, $subpage_type_label);
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
          $row[] = $base_object ? $this->t('@label (<a href=":url">@id</a>)', [
            '@label' => $base_object->label(),
            ':url' => $base_object->toUrl('edit-form')->toString(),
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
      $node_label = $node_type->label();
      $form['subpages_' . $node_type->id() . '_header'] = [
        '#type' => 'container',
        '#attributes' => [
          'class' => ['subpages-form-header-wrapper'],
        ],
        'label' => [
          '#type' => 'html_tag',
          '#tag' => 'h2',
          '#value' => $this->t('@label subpages', [
            '@label' => str_ends_with($node_label, ' subpage') ? rtrim($node_label, ' subpage') : $node_label,
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

    $this->buildBulkForm($form, $form_state, $this->getBulkFormActions());

    return $form;
  }

  /**
   * Builds the table row for an existing standard subpage.
   */
  private function buildExistingStandardSubpageRow(NodeInterface $subpage, NodeInterface $section, string $label, string $section_team): array {
    /** @var \Drupal\taxonomy\Entity\Term|null $subpage_team */
    $subpage_team = !$subpage->field_team->isEmpty() ? $subpage->field_team->entity : NULL;

    return [
      Link::createFromRoute($label, 'entity.node.canonical', ['node' => $subpage->id()]),
      $subpage->isPublished() ? $this->t('Published') : $this->t('Unpublished'),
      $subpage_team ? $subpage_team->getName() : $section_team . ' (' . $this->t('Inherit from section') . ')',
      $this->dateFormatter->format($subpage->getCreatedTime(), 'custom', 'F j, Y h:ia'),
      $this->dateFormatter->format($subpage->getChangedTime(), 'custom', 'F j, Y h:ia'),
      $this->getOperationLinks($subpage, $section),
    ];
  }

  /**
   * Builds the table row for a standard subpage that has not been created.
   */
  private function buildMissingStandardSubpageRow(SectionNodeInterface $section, string $bundle, string $label): array {
    $can_create = $this->subpageManager->getStandardSubpageCreateAccess($section, $bundle, $this->currentUser)->isAllowed();
    $row = [
      $label,
      $this->t('Not yet created'),
      '',
      '',
      '',
      $can_create ? [
        'data' => [
          '#type' => 'dropbutton',
          '#links' => [
            'create' => [
              'title' => $this->t('Create'),
              'url' => Url::fromRoute('ghi_subpages.node.pages.create', [
                'node' => $section->id(),
                'bundle' => $bundle,
              ], $this->getConfirmModalLinkOptions()),
            ],
          ],
          '#attributes' => [
            'class' => ['dropbutton--extrasmall'],
          ],
        ],
      ] : '',
    ];
    if (!$can_create) {
      $row['#disabled'] = TRUE;
    }
    return $row;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $trigger = $form_state->getTriggeringElement()['#name'] ?? '';
    if ($trigger !== 'bulk_submit') {
      return;
    }
    $action = $form_state->getValue('action');
    if (!in_array($action, ['create', 'delete'], TRUE)) {
      return;
    }
    $section = $form['#node'];
    if (!$this->subpageManager->getStructureAccess($section, $this->currentUser)->isAllowed()) {
      $form_state->setErrorByName('subpages_standard', $this->t('You cannot change the page structure.'));
      return;
    }
    $selected = $this->getSelectedRows($form_state);
    if (!$selected) {
      $form_state->setErrorByName('subpages_standard', $this->t('Select at least one page.'));
      return;
    }
    // Submitted row IDs may be stale or tampered with, so verify every choice
    // against this section before creating or deleting its pages.
    foreach ($selected as $item) {
      if ($action === 'create') {
        $bundle = str_starts_with($item, 'bundle:') ? substr($item, 7) : NULL;
        if (!$bundle || !$this->subpageManager->getStandardSubpageCreateAccess($section, $bundle, $this->currentUser)->isAllowed() || $this->subpageManager->loadStandardSubpage($section, $bundle)) {
          $form_state->setErrorByName('subpages_standard', $this->t('A selected page cannot be created.'));
          return;
        }
      }
      else {
        $id = str_starts_with($item, 'node:') ? substr($item, 5) : NULL;
        $child = $id && ctype_digit($id) ? $this->entityTypeManager->getStorage('node')->load((int) $id) : NULL;
        if (!$child || !$this->subpageManager->getStandardSubpageDeleteAccess($child, $section, $this->currentUser)->isAllowed()) {
          $form_state->setErrorByName('subpages_standard', $this->t('A selected page cannot be deleted.'));
          return;
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $trigger = $form_state->getTriggeringElement()['#name'] ?? '';
    if ($trigger !== 'bulk_submit') {
      return;
    }
    $action = $form_state->getValue('action');
    if (!array_key_exists($action, $this->getBulkFormActions())) {
      return;
    }
    $selected = $this->getSelectedRows($form_state);
    if (!$selected) {
      return;
    }
    // Drupal may inherit a content-admin destination, which would override
    // our redirects to this listing or its delete confirmation.
    $section = $form['#node'];
    if ($action === 'create') {
      $bundles = array_map(static fn (string $item): string => substr($item, 7), $selected);
      $results = $this->subpageManager->createStandardSubpages($section, $bundles);
      foreach (array_keys($results['created']) as $bundle) {
        $this->messenger()->addStatus($this->t('Created @type subpage.', ['@type' => $bundle]));
      }
      foreach (array_keys($results['failed']) as $bundle) {
        $this->messenger()->addError($this->t('Could not create @type subpage.', ['@type' => $bundle]));
      }
      Cache::invalidateTags($section->getCacheTags());
      $form_state->setIgnoreDestination();
      $form_state->setRedirect('ghi_subpages.node.pages', ['node' => $section->id()]);
      return;
    }
    if ($action === 'delete') {
      $ids = array_map(static fn (string $item): string => substr($item, 5), $selected);
      $form_state->setIgnoreDestination();
      $form_state->setRedirect('ghi_subpages.node.pages.delete', [
        'node' => $section->id(),
        'subpages' => implode(',', $ids),
      ]);
      return;
    }
    $node_ids = array_map(static fn (string $item): int => (int) (str_starts_with($item, 'node:') ? substr($item, 5) : $item), $selected);
    $nodes = $this->entityTypeManager->getStorage('node')->loadMultiple($node_ids);
    foreach ($nodes as $node) {
      $action === 'publish' ? $node->setPublished() : $node->setUnpublished();
      $node->save();
    }
    Cache::invalidateTags($section->getCacheTags());
    $form_state->setIgnoreDestination();
  }

  /**
   * Returns selected row identifiers from all subpage tables.
   */
  protected function getSelectedRows(FormStateInterface $form_state): array {
    $selected = [];
    foreach ($form_state->getValues() as $key => $values) {
      if (str_starts_with($key, 'subpages_') && is_array($values)) {
        $selected = array_merge($selected, array_values(array_filter($values)));
      }
    }
    return array_map('strval', $selected);
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
    $pages_destination = Url::fromRoute('ghi_subpages.node.pages', ['node' => $section->id()])->toString();

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
          'destination' => $pages_destination,
        ],
      ];
      $links['toggle_status'] = [
        'title' => $subpage->isPublished() ? $this->t('Unpublish') : $this->t('Publish'),
        'url' => Url::fromRoute('entity.node.publish', $route_args, $options),
      ];
    }

    if ($section instanceof SectionNodeInterface && $this->subpageManager->getStandardSubpageDeleteAccess($subpage, $section, $this->currentUser)->isAllowed()) {
      $links['delete_structure'] = [
        'title' => $this->t('Delete'),
        'url' => Url::fromRoute('ghi_subpages.node.pages.delete', [
          'node' => $section->id(),
          'subpages' => $subpage->id(),
        ], $this->getConfirmModalLinkOptions()),
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
   * Opens structural confirmation forms as accessible Drupal modals.
   */
  private function getConfirmModalLinkOptions(): array {
    return [
      'attributes' => [
        'class' => ['use-ajax'],
        'data-dialog-type' => 'modal',
        'data-dialog-options' => Json::encode(['width' => 500]),
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
    $actions = [
      'publish' => $this->t('Publish'),
      'unpublish' => $this->t('Unpublish'),
    ];
    if ($this->currentUser->hasPermission(SubpageManager::MANAGE_STRUCTURE_PERMISSION)) {
      $actions['create'] = $this->t('Create selected');
      $actions['delete'] = $this->t('Delete selected');
    }
    return $actions;
  }

}
