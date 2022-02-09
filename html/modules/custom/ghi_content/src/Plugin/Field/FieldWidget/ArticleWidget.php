<?php

namespace Drupal\ghi_content\Plugin\Field\FieldWidget;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines the 'ghi_remote_article' field widget.
 *
 * @FieldWidget(
 *   id = "ghi_remote_article",
 *   label = @Translation("Remote article"),
 *   field_types = {"ghi_remote_article"},
 * )
 */
class ArticleWidget extends WidgetBase implements ContainerFactoryPluginInterface {

  /**
   * The attachment query.
   *
   * @var \Drupal\ghi_content\RemoteSource\RemoteSourceManager
   */
  public $remoteSourceManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->remoteSourceManager = $container->get('plugin.manager.remote_source');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    $remote_sources = $this->remoteSourceManager->getDefinitions();

    $remote_source = $items[$delta]->remote_source ?? array_key_first($remote_sources);
    $article_id = $items[$delta]->article_id ?? NULL;

    $remote_source_instance = $this->remoteSourceManager->createInstance($remote_source);
    $article = $remote_source_instance->getArticle($article_id);

    $element['remote_source'] = [
      '#type' => 'remote_source',
      '#title' => $this->t('Content source'),
      '#description' => $this->t('Select the source of the article.'),
      '#default_value' => $remote_source,
    ];

    $element['article'] = [
      '#type' => 'remote_article_autocomplete',
      '#title' => $this->t('Article'),
      '#remote_source' => $remote_source,
      '#description' => $this->t('Type the title of an article to see suggestions.'),
      '#default_value' => $article ? [$article] : NULL,
    ];

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function massageFormValues(array $values, array $form, FormStateInterface $form_state) {
    foreach ($values as $delta => $value) {
      $values[$delta]['article_id'] = $value['article'][0]['article_id'];
    }
    return $values;
  }

}
