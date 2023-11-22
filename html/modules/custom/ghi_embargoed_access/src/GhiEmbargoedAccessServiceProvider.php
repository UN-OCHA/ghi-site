<?php

namespace Drupal\ghi_embargoed_access;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceProviderBase;
use Drupal\Core\DependencyInjection\ServiceProviderInterface;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Provides a global switch for the protected pages service.
 */
class GhiEmbargoedAccessServiceProvider extends ServiceProviderBase implements ServiceProviderInterface {

  /**
   * {@inheritdoc}
   */
  public function alter(ContainerBuilder $container) {
    $definition = $container->getDefinition('protected_pages.check_protected_page');
    $definition->setClass('Drupal\ghi_embargoed_access\EventSubscriber\GhiEmbargoedAccessEventSubscriber')
      ->addArgument(new Reference('config.factory'))
      ->addArgument(new Reference('ghi_embargoed_access.manager'));
  }

}
