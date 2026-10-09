<?php

namespace Drupal\ghi_subpages;

/**
 * An immutable creation and navigation definition for an operation year.
 */
final class PageSet {

  /**
   * Constructs a page set.
   */
  public function __construct(private readonly string $id, private readonly array $createOnInsert, private readonly array $navigationOrder) {
  }

  /**
   * Returns the page set ID.
   */
  public function getId(): string {
    return $this->id;
  }

  /**
   * Returns the bundles to create for a newly inserted section.
   */
  public function getCreateOnInsert(): array {
    return $this->createOnInsert;
  }

  /**
   * Returns the default section navigation order.
   */
  public function getNavigationOrder(): array {
    return $this->navigationOrder;
  }

}
