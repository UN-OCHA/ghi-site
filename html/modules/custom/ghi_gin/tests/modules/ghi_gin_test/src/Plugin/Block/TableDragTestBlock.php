<?php

namespace Drupal\ghi_gin_test\Plugin\Block;

use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ghi_gin_test\Form\TableDragTestForm;

/**
 * Exposes a native tree in the actual Layout Builder configuration modal.
 */
#[Block(
  id: 'ghi_gin_tabledrag_test',
  admin_label: new TranslatableMarkup('Native tabledrag test'),
)]
class TableDragTestBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build() {
    return ['#markup' => $this->t('Native tabledrag fixture')];
  }

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state) {
    $form['table'] = TableDragTestForm::buildTable('ghi-gin-modal-table', $this->configuration['rows'] ?? []);
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state) {
    $this->configuration['rows'] = $form_state->getValue('table');
  }

}
