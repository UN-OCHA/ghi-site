<?php

namespace Drupal\hpc_downloads\Interfaces;

/**
 * Interface declaration for PDF downloads.
 */
interface HPCDownloadPDFInterface extends HPCDownloadPluginInterface {

  /**
   * Get the PDF download title aka label.
   */
  public function getDownloadPdfTitle();

  /**
   * Get the PDF download caption.
   */
  public function getDownloadPdfCaption();

}
