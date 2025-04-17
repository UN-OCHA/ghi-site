<?php

namespace Drupal\ghi_embargoed_access;

use Drupal\Core\Access\CsrfTokenGenerator;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Routing\RedirectDestinationInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\entity_access_password\Cache\Context\EntityIsProtectedCacheContext;
use Drupal\entity_access_password\Service\PasswordAccessManagerInterface;
use Drupal\entity_access_password\Service\RouteParserInterface;
use Drupal\ghi_content\Entity\Article;
use Drupal\ghi_content\Entity\ContentBase;
use Drupal\ghi_content\Entity\Document;
use Drupal\ghi_content\Traits\ContentPathTrait;
use Drupal\ghi_sections\Entity\SectionNodeInterface;
use Drupal\ghi_subpages\Entity\SubpageNodeInterface;
use Drupal\node\Entity\NodeType;
use Drupal\node\NodeForm;
use Drupal\node\NodeInterface;
use Drupal\search_api\Plugin\search_api\datasource\ContentEntityTrackingManager;

/**
 * Embargoed access manager service class.
 */
class EmbargoedAccessManager {

  use StringTranslationTrait;
  use ContentPathTrait;

  /**
   * The name of the protected field.
   */
  const PROTECTED_FIELD = 'field_protected';

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected $entityFieldManager;

  /**
   * The system theme config object.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The Search API tracking manager.
   *
   * @var \Drupal\search_api\Plugin\search_api\datasource\ContentEntityTrackingManager
   */
  protected $searchApiTrackingManager;

  /**
   * The CSRF token generator.
   *
   * @var \Drupal\Core\Access\CsrfTokenGenerator
   */
  protected $csrfToken;

  /**
   * The redirect destination service.
   *
   * @var \Drupal\Core\Routing\RedirectDestinationInterface
   */
  protected $redirectDestination;

  /**
   * The route parser.
   *
   * @var \Drupal\entity_access_password\Service\RouteParserInterface
   */
  protected $routeParser;

  /**
   * The password access manager.
   *
   * @var \Drupal\entity_access_password\Service\PasswordAccessManagerInterface
   */
  protected $passwordAccessManager;

  /**
   * Constructs an embargoed access manager class.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, EntityFieldManagerInterface $entity_field_manager, ConfigFactoryInterface $config_factory, ContentEntityTrackingManager $search_api_tracking_manager, CsrfTokenGenerator $csrf_token, RedirectDestinationInterface $redirect_destination, ?RouteParserInterface $route_parser = NULL, ?PasswordAccessManagerInterface $password_access_manager = NULL) {
    $this->entityTypeManager = $entity_type_manager;
    $this->entityFieldManager = $entity_field_manager;
    $this->configFactory = $config_factory;
    $this->searchApiTrackingManager = $search_api_tracking_manager;
    $this->csrfToken = $csrf_token;
    $this->redirectDestination = $redirect_destination;
    $this->routeParser = $route_parser;
    $this->passwordAccessManager = $password_access_manager;
  }

  /**
   * Check if the embargoed access is enabled or not.
   *
   * @return bool
   *   TRUE if the embargoed access is enabled, false otherwise.
   */
  public function embargoedAccessEnabled() {
    return $this->configFactory->get('ghi_embargoed_access.settings')->get('enabled');
  }

  /**
   * Check the access for the given entity.
   *
   * @param \Drupal\node\NodeInterface $entity
   *   The entity to check.
   *
   * @return bool
   *   TRUE if access should be granted, FALSE otherwise.
   */
  public function entityAccess(NodeInterface $entity) {
    if (!$this->embargoedAccessEnabled()) {
      return TRUE;
    }
    if ($this->isProtected($entity) && !$this->passwordAccessManager?->hasUserAccessToEntity($entity)) {
      return FALSE;
    }
    if ($parent = $this->getProtectedParent($entity)) {
      return $this->entityAccess($parent);
    }
    return TRUE;
  }

