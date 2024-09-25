<?php

namespace Drupal\ghi_form_elements\Traits;

use Drupal\Core\Entity\Element\EntityAutocomplete;
use Drupal\Core\Link;
use Drupal\Core\Session\AnonymousUserSession;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\ghi_plan_clusters\Entity\PlanCluster;
use Drupal\ghi_plan_clusters\Entity\PlanClusterInterface;
use Drupal\ghi_sections\Entity\SectionNodeInterface;
use Drupal\ghi_subpages\Entity\LogframeSubpage;
use Drupal\node\NodeInterface;

/**
 * Helper trait for optional link support on form elements.
 */
trait OptionalLinkTrait {

  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public function getLinkFromConfiguration(array $conf, array $contexts) {
    if (array_key_exists('add_link', $conf) && empty($conf['add_link'])) {
      return NULL;
    }
    if (empty($conf['link_type'])) {
      return NULL;
    }
    if ($conf['link_type'] == 'custom') {
      if (empty($conf['link_custom']['url'])) {
        return NULL;
      }
      return $this->getLinkFromUri($conf['link_custom']['url'], $conf['label'] ?: NULL);
    }
    elseif (!empty($contexts['section_node']) && !empty($contexts['page_node'])) {
      $targets = self::getInternalLinkUrls($contexts['section_node'], $contexts['page_node']);
      $configured_target = $conf['link_internal']['target'];
      if (empty($targets[$configured_target])) {
        return NULL;
      }
      $link = $targets[$configured_target];
      return $this->getLinkFromUri($link->getUrl()->toUriString(), $conf['label'] ?: NULL);
    }
  }

  /**
   * Get a link for the given URI and optional label.
   *
   * @param string $uri
   *   A URI string.
   * @param string $label
   *   Optional label to use. If left empty, a default label will be build.
   *
   * @return \Drupal\Core\Link
   *   The link object.
   */
  protected function getLinkFromUri($uri, $label = NULL) {
    $is_internal = strpos($uri, 'internal:') === 0;
    $label = $label ?? NULL;
    try {
      $url = Url::fromUri($uri);
      if (!$url->access(new AnonymousUserSession())) {
        return NULL;
      }
      $link = $url->access() ? Link::fromTextAndUrl($label, $url) : NULL;
    }
    catch (\InvalidArgumentException $e) {
      return NULL;
    }
    if (!$link) {
      return NULL;
    }
    if (!$is_internal && $link->getUrl()->isRouted() && $node = $link->getUrl()->getRouteParameters()['node'] ?? NULL) {
      $node = $node instanceof NodeInterface ? $node : \Drupal::entityTypeManager()->getStorage('node')->load($node);
      $link->setUrl($node->toUrl());
    }

    $attributes = $link->getUrl()->getOption('attributes');

    if ($label === NULL) {
      if ($link->getUrl()->isExternal()) {
        $link->setText($this->t('Open'));
        $attributes['target'] = '_blank';
      }
      else {
        $link->setText($this->t('Go to page'));
      }
    }

    $classes = ['cd-button'];
    $classes[] = $link->getUrl()->isExternal() ? 'external' : 'read-more';
    $attributes['class'] = $classes;

    $link->getUrl()->setOption('attributes', $attributes);
    if ($is_internal) {
      $link->getUrl()->setOption('custom_path', str_replace('internal:', '', $uri));
    }
    return $link;
  }

  /**
   * Transform the given URL from an absolute path to an internal entity uri.
   *
   * @param string $url
   *   The URL to process.
   * @param string $host
   *   Optional: The host URL to use.
   *
   * @return string
   *   The transformed URL, or the original URL if no transformation can be
   *   done.
   */
  protected static function transformUrl($url, $host = NULL) {
    if (empty($url)) {
      return FALSE;
    }
    $host = $host ?? \Drupal::request()->getSchemeAndHttpHost();
    $internal_url = NULL;
    if (strpos($url, '/') === 0) {
      $internal_url = $url;
    }
    elseif (strpos($url, $host) === 0) {
      $internal_url = str_replace(rtrim($host, '/'), '', $url);
    }
    if (!$internal_url) {
      return $url;
    }
    if (strpos($internal_url, '#')) {
      $internal_url = substr($internal_url, 0, strpos($internal_url, '#'));
    }
    // This is a URL that points to an internal page.
    $path_alias_manager = self::getPathAliasManager();
    $path = $path_alias_manager->getPathByAlias($internal_url);
    $uri = Url::fromUserInput($path);
    if (!$uri || $uri->isExternal() || !$uri->isRouted() || !$uri->access()) {
      return FALSE;
    }
    $route_parameters = $uri->getRouteParameters();
    if (!empty($route_parameters['node'])) {
      $node = \Drupal::entityTypeManager()->getStorage('node')->load($route_parameters['node']);
      if ($node->toUrl()->toString() != $url) {
        return 'internal:' . $internal_url;
      }
    }
    return 'entity:' . $uri->getInternalPath();
  }

