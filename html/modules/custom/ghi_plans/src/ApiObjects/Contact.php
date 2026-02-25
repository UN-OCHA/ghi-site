<?php

namespace Drupal\ghi_plans\ApiObjects;

use Drupal\ghi_base_objects\ApiObjects\BaseObject;

/**
 * Abstraction class for API contact objects.
 */
class Contact extends BaseObject {

  /**
   * Define the dimension items used in queries.
   */
  const GRAPHQL_ITEMS = [
    'Id',
    'Name',
    'Email',
    'LeadAgency',
  ];

  /**
   * Map the raw data.
   *
   * @return object
   *   An object with the mapped data.
   */
  protected function map() {
    $data = $this->getRawData();
    return (object) [
      'id' => $data->Id,
      'name' => $data->Name,
      'mail' => $data->Email,
      'agency' => $data->LeadAgency,
    ];
  }

  /**
   * Get the mail address.
   *
   * @return string
   *   The mail address.
   */
  public function getMail() {
    return $this->map->mail;
  }

  /**
   * Get the agency.
   *
   * @return string
   *   The agency.
   */
  public function getAgency() {
    return $this->map->agency;
  }

}
