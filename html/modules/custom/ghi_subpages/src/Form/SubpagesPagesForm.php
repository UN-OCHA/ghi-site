<?php

namespace Drupal\ghi_subpages\Form;

use Drupal\Core\Access\CsrfTokenGenerator;
use Drupal\Core\Datetime\DateFormatter;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Url;
use Drupal\ghi_subpages\Helpers\SubpageHelper;
use Drupal\ghi_subpages\SubpageTrait;
use Drupal\node\NodeInterface;
use Drupal\publishcontent\Access\PublishContentAccess;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a form for managing subpages of a base entity.
 */
class SubpagesPagesForm extends FormBase {

  use SubpageTrait;

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
   * Constructs a SubpagesPages form.
   */
  public function __construct(DateFormatter $date_formatter, EntityTypeManagerInterface $entity_type_manager, PublishContentAccess $publish_content_access, AccountProxyInterface $user, CsrfTokenGenerator $csrf_token, ModuleHandlerInterface $module_handler) {
    $this->dateFormatter = $date_formatter;
    $this->entityTypeManager = $entity_type_manager;
    $this->publishContentAccess = $publish_content_access;
    $this->currentUser = $user;
    $this->csrfToken = $csrf_token;
    $this->moduleHandler = $module_handler;
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
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'ghi_subpages_admin_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, NodeInterface $node = NULL) {

    $header = [
      $this->t('Page'),
      $this->t('Status'),
      $this->t('Team'),
      $this->t('Created'),
      $this->t('Updated'),
      $this->t('Operations'),
    ];

    $rows = [];

    $node = $this->getBaseTypeNode($node);

    if (!$node->isPublished()) {
      $this->messenger()->addWarning($this->t('This @type is currently unpublished. The subpages listed on this page can only be published once the @type itself is published.', [
        '@type' => $this->entityTypeManager->getStorage('node_type')->load($node->getType())->get('name'),
      ]));
    }

    $section_team = $node->field_team->entity->getName();

    foreach (SubpageHelper::SUPPORTED_SUBPAGE_TYPES as $subpage_type) {
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

      }
      elseif (empty($subpages)) {
        $row[] = $subpage_type_label;
        $row[] = $this->t('Missing');
        $row[] = '';
        $row[] = '';
        $row[] = '';
      }

      $rows[] = $row;
    }

    $form['subpages_header'] = [
      '#type' => 'html_tag',
      '#tag' => 'h2',
      '#value' => $this->t('Standard subpages'),
    ];
    $form['subpages'] = [
      '#type' => 'table',
      '#header' => $header,
      '#rows' => $rows,
      '#empty' => $this->t('No subpages exist for this item.'),
    ];

    // Now show another table with other subpages.
    $node_types = $this->entityTypeManager->getStorage('node_type')->loadMultiple();
    $subpage_types = [];
    foreach ($node_types as $node_type) {
      if (!SubpageHelper::isSubpageType($node_type) || in_array($node_type->id(), SubpageHelper::SUPPORTED_SUBPAGE_TYPES)) {
        continue;
      }
      $subpage_types[] = $node_type->id();
    }
    /** @var \Drupal\node\NodeInterface[] $subpage_nodes */
    $subpage_nodes = $subpage_types ? $this->entityTypeManager->getStorage('node')->loadByProperties([
      'type' => $subpage_types,
      'field_entity_reference' => $node->id(),
    ]) : NULL;
    if ($subpage_nodes) {
      $header = [
        $this->t('Page'),
        $this->t('Status'),
        $this->t('Type'),
        $this->t('Created'),
        $this->t('Updated'),
        $this->t('Operations'),
      ];
      $rows = [];
      foreach ($subpage_nodes as $subpage_node) {
        $row = [];
        $row[] = $subpage_node->toLink();
        $row[] = $subpage_node->isPublished() ? $this->t('Published') : $this->t('Unpublished');
        $row[] = $subpage_node->type->entity->label();
        $row[] = $this->dateFormatter->format($subpage_node->getCreatedTime(), 'custom', 'F j, Y h:ia');
        $row[] = $this->dateFormatter->format($subpage_node->getChangedTime(), 'custom', 'F j, Y h:ia');
        $row[] = $this->getOperationLinks($subpage_node, $node);
        $rows[] = $row;
      }
      $form['subpages_other_header'] = [
        '#type' => 'html_tag',
        '#tag' => 'h2',
        '#value' => $this->t('Other subpages'),
      ];
      $form['subpages_other'] = [
        '#type' => 'table',
        '#header' => $header,
        '#rows' => $rows,
        '#empty' => $this->t('No subpages exist for this item.'),
      ];
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
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

}