  /**
   * Check if there is a protected parent for the given entity.
   *
   * @param \Drupal\node\NodeInterface $entity
   *   The entity to check.
   *
   * @return \Drupal\node\NodeInterface|null
   *   The parent node or NULL.
   */
  public function getProtectedParent(NodeInterface $entity) {
    if ($entity instanceof ContentBase && $this->getCurrentSectionNode() && $this->isProtectedEntityInSection($entity)) {
      return $this->getCurrentSectionNode();
    }
    elseif ($entity instanceof Article && $this->getCurrentDocumentNode() && $this->isProtectedEntityInDocument($entity)) {
      return $this->getCurrentDocumentNode();
    }
    elseif ($entity instanceof SubpageNodeInterface && $parent = $entity->getParentBaseNode()) {
      return $this->isProtected($parent) ? $parent : NULL;
    }
    return NULL;
  }

  /**
   * Alter the view mode for an entity.
   *
   * This is used to enforce the global embargo switch and to protect subpages
   * of sections as well as articles and documents that are accessed via a
   * section specific url.
   */
  public function alterViewMode(&$view_mode, EntityInterface $entity) {
    // Quick return to avoid instantiation.
    if ($view_mode == PasswordAccessManagerInterface::PROTECTED_VIEW_MODE && $this->embargoedAccessEnabled()) {
      return;
    }

    if ($view_mode == PasswordAccessManagerInterface::PROTECTED_VIEW_MODE && !$this->embargoedAccessEnabled()) {
      // Reset the original view mode.
      $entity_access_cache_contexts = array_filter($entity->getCacheContexts(), function ($cache_context) {
        return strpos($cache_context, 'entity_access_password_entity_is_protected:') === 0;
      });
      $entity_access_cache_context = reset($entity_access_cache_contexts);
      $context_parts = $entity_access_cache_context && strpos($entity_access_cache_context, '||') ? explode('||', $entity_access_cache_context) : NULL;
      $view_mode = $context_parts ? (end($context_parts) ?: 'full') : 'full';
      return;
    }

    if ($this->embargoedAccessEnabled()) {
      if ($entity instanceof SubpageNodeInterface && $parent = $entity->getParentBaseNode()) {
        if ($this->passwordAccessManager->isEntityViewModeProtected($view_mode, $parent)) {
          $entity->addCacheContexts([EntityIsProtectedCacheContext::CONTEXT_ID . ':' . $parent->getEntityTypeId() . '||' . $parent->id() . '||' . $view_mode]);

          if (!$this->passwordAccessManager->hasUserAccessToEntity($parent)) {
            $view_mode = PasswordAccessManagerInterface::PROTECTED_VIEW_MODE;
          }
        }
      }

      if ($entity instanceof ContentBase && $section = $this->getCurrentSectionNode()) {
        if ($this->passwordAccessManager->isEntityViewModeProtected($view_mode, $section)) {
          $entity->addCacheContexts([EntityIsProtectedCacheContext::CONTEXT_ID . ':' . $section->getEntityTypeId() . '||' . $section->id() . '||' . $view_mode]);

          if (!$this->passwordAccessManager->hasUserAccessToEntity($section)) {
            $view_mode = PasswordAccessManagerInterface::PROTECTED_VIEW_MODE;
          }
        }
      }

      if ($entity instanceof ContentBase && $document = $this->getCurrentDocumentNode()) {
        if ($this->passwordAccessManager->isEntityViewModeProtected($view_mode, $document)) {
          $entity->addCacheContexts([EntityIsProtectedCacheContext::CONTEXT_ID . ':' . $document->getEntityTypeId() . '||' . $document->id() . '||' . $view_mode]);

          if (!$this->passwordAccessManager->hasUserAccessToEntity($document)) {
            $view_mode = PasswordAccessManagerInterface::PROTECTED_VIEW_MODE;
          }
        }
      }
    }
  }

  /**
   * Alter theme suggestions for node views.
   */
  public function alterNodeThemeSuggestions(&$suggestions, $variables) {
    /** @var \Drupal\node\NodeInterface $node */
    $node = $variables['elements']['#node'];
    $view_mode = $variables['elements']['#view_mode'];
    if ($view_mode == 'password_protected') {
      $suggestions[] = 'node__' . $node->bundle() . '__full';
    }
  }

