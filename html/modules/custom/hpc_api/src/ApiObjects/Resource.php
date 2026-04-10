<?php

namespace Drupal\hpc_api\ApiObjects;

use Drupal\Core\Url;

/**
 * Class for resource objects.
 */
class Resource extends ApiObjectBase {

  /**
   * The name.
   *
   * @var string
   */
  protected string $name;

  /**
   * The mimetype.
   *
   * @var string
   */
  protected string $mimetype;

  /**
   * The URL.
   *
   * @var string
   */
  protected string $url;

  /**
   * The credit.
   *
   * @var string|null
   */
  protected ?string $credit;

  /**
   * The plan id.
   *
   * @var int|null
   */
  protected ?int $planId;

  /**
   * The field cluster id.
   *
   * @var int|null
   */
  protected ?int $fieldClusterId;

  /**
   * Define the dimension items used in queries.
   */
  const GRAPHQL_ITEMS = [
    'Id',
    'Name',
    'MimeType',
    'URL',
    'Credit',
  ];

  /**
   * {@inheritdoc}
   */
  public function __construct(object $data) {
    parent::__construct($data);
    $this->name = $data->Name;
    $this->mimetype = $data->MimeType;
    $this->url = $data->URL;
    $this->credit = $data->Credit ?? NULL;
    $this->planId = $data->PlanId ?? NULL;
    $this->fieldClusterId = $data->FieldClusterId ?? NULL;
  }

  /**
   * Get the name of the resource.
   *
   * @return string
   *   The name of the resource.
   */
  public function getName(): string {
    return $this->name;
  }

  /**
   * Get the mimetype of the resource.
   *
   * @return string
   *   The mimetype of the resource.
   */
  public function getMimeType(): string {
    return $this->mimetype;
  }

  /**
   * Get the URL.
   *
   * @return \Drupal\Core\Url
   *   The URL of the resource.
   */
  public function getUrl(): Url {
    return Url::fromUri($this->url);
  }

  /**
   * Get the credits.
   *
   * @return string|null
   *   The credits as a string. Can be empty.
   */
  public function getCredit(): ?string {
    return $this->credit;
  }

  /**
   * Get the plan id.
   *
   * @return int|null
   *   The plan id.
   */
  public function getPlanId() {
    return $this->planId;
  }

  /**
   * Get the field cluster id.
   *
   * @return int|null
   *   The field cluster id.
   */
  public function getFieldClusterId() {
    return $this->fieldClusterId;
  }

}
