<?php

namespace Drupal\ghi_blocks\Controller;

use Drupal\Component\Utility\Html;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Block\BlockManagerInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\KeyValueStore\KeyValueExpirableFactoryInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\ghi_blocks\Plugin\Block\GHIBlockBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Controller for rendering block configuration previews outside form state.
 */
class BlockPreviewController extends ControllerBase implements ContainerInjectionInterface {

  /**
   * The expirable key/value store factory.
   *
   * @var \Drupal\Core\KeyValueStore\KeyValueExpirableFactoryInterface
   */
  protected KeyValueExpirableFactoryInterface $keyValueExpirableFactory;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected AccountInterface $currentAccount;

  /**
   * The block plugin manager.
   *
   * @var \Drupal\Core\Block\BlockManagerInterface
   */
  protected BlockManagerInterface $blockManager;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $previewEntityTypeManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $instance = new static();
    $instance->keyValueExpirableFactory = $container->get('keyvalue.expirable');
    $instance->currentAccount = $container->get('current_user');
    $instance->blockManager = $container->get('plugin.manager.block');
    $instance->previewEntityTypeManager = $container->get('entity_type.manager');
    return $instance;
  }

  /**
   * Render a block configuration preview.
   *
   * @param string $token
   *   The token referencing the stored preview input state.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   The Ajax response replacing the preview placeholder.
   */
  public function preview(string $token): AjaxResponse {
    $entry = $this->loadPreviewState($token);
    $block = $this->blockManager->createInstance($entry['plugin_id'], $entry['configuration']);
    if (!$block instanceof GHIBlockBase) {
      throw new NotFoundHttpException();
    }

    $this->restorePreviewContexts($block, $entry['contexts'] ?? []);
    if (!empty($entry['current_uri'])) {
      $block->setCurrentUri($entry['current_uri']);
    }

    $build = $block->build();
    $preview = $build ? [
      '#theme' => 'block',
      '#attributes' => [
        'data-block-preview' => $block->getPluginId(),
      ] + ($build['#attributes'] ?? []),
      '#configuration' => $block->getConfiguration(),
      '#base_plugin_id' => $block->getBaseId(),
      '#plugin_id' => $block->getPluginId(),
      '#derivative_plugin_id' => $block->getDerivativeId(),
      '#id' => $block->getPluginId(),
      '#attached' => [
        'library' => ['ghi_blocks/block.preview'],
      ],
      'content' => $build,
    ] : ['#markup' => ''];

    $selector = '[data-block-preview-token="' . Html::escape($token) . '"]';
    $response = new AjaxResponse();
    $response->addCommand(new ReplaceCommand($selector, $preview));
    $response->headers->set('Cache-Control', 'private, no-store, max-age=0');
    return $response;
  }

  /**
   * Load preview input state.
   *
   * @param string $token
   *   The token referencing the stored preview input state.
   *
   * @return array
   *   The preview input state.
   */
  private function loadPreviewState(string $token): array {
    $store = $this->keyValueExpirableFactory->get(GHIBlockBase::CONFIGURATION_PREVIEW_COLLECTION);
    $entry = $store->get($token);
    if (empty($entry)) {
      throw new NotFoundHttpException();
    }
    if (($entry['uid'] ?? NULL) !== (int) $this->currentAccount->id()) {
      throw new AccessDeniedHttpException();
    }
    return $entry;
  }

  /**
   * Restore contexts from stored preview input state.
   *
   * @param \Drupal\ghi_blocks\Plugin\Block\GHIBlockBase $block
   *   The block being previewed.
   * @param array $contexts
   *   The stored context data.
   */
  private function restorePreviewContexts(GHIBlockBase $block, array $contexts): void {
    $context_definitions = $block->getContextDefinitions();
    foreach ($contexts as $context_name => $context_data) {
      // Runtime contexts can include values that this plugin does not declare.
      if (!array_key_exists($context_name, $context_definitions)) {
        continue;
      }
      $value = NULL;
      if (($context_data['type'] ?? NULL) === 'entity') {
        $value = $this->previewEntityTypeManager
          ->getStorage($context_data['entity_type_id'])
          ->load($context_data['id']);
      }
      elseif (($context_data['type'] ?? NULL) === 'scalar') {
        $value = $context_data['value'] ?? NULL;
      }
      if ($value !== NULL) {
        $block->setContextValue($context_name, $value);
      }
    }
  }

}
