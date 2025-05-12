<?php

namespace Drupal\ghi_content\ContentManager;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Routing\RedirectDestinationInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\ghi_content\Entity\ContentBase;
use Drupal\ghi_content\Entity\ContentReviewInterface;
use Drupal\ghi_content\Import\ImportManager;
use Drupal\ghi_content\RemoteContent\RemoteContentInterface;
use Drupal\ghi_content\RemoteSource\RemoteSourceManager;
use Drupal\ghi_sections\Entity\SectionNodeInterface;
use Drupal\ghi_sections\Entity\Tag;
use Drupal\ghi_sections\SectionManager;
use Drupal\ghi_sections\SectionTrait;
use Drupal\hpc_common\Helpers\ArrayHelper;
use Drupal\migrate\MigrateExecutable;
use Drupal\migrate\Plugin\MigrateIdMapInterface;
use Drupal\migrate\Plugin\MigrationPluginManager;
use Drupal\migrate\Row;
use Drupal\node\NodeInterface;
use Drupal\taxonomy\TermInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Base manager service class..
 */
abstract class BaseContentManager implements ContainerInjectionInterface {

  use SectionTrait;
  use StringTranslationTrait;
  use DependencySerializationTrait;

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected $renderer;

  /**
   * The Drupal account to use for checking for access to block.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * The current request.
   *
   * @var \Symfony\Component\HttpFoundation\Request
   */
  protected $request;

  /**
   * The route match.
   *
   * @var \Drupal\Core\Routing\RouteMatchInterface
   */
  protected $routeMatch;

  /**
   * The migration plugin manager.
   *
   * @var \Drupal\migrate\Plugin\MigrationPluginManager
   */
  protected $migrationManager;

  /**
   * The remote source manager.
   *
   * @var \Drupal\ghi_content\RemoteSource\RemoteSourceManager
   */
  protected $remoteSourceManager;

  /**
   * The remote source manager.
   *
   * @var \Drupal\ghi_content\Import\ImportManager
   */
  protected $importManager;

  /**
   * The module handler service.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * The redirect destination.
   *
   * @var \Drupal\Core\Routing\RedirectDestinationInterface
   */
  protected $redirectDestination;

  /**
   * Messenger service.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * Constructs a document manager.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, RendererInterface $renderer, AccountInterface $current_user, RequestStack $request_stack, RouteMatchInterface $route_match, MigrationPluginManager $migration_manager, RemoteSourceManager $remote_source_manager, ImportManager $import_manager, ModuleHandlerInterface $module_handler, RedirectDestinationInterface $redirect_destination, MessengerInterface $messenger) {
    $this->entityTypeManager = $entity_type_manager;
    $this->renderer = $renderer;
    $this->currentUser = $current_user;
    $this->request = $request_stack->getCurrentRequest();
    $this->routeMatch = $route_match;
    $this->migrationManager = $migration_manager;
    $this->remoteSourceManager = $remote_source_manager;
    $this->importManager = $import_manager;
    $this->moduleHandler = $module_handler;
    $this->redirectDestination = $redirect_destination;
    $this->messenger = $messenger;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('renderer'),
      $container->get('current_user'),
      $container->get('request_stack'),
      $container->get('current_route_match'),
      $container->get('plugin.manager.migration'),
      $container->get('plugin.manager.remote_source'),
      $container->get('ghi_content.import'),
      $container->get('module_handler'),
      $container->get('redirect.destination'),
      $container->get('messenger'),
    );
  }

  /**
   * Get the node bundle that the current class manages.
   *
   * @return string
   *   The bundle name.
   */
  abstract public function getNodeBundle();

  /**
   * Get the name of the remote field.
   *
   * @return string
   *   The field name.
   */
  abstract public function getRemoteFieldName();

  /**
   * Get the machine name of the element to be used for source links.
   *
   * @return string
   *   The machine name of a form element.
   */
  abstract protected function getRemoteSourceLinkType();

  /**
   * Load a local node for the given remote content.
   *
   * @param \Drupal\ghi_content\RemoteContent\RemoteContentInterface $content
   *   A content object from the remote source.
   *
   * @return \Drupal\ghi_content\Entity\ContentBase|null
   *   A local node if found or NULL.
   */
  abstract public function loadNodeForRemoteContent(RemoteContentInterface $content);

