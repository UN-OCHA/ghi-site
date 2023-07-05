<?php

namespace Drupal\ghi_subpages\Form;

use Drupal\Core\Access\CsrfTokenGenerator;
use Drupal\Core\Datetime\DateFormatter;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\Render\Markup;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Url;
use Drupal\ghi_sections\Entity\Section;
use Drupal\ghi_subpages\SubpageManager;
use Drupal\ghi_subpages\SubpageTrait;
use Drupal\ghi_templates\TemplateLinkBuilder;
use Drupal\layout_builder\LayoutEntityHelperTrait;
use Drupal\node\NodeInterface;
use Drupal\publishcontent\Access\PublishContentAccess;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a form for managing subpages of a base entity.
 */
class SubpagesPagesForm extends FormBase {

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
   * The section manager.
   *
   * @var \Drupal\ghi_subpages\SubpageManager
   */
  protected $subpageManager;

  /**
   * The link builder for templates.
   *
   * @var \Drupal\ghi_templates\TemplateLinkBuilder
   */
  protected $templateLinkBuilder;

  /**
   * Constructs a SubpagesPages form.
   */
  public function __construct(DateFormatter $date_formatter, EntityTypeManagerInterface $entity_type_manager, PublishContentAccess $publish_content_access, AccountProxyInterface $user, CsrfTokenGenerator $csrf_token, ModuleHandlerInterface $module_handler, SubpageManager $subpage_manager, TemplateLinkBuilder $template_link_builder) {
    $this->dateFormatter = $date_formatter;
    $this->entityTypeManager = $entity_type_manager;
    $this->publishContentAccess = $publish_content_access;
    $this->currentUser = $user;
    $this->csrfToken = $csrf_token;
    $this->moduleHandler = $module_handler;
    $this->subpageManager = $subpage_manager;
    $this->templateLinkBuilder = $template_link_builder;
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
      $container->get('ghi_subpages.manager'),
      $container->get('ghi_templates.link_builder')
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

    $header = [
      $this->t('Page'),
      $this->t('Status'),
      $this->t('Team'),
      $this->t('Created'),
      $this->t('Updated'),
      $this->t('Operations'),
    ];

    $rows = [];

    /** @var \Drupal\ghi_sections\Entity\SectionNodeInterface $node */
    $node = $this->getBaseTypeNode($node);

    if (!$node->isPublished()) {
      $this->messenger()->addWarning($this->t('This @type is currently unpublished. The subpages listed on this page can only be published once the @type itself is published.', [
        '@type' => $this->entityTypeManager->getStorage('node_type')->load($node->getType())->get('name'),
      ]));
    }

    $overview_links = [
      Link::createFromRoute($this->t('Article pages'), 'ghi_content.node.articles', ['node' => $node->id()])->toString(),
      Link::createFromRoute($this->t('Document pages'), 'ghi_content.node.documents', ['node' => $node->id()])->toString(),
    ];

    $form['description'] = [
      '#markup' => '<p>' . $this->t('On this page you can see all subpages that are directly linked to this @page_type. Additional content that is linked indirectly via tags can be found on these pages: @overview_links', [
        '@page_type' => $node instanceof Section ? $this->t('@type section', [
          '@type' => strtolower($node->getSectionType()),
        ]) : $this->t('section'),
        '@overview_links' => Markup::create(implode(', ', $overview_links)),
      ]) . '</p>',
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

      /** @var \Drupal\node\NodeInterface[] $subpage_nodes */
      $subpage_nodes = $this->subpageManager->getCustomSubpagesForBaseNode($node, $node_type);

      $header = [
        $this->t('Page'),
        $this->t('Status'),
        $this->t('Team'),
        $this->t('Created'),
        $this->t('Updated'),
        $this->t('Operations'),
      ];
      $rows = [];
      if (!empty($subpage_nodes)) {
        foreach ($subpage_nodes as $subpage_node) {
          /** @var \Drupal\taxonomy\Entity\Term $subpage_team */
          $subpage_team = !$subpage_node->field_team->isEmpty() ? $subpage_node->field_team->entity : NULL;

          $row = [];
          $row[] = $subpage_node->toLink();
          $row[] = $subpage_node->isPublished() ? $this->t('Published') : $this->t('Unpublished');
          $row[] = $subpage_team ? $subpage_team->getName() : $section_team . ' (' . $this->t('Inherit from section') . ')';
          $row[] = $this->dateFormatter->format($subpage_node->getCreatedTime(), 'custom', 'F j, Y h:ia');
          $row[] = $this->dateFormatter->format($subpage_node->getChangedTime(), 'custom', 'F j, Y h:ia');
          $row[] = $this->getOperationLinks($subpage_node, $node);
          $rows[$subpage_node->id()] = $row;
        }
      }
      $add_url = Url::fromRoute('node.add', [
        'node_type' => $node_type->id(),
      ], [
        'query' => ['section' => $node->id()],
      ]);
      $add_label = $this->t('Create a @type', [
        '@type' => strtolower($node_type->label()),
      ]);
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
        'add_link' => $add_url->access() ? [
          '#type' => 'html_tag',
          '#tag' => 'a',
          'markup' => [
            '#markup' => $add_label,
          ],
          '#attributes' => [
            'href' => $add_url->toString(),
            'class' => [
              'button',
              'button--primary',
              'button--action',
            ],
          ],
        ] : NULL,
      ];
      $form['subpages_' . $node_type->id()] = [
        '#type' => 'tableselect',
        '#header' => $header,
        '#options' => $rows,
        '#empty' => Markup::create(implode(' ', array_filter([
          $this->t('There is no <em>@type</em> content yet.', [
            '@type' => strtolower($node_type->label()),
          ]),
          $add_url->access() ? Link::fromTextAndUrl($add_label, $add_url)->toString() : NULL,
        ]))),
      ];

    }

    $bulk_form_actions = $this->getBulkFormActions();
    if (!empty($bulk_form_actions)) {
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
    }

    return $form;
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
      ];
      if ($this->moduleHandler->moduleExists('layout_builder_operation_link')) {
        $links += layout_builder_operation_link_entity_operation($subpage);
      }
    }

    // Add export/import links.
    $section_storage = $this->getSectionStorageForEntity($subpage);
    $export_link = $this->templateLinkBuilder->buildExportLink($section_storage, $subpage);
    if ($export_link) {
      $links['export'] = $export_link;
    }
    $import_link = $this->templateLinkBuilder->buildImportLink($section_storage, $subpage, ['query' => ['redirect_to_entity' => TRUE]]);
    if ($import_link) {
      $links['import'] = $import_link;
    }

    if ($this->publishContentAccess->access($this->currentUser, $subpage)->isAllowed() && $section->isPublished()) {
      $route_args = ['node' => $subpage->id()];
      $options = [
        'query' => [
          'token' => $token,
        ],
      ];
      $links['toggle_status'] = [
        'title' => $subpage->isPublished() ? $this->t('Unpublish') : $this->t('Publish'),
        'url' => Url::fromRoute('entity.node.publish', $route_args, $options),
      ];
    }

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

}
