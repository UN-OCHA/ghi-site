<?php

namespace Drupal\ghi_content\EntityBrowser;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\Element\EntityAutocomplete;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\ghi_content\Entity\Document;
use Drupal\ghi_sections\Entity\SectionNodeInterface;
use Drupal\ghi_subpages\Entity\SubpageNodeInterface;
use Drupal\node\NodeInterface;
use Drupal\views\ViewExecutable;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Service class for article select entity browsers.
 *
 * This class contains some logic to imrove the UI and UX of the entity browser
 * used to select articles.
 */
class ArticleSelection implements ContainerInjectionInterface {

  /**
   * The view id that this service class handles.
   */
  const VIEW_ID = 'article_selection';

  /**
   * Identifier for the tag filter on the view.
   */
  const TAG_FILTER = 'tags';

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The current request.
   *
   * @var \Symfony\Component\HttpFoundation\Request
   */
  protected $request;

  /**
   * Public constructor.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, RequestStack $request_stack) {
    $this->entityTypeManager = $entity_type_manager;
    $this->request = $request_stack->getCurrentRequest();
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('request_stack'),
    );
  }

  /**
   * Act on the pre_view hook of an entity browser view.
   *
   * Pre-populate the tag filter on article selection entity browsers when
   * opening the selection dialog for the first time.
   *
   * @param \Drupal\views\ViewExecutable $view
   *   The view to modifiy.
   * @param array $args
   *   An array of arguments.
   */
  public function preView(ViewExecutable $view, array $args) {
    $exposed_input = $view->getExposedInput();
    $already_submitted = array_key_exists(self::TAG_FILTER, $exposed_input);
    if ($view->id() != self::VIEW_ID || $already_submitted) {
      return;
    }

    // Now see if we have a qualified node and if it has tags.
    $node = $this->getCurrentNode();
    $tags = $node ? $this->getTagsFromNode($node) : NULL;
    if (empty($tags)) {
      return;
    }

    // Populate the exposed tag filter with these found tags.
    $exposed_input = $view->getExposedInput();
    $exposed_input[self::TAG_FILTER] = EntityAutocomplete::getEntityLabels($tags);
    $view->setExposedInput($exposed_input);
  }

  /**
   * Get the current node from the request.
   *
   * @return \Drupal\node\NodeInterface|null
   *   A node object if it has been found.
   */
  private function getCurrentNode() {
    // Get the path to get the node entity.
    $original_path = $this->request->query->get('original_path');
    if (!$original_path) {
      return NULL;
    }
    $node_key = array_values(array_filter(explode('/', $original_path), function ($part) {
      return str_starts_with($part, 'node.') ? $part : NULL;
    }))[0] ?? NULL;
    if (!$node_key) {
      return NULL;
    }
    [$entity_type_id, $entity_id] = explode('.', $node_key);
    return $this->entityTypeManager->getStorage($entity_type_id)->load($entity_id);
  }

  /**
   * Get the tags from the given node.
   *
   * This only works with a limited set of known node types.
   *
   * @param \Drupal\node\NodeInterface $node
   *   A node object.
   *
   * @return \Drupal\taxonomy\TermInterface[]
   *   An array of tag entities.
   */
  private function getTagsFromNode(NodeInterface $node) {
    if ($node instanceof SectionNodeInterface) {
      return $node->getTagEntities();
    }
    if ($node instanceof SubpageNodeInterface) {
      return $node->getParentBaseNode()?->getTagEntities();
    }
    if ($node instanceof Document) {
      return $node->getTags(TRUE);
    }
    return NULL;
  }

}