  /**
   * Gets the URI without the 'internal:' or 'entity:' scheme.
   *
   * The following two forms of URIs are transformed:
   * - 'entity:' URIs: to entity autocomplete ("label (entity id)") strings;
   * - 'internal:' URIs: the scheme is stripped.
   *
   * This method is the inverse of ::getUserEnteredStringAsUri().
   *
   * This method has been copied from LinkWidget::getUriAsDisplayableString().
   *
   * @param string $uri
   *   The URI to get the displayable string for.
   *
   * @return string
   *   The uri as a displayable (human-readably) string.
   *
   * @see LinkWidget::getUserEnteredStringAsUri()
   */
  protected static function getUriAsDisplayableString($uri) {
    $scheme = parse_url($uri, PHP_URL_SCHEME);

    // By default, the displayable string is the URI.
    $displayable_string = $uri;

    // A different displayable string may be chosen in case of the 'internal:'
    // or 'entity:' built-in schemes.
    if ($scheme === 'internal') {
      $uri_reference = explode(':', $uri, 2)[1];

      // @todo '<front>' is valid input for BC reasons, may be removed by
      //   https://www.drupal.org/node/2421941
      $path = parse_url($uri, PHP_URL_PATH);
      if ($path === '/') {
        $uri_reference = '<front>' . substr($uri_reference, 1);
      }

      $displayable_string = $uri_reference;
    }
    elseif ($scheme === 'entity') {
      [$entity_type, $entity_id] = explode('/', substr($uri, 7), 2);
      // Show the 'entity:' URI as the entity autocomplete would.
      // @todo Support entity types other than 'node'. Will be fixed in
      //   https://www.drupal.org/node/2423093.
      if ($entity_type == 'node' && $entity = \Drupal::entityTypeManager()->getStorage($entity_type)->load($entity_id)) {
        $displayable_string = EntityAutocomplete::getEntityLabels([$entity]);
      }
    }
    elseif ($scheme === 'route') {
      $displayable_string = ltrim($displayable_string, 'route:');
    }

    return $displayable_string;
  }

  /**
   * Collect a set of available link targets based on the current context.
   *
   * @param \Drupal\ghi_sections\Entity\SectionNodeInterface $section_node
   *   The section node.
   * @param \Drupal\node\NodeInterface $page_node
   *   The page node.
   *
   * @return \Drupal\node\NodeInterface[]
   *   An array of node objects, keyed by internal name.
   */
  public static function getInternalLinkTargets($section_node, $page_node) {
    $targets = [];
    if ($section_node instanceof SectionNodeInterface) {
      // Add the main section page as a target.
      if (!$page_node instanceof SectionNodeInterface) {
        $targets[$section_node->bundle()] = $section_node;
      }
      // Load all standard subpages for the section.
      $subpages = self::getSubpageManager()->loadSubpagesForBaseNode($section_node) ?? [];
      foreach ($subpages as $subpage) {
        $targets[$subpage->bundle()] = $subpage;
      }
    }
    // Add the cluster logframe as a target if currently on a cluster page.
    if ($page_node instanceof PlanClusterInterface && $page_node->getLogframeNode()) {
      $targets['cluster_logframe'] = $page_node->getLogframeNode();
    }
    if ($page_node instanceof LogframeSubpage && $page_node->getParentNode() instanceof PlanCluster) {
      $targets['cluster_parent'] = $page_node->getParentNode();
    }
    return $targets;
  }

  /**
   * Collect a set of available link options based on the current context.
   *
   * @param \Drupal\ghi_sections\Entity\SectionNodeInterface $section_node
   *   The section node.
   * @param \Drupal\node\NodeInterface $page_node
   *   The page node.
   *
   * @return array
   *   An array of link option labels, keyed by internal name, value is a label.
   */
  public static function getInternalLinkOptions($section_node, $page_node) {
    $targets = self::getInternalLinkTargets($section_node, $page_node);
    $options = [];
    foreach ($targets as $key => $target) {
      $args = [
        '@type' => strtolower($target->bundle()),
        '@uri' => $target->toUrl()->toString(),
      ];
      if ($key == 'cluster_logframe') {
        $options[$key] = ucfirst((string) new TranslatableMarkup('Cluster @type page (@uri)', $args));
      }
      if ($key == 'cluster_parent') {
        $options[$key] = ucfirst((string) new TranslatableMarkup('Cluster page (@uri)', $args));
      }
      else {
        $options[$key] = ucfirst((string) new TranslatableMarkup('@type page (@uri)', $args));
      }
    }
    return $options;
  }

  /**
   * Collect a set of available link Urls based on the current context.
   *
   * @param \Drupal\ghi_sections\Entity\SectionNodeInterface $section_node
   *   The section node.
   * @param \Drupal\node\NodeInterface $page_node
   *   The page node.
   *
   * @return \Drupal\Core\Link[]
   *   An array of link objects, keyed by internal name.
   */
  public static function getInternalLinkUrls(SectionNodeInterface $section_node, NodeInterface $page_node) {
    $targets = self::getInternalLinkTargets($section_node, $page_node);
    return array_map(function (NodeInterface $target) {
      return $target->toLink();
    }, $targets);
  }

  /**
   * Get the subpage manager service.
   *
   * @return \Drupal\ghi_subpages\SubpageManager
   *   The subpage manager.
   */
  protected static function getSubpageManager() {
    return \Drupal::service('ghi_subpages.manager');
  }

  /**
   * Get the path alias manager service.
   *
   * @return \Drupal\path_alias\AliasManager
   *   The path alias manager.
   */
  protected static function getPathAliasManager() {
    return \Drupal::service('path_alias.manager');
  }

  /**
   * Get the path validator service.
   *
   * @return \Drupal\Core\Path\PathValidator
   *   The path validator.
   */
  protected static function getPathValidator() {
    return \Drupal::service('path.validator');
  }

}
