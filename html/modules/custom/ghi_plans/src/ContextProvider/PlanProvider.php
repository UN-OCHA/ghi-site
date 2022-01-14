<?php

namespace Drupal\ghi_plans\ContextProvider;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Plugin\Context\ContextProviderInterface;
use Drupal\Core\Plugin\Context\ContextRepositoryInterface;
use Drupal\Core\Plugin\Context\EntityContext;
use Drupal\Core\Plugin\Context\EntityContextDefinition;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\ghi_base_objects\Entity\BaseObjectInterface;

/**
 * Provides a "plan" context.
 */
class PlanProvider implements ContextProviderInterface {

  use StringTranslationTrait;

  const SERVICE_KEY = '@ghi_plans.plan_context:plan';

  /**
   * The request stack.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * Constructs a new PlanProvider class.
   *
   * @param \Drupal\Core\Plugin\Context\ContextRepositoryInterface $context_repository
   *   The context repository.
   */
  public function __construct(ContextRepositoryInterface $context_repository) {
    $this->contextRepository = $context_repository;
  }

  /**
   * {@inheritdoc}
   */
  public function getRuntimeContexts(array $unqualified_context_ids) {
    $result = [];

    $base_object_context = $this->getCurrentBaseObjectContext();
    $base_object = $base_object_context ? $base_object_context->getContextData()->getValue() : NULL;

    if ($base_object instanceof BaseObjectInterface && $base_object->bundle() == 'plan') {
      $context = EntityContext::fromEntity($base_object, (string) $this->t('Plan'));

      // We are reusing cache contexts.
      $cacheContexts = $base_object_context->getCacheContexts();
      $cacheability = new CacheableMetadata();
      $cacheability->setCacheContexts($cacheContexts);
      $context->addCacheableDependency($cacheability);

      $result['plan'] = $context;
    }
    return $result;
  }

  /**
   * {@inheritdoc}
   */
  public function getAvailableContexts() {
    $contexts = $this->getRuntimeContexts([]);
    if (empty($contexts) && $this->getCurrentBaseObjectContext()) {
      $contexts['plan'] = new EntityContext(new EntityContextDefinition('base_object'), $this->t('Plan'));
    }
    return $contexts;
  }

  /**
   * Get the current base object.
   *
   * @return \Drupal\Core\Plugin\Context\EntityContext|null
   *   A base object context if found.
   */
  private function getCurrentBaseObjectContext() {
    $base_object_context_key = '@ghi_base_objects.base_object_context:base_object';
    $runtime_contexts = $this->contextRepository->getRuntimeContexts([$base_object_context_key]);
    return array_key_exists($base_object_context_key, $runtime_contexts) ? $runtime_contexts[$base_object_context_key] : NULL;
  }

}