  /**
   * Load the remote content for the given node.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node entity.
   * @param bool $refresh
   *   Wether to retrieve fresh data.
   *
   * @return \Drupal\ghi_content\RemoteContent\RemoteContentInterface|null
   *   The remote article object if found.
   */
  abstract public function loadRemoteContentForNode(NodeInterface $node, $refresh = FALSE);

  /**
   * Load major tags for a node.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node object.
   *
   * @return array
   *   An array with term ids as keys and term labels as values.
   */
  public function getTags(NodeInterface $node) {
    $tags = [];
    if (!$node->hasField('field_tags')) {
      // @todo This should probably return FALSE or throw an exception.
      return $tags;
    }
    $entities = $node->get('field_tags')->referencedEntities();
    if (empty($entities)) {
      return $tags;
    }
    foreach ($entities as $tag) {
      $tags[$tag->id()] = $tag->label();
    }
    return $tags;
  }

  /**
   * Load all nodes for this manager.
   *
   * @param bool $published
   *   Whether to load only published nodes.
   *
   * @return \Drupal\Core\Entity\EntityInterface[]|null
   *   An array of entity objects indexed by their ids.
   */
  public function loadAllNodes($published = TRUE) {
    $properties = [
      'type' => $this->getNodeBundle(),
    ];
    if ($published) {
      $properties['status'] = NodeInterface::PUBLISHED;
    }
    $nodes = $this->entityTypeManager->getStorage('node')
      ->loadByProperties($properties);
    return $nodes;
  }

  /**
   * Load all articles for a given set of tags.
   *
   * @param array $tags
   *   An array of tags. This can be either an array of term objects, or an
   *   array if term ids.
   * @param \Drupal\node\NodeInterface $node
   *   Optional: A node object which tags serve as a base context.
   * @param string $op
   *   The logical operator (conjunction) for combining the tags.
   * @param int $limit
   *   An optional limit.
   * @param bool $published
   *   An optional flag to restrict this to published nodes. Default is TRUE.
   *
   * @return \Drupal\node\NodeInterface[]|null
   *   An array of entity objects indexed by their ids.
   */
  public function loadNodesForTags(?array $tags = NULL, ?NodeInterface $node = NULL, $op = 'AND', $limit = NULL, $published = TRUE) {
    if (empty($tags) && $node === NULL) {
      return NULL;
    }

    $tag_field = 'field_tags';

    // Setup the base query.
    $query = $this->entityTypeManager->getStorage('node')->getQuery();
    $query->accessCheck($published);
    if ($published) {
      $query->condition('status', NodeInterface::PUBLISHED);
    }
    $query->condition('type', $this->getNodeBundle());
    if ($limit !== NULL) {
      $query->pager((int) $limit);
    }

    // For the logic behind the following conditions on tags see comments on
    // https://api.drupal.org/api/drupal/core!lib!Drupal!Core!Entity!Query!QueryInterface.php/function/QueryInterface%3A%3AandConditionGroup/8.2.x
    if ($node) {
      // Get the base tags, these must all be present in the articles.
      $node_tags = $this->getTags($node);
      foreach (array_keys($node_tags) as $tag_id) {
        $query->condition($this->getLogicalQueryCondition($query, 'AND', $tag_field, $tag_id));
      }
    }

    // Assemble the given tags into query conditions.
    $tags = $tags ?? [];
    $tag_ids = array_filter(array_map(function ($tag) {
      if (is_object($tag) && $tag instanceof TermInterface) {
        return $tag->id();
      }
      if (is_scalar($tag) && intval($tag)) {
        return intval($tag);
      }
    }, $tags));

    $condition = $op == 'AND' ? $query->andConditionGroup() : $query->orConditionGroup();
    foreach ($tag_ids as $tag_id) {
      $condition->condition($this->getLogicalQueryCondition($query, $op, $tag_field, $tag_id));
    }
    if ($condition->count()) {
      $query->condition($condition);
    }

    $results = $query->execute();
    return $this->entityTypeManager->getStorage('node')->loadMultiple($results);
  }