  /**
   * Add cache contexts for the entity to the given theme variables.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity for which the cache contexts should be added.
   * @param array $variables
   *   The variables array to add the cache contexts to.
   */
  private function addCacheContextsToThemeVariables(EntityInterface $entity, &$variables) {
    $cacheableMetadata = new CacheableMetadata();
    $cacheableMetadata->addCacheContexts([EntityIsProtectedCacheContext::CONTEXT_ID . ':' . $entity->getEntityTypeId() . '||' . $entity->id()]);
    $cacheableMetadata->applyTo($variables);
  }

  /**
   * Alter variables for the page.
   */
  public function alterHtml(&$variables) {
    if ($this->embargoedAccessEnabled()) {
      $variables['#attached']['library'][] = 'ghi_embargoed_access/protect_nodes';
    }

    $entity = $this->getCurrentNode();
    if (!$entity instanceof NodeInterface) {
      return;
    }

    if ($this->isProtectedEntityInSection($entity)) {
      $variables['attributes']['class'][] = 'section-protected';
    }

    if ($this->isProtectedEntityInDocument($entity)) {
      $variables['attributes']['class'][] = 'document-protected';
    }

    if ($this->isProtected($entity)) {
      $variables['attributes']['class'][] = 'path-protected';
    }

    if ($this->entityAccess($entity)) {
      $variables['attributes']['class'][] = 'access-granted';
    }

    $this->addCacheContextsToThemeVariables($entity, $variables);
  }

  /**
   * Alter variables for links to nodes.
   */
  public function alterLink(&$variables) {
    /** @var \Drupal\Core\Url  $url */
    $url = $variables['url'];

    $node = NULL;
    if ($url->isRouted() && $node_id = ($url->getRouteParameters()['node'] ?? NULL)) {
      $node = $this->entityTypeManager->getStorage('node')->load($node_id);
    }
    if ($url->isRouted() && !$node) {
      return;
    }

    $node = $node ?? $this->getContentNodeFromPath($url->toString());
    if (!$node instanceof NodeInterface) {
      return;
    }

    if ($this->isProtectedEntityInSection($node) || $this->isProtectedEntityInDocument($node)) {
      $variables['options']['attributes']['class'][] = 'page-internal-protected';
    }

    if ($this->isProtected($node) || $this->isProtectedUrl($url)) {
      // Node is directly protected.
      $variables['options']['attributes']['class'][] = 'protected';
    }

    $this->addCacheContextsToThemeVariables($node, $variables);
  }

  /**
   * Alter variables for the node.
   */
  public function alterNode(&$variables) {
    $entity = $variables['node'] ?? NULL;
    if (!$entity instanceof NodeInterface || $this->entityAccess($entity)) {
      return NULL;
    }
    if ($variables['view_mode'] == 'password_protected') {
      unset($variables['label']);
    }
    else {
      $variables['label'] = $entity->label();
    }

    // Protect nothing but the summaries.
    unset($variables['content']['field_summary']);
    unset($variables['document_summary']);
    if ($variables['view_mode'] == 'password_protected') {
      $variables['attributes']['class'][] = 'content-width';
      $variables['attributes']['class'][] = 'node--view-mode-full';
    }

    if ($this->isProtectedEntityInSection($entity)) {
      $variables['attributes']['class'][] = 'section-protected';
    }
    if ($this->isProtectedEntityInDocument($entity)) {
      $variables['attributes']['class'][] = 'document-protected';
    }
    $variables['attributes']['class'][] = 'protected';

    $this->addCacheContextsToThemeVariables($entity, $variables);
  }

  /**
   * Alter node forms that use the protected field.
   */
  public function alterNodeForm(array &$form, FormStateInterface $form_state) {
    $form_object = $form_state->getFormObject();
    if (!$form_object instanceof NodeForm) {
      return;
    }
    if (empty($form[self::PROTECTED_FIELD])) {
      return;
    }
    $form[self::PROTECTED_FIELD]['widget'][0]['show_title']['#access'] = FALSE;
    $form[self::PROTECTED_FIELD]['widget'][0]['show_title']['#default_value'] = FALSE;
    $form[self::PROTECTED_FIELD]['widget'][0]['hint']['#access'] = FALSE;
    $form[self::PROTECTED_FIELD]['widget'][0]['hint']['#default_value'] = NULL;

    // Subpages have the protected field, but should inherit the protection
    // status from the section.
    $entity = $form_object->getEntity();
    if ($entity instanceof SubpageNodeInterface && $parent = $entity->getParentBaseNode()) {
      $form[self::PROTECTED_FIELD]['widget'][0]['is_protected']['#access'] = FALSE;
      $form[self::PROTECTED_FIELD]['widget'][0]['is_protected_parent'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Enable password protection'),
        '#description' => $parent->get(self::PROTECTED_FIELD)->is_protected ? $this->t("This page is currently password protected because it's section page is password protected.") : $this->t('Password protection cannot be changed here. It is inherited from the section.'),
        '#default_value' => $parent->get(self::PROTECTED_FIELD)->is_protected,
        '#disabled' => 'disabled',
      ];
    }
  }

