<?php

namespace Drupal\ghi_content\Plugin\Block;

use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\Context\EntityContextDefinition;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ghi_blocks\Interfaces\AutomaticTitleBlockInterface;
use Drupal\ghi_content\Entity\Document as DocumentNode;

/**
 * Provides a 'Document' block.
 */
#[Block(
  id: 'document',
  admin_label: new TranslatableMarkup('Document'),
  category: new TranslatableMarkup('Narrative Content'),
  context_definitions: [
    'node' => new EntityContextDefinition('entity:node', new TranslatableMarkup('Node'), required: FALSE),
  ]
)]
class Document extends ContentBlockBase implements AutomaticTitleBlockInterface {

  /**
   * {@inheritdoc}
   */
  public function getAutomaticBlockTitle() {
    $document = $this->getDocument();
    return $document?->getTitle();
  }

  /**
   * {@inheritdoc}
   */
  public function buildContent() {
    $document = $this->getDocument();
    if (!$document) {
      return NULL;
    }
    $document_node = $this->documentManager->loadNodeForRemoteContent($document);
    if (!$document_node instanceof DocumentNode) {
      return NULL;
    }
    $cacheability = $this->documentArticleContext->createCacheability($document_node);

    $conf = $this->getBlockConfig();
    $show_titles = $conf['show_titles'] ?? TRUE;

    $tabs = [];
    $has_articles = FALSE;
    $chapters = $document->getChapters(FALSE);
    foreach ($chapters as $chapter) {
      $articles = $this->documentArticleContext->prepareArticles(
        $this->documentArticleContext->loadArticlesForChapter($chapter, TRUE),
        $document_node,
        $cacheability
      );
      $has_articles = $has_articles || !empty($articles);
      $tabs[] = [
        'title' => [
          '#markup' => $show_titles ? $chapter->getShortTitle() : NULL,
        ],
        'items' => [
          '#theme' => 'article_collection_cards',
          '#title' => $chapter->getShortTitle(),
          '#articles' => $articles,
          '#options' => [
            'columns' => 3,
          ],
        ],
      ];
    }

    if (!$has_articles) {
      return NULL;
    }

    $build = [
      '#cache' => [
        'tags' => $cacheability->getCacheTags(),
        'max-age' => $cacheability->getCacheMaxAge(),
      ],
    ];

    if ($tabs) {
      $build[] = [
        '#theme' => 'tab_container',
        '#tabs' => $tabs,
      ];
    }
    if ($document_node->isProtected()) {
      $build['#attributes'] = [
        'class' => ['protected'],
      ];
    }
    return $build;
  }

  /**
   * Returns generic default configuration for block plugins.
   *
   * @return array
   *   An associative array with the default configuration.
   */
  protected function getConfigurationDefaults() {
    return [
      'document' => [
        'remote_source' => NULL,
        'document_id' => NULL,
        'show_titles' => TRUE,
      ],
    ];
  }

  /**
   * Select document form.
   */
  public function getConfigForm(array $form, FormStateInterface $form_state) {
    $form['document'] = [
      '#type' => 'remote_document',
      '#default_value' => $this->getDocument(),
      '#required' => TRUE,
    ];

    $form['show_title'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Show the title'),
      '#default_value' => $this->getDefaultFormValueFromFormState($form_state, 'show_title'),
    ];

    return $form;
  }

  /**
   * Get the configured document.
   *
   * @return \Drupal\ghi_content\RemoteContent\RemoteDocumentInterface
   *   The remote document.
   */
  public function getDocument() {
    $conf = $this->getBlockConfig();
    $remote_source_key = $conf['document']['remote_source'] ?? NULL;
    if (!$remote_source_key) {
      return NULL;
    }
    /** @var \Drupal\ghi_content\RemoteSource\RemoteSourceManager $remote_source_manager */
    $remote_source_manager = \Drupal::service('plugin.manager.remote_source');
    $remote_source = $remote_source_manager->createInstance($remote_source_key);
    $document_id = $conf['document']['document_id'] ?? NULL;
    if (!$remote_source || !$document_id) {
      return NULL;
    }
    return $remote_source->getDocument($document_id);
  }

}