  /**
   * Add a logical condition to the query.
   *
   * @param \Drupal\Core\Entity\Query\QueryInterface $query
   *   The query object to modify.
   * @param string $op
   *   The logical operation, either 'AND' or 'OR'.
   * @param string $field
   *   The field for the condition.
   * @param mixed $value
   *   The value for the condition.
   *
   * @return \Drupal\Core\Entity\Query\ConditionInterface
   *   A condition object.
   */
  private function getLogicalQueryCondition(QueryInterface $query, $op, $field, $value) {
    if ($op == 'AND') {
      $condition = $query->andConditionGroup();
      $condition->condition($field, $value);
    }
    else {
      $condition = $query->orConditionGroup();
      $condition->condition($field, $value);
    }
    return $condition;
  }

  /**
   * Load all articles for a section.
   *
   * @param \Drupal\ghi_sections\Entity\SectionNodeInterface $section
   *   The section that articles belong to.
   *
   * @return \Drupal\node\NodeInterface[]|null
   *   An array of node objects indexed by their ids.
   */
  public function loadNodesForSection(SectionNodeInterface $section) {
    if (!$this->isSectionNode($section)) {
      return NULL;
    }
    return $this->loadNodesForTags(NULL, $section, 'AND');
  }

  /**
   * Load all sections where the given node can appear.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node object.
   *
   * @return \Drupal\node\NodeInterface[]
   *   Array of section nodes.
   */
  public function loadSectionsForNode(NodeInterface $node) {
    $sections = [];

    $tags = $this->getTags($node);
    if (empty($tags)) {
      return $sections;
    }

    // Setup the base query.
    $section_candidates = $this->entityTypeManager->getStorage('node')->loadByProperties([
      'type' => SectionManager::SECTION_BUNDLES,
      'field_tags' => array_keys($tags),
    ]);

    foreach ($section_candidates as $section) {
      $section_tags = $this->getTags($section);
      if (count(array_diff_key($section_tags, $tags)) > 0) {
        // We only want to keep sections where all tags are part of the article
        // tags. But here we have at least one section tag that is not present
        // on the article, so we skip this section.
        continue;
      }
      $sections[] = $section;
    }

    return $sections;
  }

  /**
   * Load available tags for a section.
   *
   * @param \Drupal\ghi_sections\Entity\SectionNodeInterface $section
   *   The section.
   *
   * @return array
   *   An array with term ids as keys and term labels as values.
   */
  public function loadAvailableTagsForSection(SectionNodeInterface $section) {
    $nodes = $this->loadNodesForSection($section);
    $section_tags = $this->getTags($section);
    $article_tags = $this->getAvailableTags($nodes);
    return array_unique($section_tags + $article_tags);
  }

  /**
   * Load available tags for a section.
   *
   * @param \Drupal\node\NodeInterface[] $nodes
   *   The nodes to extract the tags from.
   *
   * @return array
   *   An array with term ids as keys and term labels as values.
   */
  public function getAvailableTags(array $nodes) {
    $tags = [];
    foreach ($nodes as $node) {
      $tags = $tags + $this->getTags($node);
    }
    return $tags;
  }

  /**
   * Sort the given tags.
   *
   * @param string[] $tags
   *   An array of tag labels, keyed by term id.
   */
  public function sortTags(&$tags) {
    // Load the tags and make sure they are actually terms of type tags.
    $entities = $this->entityTypeManager->getStorage('taxonomy_term')->loadMultiple(array_keys($tags));
    $entities = array_filter($entities, function ($term) {
      return $term instanceof Tag;
    });

    $grouped_tags = [];
    /** @var \Drupal\ghi_sections\Entity\Tag[] $entities */
    foreach ($entities as $entity) {
      $type = $entity->getType() ?? 'undefined';
      $grouped_tags[$type] = $grouped_tags[$type] ?? [];
      $grouped_tags[$type][$entity->id()] = $tags[$entity->id()];
    }
    $type_order = [
      'year',
      'document_type',
      'country',
      'theme',
      'undefined',
    ];
    $tags = [];
    foreach ($type_order as $type) {
      if (empty($grouped_tags[$type])) {
        continue;
      }
      asort($grouped_tags[$type]);
      $tags += $grouped_tags[$type];
    }
  }

