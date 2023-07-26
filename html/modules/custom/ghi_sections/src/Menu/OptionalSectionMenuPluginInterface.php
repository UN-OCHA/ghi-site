<?php

namespace Drupal\ghi_sections\Menu;

use Drupal\Core\Form\FormStateInterface;

/**
 * Interface for optional section menu item plugins.
 */
interface OptionalSectionMenuPluginInterface extends SectionMenuPluginInterface {

  /**
   * Get the plugin configuration for an instance.
   *
   * @return array
   *   Plugin configuration form.
   */
  public function buildForm($form, FormStateInterface $form_state);

  /**
   * Check if this plugin is currently available.
   *
   * @return bool
   *   TRUE if available, FALSE otherwise.
   */
  public function isAvailable();

}
