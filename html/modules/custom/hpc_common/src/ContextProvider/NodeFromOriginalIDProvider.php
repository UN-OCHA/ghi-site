<?php

namespace Drupal\hpc_common\ContextProvider;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\Context\ContextProviderInterface;
use Drupal\Core\Plugin\Context\EntityContext;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\hpc_common\Helpers\NodeHelper;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Sets a node based on an HPC ID as context.
 */
class NodeFromOriginalIDProvider implements ContextProviderInterface {

  use StringTranslationTrait;

  /**
   * The route provider.
   *
   * @var \Drupal\Core\Routing\RouteProviderInterface
   */
  protected $routeProvider;

  /**
   * The request stack.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a new CurrentUserContext.
   *
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   *   The request stack.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(RequestStack $request_stack, EntityTypeManagerInterface $entity_type_manager) {
    $this->requestStack = $request_stack;
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public function getRuntimeContexts(array $unqualified_context_ids) {
    $original_id = NULL;
    $type = NULL;
    $result = [];

    $request = $this->requestStack->getCurrentRequest();
    $supported_parameters_map = [
      'plan_id' => 'plan',
      'donor_id' => 'organization',
      'country_id' => 'location',
      'emergency_id' => 'emergency',
    ];

    if ($request->attributes->has('node')) {
      $node = $request->attributes->get('node');
      $entity_storage = $this->entityTypeManager->getStorage('node');
      $node = is_object($node) ? $node : $entity_storage->load($node);
      if (in_array($node->getType(), $supported_parameters_map)) {
        $context = EntityContext::fromEntity($node, $this->t('Node from Original ID'));
        return [
          'node_from_original_id' => $context,
          'node' => $context,
        ];
      }
    }

    foreach ($supported_parameters_map as $key => $bundle) {
      if (!$request->attributes->has($key)) {
        continue;
      }
      $original_id = (int) $request->attributes->get($key);
      $type = $bundle;
    }

    $node = NULL;
    if ($original_id && $type) {
      $node = NodeHelper::getNodeFromOriginalId($original_id, $type);
    }

    $context = NULL;
    if ($node) {
      $context = EntityContext::fromEntity($node, (string) $this->t('Node from Original ID'));
    }
    else {
      // If no suitable node is available, provide an empty context object.
      $context = EntityContext::fromEntityTypeId('node', (string) $this->t('Node from Original ID'));
    }

    $result = [
      'node_from_original_id' => $context,
      'node' => $context,
    ];
    return $result;
  }

  /**
   * {@inheritdoc}
   */
  public function getAvailableContexts() {
    return $this->getRuntimeContexts([]);
  }

}
