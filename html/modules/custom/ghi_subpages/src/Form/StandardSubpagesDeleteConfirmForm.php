<?php

namespace Drupal\ghi_subpages\Form;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Form\FormStateInterface;
use Drupal\node\NodeInterface;

/**
 * Confirms individual or bulk deletion of direct standard subpages.
 */
class StandardSubpagesDeleteConfirmForm extends SubpagesStructureConfirmFormBase {

  /**
   * The selected child node IDs.
   *
   * @var int[]
   */
  protected $childIds = [];

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'ghi_subpages_standard_delete_confirm';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?NodeInterface $node = NULL, string $subpages = '') {
    $this->section = $node;
    $this->childIds = array_map('intval', explode(',', $subpages));
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return $this->t('Delete @count standard subpages?', ['@count' => count($this->childIds)]);
  }

  /**
   * {@inheritdoc}
   */
  public function getConfirmText() {
    return $this->t('Delete selected pages');
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $nodes = $this->entityTypeManager->getStorage('node')->loadMultiple($this->childIds);
    if (count($nodes) !== count($this->childIds)) {
      $this->messenger()->addError($this->t('One or more selected pages no longer exist.'));
    }
    else {
      try {
        $this->subpageManager->deleteStandardSubpages($this->section, array_values($nodes));
        Cache::invalidateTags($this->section->getCacheTags());
        $this->messenger()->addStatus($this->t('Deleted @count standard subpages.', ['@count' => count($nodes)]));
      }
      catch (\LogicException $exception) {
        $this->messenger()->addError($this->t('The selected pages could not be deleted.'));
      }
    }
    $this->redirectToSubpagesListing($form_state);
  }

}
