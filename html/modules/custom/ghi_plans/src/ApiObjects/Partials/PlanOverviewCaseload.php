<?php

namespace Drupal\ghi_plans\ApiObjects\Partials;

use Drupal\ghi_plans\ApiObjects\Attachments\CaseloadAttachmentInterface;
use Drupal\ghi_plans\ApiObjects\PlanEntityInterface;
use Drupal\ghi_plans\ApiObjects\Prototypes\AttachmentPrototype;
use Drupal\hpc_api\ApiObjects\ApiObjectBase;

/**
 * Abstraction class for a plan partial object.
 *
 * This kind of partial object is a stripped-down, limited-data, object that
 * appears in some specific endpoints. We map this here to provide type hinting
 * and abstracted data access.
 */
class PlanOverviewCaseload extends ApiObjectBase implements CaseloadAttachmentInterface {

  /**
   * The custom id.
   *
   * @var string
   */
  protected string $customId;

  /**
   * The plan id.
   *
   * @var int
   */
  protected int $planId;

  /**
   * The fields.
   *
   * @var array
   */
  protected array $fields;

  /**
   * The field types.
   *
   * @var array
   */
  protected array $fieldTypes;

  /**
   * {@inheritdoc}
   */
  public function __construct(object $data) {
    parent::__construct($data);
    $this->customId = $data->CustomReference;
    $this->planId = (int) $data->PlanId;

    $calculated_fields = $data->calculatedFields ?? [];
    if ($calculated_fields && !is_array($calculated_fields) && is_object($calculated_fields)) {
      $calculated_fields = [$calculated_fields];
    }
    $fields = array_merge($data->totals, $calculated_fields);
    $this->fields = $fields;
    $this->fieldTypes = array_map(function ($item) {
      return $item->type;
    }, $fields);
  }

  /**
   * {@inheritdoc}
   */
  public function getTitle() {
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription() {
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getCustomId() {
    return $this->customId;
  }

  /**
   * {@inheritdoc}
   */
  public function getCustomIdWithRefCode(): string {
    return 'PL' . $this->getCustomId();
  }

  /**
   * {@inheritdoc}
   */
  public function getComposedReference(): string {
    return $this->getCustomIdWithRefCode();
  }

  /**
   * {@inheritdoc}
   */
  public function getFields() {
    return $this->fields;
  }

  /**
   * {@inheritdoc}
   */
  public function getFieldTypes() {
    return $this->fieldTypes;
  }

  /**
   * {@inheritdoc}
   */
  public function getPlanId() {
    return $this->planId;
  }

  /**
   * {@inheritdoc}
   */
  public function getSourceEntityType() {
    return 'plan';
  }

  /**
   * {@inheritdoc}
   */
  public function getSourceEntityId() {
    return $this->getPlanId();
  }

  /**
   * {@inheritdoc}
   */
  public function getSourceEntity(): ?PlanEntityInterface {
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getPrototype(): ?AttachmentPrototype {
    return NULL;
  }

}