  /**
   * Group node ids by associated tags.
   *
   * @param \Drupal\node\NodeInterface[] $nodes
   *   The nodes to process.
   * @param array $additional_tags
   *   An optional array of additional tags to apply to every node.
   *
   * @return array
   *   An array with term ids as keys. The values are arrays of node ids.
   */
  public function getNodeIdsGroupedByTag(array $nodes, array $additional_tags = []) {
    $tags = [];
    foreach ($nodes as $node) {
      foreach (array_unique($additional_tags + $this->getTags($node)) as $id => $tag) {
        $tags[$id][$node->id()] = $node->id();
      }
    }
    return $tags;
  }

  /**
   * Save a content node programatically.
   *
   * Besides saving the node, this does 2 additional things.
   * 1. It handles the presence of an IPE token, which would prevent updates to
   *    the layout sections when issued from the node edit form.
   * 2. It updates the migration status of the node, so that it doesn't get
   *    wrongly flagged as needing an update.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node object.
   * @param bool $update_migration_state
   *   Optionally allows to prevent updating the migration statue. This can be
   *   used if a node is saved manually suite to updates during a migration
   *   run, where the migration system already takes care of updating the
   *   status.
   */
  public function saveContentNode(NodeInterface $node, $update_migration_state = TRUE) {
    // If the layout builder ipe module is used, we need to remove their token,
    // otherwhise layout updates (paragraphs) will be reverted before saving
    // because this action is issued from the node edit form.
    $ipe_token = $this->request->get('layout_builder_ipe_token');
    if ($ipe_token) {
      $this->request->request->remove('layout_builder_ipe_token');
    }

    // Save the node.
    $node->save();

    // The next thing we need to do after that is to update the migration state,
    // so that this article is not wrongly treated as changed on the next
    // migration run.
    if ($update_migration_state) {
      $this->updateMigrationState($node);
    }
  }

  /**
   * Update the given node according to the data on its remote source.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node object.
   * @param bool $dry_run
   *   Whether the update should actually modify data.
   * @param bool $reset
   *   Whether node should be reset to it's original state (as if it would be
   *   created right now based on the configuration on the remote).
   *
   * @see ghi_content_node_presave()
   */
  abstract public function updateNodeFromRemote(NodeInterface $node, $dry_run = FALSE, $reset = FALSE);

  /**
   * Check if the given node is in-sync with its remote source.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node object.
   *
   * @return bool|null
   *   TRUE if in-sync, FALSE if not and NULL if the migration is not found.
   *
   * @see ghi_content_form_node_article_edit_form_alter()
   */
  abstract public function isUpToDateWithRemote(NodeInterface $node);

  /**
   * Normalize an article node for comparision between local and remote data.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node object to normalize.
   *
   * @return array
   *   A normalized array based on the given node object.
   */
  protected function normalizeContentNodeData(NodeInterface $node) {
    $migration = $this->getMigration($node);
    $expected_fields = array_map(function ($item) {
      return str_contains($item, '/') ? explode('/', $item)[0] : $item;
    }, array_keys($migration->getProcess()));

    $data = $node->toArray();
    $data = array_intersect_key($data, array_flip($expected_fields));
    ArrayHelper::sortMultiDimensionalArrayByKeys($data);
    ArrayHelper::reduceArray($data);
    $this->moduleHandler->alter('normalize_content', $data);
    return $data;
  }

  /**
   * Cleanup after a content object has been deleted.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node object.
   *
   * @see ghi_content_node_predelete()
   */
  public function cleanupContentOnDelete(NodeInterface $node) {
    if ($node->bundle() != $this->getNodeBundle()) {
      return;
    }
    $this->removeMigrationMapEntries($node);
  }

  /**
   * Get the migration for the given node.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node object.
   *
   * @return \Drupal\migrate\Plugin\MigrationInterface|null
   *   The migration plugin if found.
   */
  abstract protected function getMigration(NodeInterface $node);

