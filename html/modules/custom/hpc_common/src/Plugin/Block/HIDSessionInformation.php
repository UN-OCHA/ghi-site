<?php

namespace Drupal\hpc_common\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Routing\CurrentRouteMatch;

/**
 * Provides block to show HID Session Information.
 *
 * @Block(
 *  id = "hid_session_information",
 *  admin_label = @Translation("HID Session Information"),
 *  category = @Translation("HID")
 * )
 */
class HIDSessionInformation extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * The account interface.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $account;

  /**
   * The request stack.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $request;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The logger.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactoryInterface
   */
  protected $logger;

  /**
   * The renderer.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected $render;

  /**
   * The route match.
   *
   * @var \Drupal\Core\Routing\CurrentRouteMatch
   */
  protected $routeMatch;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, AccountInterface $account, RequestStack $request, EntityTypeManagerInterface $entityTypeManager, LoggerChannelFactoryInterface $logger, RendererInterface $render, CurrentRouteMatch $routeMatch) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->account = $account;
    $this->request = $request;
    $this->entityTypeManager = $entityTypeManager;
    $this->logger = $logger;
    $this->render = $render;
    $this->routeMatch = $routeMatch;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('current_user'),
      $container->get('request_stack'),
      $container->get('entity_type.manager'),
      $container->get('logger.factory'),
      $container->get('renderer'),
      $container->get('current_route_match')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $user_viewed = $this->routeMatch->getParameter('user');
    if (!$user_viewed) {
      return NULL;
    }
    $id = $this->getHidId($user_viewed);

    // If we get no ID, it means the user has never logged in and we have no
    // data to show. In this case, we do not show this block.
    if (!$id) {
      return NULL;
    }

    // Check the user viewed is the same as logged in user.
    $user_viewed_is_same_as_logged_in_user = $this->account->id() == $user_viewed->id();
    return [
      '#theme' => 'hpc_hid_session_information',
      '#description' => $user_viewed_is_same_as_logged_in_user ?
      $this->t('This section gives you information about your current HID session, which is used to retrieve non public data from the HPC API.') :
      $this->t('This section gives you information about the HID data, which is used to retrieve non public data from the HPC API for this user.'),
      '#user_id' => $id ? $id : '',
      '#id_message' => $user_viewed_is_same_as_logged_in_user ?
      $this->t('This is your permanent HID user ID.') :
      $this->t('This is the permanent HID user ID of this user.'),
      '#token' => $user_viewed_is_same_as_logged_in_user ? $this->getHidAccessToken($user_viewed) : '***********',
      '#token_message' => $user_viewed_is_same_as_logged_in_user ?
      $this->t('This is your current access token. Treat it like a password, do not share it with anyone.') :
      $this->t('The access token is only visible to the user it belongs to.'),
    ];
  }

  /**
   * Get HID access token.
   */
  private function getHidAccessToken($user) {
    $session = $this->request->getCurrentRequest()->getSession();
    return !empty($session->get('social_auth_hid_access_token')) ? $session->get('social_auth_hid_access_token')->getToken() : NULL;
  }

  /**
   * Get HID ID.
   */
  private function getHidId($user) {
    // Skip for anonymous users.
    if ($user->isAnonymous()) {
      return FALSE;
    }

    $social_auth_entities = $this->entityTypeManager->getStorage('social_auth')->loadByProperties(['user_id' => $user->id()]);
    // No entities means nothing to show.
    if (!count($social_auth_entities)) {
      return FALSE;
    }

    if (count($social_auth_entities) > 1) {
      // Too many HID identities and no way of knowing how to handle this.
      $this->logger->get('hpc_common')->error($this->t('Too many HID identities and no way of knowing how to handle this.'));
      return FALSE;
    }

    $social_auth_entity = reset($social_auth_entities);
    return $social_auth_entity->hasField('provider_user_id') ? $social_auth_entity->get('provider_user_id')->getString() : FALSE;
  }

  /**
   * Disable caching.
   *
   * We do not cache this block as viewing different user HID details would not
   * refresh the details and, thereby, show wrong details.
   */
  public function getCacheMaxAge() {
    return 0;
  }

}
