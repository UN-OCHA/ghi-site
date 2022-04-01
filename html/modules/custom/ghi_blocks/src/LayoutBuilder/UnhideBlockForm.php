<?php

namespace Drupal\ghi_blocks\LayoutBuilder;

use Drupal\Core\Form\FormStateInterface;
use Drupal\layout_builder\SectionStorageInterface;

/**
 * Provides a form to confirm the unhiding of a block.
 */
class UnhideBlockForm extends ConfirmFormBase {

  /**
   * {@inheritdoc}
   */
  public function getDescription() {
    return $this->t('The block will be unhidden.');
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    $label = $this->sectionStorage
      ->getSection($this->delta)
      ->getComponent($this->uuid)
      ->getPlugin()
      ->label();

    return $this->t('Are you sure you want to unhide the %label block?', ['%label' => $label]);
  }

  /**
   * {@inheritdoc}
   */
  public function getConfirmText() {
    return $this->t('Unhide');
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'layout_builder_unhide_block';
  }

  /**
   * {@inheritdoc}
   */
  protected function handleSectionStorage(SectionStorageInterface $section_storage, FormStateInterface $form_state) {
    $component = $section_storage->getSection($this->delta)->getComponent($this->uuid);
    $configuration = $component->get('configuration');
    $configuration['visibility_status'] = NULL;
    $component->setConfiguration($configuration);
  }

}
