<?php

namespace Drupal\ghi_plan_clusters\Form;

/**
 * Provides a form to confirm the rebuilding of plan cluster logframe subpages.
 */
class LogframeDeleteConfirmForm extends LogframeConfirmFormBase {

  /**
   * {@inheritdoc}
   */
  public function getDescription() {
    return $this->t("All existing cluster logframes will be deleted.");
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return $this->t('Are you sure you want to delete all cluster logframes for this section?');
  }

  /**
   * {@inheritdoc}
   */
  public function getConfirmText() {
    return $this->t('Delete cluster logframes');
  }

  /**
   * {@inheritdoc}
   */
  public function processLogframeAction() {
    $this->planClusterManager->deleteLogframeSubpagesForBaseNode($this->node);
  }

}
