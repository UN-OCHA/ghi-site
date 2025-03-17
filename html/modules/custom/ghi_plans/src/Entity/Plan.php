<?php

namespace Drupal\ghi_plans\Entity;

use Drupal\Component\Render\FormattableMarkup;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ghi_base_objects\ApiObjects\Country;
use Drupal\ghi_base_objects\Entity\BaseObject;
use Drupal\ghi_base_objects\Entity\BaseObjectFocusCountryInterface;
use Drupal\ghi_base_objects\Entity\BaseObjectMetaDataInterface;
use Drupal\ghi_plans\Traits\AttachmentFilterTrait;
use Drupal\ghi_plans\Traits\FtsLinkTrait;
use Drupal\ghi_plans\Traits\PlanTypeTrait;

/**
 * Bundle class for plan base objects.
 */
class Plan extends BaseObject implements BaseObjectMetaDataInterface, BaseObjectFocusCountryInterface {

  use PlanTypeTrait;
  use FtsLinkTrait;
  use AttachmentFilterTrait;

  public const CLUSTER_TYPE_CLUSTER = 'cluster';
  public const CLUSTER_TYPE_SECTOR = 'sector';

  /**
   * {@inheritdoc}
   */
  public function toUrl($rel = 'canonical', array $options = []) {
    // Allow to link to FTS.
    if ($rel == 'fts_summary') {
      return self::buildFtsUrl($this, 'summary');
    }
    return parent::toUrl($rel, $options);
  }

  /**
   * {@inheritdoc}
   */
  public function getPageTitleMetaData() {
    $langcode = $this->getPlanLanguage() ?? 'en';
    $document_published = $this->getPlanDocumentPublishedDate();
    $t_options = ['langcode' => $langcode];
    return array_filter([
      $this->getPlanSubtitle() ? new FormattableMarkup('<span class="icon-wrapper"><span class="icon plan-subtitle"></span>@subtitle</span>', [
        '@subtitle' => $this->getPlanSubtitle(),
      ]) : NULL,
      $this->getPlanStartDate() ? new TranslatableMarkup('<strong>From:</strong> @start_date <strong>to:</strong> @end_date', [
        '@start_date' => DrupalDateTime::createFromFormat('Y-m-d', $this->getPlanStartDate())->format('d/m/Y'),
        '@end_date' => DrupalDateTime::createFromFormat('Y-m-d', $this->getPlanEndDate())->format('d/m/Y'),
      ], $t_options) : NULL,
      $document_published ? new TranslatableMarkup('<strong>Published:</strong> @date', [
        '@date' => DrupalDateTime::createFromFormat('Y-m-d', $document_published)->format('d/m/Y'),
      ], $t_options) : new TranslatableMarkup('<strong>Unpublished</strong>', [], $t_options),
      $this->getPlanCoordinator() ? new TranslatableMarkup('<span class="icon-wrapper"><span class="icon plan-coordinator"></span><strong>Coordinated by:</strong> @coordinator</span>', [
        '@coordinator' => implode(' & ', $this->getPlanCoordinator()),
      ], $t_options) : NULL,
    ]);
  }

