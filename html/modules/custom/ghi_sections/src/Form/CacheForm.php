<?php

namespace Drupal\ghi_sections\Form;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a form for batch logframe rebuild.
 */
class CacheForm extends FormBase {

  /**
   * The cache service.
   *
   * @var \Drupal\Core\Cache\CacheBackendInterface
   */
  public $cache;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $instance = new static();
    $instance->cache = $container->get('cache.default');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'ghi_sections_cache_form';
  }

  /**
   * Title callback for the form route.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node object.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup
   *   The title for the form page.
   */
  public function getTitle(NodeInterface $node) {
    return $this->t('Cache control for <em>@label</em>', [
      '@label' => $node->label(),
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, NodeInterface $node = NULL) {
    $form['#node'] = $node;
    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Clear API cache'),
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    /** @var \Drupal\ghi_sections\Entity\Section $node */
    $node = $form['#node'];
    Cache::invalidateTags($node->getApiCacheTagsToInvalidate());
    $this->messenger()->addStatus($this->t('The API cache for @label has been cleared', [
      '@label' => $node->label(),
    ]));
  }

}
