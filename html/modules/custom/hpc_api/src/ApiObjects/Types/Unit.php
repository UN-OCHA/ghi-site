<?php

namespace Drupal\hpc_api\ApiObjects\Types;

use Drupal\hpc_api\ApiObjects\Type;

/**
 * Class for unit objects.
 */
class Unit extends Type {

  /**
   * The French name.
   *
   * @var string
   */
  protected string $nameFrench;

  const GRAPHQL_ITEMS = ['Id', 'Name', 'NameFrench'];

  const TYPE_PERCENTAGE = 'percentage';
  const TYPE_AMOUNT = 'amount';

  const GROUP_PEOPLE = 'people';
  const GROUP_AMOUNT = 'amount';

  /**
   * {@inheritdoc}
   */
  public function __construct(object $data) {
    parent::__construct($data);
    $this->nameFrench = $data->NameFrench ?? NULL;
  }

  /**
   * Get the french name.
   *
   * @return string|null
   *   Either a string or NULL.
   */
  public function getNameFrench(): ?string {
    return $this->nameFrench;
  }

  /**
   * Get the localized name.
   *
   * @param string $langcode
   *   The langcode.
   *
   * @return string
   *   A localized name or the none localized one.
   */
  public function getLocalizedName(string $langcode): string {
    $name = match ($langcode) {
      'fr' => $this->getNameFrench(),
      default => NULL,
    };
    return $name ?? $this->getName();
  }

  /**
   * Get the unit type.
   *
   * @return string
   *   Either 'percentage' or 'amount'.
   */
  public function getType() {
    return $this->isPercentage() ? self::TYPE_PERCENTAGE : self::TYPE_AMOUNT;
  }

  /**
   * Whether this unit represents a percentage.
   *
   * @return bool
   *   TRUE if the unit represents a percentage, FALSE otherwise.
   */
  public function isPercentage(): bool {
    $percentage_types = [
      'Percentage',
    ];
    return in_array($this->getName(), $percentage_types);
  }

  /**
   * Get the unit group.
   *
   * @return string
   *   Either 'people' or 'amount'.
   */
  public function getGroup(): string {
    $people_group = [
      'Individuals',
      'Children',
      'Community Leaders',
      'Government Officials',
      'Health Workers',
      'Women',
      'Students',
      'Boys',
      'Education Personnel',
      'Leaders',
      'Officials',
      'Parents',
      'Teachers',
      'Infants',
      'Medical Staff',
      'Patients',
      'Pregnant Women',
      'Staff',
      'Surgeon',
      'Participants',
      'Survivors',
      'Agents',
      'Investigators',
      'Victims',
      'Masons',
      'Community Mediators',
      'People',
      'Members',
      'Artisans',
      'Returnees',
      'Teams',
      'Volunteers',
      'Pupils',
      'Mothers',
      'Trainers',
      'Child/mother Couples',
      'Family',
      'Radio operators',
      'Heads of household',
      'Women/Girls',
    ];
    return in_array($this->getName(), $people_group) ? self::GROUP_PEOPLE : self::GROUP_AMOUNT;
  }

}
