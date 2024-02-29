<?php

namespace Drupal\ghi_templates;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\ghi_templates\Entity\PageTemplateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Manager class for page templates.
 */
class PageTemplateManager implements ContainerInjectionInterface {

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Public constructor.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
    );
  }

  /**
   * Load a page template by it's id.
   *
   * @param int $id
   *   The id of the page template to load.
   *
   * @return \Drupal\ghi_templates\Entity\PageTemplateInterface
   *   The page template entity.
   */
  public function loadPageTemplate($id) {
    return $this->entityTypeManager->getStorage('page_template')->load($id);
  }

  /**
   * Load page templates available for the given entity.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity for which to load page templates.
   *
   * @return \Drupal\ghi_templates\Entity\PageTemplateInterface[]
   *   An array of page tenmplate entities.
   */
  public function loadAvailableTemplatesForEntity(EntityInterface $entity) {
    $page_templates = $this->entityTypeManager->getStorage('page_template')->loadByProperties([
      'status' => TRUE,
    ]);
    $page_templates = array_filter($page_templates, function (PageTemplateInterface $page_template) use ($entity) {
      $source = $page_template->getSourceEntity();
      if (!$source) {
        return FALSE;
      }
      if ($source->getEntityTypeId() != $entity->getEntityTypeId()) {
        return FALSE;
      }
      if ($entity->getEntityType()->hasKey('bundle')) {
        return $source->bundle() == $entity->bundle();
      }
      return TRUE;
    });
    return $page_templates;
  }

}
