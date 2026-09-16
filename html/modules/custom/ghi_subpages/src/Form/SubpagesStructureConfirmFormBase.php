<?php

namespace Drupal\ghi_subpages\Form;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\ghi_subpages\SubpageManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides shared mechanics for structural confirmation forms.
 */
abstract class SubpagesStructureConfirmFormBase extends ConfirmFormBase {

  /**
   * The parent section.
   *
   * @var \Drupal\node\NodeInterface
   */
  protected $section;

  /**
   * Constructs a structural confirmation form.
   */
  public function __construct(protected readonly SubpageManager $subpageManager, protected readonly EntityTypeManagerInterface $entityTypeManager) {
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static($container->get('ghi_subpages.manager'), $container->get('entity_type.manager'));
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl() {
    return Url::fromRoute('ghi_subpages.node.pages', ['node' => $this->section->id()]);
  }

  /**
   * Redirects to the parent section's subpages listing.
   */
  protected function redirectToSubpagesListing(FormStateInterface $form_state): void {
    // Drupal's destination would override the explicit redirect and could send
    // a completed or cancelled action back to content admin instead.
    $form_state->setIgnoreDestination();
    $form_state->setRedirectUrl($this->getCancelUrl());
  }

}
