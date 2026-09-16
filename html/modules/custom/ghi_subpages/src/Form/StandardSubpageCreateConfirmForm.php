<?php

namespace Drupal\ghi_subpages\Form;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\ghi_subpages\SubpageManager;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Confirms manual creation of one empty standard subpage.
 */
class StandardSubpageCreateConfirmForm extends SubpagesStructureConfirmFormBase {

  /**
   * The parent section.
   *
   * @var \Drupal\node\NodeInterface
   */
  protected $section;

  /**
   * The selected bundle.
   *
   * @var string
   */
  protected $bundle;

  /**
   * Constructs the form.
   */
  public function __construct(private readonly SubpageManager $subpageManager, private readonly EntityTypeManagerInterface $entityTypeManager) {
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
  public function getFormId() {
    return 'ghi_subpages_standard_create_confirm';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?NodeInterface $node = NULL, string $bundle = '') {
    $this->section = $node;
    $this->bundle = $bundle;
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return $this->t('Create the @type subpage?', ['@type' => $this->bundle]);
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription() {
    $node_type = $this->entityTypeManager->getStorage('node_type')->load($this->bundle);
    return $this->t('Please confirm that the @subpage_type subpage for @plan_label should be created', [
      '@subpage_type' => $node_type?->label() ?? $this->bundle,
      '@plan_label' => $this->section->label(),
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl() {
    return Url::fromRoute('ghi_subpages.node.pages', ['node' => $this->section->id()]);
  }

  /**
   * {@inheritdoc}
   */
  public function getConfirmText() {
    return $this->t('Create page');
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    try {
      $this->subpageManager->createStandardSubpage($this->section, $this->bundle);
      Cache::invalidateTags($this->section->getCacheTags());
      $this->messenger()->addStatus($this->t('Created @type subpage.', ['@type' => $this->bundle]));
    }
    catch (\LogicException $exception) {
      $this->messenger()->addError($this->t('The selected page could not be created.'));
    }
    // Drupal's destination overrides form redirects; ignore it so a link from
    // content admin still returns to this section's Subpages task.
    $form_state->setIgnoreDestination();
    $form_state->setRedirect('ghi_subpages.node.pages', ['node' => $this->section->id()]);
  }

}