  /**
   * Update the migration state of the given node.
   *
   * This is usefull when manually importing the source data.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node object.
   */
  public function updateMigrationState(NodeInterface $node) {
    $migration = $this->getMigration($node);
    if (!$migration) {
      return;
    }

    $migrate_executable = new MigrateExecutable($migration);

    /** @var \Drupal\ghi_content\Plugin\migrate\source\RemoteSourceGraphQL $source */
    $source = $migration->getSourcePlugin();
    $source_id = $migration->getIdMap()->lookupSourceId(['nid' => $node->id()]);
    $destination = $migration->getDestinationPlugin();

    $source_iterator = $source->initializeIterator();
    $source_iterator->rewind();
    foreach ($source_iterator as $row_data) {
      $row = new Row($row_data + $migration->getSourceConfiguration(), $source_id);
      if ($source_id != $row->getSourceIdValues()) {
        continue;
      }
      $migrate_executable->processRow($row);
      $id_map = $migration->getIdMap()->getRowBySource($row->getSourceIdValues());
      if (!$id_map) {
        continue;
      }

      $row->setIdMap($id_map);
      $row->rehash();

      $destination_ids = $migration->getIdMap()->lookupDestinationIds($source_id);
      $destination_id_values = $destination_ids ? reset($destination_ids) : [];
      $migration->getIdMap()->saveIdMapping($row, $destination_id_values, MigrateIdMapInterface::STATUS_IMPORTED, $destination->rollbackAction());
    }
  }

  /**
   * Remove migration map entries for the given node.
   *
   * Doing this, allows to re-import a previously imported article that has
   * been deleted on the backend. This is more of an user-1 rescue thing to do.
   * Generally, articles can't be deleted in the backend but need to be removed
   * (unpublished/deleted) from the remote source.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node object.
   */
  protected function removeMigrationMapEntries(NodeInterface $node) {
    $migration = $this->getMigration($node);
    if (!$migration) {
      return;
    }
    $source_id = $migration->getIdMap()->lookupSourceId(['nid' => $node->id()]);
    $migration->getIdMap()->delete($source_id);
  }

