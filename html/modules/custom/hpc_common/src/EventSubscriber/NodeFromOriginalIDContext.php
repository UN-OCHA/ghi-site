<?php

namespace Drupal\hpc_common\EventSubscriber;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\page_manager\Event\PageManagerContextEvent;
use Drupal\Core\Plugin\Context\EntityContext;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\page_manager\Event\PageManagerEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RequestStack;

use Drupal\hpc_common\Helpers\NodeHelper;

/**
 * Sets a node based on an HPC ID as context.
 */
class NodeFromOriginalIDContext implements EventSubscriberInterface {

  use StringTranslationTrait;

  /**
   * The request stack.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * Constructs a new NodeFromOriginalIDContext.
   *
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   *   The request stack.
   */
  public function __construct(RequestStack $request_stack) {
    $this->requestStack = $request_stack;
  }

  /**
   * Adds in the node as a context if available.
   *
   * @param \Drupal\page_manager\Event\PageManagerContextEvent $event
   *   The page entity context event.
   */
  public function onPageContext(PageManagerContextEvent $event) {

    // Get a reference to the page entity.
    $page = $event->getPage();

    // This is in IPE context, so the information about the actual page path
    // (not the ajax path) comes in form of a POST parameter.
    $current_path = $this->requestStack->getCurrentRequest()->query->get('currentPath');

    $original_id = NULL;
    $type = NULL;

    $supported_parameters_map = [
      'plan_id' => 'plan',
      'donor_id' => 'organization',
      'country_id' => 'location',
      'emergency_id' => 'emergency',
    ];

    if (!$current_path) {
      $request = $this->requestStack->getCurrentRequest();

      foreach ($supported_parameters_map as $key => $bundle) {
        if (!$page->hasParameter($key) && !$request->attributes->has($key)) {
          continue;
        }
        $type = $bundle;

        if (!$request->attributes->has($key)) {
          continue;
        }
        $original_id = (int) $request->attributes->get($key);
      }
    }
    else {
      // This is in IPE context.
      $current_args = explode('/', $current_path);

      $page_path_pattern = ltrim($page->getPath(), '/');
      $page_args = explode('/', $page_path_pattern);
      foreach ($page_args as $key => $page_arg) {
        if (strpos($page_arg, '{') !== 0) {
          continue;
        }
        $placeholder_key = trim($page_arg, '{}');
        if (empty($supported_parameters_map[$placeholder_key])) {
          continue;
        }
        $type = $supported_parameters_map[$placeholder_key];
        $original_id = !empty($current_args[$key]) ? $current_args[$key] : NULL;
      }

    }

    if (!$type) {
      return;
    }

    $node = NULL;
    if ($original_id && $type) {
      $node = NodeHelper::getNodeFromOriginalId($original_id, $type);
    }

    if ($node) {
      $context = EntityContext::fromEntity($node, (string) $this->t('Node from Original ID'));
    }
    else {
      // If no suitable node is available, provide an empty context object.
      $context = EntityContext::fromEntityTypeId('node', (string) $this->t('Node from Original ID'));
    }

    $cacheability = new CacheableMetadata();
    $cacheability->setCacheContexts(['route']);
    $context->addCacheableDependency($cacheability);
    $event->getPage()->addContext('node_from_original_id', $context);
    $event->getPage()->addContext('node', $context);
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events = [];
    if (class_exists('PageManagerEvents')) {
      $events[PageManagerEvents::PAGE_CONTEXT][] = 'onPageContext';
    }
    return $events;
  }

}
