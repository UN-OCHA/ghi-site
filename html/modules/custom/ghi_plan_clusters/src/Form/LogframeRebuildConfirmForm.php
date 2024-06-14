<?php

namespace Drupal\ghi_plan_clusters\Form;

/**
 * Provides a form to confirm the rebuilding of plan cluster logframe subpages.
 */
class LogframeRebuildConfirmForm extends LogframeConfirmFormBase {

  /**
   * {@inheritdoc}
   */
  public function getDescription() {
    return $this->t("For each cluster subpage listed here, the cluster logframe is rebuild. If the cluster logframe doesn't exist yet it will be created.");
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return $this->t('Are you sure you want to bulk-rebuild the cluster logframes?');
  }

  /**
   * {@inheritdoc}
   */
  public function getConfirmText() {
    return $this->t('Rebuild cluster logframes');
  }

  /**
   * {@inheritdoc}
   */
  public function processLogframeAction() {
    $this->planClusterManager->assureLogframeSubpagesForBaseNode($this->node, TRUE);
  }

}
