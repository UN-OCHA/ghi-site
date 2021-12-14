<?php

namespace Drupal\hpc_common\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Routing\CurrentRouteMatch;
use Drupal\hpc_common\Hid\HidUserData;

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
   * The route match.
   *
   * @var \Drupal\Core\Routing\CurrentRouteMatch
   */
  protected $routeMatch;

  /**
   * The account interface.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $account;

  /**
   * The HID user data service.
   *
   * @var \Drupal\hpc_common\Hid\HidUserData
   */
  protected $hidUserData;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, CurrentRouteMatch $routeMatch, AccountInterface $account, HidUserData $hid_user_data) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->routeMatch = $routeMatch;
    $this->account = $account;
    $this->hidUserData = $hid_user_data;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('current_route_match'),
      $container->get('current_user'),
      $container->get('hpc_common.hid_user_data'),
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
    // Get the Hid ID associated to the user.
    $id = $this->hidUserData->getId($user_viewed);

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
      '#token' => $user_viewed_is_same_as_logged_in_user ? $this->hidUserData->getAccessToken($user_viewed) : '***********',
      '#token_message' => $user_viewed_is_same_as_logged_in_user ?
      $this->t('This is your current access token. Treat it like a password, do not share it with anyone.') :
      $this->t('The access token is only visible to the user it belongs to.'),
    ];
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
