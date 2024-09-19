<?php

namespace Drupal\ghi_plans\Entity;

use Drupal\Component\Render\FormattableMarkup;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ghi_base_objects\Entity\BaseObject;
use Drupal\ghi_base_objects\Entity\BaseObjectMetaDataInterface;
use Drupal\ghi_plans\Traits\PlanTypeTrait;

/**
 * Bundle class for plan base objects.
 */
class Plan extends BaseObject implements BaseObjectMetaDataInterface {

  use PlanTypeTrait;

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
      $this->getPlanStartDate() ? new TranslatableMarkup('<strong>From:</strong> @start_date to @end_date', [
        '@start_date' => DrupalDateTime::createFromFormat('Y-m-d', $this->getPlanStartDate())->format('d/m/Y'),
        '@end_date' => DrupalDateTime::createFromFormat('Y-m-d', $this->getPlanEndDate())->format('d/m/Y'),
      ], $t_options) : NULL,
      $document_published ? new TranslatableMarkup('<strong>Published on:</strong> @date', [
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
   * @return \Drupal\taxonomy\TermInterface|null
   *   The plan type.
   */
  public function getPlanType() {
    if (!$this->hasField('field_plan_type')) {
      return NULL;
    }
    return $this->get('field_plan_type')?->entity ?? NULL;
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
    }, $value)) : NULL;
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
    $included_in_totals = $plan_type ? $plan_type->get('field_included_in_totals')->value : FALSE;
    $plan_type_label = $override ? $this->getPlanSubtitle() : $plan_type->label();
    return $plan_type_label ? $this->getPlanTypeShortName($plan_type_label, $included_in_totals) : NULL;
  }

  /**
   * Get the plan status label.
   *
   * @return string|null
   *   A label for the plan status or NULL if the field is not found.
   */
  public function getPlanStatusLabel() {
    if (!$this->hasField('field_plan_status')) {
      return NULL;
    }
    $plan_status = $this->get('field_plan_status') ?? NULL;
    if (!$plan_status) {
      return NULL;
    }
    $field_definition = $plan_status->getFieldDefinition();
    return $plan_status->value ? $field_definition->getSetting('on_label') : $field_definition->getSetting('off_label');
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
