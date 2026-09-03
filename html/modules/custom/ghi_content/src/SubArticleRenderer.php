<?php

namespace Drupal\ghi_content;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Plugin\Context\EntityContext;
use Drupal\Core\Plugin\Context\ContextRepositoryInterface;
use Drupal\Core\Render\Element;
use Drupal\ghi_base_objects\Helpers\BaseObjectHelper;
use Drupal\ghi_content\Entity\Article;
use Drupal\layout_builder\LayoutEntityHelperTrait;
use Drupal\layout_builder\SectionComponent;
use Drupal\node\NodeInterface;

/**
 * Builds local sub-article layout builder components.
 */
class SubArticleRenderer {

  use LayoutEntityHelperTrait;

  /**
   * The context repository.
   *
   * @var \Drupal\Core\Plugin\Context\ContextRepositoryInterface
   */
  protected $contextRepository;

  /**
   * Constructs a sub-article renderer.
   *
   * @param \Drupal\Core\Plugin\Context\ContextRepositoryInterface $context_repository
   *   The context repository.
   */
  public function __construct(ContextRepositoryInterface $context_repository) {
    $this->contextRepository = $context_repository;
  }

  /**
   * Build selected layout builder components of a local sub article.
   *
   * @param \Drupal\ghi_content\Entity\Article $article
   *   The local sub article.
   * @param \Drupal\node\NodeInterface|null $context_node
   *   An optional context node for the local sub article.
   * @param \Drupal\Core\Plugin\Context\ContextInterface[] $contexts
   *   Additional contexts from the parent rendering environment.
   * @param int $offset
   *   The number of components to skip.
   * @param int|null $limit
   *   The maximum number of rendered components to build, or NULL for all
   *   remaining.
   * @param int|null $next_offset
   *   The next raw layout builder component offset after the rendered slice.
   *
   * @return array
   *   A render array.
   */
  public function build(Article $article, ?NodeInterface $context_node = NULL, array $contexts = [], int $offset = 0, ?int $limit = NULL, ?int &$next_offset = NULL) {
    $this->applyContextNode($article, $context_node);
    $components = $this->getComponents($article);
    if (empty($components)) {
      $next_offset = $offset;
      return [];
    }

    $build = [
      '#skip_footnotes_processing' => TRUE,
      '#cache' => [
        'contexts' => [
          'url.path',
          'url.query_args',
          'user.permissions',
        ],
        'tags' => $this->getCacheTags($article, $context_node),
      ],
    ];

    $rendered_count = 0;
    $next_offset = $offset;
    $contexts = $contexts + $this->getBaseObjectContexts($context_node) + $this->getRuntimeContexts();
    // Nested components belong to this article, even when the parent passed
    // its own layout entity along with the other rendering contexts.
    $contexts['layout_builder.entity'] = EntityContext::fromEntity($article);
    foreach (array_values($components) as $component_offset => $component) {
      if ($component_offset < $offset) {
        continue;
      }
      $component_contexts = $this->getComponentContexts($component, $contexts);
      // Use the core component renderer so missing optional page contexts are
      // handled the same way as a normal Layout Builder entity render.
      $component_build = $component->toRenderArray($component_contexts);
      $next_offset = $component_offset + 1;
      if (!$this->hasRenderableContent($component_build)) {
        // Components that render only cache metadata or attachments should not
        // consume one of the visible preview slots, but the AJAX offset still
        // needs to move past them so the deferred request does not revisit
        // already-inspected components.
        continue;
      }
      $build[$component->getUuid()] = $component_build;
      $rendered_count++;
      if ($limit !== NULL && $rendered_count >= $limit) {
        break;
      }
    }

    return $build;
  }

  /**
   * Get render contexts for an individual layout builder component.
   *
   * GHI blocks may store explicit base object mappings such as "plan--1499".
   * A standalone article render has those available as named contexts, but a
   * nested sub-article render is outside that route/context derivation. Add
   * the missing named contexts here so the component's stored mapping can be
   * resolved without changing the contexts of sibling components.
   *
   * @param \Drupal\layout_builder\SectionComponent $component
   *   The layout builder component to render.
   * @param \Drupal\Core\Plugin\Context\ContextInterface[] $contexts
   *   The base contexts for the sub-article render.
   *
   * @return \Drupal\Core\Plugin\Context\ContextInterface[]
   *   Contexts for the component.
   */
  private function getComponentContexts(SectionComponent $component, array $contexts) {
    $configuration = $component->get('configuration') ?? [];
    $context_mapping = $configuration['context_mapping'] ?? [];
    if (empty($context_mapping)) {
      return $contexts;
    }

    foreach (array_filter($context_mapping) as $context_name) {
      if (!is_string($context_name) || !str_contains($context_name, '--') || array_key_exists($context_name, $contexts)) {
        continue;
      }
      [$bundle, $source_id] = explode('--', $context_name, 2);
      if (!$bundle || !$source_id) {
        continue;
      }
      $base_object = BaseObjectHelper::getBaseObjectFromOriginalId($source_id, $bundle);
      if (!$base_object) {
        continue;
      }
      $contexts[$context_name] = EntityContext::fromEntity($base_object);
    }

    return $contexts;
  }

