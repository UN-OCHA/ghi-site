<?php

namespace Drupal\ghi_enforce_alias_pattern;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Drupal\Core\Entity\ContentEntityFormInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\pathauto\PathautoGeneratorInterface;

/**
 * Manager class for enforced alias pattern.
 */
class EnforceAliasPatternManager {

  use StringTranslationTrait;
  use DependencySerializationTrait;

  /**
   * The pathauto generator service.
   *
   * @var \Drupal\pathauto\PathautoGeneratorInterface
   */
  protected $pathautoGenerator;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Construct a manager class for the enforce alias pattern module.
   *
   * @param \Drupal\pathauto\PathautoGeneratorInterface $pathauto_generator
   *   The pathauto generator service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(PathautoGeneratorInterface $pathauto_generator, EntityTypeManagerInterface $entity_type_manager) {
    $this->pathautoGenerator = $pathauto_generator;
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * Alter a form.
   */
  public function formAlter(array &$form, FormStateInterface $form_state) {
    $form_object = $form_state->getFormObject();
    if (!$form_object instanceof ContentEntityFormInterface || empty($form['path'])) {
      return;
    }
    $entity = $form_object->getEntity();
    $pattern = $this->pathautoGenerator->getPatternByEntity($entity);
    if (!$pattern) {
      return;
    }
    $alias = $entity->path->alias ?? $this->pathautoGenerator->createEntityAlias($entity, 'return');
    $parts = $alias ? explode('/', trim($alias, '/')) : [];
    if (count($parts) <= 1) {
      // Bail out if there are not enough parts in the alias pattern.
      return;
    }
    $editable_part = array_pop($parts);
    $form['path']['widget'][0]['enforced_alias'] = [
      '#type' => 'enforced_alias',
      '#title' => $form['path']['widget'][0]['alias']['#title'],
      '#original_alias' => $form['path']['widget'][0]['alias']['#default_value'],
      '#fixed_prefix' => implode('/', $parts),
      '#generated_alias' => $this->pathautoGenerator->createEntityAlias($entity, 'return'),
      '#default_value' => $editable_part,
      '#element_validate' => [[$this, 'validatePathAlias']],
      '#element_submit' => [[$this, 'submitPathAlias']],
      '#states' => [
        'invisible' => $form['path']['widget'][0]['alias']['#states']['disabled'],
      ],
    ];
    $form['path']['widget'][0]['alias']['#value'] = '';
    $form['path']['widget'][0]['alias']['#access'] = FALSE;

    $form['#attached']['library'][] = 'ghi_enforce_alias_pattern/entity_form';
  }

  /**
   * Validate the enforced path alias.
   */
  public function validatePathAlias(array &$element, FormStateInterface $form_state) {
    $pauthauto = $form_state->getValue($form_state->getCompleteForm()['path']['widget'][0]['pathauto']);
    if ($pauthauto) {
      return;
    }
    $value = $form_state->getValue($element['#parents']);
    $path_element = NestedArray::getValue($form_state->getCompleteForm(), array_slice($element['#array_parents'], 0, -1));
    if (empty($path_element)) {
      return;
    }
    if (empty($value)) {
      $form_state->setError($element, $this->t('You must provide a value for the alias.'));
    }
    elseif (strpos($value, '/')) {
      $form_state->setError($element, $this->t('Slashes are not allowed in the alias.'));
    }
    else {
      $alias = '/' . $element['#fixed_prefix'] . '/' . $value;
      /** @var \Drupal\path_alias\PathAliasInterface $path_alias */
      $path_alias = $this->entityTypeManager->getStorage('path_alias')->create([
        'path' => $path_element['source']['#value'],
        'alias' => $alias,
        'langcode' => $path_element['langcode']['#value'],
      ]);
      $violations = $path_alias->validate();

      foreach ($violations as $violation) {
        // Newly created entities do not have a system path yet, so we need to
        // disregard some violations.
        if (!$path_alias->getPath() && $violation->getPropertyPath() === 'path') {
          continue;
        }
        $form_state->setError($element, $violation->getMessage());
      }
    }
  }

  /**
   * Submit the enforced path alias.
   */
  public function submitPathAlias(array &$element, FormStateInterface $form_state) {
    $pauthauto = $form_state->getValue($form_state->getCompleteForm()['path']['widget'][0]['pathauto']);
    if ($pauthauto) {
      return;
    }
    $value = $form_state->getValue($element['#parents']);
    $alias = '/' . $element['#fixed_prefix'] . '/' . $value;
    $form_state->setValueForElement($form_state->getCompleteForm()['path']['widget'][0]['alias'], $alias);
  }

}
