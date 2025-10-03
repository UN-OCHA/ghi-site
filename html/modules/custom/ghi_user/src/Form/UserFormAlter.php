<?php

namespace Drupal\ghi_user\Form;

use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Block\BlockManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\Render\Element;
use Drupal\Core\Routing\RedirectDestinationTrait;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Class for altering the login form.
 *
 * @package Drupal\ghi_user\Form
 */
class UserFormAlter {

  use RedirectDestinationTrait;
  use StringTranslationTrait;

  /**
   * The module handler service.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected ModuleHandlerInterface $moduleHandler;

  /**
   * The block manager service.
   *
   * @var \Drupal\Core\Block\BlockManagerInterface
   */
  protected BlockManagerInterface $blockManager;

  /**
   * The current Drupal user.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected AccountProxyInterface $currentUser;

  /**
   * Constructs this form alter service class.
   */
  public function __construct(ModuleHandlerInterface $module_handler, BlockManagerInterface $block_manager, AccountProxyInterface $curent_user) {
    $this->moduleHandler = $module_handler;
    $this->blockManager = $block_manager;
    $this->currentUser = $curent_user;
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

  /**
   * Alter the login form.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state object.
   */
  public function alterEditForm(&$form, FormStateInterface $form_state) {
    if ($this->currentUser->hasPermission('administer users')) {
      // Administrator should see all fields.
      return;
    }
    /** @var \Drupal\user\UserInterface $user */
    $user = $form_state->getFormObject()->getEntity();

    // For non-administrators we provide a customized display of the form where
    // most of the fields are disabled but still shown for information purposes.
    // See common_design_subtheme/scss/ghi/user/_edit.scss for the styling.
    $form['#attributes']['class'][] = 'content-width';
    $form['account_data'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Account data'),
      '#collapsible' => FALSE,
      '#attributes' => [
        'class' => ['account-data'],
      ],
    ];
    $form['account_data']['username'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Username'),
      '#default_value' => $user->label(),
      '#disabled' => TRUE,
      '#description' => $this->t('The username is used internally and cannot be changed.'),
    ];
    $form['account_data']['mail'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Email'),
      '#default_value' => $user->getEmail(),
      '#disabled' => TRUE,
      '#description' => $this->t('The email address is used internally and cannot be changed.'),
    ];
    $form['account_data']['team'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Team'),
      '#default_value' => $user->get('field_team')->target_id?->entity?->label() ?? $this->t('None'),
      '#disabled' => TRUE,
      '#description' => $this->t('The team can only be changed by an administrator.'),
    ];
    $form['timezone']['#type'] = 'fieldset';
    $form['timezone']['#collapsible'] = FALSE;
    $form['timezone']['#attributes']['class'][] = 'account-data';

    // Whitelist elements to show.
    $keep_children = [
      'account_data',
      'timezone',
      'form_build_id',
      'form_token',
      'form_id',
      'actions',
      'footer',
    ];
    foreach (Element::children($form) as $key) {
      if (!in_array($key, $keep_children)) {
        $form[$key]['#access'] = FALSE;
      }
    }
  }

}
