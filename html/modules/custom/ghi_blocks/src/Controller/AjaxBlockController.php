<?php

namespace Drupal\ghi_blocks\Controller;

use Drupal\Component\Plugin\Exception\ContextException;
use Drupal\Component\Utility\Html;
use Drupal\Core\Access\AccessManagerInterface;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Ajax\AjaxHelperTrait;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\OpenModalDialogCommand;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Plugin\Context\EntityContext;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Drupal\ghi_blocks\Plugin\Block\GHIBlockBase;
use Drupal\ghi_blocks\Plugin\Block\Plan\PlanEntityLogframe;
use Drupal\hpc_common\Helpers\BlockHelper;
use Drupal\layout_builder\Event\SectionComponentBuildRenderArrayEvent;
use Drupal\layout_builder\LayoutBuilderEvents;
use Drupal\layout_builder\LayoutEntityHelperTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Controller class for ajax interactions on blocks in GHI.
 */
class AjaxBlockController extends ControllerBase implements ContainerInjectionInterface {

  use AjaxHelperTrait;
  use LayoutEntityHelperTrait;

  /**
   * The current request.
   *
   * @var \Symfony\Component\HttpFoundation\Request
   */
  protected $currentRequest;

  /**
   * The event dispatcher.
   *
   * @var \Symfony\Contracts\EventDispatcher\EventDispatcherInterface
   */
  protected $eventDispatcher;

  /**
   * The access manager.
   *
   * @var \Drupal\Core\Access\AccessManagerInterface
   */
  protected AccessManagerInterface $accessManager;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected AccountInterface $currentAccount;

  /**
   * The router.
   *
   * @var \Drupal\Core\Routing\Router
   */
  protected $router;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $instance = new static();
    $instance->currentRequest = $container->get('request_stack')->getCurrentRequest();
    $instance->eventDispatcher = $container->get('event_dispatcher');
    $instance->accessManager = $container->get('access_manager');
    $instance->currentAccount = $container->get('current_user');
    $instance->router = $container->get('router.no_access_checks');
    return $instance;
  }

  /**
   * Load a block and replace it in the page.
   *
   * @param string $plugin_id
   *   The plugin id.
   * @param string $block_uuid
   *   The blocks UUID.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   The response object.
   */
  public function loadBlock($plugin_id, $block_uuid) {
    $uri = $this->currentRequest->query->get('current_uri') ?? NULL;

    if (!$this->isAjax()) {
      // Handle non-ajax request, e.g. when the manually selects to open the
      // link in a new window/tab.
      $url = $uri ? Url::fromUserInput($uri) : NULL;
      if (!$uri || !$url || !$url->isRouted()) {
        throw new NotFoundHttpException();
      }
      return $this->redirect($url->getRouteName(), $url->getRouteParameters());
    }

    if (!$uri) {
      return $this->sendErrorResponse();
    }
    $block_instance = BlockHelper::getBlockInstance($uri, $plugin_id, $block_uuid);
    if (!$block_instance) {
      return $this->sendErrorResponse();
    }

    $build = NULL;
    $selector = '.block-' . Html::getClass($block_instance->getPluginId()) . ' > .block-content';

    $node = $block_instance->getPageNode();
    if ($node) {
      // This is for node based pages, e.g. sections. It would not work for
      // page manager pages.
      $contexts = $block_instance->getContexts();
      $contexts['layout_builder.entity'] = EntityContext::fromEntity($node);

      // Try to find the section component to which the block belongs. If that
      // works, we can use the SectionComponentBuildRenderArrayEvent to have the
      // content build, instead of calling ::build directly on the block plugin.
      // This will assure that all process hooks are called as for the original
      // build of the block, thus containing all admin links too.
      $sections = $this->getEntitySections($node);
      $section_component = NULL;
      foreach ($sections as $section) {
        foreach ($section->getComponents() as $component) {
          $plugin = $component->getPlugin();
          if (!$plugin instanceof GHIBlockBase) {
            continue;
          }
          $plugin_uuid = $plugin->getUuid() ?? $component->getUuid();
          if ($plugin_uuid != $block_uuid) {
            continue;
          }
          $section_component = $component;
        }
      }

      if ($section_component) {
        try {
          $event = new SectionComponentBuildRenderArrayEvent($section_component, $contexts, FALSE);
          $this->eventDispatcher->dispatch($event, LayoutBuilderEvents::SECTION_COMPONENT_BUILD_RENDER_ARRAY);
          $build = $event->getBuild();
          $selector = '.ghi-block-' . $block_instance->getUuid();
        }
        catch (ContextException $e) {
          // Just fail silently.
        }
      }
    }
    if (!$build) {
      // This is our fallback.
      $build = $block_instance->build();
    }

    $ajax_response = new AjaxResponse();
    $ajax_response->addCommand(new ReplaceCommand($selector, $build));
    return $ajax_response;
  }

  /**
   * Load a resolved logframe item for a single entity.
   *
   * @param string $plugin_id
   *   The plugin id.
   * @param string $block_uuid
   *   The block UUID.
   * @param string $entity_id
   *   The logframe entity id.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   The response object.
   */
  public function loadLogframeItem($plugin_id, $block_uuid, $entity_id) {
    $uri = $this->currentRequest->query->get('current_uri') ?? NULL;

    if (!$this->isAjax() || !$uri) {
      throw new NotFoundHttpException();
    }

    if (!$this->checkUriAccess($uri)->isAllowed()) {
      throw new AccessDeniedHttpException();
    }

    $block_instance = BlockHelper::getBlockInstance($uri, $plugin_id, $block_uuid);
    if (!$block_instance instanceof PlanEntityLogframe) {
      return $this->sendErrorResponse();
    }

    $build = $block_instance->buildAjaxLogframeItem((int) $entity_id);
    if (!$build) {
      return $this->sendErrorResponse();
    }

    $selector = sprintf(
      '.item-wrapper[data-logframe-block="%s"][data-logframe-entity="%d"]',
      Html::escape($block_uuid),
      (int) $entity_id,
    );

    $ajax_response = new AjaxResponse();
    $ajax_response->addCommand(new ReplaceCommand($selector, $build));
    return $ajax_response;
  }

  /**
   * Check access for the page URI used to rebuild a lazy block fragment.
   *
   * @param string $uri
   *   The current page URI supplied by the lazy block request.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The route access result with cacheability metadata.
   */
  protected function checkUriAccess(string $uri): AccessResultInterface {
    $request = Request::create($uri);
    try {
      // The endpoint needs no-access matching only to identify the page route.
      // Access is checked explicitly below before any block fragment is built.
      $request->attributes->add($this->router->matchRequest($request));
    }
    catch (\Exception $e) {
      throw new NotFoundHttpException();
    }

    return $this->accessManager->checkRequest($request, $this->currentAccount, TRUE);
  }

  /**
   * Show an error message as a modal.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   The response object.
   */
  private function sendErrorResponse() {
    $ajax_response = new AjaxResponse();
    $ajax_response->setAttachments(['library' => ['core/drupal.dialog.ajax']]);
    $ajax_response->addCommand(new OpenModalDialogCommand($this->t('Error'), $this->t('There was a problem serving the request. Please try again later.'), [
      'classes' => [
        'ui-dialog' => 'ajax-block-error',
      ],
      'width' => '50%',
    ]));
    return $ajax_response;
  }

}
