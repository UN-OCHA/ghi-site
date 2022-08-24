<?php

namespace Drupal\ghi_sections\Plugin\Validation\Constraint;

use Drupal\Core\Entity\FieldableEntityInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidatorInterface;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * Checks that the submitted value is a unique integer.
 *
 * @Constraint(
 *   id = "UniqueSectionYear",
 *   label = @Translation("Unique section year", context = "Validation"),
 *   type = "string"
 * )
 */
class UniqueSectionYear extends Constraint implements ConstraintValidatorInterface {

  /**
   * A context object.
   *
   * @var \Symfony\Component\Validator\Context\ExecutionContextInterface
   */
  protected $context;

  /**
   * {@inheritDoc}
   */
  public function initialize(ExecutionContextInterface $context) {
    $this->context = $context;
  }

  /**
   * {@inheritdoc}
   */
  public function validatedBy() {
    return get_class($this);
  }

  /**
   * {@inheritdoc}
   */
  public function validate($item_list, Constraint $constraint) {
    /** @var \Drupal\Core\Field\FieldItemListInterface $item_list */
    $entity = $item_list->getEntity();
    $field_name = $item_list->getFieldDefinition()->getName();
    if (!empty($item_list->value)) {
      $year = $item_list->value;
      $properties = [
        'type' => 'homepage',
        $field_name => $year,
      ];
      $sections = $this->getEntityTypeManager()->getStorage('node')->loadByProperties($properties);
      if (count($sections) && $entity) {
        // Filter out the entity that this field belongs to.
        $sections = array_filter($sections, function (FieldableEntityInterface $section) use ($entity) {
          return $section->id() != $entity->id();
        });
      }
      if (count($sections)) {
        $used_years = array_map(function (FieldableEntityInterface $section) use ($field_name) {
          return $section->get($field_name)->value;
        }, $sections);
        $arguments = [
          '%value' => $year,
          '@used_years' => implode(', ', $used_years),
        ];
        if (count($used_years) > 1) {
          $this->context->addViolation('%value is already in use. Please choose a value different to @used_years.', $arguments);
        }
        else {
          $this->context->addViolation('%value is already in use. Please choose a different value.', $arguments);
        }
      }
    }
  }

  /**
   * Get the entity type manager.
   *
   * @return \Drupal\Core\Entity\EntityTypeManagerInterface
   *   The entity type manager.
   */
  public static function getEntityTypeManager() {
    return \Drupal::entityTypeManager();
  }

}
