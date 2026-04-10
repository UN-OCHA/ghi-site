<?php

namespace Drupal\ghi_plans\ApiObjects;

use Drupal\hpc_api\ApiObjects\ApiObjectBase;

/**
 * Abstraction class for API contact objects.
 */
class Contact extends ApiObjectBase {

  /**
   * The name of the contact.
   *
   * @var string
   */
  protected string $name;

  /**
   * The mail address.
   *
   * @var string|null
   */
  protected ?string $mail;

  /**
   * The agency.
   *
   * @var string|null
   */
  protected ?string $agency;

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
   * {@inheritdoc}
   */
  public function __construct(object $data) {
    parent::__construct($data);
    $this->name = $data->Name;
    $this->mail = $data->Email ?? NULL;
    $this->agency = $data->LeadAgency ?? NULL;
  }

  /**
   * Get the name.
   *
   * @return string
   *   The name.
   */
  public function getName(): string {
    return $this->name;
  }

  /**
   * Get the mail address.
   *
   * @return string
   *   The mail address.
   */
  public function getMail(): ?string {
    return $this->mail;
  }

  /**
   * Get the agency.
   *
   * @return string
   *   The agency.
   */
  public function getAgency(): ?string {
    return $this->agency;
  }

}
