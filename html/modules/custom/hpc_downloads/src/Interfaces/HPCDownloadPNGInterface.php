<?php

namespace Drupal\hpc_downloads\Interfaces;

/**
 * Interface declaration for PNG downloads.
 */
interface HPCDownloadPNGInterface extends HPCDownloadPluginInterface {

  /**
   * Get the selector used to crop the PNG download.
   *
   * @return string|null
   *   The CSS selector to pass to Snap, or NULL to use the download method
   *   default.
   */
  public function getDownloadPngSelector(): ?string;

}
