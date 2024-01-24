<?php

namespace Drupal\ghi_form_elements\Traits;

use Drupal\Core\Entity\Element\EntityAutocomplete;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\node\NodeInterface;

/**
 * Helper trait for optional link support on form elements.
 */
trait OptionalLinkTrait {

  /**
   * {@inheritdoc}
   */
  public function getLinkFromConfiguration(array $conf) {
    if (empty($conf['add_link'])) {
      return NULL;
    }
    if (empty($conf['link']['label']) || empty($conf['link']['url'])) {
      return NULL;
    }
    try {
      $url = Url::fromUri($conf['link']['url']);
      $link = $url->access() ? Link::fromTextAndUrl($conf['link']['label'], $url) : NULL;
    }
    catch (\InvalidArgumentException $e) {
      return NULL;
    }
    if (!$link) {
      return NULL;
    }
    if ($link->getUrl()->isRouted() && $node = $link->getUrl()->getRouteParameters()['node'] ?? NULL) {
      $node = $node instanceof NodeInterface ? $node : \Drupal::entityTypeManager()->getStorage('node')->load($node);
      $link->setUrl($node->toUrl());
    }
    $classes = ['cd-button'];
    $classes[] = $link->getUrl()->isExternal() ? 'external' : 'read-more';
    $attributes = $link->getUrl()->getOption('attributes');
    $attributes['class'] = $classes;
    $link->getUrl()->setOption('attributes', $attributes);
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
    // This is a URL that points to an internal page.
    $path_alias_manager = self::getPathAliasManager();
    $path = $path_alias_manager->getPathByAlias($internal_url);
    $uri = Url::fromUserInput($path);
    if (!$uri || $uri->isExternal() || !$uri->isRouted() || !$uri->access()) {
      return FALSE;
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
