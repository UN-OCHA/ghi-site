<?php

namespace Drupal\ghi_content\EventSubscriber;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\ghi_content\ContentManager\ManagerFactory;
use Drupal\migrate\Event\MigrateEvents;
use Drupal\migrate\Event\MigratePostRowSaveEvent;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Reacting to content nodes beeing imported/updated.
 *
 * @package Drupal\ghi_content\EventSubscriber
 */
class PostRowSaveEventSubscriber implements EventSubscriberInterface {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The remote source manager.
   *
   * @var \Drupal\ghi_content\ContentManager\ManagerFactory
   */
  protected $managerFactory;

  /**
   * Create an instance of the class.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   Core's entity query.
   * @param \Drupal\ghi_content\ContentManager\ManagerFactory $manager_factory
   *   Core's entity query.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, ManagerFactory $manager_factory) {
    $this->entityTypeManager = $entity_type_manager;
    $this->managerFactory = $manager_factory;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('ghi_content.manager.factory'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[MigrateEvents::POST_ROW_SAVE][] = ['onMigratePostRowSave'];
    return $events;
  }

  /**
   * React to an entity having been saved during a migration run.
   *
   * Check update imported or updated article or document nodes with their
   * version from the remote source system.
   *
   * @param \Drupal\migrate\Event\MigratePostRowSaveEvent $event
   *   The event object.
   */
  public function onMigratePostRowSave(MigratePostRowSaveEvent $event) {
    $migration_ids = [
      'articles_hpc_content_module',
      'documents_hpc_content_module',
    ];
    if (!in_array($event->getMigration()->id(), $migration_ids)) {
      return;
    }
    if (!$event->getRow()->changed()) {
      return;
    }
    $ids = $event->getDestinationIdValues();
    $entities = $this->entityTypeManager->getStorage('node')->loadMultiple($ids);
    foreach ($entities as $entity) {
      $content_manager = $this->managerFactory->getContentManager($entity);
      if (!$content_manager) {
        continue;
      }
      $content_manager->updateNodeFromRemote($entity);
      $content_manager->saveContentNode($entity);
    }
  }

}
