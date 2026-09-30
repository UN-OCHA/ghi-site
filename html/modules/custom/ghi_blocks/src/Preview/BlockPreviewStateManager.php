<?php

namespace Drupal\ghi_blocks\Preview;

use Drupal\Component\Uuid\UuidInterface;
use Drupal\Core\Block\BlockManagerInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\KeyValueStore\KeyValueExpirableFactoryInterface;
use Drupal\Core\KeyValueStore\KeyValueStoreExpirableInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\ghi_blocks\Plugin\Block\GHIBlockBase;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Stores and restores short-lived block preview state.
 */
final class BlockPreviewStateManager {

  /**
   * The expirable key/value collection for block preview state.
   */
  private const COLLECTION = 'ghi_blocks.block_preview_state';

  /**
   * The lifetime of stored block preview state.
   */
  private const TTL = 3600;

  /**
   * Preview tokens keyed by the in-memory block instance that owns them.
   *
   * @var \WeakMap<\Drupal\ghi_blocks\Plugin\Block\GHIBlockBase, string>
   */
  private \WeakMap $tokens;

  /**
   * Constructs a block preview state manager.
   */
  public function __construct(private readonly KeyValueExpirableFactoryInterface $keyValueExpirableFactory, private readonly UuidInterface $uuid, private readonly AccountInterface $currentUser, private readonly EntityTypeManagerInterface $entityTypeManager, private readonly BlockManagerInterface $blockManager) {
    $this->tokens = new \WeakMap();
  }

  /**
   * Store a block snapshot, reusing its existing token when possible.
   *
   * @param \Drupal\ghi_blocks\Plugin\Block\GHIBlockBase $block
   *   The block to store.
   *
   * @return string
   *   The preview state token.
   */
  public function store(GHIBlockBase $block): string {
    if (isset($this->tokens[$block])) {
      $token = $this->tokens[$block];
      $state = $this->getStore()->get($token);
      if (!empty($state) && ($state['uid'] ?? NULL) === (int) $this->currentUser->id()) {
        return $token;
      }
      unset($this->tokens[$block]);
    }

    $token = $this->uuid->generate();
    $this->getStore()->setWithExpire($token, [
      'uid' => (int) $this->currentUser->id(),
      'plugin_id' => $block->getPluginId(),
      'configuration' => $block->getConfiguration(),
      'contexts' => $this->serializeContexts($block),
      'current_uri' => $block->getCurrentUri(),
    ], self::TTL);
    $this->tokens[$block] = $token;
    return $token;
  }

  /**
   * Restore a preview block from a token owned by the current user.
   *
   * @param string $token
   *   The preview state token.
   *
   * @return \Drupal\ghi_blocks\Plugin\Block\GHIBlockBase
   *   The restored preview block.
   */
  public function restore(string $token): GHIBlockBase {
    $state = $this->loadState($token);
    $configuration = $state['configuration'];
    $configuration['is_preview'] = TRUE;
    $block = $this->blockManager->createInstance($state['plugin_id'], $configuration);
    if (!$block instanceof GHIBlockBase) {
      throw new NotFoundHttpException();
    }

    $this->restoreContexts($block, $state['contexts'] ?? []);
    if (!empty($state['current_uri'])) {
      $block->setCurrentUri($state['current_uri']);
    }
    $this->tokens[$block] = $token;
    return $block;
  }

  /**
   * Store an auxiliary resource under an existing preview token.
   *
   * @param string $token
   *   The preview state token.
   * @param string $namespace
   *   The resource namespace.
   * @param string $resource_key
   *   The resource key within the namespace.
   * @param mixed $value
   *   The value to store.
   */
  public function storeResource(string $token, string $namespace, string $resource_key, mixed $value): void {
    $this->loadState($token);
    $this->getStore()->setWithExpire($this->getResourceStoreKey($token, $namespace, $resource_key), [
      'uid' => (int) $this->currentUser->id(),
      'value' => $value,
    ], self::TTL);
  }

  /**
   * Load an auxiliary resource owned by the current user.
   *
   * @param string $token
   *   The preview state token.
   * @param string $namespace
   *   The resource namespace.
   * @param string $resource_key
   *   The resource key within the namespace.
   *
   * @return mixed|null
   *   The stored value, or NULL when no resource exists.
   */
  public function getResource(string $token, string $namespace, string $resource_key): mixed {
    $this->loadState($token);
    $entry = $this->getStore()->get($this->getResourceStoreKey($token, $namespace, $resource_key));
    if (empty($entry)) {
      return NULL;
    }
    if (($entry['uid'] ?? NULL) !== (int) $this->currentUser->id()) {
      throw new AccessDeniedHttpException();
    }
    return $entry['value'] ?? NULL;
  }

  /**
   * Load and validate the main preview state entry.
   *
   * @param string $token
   *   The preview state token.
   *
   * @return array
   *   The stored preview state.
   */
  private function loadState(string $token): array {
    $state = $this->getStore()->get($token);
    if (empty($state)) {
      throw new NotFoundHttpException();
    }
    if (($state['uid'] ?? NULL) !== (int) $this->currentUser->id()) {
      throw new AccessDeniedHttpException();
    }
    return $state;
  }

  /**
   * Get compact serializable context values for a block.
   *
   * @param \Drupal\ghi_blocks\Plugin\Block\GHIBlockBase $block
   *   The block whose contexts should be stored.
   *
   * @return array
   *   The context data keyed by context name.
   */
  private function serializeContexts(GHIBlockBase $block): array {
    $contexts = [];
    foreach ($block->getContexts() as $context_name => $context) {
      if (!$context->hasContextValue()) {
        continue;
      }
      $value = $context->getContextValue();
      if ($value instanceof EntityInterface) {
        if ($value->id() === NULL) {
          continue;
        }
        $contexts[$context_name] = [
          'type' => 'entity',
          'entity_type_id' => $value->getEntityTypeId(),
          'id' => $value->id(),
        ];
      }
      elseif (is_scalar($value) || $value === NULL) {
        $contexts[$context_name] = [
          'type' => 'scalar',
          'value' => $value,
        ];
      }
    }
    return $contexts;
  }

  /**
   * Restore declared block contexts from compact stored values.
   *
   * @param \Drupal\ghi_blocks\Plugin\Block\GHIBlockBase $block
   *   The restored block.
   * @param array $contexts
   *   The stored context data.
   */
  private function restoreContexts(GHIBlockBase $block, array $contexts): void {
    $context_definitions = $block->getContextDefinitions();
    foreach ($contexts as $context_name => $context_data) {
      if (!array_key_exists($context_name, $context_definitions)) {
        continue;
      }
      $value = NULL;
      if (($context_data['type'] ?? NULL) === 'entity') {
        $value = $this->entityTypeManager->getStorage($context_data['entity_type_id'])->load($context_data['id']);
      }
      elseif (($context_data['type'] ?? NULL) === 'scalar') {
        $value = $context_data['value'] ?? NULL;
      }
      if ($value !== NULL) {
        $block->setContextValue($context_name, $value);
      }
    }
  }

  /**
   * Get the shared expirable preview state store.
   */
  private function getStore(): KeyValueStoreExpirableInterface {
    return $this->keyValueExpirableFactory->get(self::COLLECTION);
  }

  /**
   * Build the storage key for an auxiliary preview resource.
   */
  private function getResourceStoreKey(string $token, string $namespace, string $resource_key): string {
    return implode(':', [$token, 'resource', $namespace, $resource_key]);
  }

}
