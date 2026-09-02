<?php

namespace Drupal\ghi_plans\ApiObjects;

use Drupal\Core\Url;
use Drupal\ghi_base_objects\ApiObjects\BaseObject;
use Drupal\hpc_common\Helpers\CommonHelper;

/**
 * Abstraction class for API organization objects.
 */
class Organization extends BaseObject {

  /**
   * The abbreviation.
   *
   * @var string|null
   */
  protected ?string $abbreviation;

  /**
   * The url.
   *
   * @var string|null
   */
  protected ?string $url;

  /**
   * Define the dimension items used in queries.
   */
  const GRAPHQL_ITEMS = [
    'Id',
    'Name',
    'NativeName',
    'Abbreviation',
    'url',
  ];

  /**
   * {@inheritdoc}
   */
  public function __construct(object $data) {
    parent::__construct($data);
    $this->abbreviation = $data->Abbreviation ?? ($data->abbreviation ?? NULL);
    $this->url = CommonHelper::assureWellFormedUri($data->Url ?? '');
  }

  /**
   * Get the abbreviation.
   *
   * @return string|null
   *   The abbreviation of the organization.
   */
  public function getAbbreviation(): ?string {
    return $this->abbreviation;
  }

  /**
   * Get the url.
   *
   * @return \Drupal\Core\Url
   *   The url of the organization.
   */
  public function getUrl(?array $options = []): ?Url {
    return $this->url ? Url::fromUri($this->url, $options) : NULL;
  }

}
