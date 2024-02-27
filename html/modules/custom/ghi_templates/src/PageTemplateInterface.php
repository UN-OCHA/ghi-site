<?php

namespace Drupal\ghi_templates;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityChangedInterface;
use Drupal\Core\Entity\EntityPublishedInterface;
use Drupal\user\EntityOwnerInterface;

/**
 * Provides an interface defining a page template entity.
 */
interface PageTemplateInterface extends ContentEntityInterface, EntityChangedInterface, EntityOwnerInterface, EntityPublishedInterface {

  /**
   * Setup the template.
   */
  public function setupTemplate();

}
