<?php

namespace Drupal\ghi_blocks\Controller;

use Drupal\Component\Utility\Html;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\OpenModalDialogCommand;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Http\RequestStack;
use Drupal\hpc_common\Helpers\BlockHelper;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Controller class for ajax interactions on blocks in GHI.
 */
class AjaxBlockController extends ControllerBase implements ContainerInjectionInterface {

  /**
   * The current request.
   *
   * @var \Symfony\Component\HttpFoundation\Request
   */
  protected $currentRequest;

  /**
   * {@inheritdoc}
   */
  public function __construct(RequestStack $request_stack) {
    $this->currentRequest = $request_stack->getCurrentRequest();
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('request_stack'),
    );
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
    $build = $block_instance->build();
    $selector = '.block-' . Html::getClass($block_instance->getPluginId()) . ' > .block-content';
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