  /**
   * Get the plan year.
   *
   * @return string
   *   The plan year.
   */
  public function getYear() {
    return $this->get('field_year')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function getFocusCountry() {
    if (!$this->hasField('field_focus_country')) {
      return NULL;
    }
    return $this->get('field_focus_country')->entity;
  }

  /**
   * {@inheritdoc}
   */
  public function getFocusCountryOverride() {
    if (!$this->hasField('field_focus_country_override') || $this->get('field_focus_country_override')->isEmpty()) {
      return NULL;
    }
    return [
      (string) $this->get('field_focus_country_override')->lat,
      (string) $this->get('field_focus_country_override')->lon,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getFocusCountryMapLocation(?Country $default_country = NULL) {
    $focus_country = $this->getFocusCountry();
    if (!$focus_country && !$default_country) {
      return NULL;
    }
    $lat_lng = $focus_country ? [
      (string) $focus_country->get('field_latitude')->value,
      (string) $focus_country->get('field_longitude')->value,
    ] : NULL;
    if ($override = $this->getFocusCountryOverride()) {
      $lat_lng = $override;
    }
    if (!$lat_lng) {
      return NULL;
    }
    return new Country((object) [
      'id' => $focus_country?->getSourceId() ?? $default_country->id(),
      'name' => $focus_country?->label() ?? $default_country->getName(),
      'latitude' => $lat_lng[0],
      'longitude' => $lat_lng[1],
    ]);
  }

  /**
   * Get the plan language.
   *
   * @return string|null
   *   The plan language.
   */
  public function getPlanLanguage() {
    if (!$this->hasField('field_language')) {
      return NULL;
    }
    return $this->get('field_language')?->value ?? NULL;
  }

  /**
   * Get the plan type.
   *
   * @return \Drupal\ghi_plans\Entity\PlanType|null
   *   The plan type.
   */
  public function getPlanType() {
    if (!$this->hasField('field_plan_type')) {
      return NULL;
    }
    return $this->get('field_plan_type')?->entity ?? NULL;
  }

  /**
   * Check if the plan is of the given type.
   *
   * @param string $type_name
   *   The type name to check.
   *
   * @return bool
   *   TRUE if the plan is of the given type, FALSE otherwise.
   */
  private function isType($type_name) {
    $name = $this->getPlanType()?->label();
    if (empty($name)) {
      return FALSE;
    }
    return $name == $type_name;
  }

  /**
   * Check if the plan is an HRP.
   *
   * @return bool
   *   TRUE if the plan is an HRP, FALSE otherwise.
   */
  public function isHrp() {
    return $this->isType('Humanitarian response plan');
  }

  /**
   * Check if the plan is an RRP.
   *
   * @return bool
   *   TRUE if the plan is an RRP, FALSE otherwise.
   */
  public function isRrp() {
    return $this->isType('Regional response plan');
  }

  /**
   * Check if the plan is a Flash Appeal.
   *
   * @return bool
   *   TRUE if the plan is a Flash Appeal, FALSE otherwise.
   */
  public function isFlashAppeal() {
    return $this->isType('Flash appeal');
  }

  /**
   * Check if the plan is of type Other.
   *
   * @return bool
   *   TRUE if the plan is of type Other, FALSE otherwise.
   */
  public function isOther() {
    return $this->isType('Other');
  }

  /**
   * Get the plan cluster type.
   *
   * @return string|null
   *   The plan cluster type.
   */
  public function getPlanClusterType() {
    if (!$this->hasField('field_plan_cluster_type')) {
      return NULL;
    }
    $allowed = [
      self::CLUSTER_TYPE_CLUSTER,
      self::CLUSTER_TYPE_SECTOR,
    ];
    $cluster_type = $this->get('field_plan_cluster_type')?->value ?? self::CLUSTER_TYPE_CLUSTER;
    return in_array($cluster_type, $allowed) ? $cluster_type : self::CLUSTER_TYPE_CLUSTER;
  }

  /**
   * Get the plan subtitle.
   *
   * @return string|null
   *   The plan subtitle.
   */
  public function getPlanSubtitle() {
    if (!$this->hasField('field_subtitle')) {
      return NULL;
    }
    return $this->get('field_subtitle')->value;
  }

  /**
   * Get the plan start date.
   *
   * @return string|null
   *   The plan start date.
   */
  public function getPlanStartDate() {
    if (!$this->hasField('field_plan_date_range')) {
      return NULL;
    }
    return $this->get('field_plan_date_range')->value;
  }

  /**
   * Get the plan end date.
   *
   * @return string|null
   *   The plan end date.
   */
  public function getPlanEndDate() {
    if (!$this->hasField('field_plan_date_range')) {
      return NULL;
    }
    return $this->get('field_plan_date_range')->end_value;
  }

  /**
   * Get the plan document publication date.
   *
   * @return string|null
   *   The plan end date.
   */
  public function getPlanDocumentPublishedDate() {
    if (!$this->hasField('field_document_published_on')) {
      return NULL;
    }
    return $this->get('field_document_published_on')->value;
  }

  /**
   * Get the plan document coordinator(s).
   *
   * @return string[]|null
   *   The plan coordinator(s).
   */
  public function getPlanCoordinator() {
    if (!$this->hasField('field_plan_coordinator')) {
      return NULL;
    }
    $value = $this->get('field_plan_coordinator')->getValue();
    return !empty($value) ? array_filter(array_map(function ($item) {
      return $item['value'];
    }, array_filter($value))) : NULL;
  }

  /**
   * Get the short version of the plan type label.
   *
   * @param bool $override
   *   Whether the overridden label can be used if it's available.
   *
   * @return string|null
   *   The short label of the plan type.
   */
  public function getPlanTypeShortLabel($override = TRUE) {
    $plan_type = $this->getPlanType();
    $plan_type_label = $override ? $this->getPlanSubtitle() : $plan_type->label();
    return $plan_type_label ? $this->getPlanTypeShortName($plan_type_label) : NULL;
  }

  /**
   * Get the plan status label.
   *
   * @return string|null
   *   A label for the plan status or NULL if the field is not found.
   */
  public function getPlanStatusLabel() {
    if (!$this->hasField('field_released')) {
      return NULL;
    }
    $released = $this->get('field_released') ?? NULL;
    if (!$released) {
      return NULL;
    }
    $field_definition = $released->getFieldDefinition();
    return $released->value ? $field_definition->getSetting('on_label') : $field_definition->getSetting('off_label');
  }

  /**
   * Get the operations category.
   *
   * @return \Drupal\taxonomy\TermInterface|null
   *   The operations category term or NULL if not set.
   */
  public function getOperationsCategory() {
    if (!$this->hasField('field_operations_category')) {
      return NULL;
    }
    return $this->get('field_operations_category')?->entity ?: NULL;
  }

  /**
   * Get the configured plan caseload.
   *
   * @return int|null
   *   The ID of the configured plan caseload.
   */
  public function getPlanCaseloadId() {
    if (!$this->hasField('field_plan_caseload')) {
      return NULL;
    }
    return $this->get('field_plan_caseload')?->attachment_id ?: NULL;
  }

  /**
   * Get the plan caseload attachment.
   *
   * @param array $caseloads
   *   The caseloads to choose from.
   *
   * @return object|null
   *   A caseload object or NULL.
   */
  public function getPlanCaseload(array $caseloads) {
    return $this->findPlanCaseload($caseloads, $this->getPlanCaseloadId());
  }

  /**
   * Get the document uri.
   *
   * @return string|null
   *   A uri to the document for the plan.
   */
  public function getDocumentUri() {
    if (!$this->hasField('field_plan_document_link')) {
      return NULL;
    }
    return $this->get('field_plan_document_link')->uri ?? NULL;
  }

  /**
   * Get the decimal format to use for number formatting.
   *
   * @return string|null
   *   Either 'comma', 'point' or NULL.
   */
  public function getDecimalFormat() {
    if (!$this->hasField('field_decimal_format')) {
      return NULL;
    }
    return $this->get('field_decimal_format')->value ?? NULL;
  }

  /**
   * Get the maximum admin level for the plan.
   *
   * @return int|null
   *   The highest supported admin level.
   */
  public function getMaxAdminLevel() {
    if (!$this->hasField('field_max_admin_level')) {
      return NULL;
    }
    return $this->get('field_max_admin_level')->value ?? NULL;
  }

  /**
   * Whether the plan is marked as restricted.
   *
   * @return bool
   *   TRUE id the plan is marked as restricted, FALSE otherwhise.
   */
  public function isRestricted() {
    if (!$this->hasField('field_restricted')) {
      return NULL;
    }
    return $this->get('field_restricted')->value ?? FALSE;
  }

  /**
   * Whether the plan is marked as restricted.
   *
   * @return bool
   *   TRUE id the plan is marked as restricted, FALSE otherwhise.
   */
  public function isReleased() {
    if (!$this->hasField('field_released')) {
      return NULL;
    }
    return $this->get('field_released')->value ?? FALSE;
  }

  /**
   * Whether this plan can link financial data to FTS.
   *
   * @return bool
   *   Whether the plan can be linked to FTS.
   */
  public function canLinkToFts() {
    if (!$this->hasField('field_link_to_fts')) {
      return FALSE;
    }
    return $this->get('field_link_to_fts')->value ?? FALSE;
  }

}
