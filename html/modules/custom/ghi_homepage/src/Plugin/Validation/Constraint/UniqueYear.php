<?php

namespace Drupal\ghi_homepage\Plugin\Validation\Constraint;

use Drupal\Core\Entity\FieldableEntityInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidatorInterface;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * Checks that the submitted value is a unique integer.
 *
 * @Constraint(
 *   id = "UniqueYear",
 *   label = @Translation("Unique year", context = "Validation"),
 *   type = { "integer", "string" }
 * )
 */
class UniqueYear extends Constraint implements ConstraintValidatorInterface {

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
    if ($entity && !empty($item_list->value)) {
      $year = $item_list->value;
      $properties = [
        'type' => $entity->bundle(),
        $field_name => $year,
      ];
      $entities = $this->getEntityTypeManager()->getStorage('node')->loadByProperties($properties);
      if (count($entities) && $entity) {
        // Filter out the entity that this field belongs to.
        $entities = array_filter($entities, function (FieldableEntityInterface $_entity) use ($entity) {
          return $_entity->id() != $entity->id();
        });
      }
      if (count($entities)) {
        $used_years = array_map(function (FieldableEntityInterface $_entity) use ($field_name) {
          return $_entity->get($field_name)->value;
        }, $entities);
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
