<?php

namespace Drupal\ghi_user\Form;

use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Block\BlockManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\Routing\RedirectDestinationTrait;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Class for altering the login form.
 *
 * @package Drupal\ghi_user\Form
 */
class LoginFormAlter {

  use RedirectDestinationTrait;
  use StringTranslationTrait;

  /**
   * The module handler service.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * The block manager service.
   *
   * @var \Drupal\Core\Block\BlockManagerInterface
   */
  protected $blockManager;

  /**
   * Constructs a document manager.
   */
  public function __construct(ModuleHandlerInterface $module_handler, BlockManagerInterface $block_manager) {
    $this->moduleHandler = $module_handler;
    $this->blockManager = $block_manager;
  }

  /**
   * Alter the login form.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state object.
   */
  public function alterLoginForm(&$form, FormStateInterface $form_state) {
    $form['name']['#access'] = FALSE;
    $form['pass']['#access'] = FALSE;
    $form['actions']['submit']['#access'] = FALSE;
    $form['#attributes']['class'][] = 'content-width';

    $message = [
      [
        '#type' => 'html_tag',
        '#tag' => 'p',
        '#value' => $this->t('If you have a UN agency account you can log in directly.'),
      ],
      [
        '#type' => 'html_tag',
        '#tag' => 'p',
        '#value' => $this->t('If you do not have a UN agency account, then you will first need your email address to be added to an approved user list. Contact <a href="mailto:ocha-hpc@un.org">ocha-hpc@un.org</a> to request this. Once added, you can use the ‘UN agency’ link below with that email address to log in.'),
      ],
    ];

    $route_name = 'ocha_entraid.login';

    $options = [
      'attributes' => [
        'class' => [
          'login-link',
          'cd-button',
        ],
      ],
    ];

    $destination = $this->getRedirectDestination()->get();
    if ($destination) {
      $options['query'] = [
        'destination' => '/' . ltrim($destination, '/'),
      ];
    }

    $login_attributes = [
      'attributes' => [
        'class' => [Html::getClass('login-link--un')],
      ],
    ];
    $login_link = Link::createFromRoute($this->t('Continue with your UN Agency email'), $route_name, [], NestedArray::mergeDeep($options, $login_attributes));

    $build = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['site-login'],
      ],
      'message' => [
        '#type' => 'html_tag',
        '#tag' => 'div',
        'content' => $message,
      ],
      'link' => [
        '#type' => 'html_tag',
        '#tag' => 'div',
        'content' => $login_link->toRenderable(),
      ],
      '#cache' => [
        'contexts' => [
          'url.path',
          'url.query_args',
        ],
      ],
    ];

    if ($this->moduleHandler->moduleExists('social_auth')) {
      /** @var \Drupal\social_auth\Plugin\Block\SocialAuthLoginBlock $social_auth_login_block */
      $social_auth_login_block = $this->blockManager->createInstance('social_auth_login');
      /** @var \Drupal\social_auth\Plugin\Network\NetworkInterface[] $login_networks */
      $login_networks = $social_auth_login_block->build()['#networks'] ?? [];
      $build['social_auth'] = [
        '#type' => 'container',
        '#attributes' => [
          'class' => ['site-login-other'],
        ],
        '#access' => !empty($login_networks),
        'message' => [
          '#type' => 'html_tag',
          '#tag' => 'p',
          '#value' => $this->t('You can also login using one of the following options:'),
        ],
        'links' => [
          '#type' => 'html_tag',
          '#tag' => 'div',
        ],
      ];

      foreach ($login_networks as $login_network) {
        $login_attributes = [
          'class' => [Html::getClass('login-link--' . $login_network->getShortName())],
        ];
        $login_url = $login_network->getRedirectUrl();
        $login_url->setOption('attributes', NestedArray::mergeDeep($options['attributes'], $login_attributes));
        $link = Link::fromTextAndUrl($login_network->getSocialNetwork(), $login_url);
        $build['social_auth']['links'][] = $link->toRenderable();
      }
    }
    $form['login'] = $build;
  }

}