  /**
   * Count the layout builder components of a local sub article.
   *
   * @param \Drupal\ghi_content\Entity\Article $article
   *   The local sub article.
   *
   * @return int
   *   The number of components.
   */
  public function countComponents(Article $article) {
    return count($this->getComponents($article));
  }

  /**
   * Get a flat ordered list of layout builder components.
   *
   * @param \Drupal\ghi_content\Entity\Article $article
   *   The local sub article.
   *
   * @return \Drupal\layout_builder\SectionComponent[]
   *   The components, ordered by section and component weight.
   */
  private function getComponents(Article $article) {
    $components = [];
    foreach ($this->getEntitySections($article) as $section) {
      $section_components = $section->getComponents();
      uasort($section_components, function (SectionComponent $a, SectionComponent $b) {
        return $a->getWeight() <=> $b->getWeight();
      });
      foreach ($section_components as $component) {
        $components[] = $component;
      }
    }
    return $components;
  }

  /**
   * Check if a component render array contains visible content.
   *
   * Some Layout Builder components are valid but render only attachments/cache
   * metadata in the current context. Those should not count towards the
   * configured preview component limit.
   *
   * @param array $build
   *   A component render array.
   *
   * @return bool
   *   TRUE if the component has renderable content, FALSE otherwise.
   */
  private function hasRenderableContent(array $build) {
    if (empty($build)) {
      return FALSE;
    }
    if (!empty(Element::children($build))) {
      return TRUE;
    }
    foreach (['#markup', '#plain_text', '#theme', '#type'] as $key) {
      if (!empty($build[$key])) {
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
   * Apply the parent page context to the local sub article if valid.
   *
   * @param \Drupal\ghi_content\Entity\Article $article
   *   The local sub article.
   * @param \Drupal\node\NodeInterface|null $context_node
   *   The optional context node.
   */
  private function applyContextNode(Article $article, ?NodeInterface $context_node = NULL) {
    if ($context_node && $article->isValidContextNode($context_node)) {
      $article->setContextNode($context_node);
    }
  }

  /**
   * Get runtime contexts from the current rendering environment.
   *
   * @return \Drupal\Core\Plugin\Context\ContextInterface[]
   *   Runtime contexts keyed by context name.
   */
  private function getRuntimeContexts() {
    $available_context_ids = array_keys($this->contextRepository->getAvailableContexts());
    return $this->contextRepository->getRuntimeContexts($available_context_ids);
  }

  /**
   * Get base object contexts from the parent page context node.
   *
   * @param \Drupal\node\NodeInterface|null $context_node
   *   The optional context node.
   *
   * @return \Drupal\Core\Plugin\Context\ContextInterface[]
   *   Base object contexts keyed by common GHI context names.
   */
  private function getBaseObjectContexts(?NodeInterface $context_node = NULL) {
    if (!$context_node) {
      return [];
    }
    $contexts = [];
    foreach (BaseObjectHelper::getBaseObjectsFromNode($context_node) ?? [] as $base_object) {
      $contexts[$base_object->bundle()] = EntityContext::fromEntity($base_object);
      if ($base_object->bundle() == 'governing_entity') {
        $contexts['plan_cluster'] = EntityContext::fromEntity($base_object);
      }
    }
    return $contexts;
  }

  /**
   * Get cache tags for a sub-article render array.
   *
   * @param \Drupal\ghi_content\Entity\Article $article
   *   The local sub article.
   * @param \Drupal\node\NodeInterface|null $context_node
   *   The optional context node.
   *
   * @return string[]
   *   Cache tags.
   */
  private function getCacheTags(Article $article, ?NodeInterface $context_node = NULL) {
    $cache_tags = $article->getCacheTags();
    if ($context_node) {
      $cache_tags = Cache::mergeTags($cache_tags, $context_node->getCacheTags());
    }
    return $cache_tags;
  }

}