  /**
   * Alter the node edit forms for article nodes.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state object.
   */
  public function nodeEditFormAlter(array &$form, FormStateInterface $form_state) {
    /** @var \Drupal\node\NodeForm $form_object */
    $form_object = $form_state->getFormObject();
    /** @var \Drupal\node\NodeInterface $node */
    $node = $form_object->getEntity();
    if (!$node instanceof ContentBase) {
      return;
    }
    $bundle_label = $node->type->entity->label();
    $t_args = [
      '@label' => strtolower($bundle_label),
      '@remote_source' => $this->getRemoteSource($node)?->getPluginLabel(),
    ];

    // Disable the image field because we sync this automatically.
    $form['field_image']['#disabled'] = 'disabled';

    // Disable the remote field.
    $remote_field = $this->getRemoteFieldName();
    $form[$remote_field]['#disabled'] = TRUE;
    $form[$remote_field]['#attributes']['title'] = $this->t('This field cannot be edited anymore after the page has been created.');

    $admin_permission = $this->currentUser->hasPermission('administer content types');
    $form['meta']['#access'] = $admin_permission;
    $form['meta']['published']['#access'] = $admin_permission;
    $form['meta']['author']['#access'] = $admin_permission;
    $form['author']['#access'] = $admin_permission;
    $form['changed']['#access'] = $admin_permission;
    $form['options']['#access'] = $admin_permission;

    // If the hero image control checkbox is NULL (never actively saved), we
    // want to make sure that it shows as selected, which is the default state
    // for new content pages.
    if ($node->hasField('field_display_hero_image') && $node->get('field_display_hero_image')->value === NULL) {
      $form['field_display_hero_image']['widget']['value']['#default_value'] = TRUE;
    }

    // If the inherit section image control checkbox is NULL (never actively
    // saved), we want to make sure that it shows as selected, which is the
    // default state for new content pages.
    if ($node->hasField('field_inherit_section_image')) {
      $inherit_section_image = $node->get('field_inherit_section_image')->value;
      if ($inherit_section_image === NULL) {
        $form['field_inherit_section_image']['widget']['value']['#default_value'] = TRUE;
      }
    }

    $content = $this->loadRemoteContentForNode($node);
    $content_in_sync = $content ? $this->isUpToDateWithRemote($node) : NULL;

    $form['remote_content_info'] = [
      '#type' => 'details',
      '#title' => $this->t('Remote @label', [
        '@label' => strtolower($bundle_label),
      ]),
      '#open' => !$content_in_sync,
      '#group' => 'advanced',
    ];

    $form['remote_content_info']['status'] = [
      '#type' => 'item',
      '#title' => $this->t('Up to date'),
      '#markup' => '<p>' . $this->t('This @label is up to date with its source content on @remote_source.', $t_args) . '</p>',
      '#weight' => 1,
    ];

    if (!$content) {
      $form['remote_content_info']['status']['#title'] = $this->t('Deleted');
      $form['remote_content_info']['status']['#markup'] = '<p>' . $this->t('The source of this @label page has been removed on @remote_source.', $t_args) . '</p>';
    }
    elseif (!$content_in_sync) {
      $form['remote_content_info']['status']['#title'] = $this->t('Outdated');
      $form['remote_content_info']['status']['#markup'] = '<p>' . $this->t('The source of this @label page has changed on @remote_source. It will be automatically updated the next time that the @label import will run. To apply the changes immediately use the button below.', $t_args) . '</p>';

      $form['remote_content_info']['apply_changes'] = [
        '#type' => 'submit',
        '#value' => $this->t('Apply changes from @remote_source', $t_args),
        '#submit' => [[$this, 'applyChangesSubmit']],
        '#weight' => 2,
        '#limit_validation_errors' => [],
      ];
    }

    if ($content) {
      $queryParams = $this->request->query->all();
      $redirect_url = Url::fromRouteMatch($this->routeMatch);
      if (!empty($queryParams)) {
        $redirect_url->setOption('query', $queryParams);
      }
      $this->redirectDestination->set($redirect_url->toString());
      $form['remote_content_info']['link_label'] = [
        '#type' => 'item',
        '#title' => $this->t('Go to @remote_source', $t_args),
        '#weight' => 3,
      ];
      $view_builder = $this->entityTypeManager->getViewBuilder('node');
      $form['remote_content_info']['link'] = $view_builder->viewField($node->get($remote_field), [
        'label' => 'hidden',
        'type' => $this->getRemoteSourceLinkType(),
        'settings' => [
          'link_label' => $this->t('Edit this @label on @remote_source', $t_args),
          'link_to_edit' => TRUE,
          'include_publisher_destination' => TRUE,
        ],
      ]);
      $form['remote_content_info']['link']['#weight'] = 4;

      // Add a way to reset the article.
      $form['remote_content_reset'] = [
        '#type' => 'details',
        '#title' => $this->t('Reset @label', $t_args),
        '#description' => $this->t('Reset the content paragraphs to the initial state. This will remove any customizations made to this @label page and re-import all content paragraphs in the order defined on @remote_source.', $t_args),
        '#open' => FALSE,
        '#group' => 'advanced',
      ];
      $form['remote_content_reset']['reset'] = [
        '#type' => 'submit',
        '#value' => $this->t('Reset now'),
        '#submit' => [[$this, 'formResetSubmit']],
        '#weight' => 2,
        '#limit_validation_errors' => [],
      ];

      if ($node instanceof ContentReviewInterface) {
        $form['needs_review'] = [
          '#type' => 'details',
          '#title' => $this->t('Review status'),
          '#open' => $node->needsReview(),
          '#group' => 'advanced',
        ];
        $form['needs_review']['status'] = [
          '#type' => 'item',
          '#title' => $node->needsReview() ? $this->t('Needs review') : $this->t('No issues'),
          '#markup' => '<p>' . ($node->needsReview() ? $this->t('This @label has been flagged as needing a review.', $t_args) : $this->t('This @label has not been flagged as needing a review yet.', $t_args)) . '</p>',
          '#weight' => 1,
        ];
        $form['needs_review']['confirm'] = [
          '#type' => 'submit',
          '#value' => $node->needsReview() ? $this->t('Mark as reviewed') : $this->t('Mark as needing a reviewed'),
          '#submit' => [[$this, 'formNeedsReviewSubmit']],
          '#weight' => 2,
          '#limit_validation_errors' => [],
        ];
      }
    }
    $form['#attached']['library'][] = 'ghi_content/admin.remote_content_edit';
  }

