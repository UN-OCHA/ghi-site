<?php

namespace Drupal\hpc_common\Hid;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Class for retrieving usage years for different objects.
 */
class HidUserData {

  use StringTranslationTrait;

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
   * The account interface.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * Constructs a new UsageYears object.
   */
  public function __construct(RequestStack $request, EntityTypeManagerInterface $entity_type_manager, AccountInterface $current_user) {
    $this->request = $request;
    $this->entityTypeManager = $entity_type_manager;
    $this->currentUser = $current_user;
  }

  /**
   * Get HID ID of the given or current user.
   */
  public function getId(AccountInterface $user = NULL) {
    if ($user === NULL) {
      $user = $this->currentUser;
    }
    // Skip for anonymous users.
    if ($user->isAnonymous()) {
      return FALSE;
    }

    /** @var \Drupal\social_auth\Entity\SocialAuth[] $social_auth_entities */
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
   * Get HID access token.
   */
  public function getAccessToken(AccountInterface $user = NULL) {
    if ($user === NULL) {
      $user = $this->currentUser;
    }
    elseif ($user->id() != $this->currentUser->id()) {
      // The access token should only be availabe to the user itself.
      return FALSE;
    }
    $session = $this->request->getCurrentRequest()->getSession();
    return !empty($session->get('social_auth_hid_access_token')) ? $session->get('social_auth_hid_access_token')->getToken() : NULL;
  }

}
