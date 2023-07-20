<?php

namespace Drupal\ghi_subpages_custom\Plugin\Validation\Constraint;

use Drupal\Component\Render\FormattableMarkup;
use Drupal\ghi_subpages\SubpageManager;
use Drupal\ghi_subpages_custom\Entity\CustomSubpage;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidatorInterface;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * Validation constraint for custom subpage nodes.
 *
 * @Constraint(
 *   id = "ReservedTitle",
 *   label = @Translation("Check for reserved titles for custom subpages.", context = "Validation"),
 * )
 */
class ReservedTitleConstraint extends Constraint implements ConstraintValidatorInterface {

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
  public function validate($entity, Constraint $constraint) {
    if (isset($entity) && $entity instanceof CustomSubpage) {
      $reserved_title = in_array(strtolower(trim($entity->label())), SubpageManager::SUPPORTED_SUBPAGE_TYPES);
      if ($reserved_title) {
        $placeholders = [
          '@title' => $entity->label(),
        ];
        $this->context->addViolation((string) (new FormattableMarkup('@title is a reserved title. Please choose a different one.', $placeholders)));
      }
    }
  }

}
