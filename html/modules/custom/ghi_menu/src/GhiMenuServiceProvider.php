<?php

namespace Drupal\ghi_menu;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceProviderBase;
use Drupal\Core\DependencyInjection\ServiceProviderInterface;

/**
 * Service provider for GHI Menus.
 */
class GhiMenuServiceProvider extends ServiceProviderBase implements ServiceProviderInterface {

  /**
   * {@inheritdoc}
   */
  public function alter(ContainerBuilder $container) {
    $definition = $container->getDefinition('entity.autocomplete_matcher');
    $definition->setClass('Drupal\ghi_menu\GhiEntityAutocompleteMatcher');
  }

}
