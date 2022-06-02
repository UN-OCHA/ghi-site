<?php

namespace Drupal\hpc_common\EventSubscriber;

use Drupal\Core\Plugin\Context\ContextRepositoryInterface;
use Drupal\page_manager\Event\PageManagerContextEvent;
use Drupal\page_manager\Event\PageManagerEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Sets the page parameters as a context.
 */
class PageParameterContext implements EventSubscriberInterface {

  /**
   * The context repository service.
   *
   * @var \Drupal\Core\Plugin\Context\ContextRepositoryInterface
   */
  protected $contextRepository;

  /**
   * Constructs a new PageParameterContext.
   *
   * @param \Drupal\Core\Plugin\Context\ContextRepositoryInterface $context_repository
   *   The context repository service.
   */
  public function __construct(ContextRepositoryInterface $context_repository) {
    $this->contextRepository = $context_repository;
  }

  /**
   * Adds the current page arguments as contexts.
   *
   * @param \Drupal\page_manager\Event\PageManagerContextEvent $event
   *   The page entity context event.
   */
  public function onPageContext(PageManagerContextEvent $event) {
    $parameters = $event->getPage()->getParameters();
    $available_contexts = $this->contextRepository->getAvailableContexts();
    foreach ($this->contextRepository->getRuntimeContexts(array_keys($available_contexts)) as $context_key => $context) {
      [, $parameter_name] = explode(':', $context_key);
      if (!array_key_exists($parameter_name, $parameters)) {
        continue;
      }
      $event->getPage()
        ->addContext($context_key, $context)
        ->addContext($parameter_name, $context);
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events = [];
    $events[PageManagerEvents::PAGE_CONTEXT][] = 'onPageContext';
    return $events;
  }

}