  /**
   * Preprocess a views table display.
   *
   * Marks all rows representing protected entities with a class 'protected'.
   *
   * @param array $variables
   *   The variables passed in from hook_preprocess_views_view_table.
   */
  public function preprocessViewsTable(&$variables) {
    if (!$this->embargoedAccessEnabled()) {
      return;
    }

    /** @var \Drupal\views\ViewExecutable $view */
    $view = $variables['view'];
    if (empty($view->result)) {
      return NULL;
    }

    foreach ($variables['rows'] as $row_key => &$row) {
      $entity = $view->result[$row_key]->_entity;
      if (!$entity instanceof NodeInterface || !$this->isProtected($entity)) {
        continue;
      }
      $variables['rows'][$row_key]['attributes']->addClass('protected');
    }

    // Adding the library here is necessary in order to also support entity
    // browser views that are embedded in an iframe. Unfortunately, these do
    // not go through the typical rendering process and hook_preprocess_html
    // does not get called.
    $variables['#attached']['library'][] = 'ghi_embargoed_access/protect_nodes';
  }

  /**
   * Checks if the given node supports protection.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node to check.
   *
   * @return bool
   *   TRUE if the node supports protectin, FALSE otherwise.
   */
  public function supportsProtections(NodeInterface $node) {
    return $node->hasField(self::PROTECTED_FIELD);
  }

  /**
   * Check if the given node is protected.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node to check.
   *
   * @return bool
   *   TRUE if the node is currently protected, FALSE otherwise.
   */
  public function isProtected(NodeInterface $node) {
    if (!$this->embargoedAccessEnabled()) {
      return FALSE;
    }
    $is_protected = FALSE;
    if ($this->getProtectedParent($node)) {
      $is_protected = TRUE;
    }
    if (!$is_protected && !$node->hasField(self::PROTECTED_FIELD)) {
      return FALSE;
    }
    return $is_protected || !empty($node->get(self::PROTECTED_FIELD)->is_protected);
  }

  /**
   * Check if the given URL is protected.
   *
   * @param \Drupal\Core\Url $url
   *   The url to check.
   *
   * @return bool
   *   TRUE if the url represents a protected page (directly or indirectly),
   *   FALSE otherwise.
   */
  public function isProtectedUrl(Url $url) {
    $node = $this->getNodeByUrlAlias($url->toString());
    if ($node && $this->isProtected($node)) {
      return TRUE;
    }
    $document = $this->getDocumentNodeFromPath($url->toString());
    if ($document && $this->isProtected($document)) {
      return TRUE;
    }
    $section = $this->getSectionNodeFromPath($url->toString());
    if ($section && $this->isProtected($section)) {
      return TRUE;
    }
    return FALSE;
  }

  /**
   * Check if the given node is protected as part of a section (indirectly).
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node to check.
   *
   * @return bool
   *   TRUE if the node is currently indirectly protected, FALSE otherwise.
   */
  private function isProtectedEntityInSection($node) {
    $current_section = $this->getCurrentSectionNode();
    if (!$current_section || !$this->isProtected($current_section)) {
      return FALSE;
    }
    $is_same_section = $current_section && $node instanceof SectionNodeInterface && $node->id() == $current_section->id();
    $is_subpage_of_current_section = $current_section && $node instanceof SubpageNodeInterface && $node->getParentBaseNode()->id() == $current_section->id();
    $is_content_in_section = $current_section && $node instanceof ContentBase && $node->isPartOfSection($current_section);
    return $is_same_section || $is_subpage_of_current_section || $is_content_in_section;
  }

