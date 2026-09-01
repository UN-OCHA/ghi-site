<?php

namespace Drupal\hpc_api\ApiObjects;

/**
 * Class for plan year objects.
 */
class PlanYear extends ApiObjectBase {

  /**
   * The year.
   *
   * @var int
   */
  protected int $year;

  /**
   * {@inheritdoc}
   */
  public function __construct(object $data) {
    parent::__construct($data);
    $this->year = (int) $data->CalendarYear;
  }

  /**
   * Get the name of the type.
   *
   * @return string
   *   The name.
   */
  public function getYear() {
    return $this->year;
  }

}
