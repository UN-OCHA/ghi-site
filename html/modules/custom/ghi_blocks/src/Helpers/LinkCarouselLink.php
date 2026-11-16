<?php

namespace Drupal\ghi_blocks\Helpers;

use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\EntityPublishedInterface;
use Drupal\Core\Session\AnonymousUserSession;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;

/**
 * Resolves editorial destinations and their current public availability.
 */
final class LinkCarouselLink {

  /**
   * Returns a supported URI, or NULL for a missing or invalid destination.
   *
   * External destinations are validated without network requests.
   * Internal destinations must exist but may still be unpublished drafts.
   */
  public static function normalize(string $value): ?string {
    return self::resolveDestination($value, new CacheableMetadata(), FALSE)['uri'];
  }

  /**
   * Resolves a public destination once, retaining unavailable dependencies.
   *
   * @param string $value
   *   An editorial URL, internal path or entity URI.
   * @param \Drupal\Core\Cache\CacheableMetadata|null $cache
   *   Optional destination and anonymous-access cache metadata.
   *
   * @return array{url: ?\Drupal\Core\Url, issues: \Drupal\Core\StringTranslation\TranslatableMarkup[]}
   *   A usable URL or the specific reasons why it is currently unavailable.
   */
  public static function resolve(string $value, ?CacheableMetadata $cache = NULL): array {
    $cache ??= new CacheableMetadata();
    $result = self::resolveDestination($value, $cache, TRUE);
    unset($result['uri']);
    return $result;
  }

  /**
   * Parses a supported destination, optionally checking public availability.
   */
  private static function resolveDestination(string $value, CacheableMetadata $cache, bool $check_access): array {
    // Alias changes can make existing and unresolved paths point elsewhere.
    $cache->addCacheTags(['route_match']);
    $value = trim($value);
    if ($value === '' || str_starts_with($value, '//')) {
      return self::invalidDestination($cache);
    }
    if (str_starts_with($value, '/')) {
      $value = 'internal:' . $value;
    }
    $scheme = strtolower(parse_url($value, PHP_URL_SCHEME) ?: '');
    $entity = NULL;
    try {
      if (in_array($scheme, ['http', 'https'], TRUE)) {
        if (!UrlHelper::isValid($value, TRUE)) {
          return self::invalidDestination($cache);
        }
        $url = Url::fromUri($value);
      }
      elseif ($scheme === 'internal') {
        $path = substr($value, strlen('internal:'));
        if (!str_starts_with($path, '/') || str_starts_with($path, '//')) {
          return self::invalidDestination($cache);
        }
        $url = \Drupal::pathValidator()->getUrlIfValidWithoutAccessCheck($path);
        if (!$url || !$url->isRouted()) {
          return self::invalidDestination($cache);
        }
        // Drupal's built-in URI handling expects a lowercase scheme.
        $value = 'internal:' . $path;
      }
      elseif ($scheme === 'entity') {
        $parts = explode('/', parse_url(substr($value, strlen('entity:')), PHP_URL_PATH) ?: '', 2);
        [$type, $id] = $parts + [NULL, NULL];
        $manager = \Drupal::entityTypeManager();
        if (!$type || !$id || !$manager->hasDefinition($type)) {
          return self::invalidDestination($cache);
        }
        // Url::fromUri() assumes both entity URI parts are present.
        $value = 'entity:' . substr($value, strlen('entity:'));
        $url = Url::fromUri($value);
        $entity = $manager->getStorage($type)->load($id);
        if (!$url->isRouted() || !$entity || !$entity->hasLinkTemplate('canonical')) {
          $cache->addCacheTags($manager->getDefinition($type)->getListCacheTags());
          return self::invalidDestination($cache);
        }
      }
      else {
        return self::invalidDestination($cache);
      }

      if ($url->isRouted() && !$entity) {
        $node_id = $url->getRouteParameters()['node'] ?? NULL;
        if (is_scalar($node_id)) {
          // Keep raw route parameters: the access manager converts them itself.
          $entity = \Drupal::entityTypeManager()->getStorage('node')->load($node_id);
          if (!$entity) {
            return self::invalidDestination($cache);
          }
        }
      }
      if ($entity) {
        $cache->addCacheableDependency($entity);
      }
      $issues = [];
      if ($check_access) {
        // The editor and public page must agree, regardless of the permissions
        // of the account currently configuring or previewing the element.
        $access = $url->access(new AnonymousUserSession(), TRUE);
        $cache->addCacheableDependency($access);
        if ($entity instanceof EntityPublishedInterface && !$entity->isPublished()) {
          $issues[] = new TranslatableMarkup('This link points to unpublished content.');
        }
        elseif (!$access->isAllowed()) {
          $issues[] = new TranslatableMarkup('This link is not currently accessible to anonymous visitors.');
        }
      }
      return ['uri' => $value, 'url' => $issues ? NULL : $url, 'issues' => $issues];
    }
    catch (\InvalidArgumentException) {
      return self::invalidDestination($cache);
    }
  }

  /**
   * Retains dependencies for destinations that may become available later.
   */
  private static function invalidDestination(CacheableMetadata $cache): array {
    $cache->addCacheTags(['node_list']);
    return [
      'uri' => NULL,
      'url' => NULL,
      'issues' => [new TranslatableMarkup('Enter a valid URL or an existing content link.')],
    ];
  }

}