  /**
   * Check if the given node is protected as part of a document (indirectly).
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node to check.
   *
   * @return bool
   *   TRUE if the node is currently indirectly protected, FALSE otherwise.
   */
  private function isProtectedEntityInDocument($node) {
    $current_document = $this->getCurrentDocumentNode();
    if (!$current_document || !$this->isProtected($current_document)) {
      return FALSE;
    }
    $is_same_document = $current_document && $node instanceof Document && $node->id() == $current_document->id();
    $is_article_in_current_document = $current_document && $node instanceof Article && $current_document->hasArticle($node);
    return $is_same_document || $is_article_in_current_document;
  }

  /**
   * Protect the given node.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node to protect.
   */
  public function protectNode(NodeInterface $node) {
    if ($this->isProtected($node)) {
      // Already done.
      return;
    }
    $node->get(self::PROTECTED_FIELD)->setValue([
      'is_protected' => TRUE,
      'show_title' => FALSE,
      'hint' => '',
    ]);
    $node->setNewRevision(FALSE);
    $node->setSyncing(TRUE);
    $node->save();
    Cache::invalidateTags($node->getCacheTags());
    $this->searchApiTrackingManager->entityUpdate($node);
  }

  /**
   * Unprotect the given node.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node to unprotect.
   */
  public function unprotectNode(NodeInterface $node) {
    if (!$this->isProtected($node)) {
      // Already done.
      return;
    }
    $node->get(self::PROTECTED_FIELD)->setValue(NULL);
    $node->setNewRevision(FALSE);
    $node->setSyncing(TRUE);
    $node->save();
    Cache::invalidateTags($node->getCacheTags());
    $this->searchApiTrackingManager->entityUpdate($node);
  }

  /**
   * Mark all embargoed nodes for re-index.
   */
  public function markAllForReindex() {
    $protected_nodes = $this->entityTypeManager->getStorage('node')->loadByProperties([
      self::PROTECTED_FIELD . '.is_protected' => TRUE,
    ]);
    if (empty($protected_nodes)) {
      return;
    }
    foreach ($protected_nodes as $node) {
      $this->searchApiTrackingManager->entityUpdate($node);
    }
  }

  /**
   * Get the node types that can be embargoed.
   *
   * @return \Drupal\node\Entity\NodeType[]
   *   An array of node types.
   */
  public function getEmbargoedNodeTypes() {
    $field_storages = $this->entityFieldManager->getFieldStorageDefinitions('node');
    if (empty($field_storages[self::PROTECTED_FIELD])) {
      return [];
    }
    $field_storage = $field_storages[self::PROTECTED_FIELD];
    $bundles = $this->entityTypeManager->getStorage('node_type')->loadByProperties([
      'type' => $field_storage->getBundles(),
    ]);
    return $bundles;
  }

  /**
   * Get all embargoed nodes for the given node type.
   *
   * @param \Drupal\node\Entity\NodeType $node_type
   *   The node type for which to load the protected nodes.
   *
   * @return \Drupal\node\NodeInterface[]
   *   An array of node objects.
   */
  public function getEmbargoedNodesForNodeType(NodeType $node_type) {
    return $this->entityTypeManager->getStorage('node')->loadByProperties([
      'type' => $node_type->id(),
      self::PROTECTED_FIELD . '.is_protected' => TRUE,
    ]);
  }

  /**
   * Get the operations links for the given subpage.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node.
   *
   * @return array
   *   An array of operations links.
   */
  public function getOperationLinks(NodeInterface $node) {
    $links = [];

    if (!$this->embargoedAccessEnabled() || !$this->supportsProtections($node)) {
      return $links;
    }

    // The token for the publishing links need to be generated manually here.
    $token = $this->csrfToken->get('node/' . $node->id() . '/embargoed-access/toggle');

    $destination = $this->redirectDestination->getAsArray();

    if ($node->access('update')) {
      $route_args = ['node' => $node->id()];
      $options = [
        'query' => [
          'token' => $token,
        ] + $destination,
      ];
      $links['toggle_protected'] = [
        'title' => !$this->isProtected($node) ? $this->t('Password-protect') : $this->t("Don't password-protect"),
        'url' => Url::fromRoute('ghi_embargoed_access.toggle', $route_args, $options),
        'weight' => 60,
      ];
    }
    return $links;
  }

}
