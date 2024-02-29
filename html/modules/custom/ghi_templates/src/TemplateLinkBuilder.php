<?php

namespace Drupal\ghi_templates;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\layout_builder\Plugin\SectionStorage\DefaultsSectionStorage;
use Drupal\layout_builder\SectionStorageInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Service class for template links.
 */
class TemplateLinkBuilder {

  use StringTranslationTrait;

  /**
   * The layout builder ipe config object.
   *
   * @var \Drupal\Core\Config\Config
   */
  protected $modalConfig;

  /**
   * The request stack.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * Public constructor.
   */
  public function __construct(ConfigFactoryInterface $config_factory, RequestStack $request_stack) {
    $this->modalConfig = $config_factory->get('layout_builder_modal.settings');
    $this->requestStack = $request_stack;
  }

  /**
   * Get the common attributes for template links.
   *
   * @return array
   *   The link attributes array.
   */
  private function getLinkAttributes() {
    return [
      'class' => [
        'use-ajax',
      ],
      'data-dialog-type' => 'dialog',
      'data-dialog-options' => Json::encode([
        'width' => $this->modalConfig->get('modal_width'),
        'height' => $this->modalConfig->get('modal_height'),
        'target' => 'layout-builder-modal',
        'autoResize' => $this->modalConfig->get('modal_autoresize'),
        'modal' => TRUE,
      ]),
    ];
  }

  /**
   * Build an apply link for the given entity and section storage.
   *
   * @param \Drupal\layout_builder\SectionStorageInterface $section_storage
   *   The section storage.
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity.
   * @param array $options
   *   Optional options for the url.
   *
   * @return array
   *   A link array to be used in dropbutton elements.
   */
  public function buildApplyPageTemplateLink(SectionStorageInterface $section_storage, EntityInterface $entity, array $options = []) {
    $route_params = [
      'section_storage_type' => $section_storage->getStorageType(),
      'section_storage' => $section_storage->getStorageId(),
    ];
    $url = Url::fromRoute('ghi_templates.entity.page_template.apply', $route_params + [
      'entity' => $entity->id(),
      'entity_type' => $entity->getEntityTypeId(),
    ], $options);
    if (!$url->access()) {
      return NULL;
    }

    // Add the redirect destination.
    $this->addRedirectDestination($url);

    return [
      'title' => $this->t('Page templates'),
      'url' => $url,
      'attributes' => $this->getLinkAttributes(),
    ];
  }

  /**
   * Build an apply link for the given entity and section storage.
   *
   * @param \Drupal\layout_builder\SectionStorageInterface $section_storage
   *   The section storage.
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity.
   * @param array $options
   *   Optional options for the url.
   *
   * @return array
   *   A link array to be used in dropbutton elements.
   */
  public function buildStorePageTemplateLink(SectionStorageInterface $section_storage, EntityInterface $entity, array $options = []) {
    $route_params = [
      'section_storage_type' => $section_storage->getStorageType(),
      'section_storage' => $section_storage->getStorageId(),
    ];
    $url = Url::fromRoute('ghi_templates.entity.page_template.store', $route_params + [
      'entity' => $entity->id(),
      'entity_type' => $entity->getEntityTypeId(),
    ], $options);
    if (!$url->access()) {
      return NULL;
    }

    // Add the redirect destination.
    $this->addRedirectDestination($url);

    return [
      'title' => $this->t('Save as template'),
      'url' => $url,
      'attributes' => $this->getLinkAttributes(),
    ];
  }

  /**
   * Build an import link for the given entity and section storage.
   *
   * @param \Drupal\layout_builder\SectionStorageInterface $section_storage
   *   The section storage.
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity.
   * @param array $options
   *   Optional options for the url.
   *
   * @return array
   *   A link array to be used in dropbutton elements.
   */
  public function buildImportLink(SectionStorageInterface $section_storage, EntityInterface $entity, array $options = []) {
    $route_params = [
      'section_storage_type' => $section_storage->getStorageType(),
      'section_storage' => $section_storage->getStorageId(),
    ];
    $url = Url::fromRoute('ghi_templates.entity.page_config.import', $route_params + [
      'entity' => $entity->id(),
      'entity_type' => $entity->getEntityTypeId(),
    ], $options);
    if (!$url->access()) {
      return NULL;
    }

    // Add the redirect destination.
    $this->addRedirectDestination($url);

    return [
      'title' => $this->t('Import'),
      'url' => $url,
      'attributes' => $this->getLinkAttributes(),
    ];
  }

  /**
   * Build an export link for the given entity and section storage.
   *
   * @param \Drupal\layout_builder\SectionStorageInterface $section_storage
   *   The section storage.
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity.
   *
   * @return array
   *   A link array to be used in dropbutton elements.
   */
  public function buildExportLink(SectionStorageInterface $section_storage, EntityInterface $entity) {
    if ($section_storage instanceof DefaultsSectionStorage) {
      return NULL;
    }
    $route_params = [
      'section_storage_type' => $section_storage->getStorageType(),
      'section_storage' => $section_storage->getStorageId(),
    ];
    $url = Url::fromRoute('ghi_templates.entity.page_config.export', $route_params);
    if (!$url->access()) {
      return NULL;
    }

    // Add the redirect destination.
    $this->addRedirectDestination($url);

    return [
      'title' => $this->t('Export'),
      'url' => $url,
      'attributes' => $this->getLinkAttributes(),
    ];
  }

  /**
   * Add a redirect destination to the given url.
   *
   * @param \Drupal\Core\Url $url
   *   The url to modify.
   */
  private function addRedirectDestination(Url $url) {
    $request = $this->requestStack->getCurrentRequest();
    $query = $url->getOption('query');
    $query['destination'] = $request->query->has('destination') ? $request->query->get('destination') : $request->getPathInfo();
    $url->setOption('query', $query);
  }

}
