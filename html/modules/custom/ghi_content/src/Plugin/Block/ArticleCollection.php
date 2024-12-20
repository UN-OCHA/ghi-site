<?php

namespace Drupal\ghi_content\Plugin\Block;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\ghi_blocks\Interfaces\MultiStepFormBlockInterface;
use Drupal\ghi_blocks\Interfaces\OptionalTitleBlockInterface;
use Drupal\ghi_blocks\Plugin\Block\GHIBlockBase;
use Drupal\ghi_form_elements\Traits\ConfigurationContainerTrait;
use Drupal\link\Plugin\Field\FieldWidget\LinkWidget;

/**
 * Provides a 'ArticleCollectio' block.
 *
 * @Block(
 *  id = "article_collection",
 *  admin_label = @Translation("Article collection"),
 *  category = @Translation("Narrative Content"),
 *  context_definitions = {
 *    "node" = @ContextDefinition("entity:node", label = @Translation("Node"), required = FALSE),
 *  },
 *  config_forms = {
 *    "tabs" = {
 *      "title" = @Translation("Tabs"),
 *      "callback" = "tabsForm",
 *      "base_form" = TRUE
 *    },
 *    "display" = {
 *      "title" = @Translation("Display"),
 *      "callback" = "displayForm"
 *    }
 *  }
 * )
 */
class ArticleCollection extends GHIBlockBase implements MultiStepFormBlockInterface, OptionalTitleBlockInterface {

  use ConfigurationContainerTrait;

  /**
   * {@inheritdoc}
   */
  public function buildContent() {
    $conf = $this->getBlockConfig();
    $context = $this->getBlockContext();

    $items = $this->getConfiguredItems($conf['tabs']['article_collections'] ?? []);
    if (empty($items)) {
      return NULL;
    }

    $cache_tags = [];
    $tabs = [];
    foreach ($items as $item) {

      /** @var \Drupal\ghi_form_elements\ConfigurationContainerItemPluginInterface $item_type */
      $item_type = $this->getItemTypePluginForColumn($item, $context);
      $cache_tags = Cache::mergeTags($cache_tags, $item_type->getCacheTags() ?? []);
      $rendered = $item_type->getRenderArray();
      if (empty($rendered)) {
        continue;
      }
      $tabs[] = [
        'title' => [
          '#markup' => $item_type->getLabel(),
        ],
        'items' => $rendered,
      ];
    }

    $link = NULL;
    if (!empty($conf['display']['publications_url'])) {
      $link = Link::fromTextAndUrl($this->t('View all articles'), Url::fromUri($conf['display']['publications_url']));
      $link->getUrl()->setOptions([
        'attributes' => [
          'class' => ['cd-button', 'external'],
        ],
      ]);
    }

    $build = [
      '#cache' => [
        'tags' => $cache_tags,
      ],
    ];

    if ($tabs) {
      $build[] = [
        '#theme' => 'tab_container',
        '#tabs' => $tabs,
      ];
      if ($link) {
        $build[] = $link->toRenderable();
      }
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
      'tabs' => [
        'article_collections' => [],
      ],
      'display' => [
        'publications_url' => NULL,
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultSubform($is_new = FALSE) {
    return 'tabs';
  }

  /**
   * {@inheritdoc}
   */
  public function getTitleSubform() {
    return 'display';
  }

  /**
   * {@inheritdoc}
   */
  public function tabsForm(array $form, FormStateInterface $form_state) {
    $form['article_collections'] = [
      '#type' => 'configuration_container',
      '#title' => $this->t('Configured article collections'),
      '#title_display' => 'invisible',
      '#item_type_label' => $this->t('Article collection'),
      '#default_value' => $this->getDefaultFormValueFromFormState($form_state, 'article_collections'),
      '#allowed_item_types' => $this->getAllowedItemTypes(),
      '#preview' => [
        'columns' => [
          'label' => $this->t('Label'),
          'tag_summary' => $this->t('Tags'),
          'article_count' => $this->t('Available articles'),
          'display_summary' => $this->t('Display summary'),
        ],
      ],
      '#element_context' => $this->getBlockContext(),
    ];
    return $form;
  }

  /**
   * Form callback for the display configuration form.
   */
  public function displayForm(array $form, FormStateInterface $form_state) {
    $form['publications_url'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Publications URL'),
      '#description' => $this->t('Add an optional link to an external source of publications.'),
      '#default_value' => $this->getDefaultFormValueFromFormState($form_state, 'publications_url'),
      '#element_validate' => [
        [LinkWidget::class, 'validateUriElement'],
      ],
    ];
    return $form;
  }

  /**
   * Get the custom context for this block.
   *
   * @return array
   *   An array with context data or query handlers.
   */
  public function getBlockContext() {
    return [
      'page_node' => $this->getCurrentBaseEntity(),
      'section' => $this->getCurrentSectionNode(),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getAllowedItemTypes() {
    $item_types = [
      'article_collection' => [],
    ];
    return $item_types;
  }

}
