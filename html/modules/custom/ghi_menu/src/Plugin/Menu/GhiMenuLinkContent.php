<?php

namespace Drupal\ghi_menu\Plugin\Menu;

use Drupal\Core\Url;
use Drupal\menu_link_content\Plugin\Menu\MenuLinkContent;

/**
 * Custom menu plugin.
 *
 * This mainly handles the GHO menu, assuring that uris like
 * '/document/xyz/article/abc' stay like that and are not shortened to
 * '/article/abc'. The reason for the shortening is that the actual article's
 * is canonical url is teh short one, but in order to provide context for
 * things like the document menu, we support manually prefixing the article uri
 * with the uri to the document that the article belongs to.
 */
class GhiMenuLinkContent extends MenuLinkContent {

  /**
   * {@inheritdoc}
   */
  public function getUrlObject($title_attribute = TRUE) {
    if ($this->pluginDefinition['menu_name'] != 'gho-menu') {
      return parent::getUrlObject($title_attribute);
    }

    $entity = $this->getEntity();
    $link = $entity->link->first() ?? NULL;
    if (!$link || strpos($link->uri, 'internal:/document/') !== 0) {
      return parent::getUrlObject($title_attribute);
    }
    $uri = str_replace('internal:/document/', 'base:/document/', $link->uri);
    return Url::fromUri($uri);
  }

}
