<?php

namespace Drupal\ghi_blocks\LayoutBuilder;

use Drupal\Core\Form\FormStateInterface;
use Drupal\layout_builder\Form\LayoutRebuildConfirmFormBase;
use Drupal\layout_builder\SectionStorageInterface;

/**
 * Provides a form to confirm the hiding of a block.
 */
class HideBlockForm extends LayoutRebuildConfirmFormBase {

  /**
   * The current region.
   *
   * @var string
   */
  protected $region;

  /**
   * The UUID of the block being removed.
   *
   * @var string
   */
  protected $uuid;

  /**
   * {@inheritdoc}
   */
  public function getDescription() {
    return $this->t("The block will be kept with it's current configuration, but it will be hidden from public display. The block can be enabled again at any time.");
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

    return $this->t('Are you sure you want to hide the %label block?', ['%label' => $label]);
  }

  /**
   * {@inheritdoc}
   */
  public function getConfirmText() {
    return $this->t('Hide');
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'layout_builder_hide_block';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, SectionStorageInterface $section_storage = NULL, $delta = NULL, $region = NULL, $uuid = NULL) {
    $this->region = $region;
    $this->uuid = $uuid;
    return parent::buildForm($form, $form_state, $section_storage, $delta);
  }

  /**
   * {@inheritdoc}
   */
  protected function handleSectionStorage(SectionStorageInterface $section_storage, FormStateInterface $form_state) {
    $component = $section_storage->getSection($this->delta)->getComponent($this->uuid);
    $configuration = $component->get('configuration');
    $configuration['visibility_status'] = 'hidden';
    $component->setConfiguration($configuration);
  }

}
