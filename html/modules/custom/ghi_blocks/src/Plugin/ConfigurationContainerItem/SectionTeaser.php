<?php

namespace Drupal\ghi_blocks\Plugin\ConfigurationContainerItem;

use Drupal\Component\Render\FormattableMarkup;
use Drupal\Core\Form\FormStateInterface;
use Drupal\ghi_form_elements\ConfigurationContainerItemPluginBase;
use Drupal\ghi_sections\Entity\SectionNodeInterface;

/**
 * Provides a section teaser item for configuration containers.
 *
 * @ConfigurationContainerItem(
 *   id = "section_teaser",
 *   label = @Translation("Section"),
 *   description = @Translation("This item displays a section teaser."),
 * )
 */
class SectionTeaser extends ConfigurationContainerItemPluginBase {

  /**
   * {@inheritdoc}
   */
  public function buildForm($element, FormStateInterface $form_state) {
    $element = parent::buildForm($element, $form_state);
    $entity_id = $this->config['value'] ?? NULL;
    $entity = $entity_id ? $this->entityTypeManager->getStorage('node')->load($entity_id) : NULL;

    // This doesn't need a title.
    $element['label']['#access'] = FALSE;

    // Add an autocomplete element.
    $element['value'] = [
      '#type' => 'entity_autocomplete',
      '#title' => $this->t('Section'),
      '#description' => $this->t('Select the section. Start typing to see suggestions based on the section title'),
      '#target_type' => 'node',
      '#selection_settings' => [
        'target_bundles' => ['section'],
      ],
      '#default_value' => $entity,
    ];
    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function getLabel() {
    $value = $this->getValue();
    $entity = $this->entityTypeManager->getStorage('node')->load($value);
    if (!$entity) {
      return $this->t('<em>Unavailable</em>');
    }
    $label = $entity->label();
    if ($entity instanceof SectionNodeInterface && $entity->isProtected()) {
      $label = new FormattableMarkup('<span class="protected">@label</span>', [
        '@label' => $label,
      ]);
    }
    return $label;
  }

  /**
   * {@inheritdoc}
   */
  public function getRenderArray() {
    $value = $this->getValue();
    $entity = $this->entityTypeManager->getStorage('node')->load($value);
    if (!$entity || !$entity->access('view')) {
      return NULL;
    }
    $build = $this->entityTypeManager->getViewBuilder('node')->view($entity, 'teaser');
    $build['#cache'] = [
      'contexts' => $entity->getCacheContexts(),
      'tags' => $entity->getCacheTags(),
    ];
    return $build;
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheTags() {
    $value = $this->getValue();
    $entity = $this->entityTypeManager->getStorage('node')->load($value);
    return $entity ? $entity->getCacheTags() : [];
  }

}
