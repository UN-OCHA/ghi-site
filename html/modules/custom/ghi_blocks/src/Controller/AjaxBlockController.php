<?php

namespace Drupal\ghi_blocks\Controller;

use Drupal\Component\Plugin\Exception\ContextException;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\OpenModalDialogCommand;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Plugin\Context\EntityContext;
use Drupal\ghi_blocks\Plugin\Block\GHIBlockBase;
use Drupal\hpc_common\Helpers\BlockHelper;
use Drupal\layout_builder\Event\SectionComponentBuildRenderArrayEvent;
use Drupal\layout_builder\LayoutBuilderEvents;
use Drupal\layout_builder\LayoutEntityHelperTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Controller class for ajax interactions on blocks in GHI.
 */
class AjaxBlockController extends ControllerBase implements ContainerInjectionInterface {

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
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $instance = new static();
    $instance->currentRequest = $container->get('request_stack')->getCurrentRequest();
    $instance->eventDispatcher = $container->get('event_dispatcher');
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
    if (!$uri) {
      return $this->sendErrorResponse();
    }
    $block_instance = BlockHelper::getBlockInstance($uri, $plugin_id, $block_uuid);
    if (!$block_instance) {
      return $this->sendErrorResponse();
    }

    $node = $block_instance->getPageNode();
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

    $build = NULL;
    if ($section_component) {
      try {
        $event = new SectionComponentBuildRenderArrayEvent($section_component, $contexts, FALSE);
        $this->eventDispatcher->dispatch($event, LayoutBuilderEvents::SECTION_COMPONENT_BUILD_RENDER_ARRAY);
        $build = $event->getBuild();
      }
      catch (ContextException $e) {
        // Just fail silently.
      }
    }
    if (!$build) {
      // This is our fallback.
      $build = $block_instance->build();
    }

    $selector = '.ghi-block-' . $block_instance->getUuid();
    $ajax_response = new AjaxResponse();
    $ajax_response->addCommand(new ReplaceCommand($selector, $build));
    return $ajax_response;
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
