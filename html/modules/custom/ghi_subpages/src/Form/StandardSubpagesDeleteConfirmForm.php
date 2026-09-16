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
 * Confirms individual or bulk deletion of direct standard subpages.
 */
class StandardSubpagesDeleteConfirmForm extends SubpagesStructureConfirmFormBase {

  /**
   * The parent section.
   *
   * @var \Drupal\node\NodeInterface
   */
  protected $section;

  /**
   * The selected child node IDs.
   *
   * @var int[]
   */
  protected $childIds = [];

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
    return 'ghi_subpages_standard_delete_confirm';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?NodeInterface $node = NULL, string $subpages = '') {
    $this->section = $node;
    $this->childIds = array_map('intval', explode(',', $subpages));
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return $this->t('Delete @count standard subpages?', ['@count' => count($this->childIds)]);
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
    return $this->t('Delete selected pages');
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $nodes = $this->entityTypeManager->getStorage('node')->loadMultiple($this->childIds);
    if (count($nodes) !== count($this->childIds)) {
      $this->messenger()->addError($this->t('One or more selected pages no longer exist.'));
    }
    else {
      try {
        $this->subpageManager->deleteStandardSubpages($this->section, array_values($nodes));
        Cache::invalidateTags($this->section->getCacheTags());
        $this->messenger()->addStatus($this->t('Deleted @count standard subpages.', ['@count' => count($nodes)]));
      }
      catch (\LogicException $exception) {
        $this->messenger()->addError($this->t('The selected pages could not be deleted.'));
      }
    }
    // Drupal's destination overrides form redirects; ignore it so a link from
    // content admin still returns to this section's Subpages task.
    $form_state->setIgnoreDestination();
    $form_state->setRedirect('ghi_subpages.node.pages', ['node' => $this->section->id()]);
  }

}
