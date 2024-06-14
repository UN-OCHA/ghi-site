<?php

namespace Drupal\ghi_plan_clusters\Form;

/**
 * Provides a form to confirm the rebuilding of plan cluster logframe subpages.
 */
class LogframeCreateConfirmForm extends LogframeConfirmFormBase {

  /**
   * {@inheritdoc}
   */
  public function getDescription() {
    return $this->t("For each cluster subpage listed here, a new cluster logframe is created if it doesn't exist yet. Existing cluster logframes will not be changed.");
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return $this->t('Are you sure you want to bulk-create the cluster logframes?');
  }

  /**
   * {@inheritdoc}
   */
  public function getConfirmText() {
    return $this->t('Create cluster logframes');
  }

  /**
   * {@inheritdoc}
   */
  public function processLogframeAction() {
    $this->planClusterManager->assureLogframeSubpagesForBaseNode($this->node);
  }

}
