<?php

namespace Drupal\hpc_api\ApiObjects\Types;

use Drupal\hpc_api\ApiObjects\Type;

/**
 * Class for revision state objects.
 */
class RevisionState extends Type {

  /**
   * Original revision state.
   */
  private const int PLAN_ORIGINAL = 1;

  /**
   * Current revision state.
   */
  private const int CURRENT = 2;

  /**
   * GHO revision state.
   */
  private const int GHO = 3;

  /**
   * Get the machine name of the revision state.
   *
   * @return string
   *   The machine name of the revision state.
   */
  public function getMachineName() {
    return match ($this->id()) {
      self::PLAN_ORIGINAL => 'original',
      self::CURRENT => 'current',
      self::GHO => 'gho',
      default => 'invalid',
    };
  }

  /**
   * Check if this state represents the original plan revision.
   *
   * @return bool
   *   TRUE if this state represents the original plan revision.
   */
  public function isPlanOriginal() {
    return $this->id() == self::PLAN_ORIGINAL;
  }

  /**
   * Check if this state represents the current revision.
   *
   * @return bool
   *   TRUE if this state represents the current revision.
   */
  public function isCurrent() {
    return $this->id() == self::CURRENT;
  }

  /**
   * Check if this state represents the GHO revision.
   *
   * @return bool
   *   TRUE if this state represents the GHO revision.
   */
  public function isGho() {
    return $this->id() == self::GHO;
  }

}
