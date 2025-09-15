<?php

namespace Drupal\ghi_subpages\LayoutBuilder;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Link;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\layout_builder\SectionStorageInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Service class for template links.
 */
class LogframeRebuildLinkBuilder {

  use StringTranslationTrait;

  /**
   * The layout builder modal config object.
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
   * Build an import link for the given entity and section storage.
   *
   * @param \Drupal\layout_builder\SectionStorageInterface $section_storage
   *   The section storage.
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity.
   * @param array $options
   *   Optional options for the url.
   *
   * @return \Drupal\Core\Link
   *   A link.
   */
  public function buildLogframeRebuildLink(SectionStorageInterface $section_storage, EntityInterface $entity, array $options = []) {
    $route_params = [
      'section_storage_type' => $section_storage->getStorageType(),
      'section_storage' => $section_storage->getStorageId(),
      'entity' => $entity->id(),
      'entity_type' => $entity->getEntityTypeId(),
    ];
    $options['attributes'] = $this->getLinkAttributes();

    // Add the redirect destination.
    $this->addRedirectDestination($options);
    $link = Link::createFromRoute($this->t('Rebuild logframe'), 'ghi_subpages.entity.logframe_rebuild', $route_params, $options);
    return $link;
  }

  /**
   * Add a redirect destination to the given url options.
   *
   * @param array $options
   *   The url options to modify.
   */
  private function addRedirectDestination(array $options) {
    $request = $this->requestStack->getCurrentRequest();
    $query = $options['query'] ?? [];
    $query['destination'] = $request->query->has('destination') ? $request->query->get('destination') : $request->getPathInfo();
    $options['query'] = $query;
  }

}