  /**
   * Form submit handler for the "Apply changes" button on remote content forms.
   *
   * This just saves the node.
   */
  public function applyChangesSubmit($form, FormStateInterface $form_state) {
    /** @var \Drupal\node\NodeForm $form_object */
    $form_object = $form_state->getFormObject();
    $node = $form_object->getEntity();

    // Update based on what's new on the remote.
    $this->updateNodeFromRemote($node);

    // Save the content node, making sure that common logic is applied.
    $this->saveContentNode($node);

    $form_state->setRebuild(FALSE);

    $t_args = [
      '@label' => $node->label(),
      '@remote_source' => $this->getRemoteSource($node)?->getPluginLabel() ?? $this->t('Content Management backend'),
      '@type' => strtolower($node->type->entity->label()),
      '@review_queue_url' => Url::fromUserInput('/admin/content/review-queue')->toString(),
    ];
    $messages = [
      $this->t('The changes from @remote_source have been applied to <em>@label</em>.', $t_args),
    ];
    if ($node instanceof ContentReviewInterface && $node->needsReview()) {
      $messages[] = $this->t('The @type needs to be reviewed to confirm the positioning of newly added elements.', $t_args);
      $messages[] = $this->t('Go to the <a href="@review_queue_url">review queue</a> to review the changes.', $t_args);
    }
    $this->messenger->addStatus(implode('<br />', $messages));
  }

  /**
   * Form submit handler for the "Reset" button on remote content forms.
   */
  public function formResetSubmit($form, FormStateInterface $form_state) {
    /** @var \Drupal\node\NodeForm $form_object */
    $form_object = $form_state->getFormObject();
    $node = $form_object->getEntity();

    // Reset the article to it's exact version in the remote system.
    $this->updateNodeFromRemote($node, FALSE, TRUE);

    // Save the content node, making sure that common logic is applied.
    $this->saveContentNode($node);

    $form_state->setRebuild(FALSE);

    $this->messenger->addStatus($this->t('<em>@label</em> has been reset to the initial state of @remote_source.', [
      '@label' => $node->label(),
      '@remote_source' => $this->getRemoteSource($node)?->getPluginLabel(),
    ]));
  }

  /**
   * Form submit handler for the review status buttons on remote content forms.
   */
  public function formNeedsReviewSubmit($form, FormStateInterface $form_state) {
    /** @var \Drupal\node\NodeForm $form_object */
    $form_object = $form_state->getFormObject();
    $node = $form_object->getEntity();

    // Save the content node, making sure that common logic is applied.
    if (!$node instanceof ContentReviewInterface) {
      return;
    }

    if ($node->needsReview()) {
      $node->needsReview(FALSE);
      $this->messenger->addStatus($this->t('The review status for <em>@label</em> has been saved.', [
        '@label' => $node->label(),
        '@remote_source' => $this->getRemoteSource($node)?->getPluginLabel(),
      ]));
    }
    else {
      $node->needsReview(TRUE);
      $this->messenger->addStatus($this->t('The review status for <em>@label</em> has been saved.', [
        '@label' => $node->label(),
        '@remote_source' => $this->getRemoteSource($node)?->getPluginLabel(),
      ]));
    }

    $this->saveContentNode($node);
    $form_state->setRebuild(FALSE);
  }

  /**
   * Get the remote source plugin for the content node.
   *
   * @param \Drupal\ghi_content\Entity\ContentBase $node
   *   The content node object.
   *
   * @return \Drupal\ghi_content\RemoteSource\RemoteSourceInterface
   *   The remote source instance.
   */
  private function getRemoteSource(ContentBase $node) {
    $remote_field = $this->getRemoteFieldName();
    if (!$node->hasField($remote_field)) {
      return NULL;
    }
    $remote_source = $node->get($remote_field)->remote_source;
    if (!$remote_source || !$this->remoteSourceManager->hasDefinition($remote_source)) {
      return NULL;
    }
    return $this->remoteSourceManager->createInstance($remote_source);
  }

}
