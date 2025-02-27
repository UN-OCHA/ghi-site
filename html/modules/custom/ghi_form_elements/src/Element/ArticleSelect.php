<?php

namespace Drupal\ghi_form_elements\Element;

use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Attribute\FormElement;
use Drupal\Core\Render\Element;
use Drupal\Core\Render\Element\FormElementBase;
use Drupal\Core\Render\Markup;
use Drupal\entity_browser\Element\EntityBrowserElement;
use Drupal\ghi_content\Entity\Article;
use Drupal\hpc_common\Traits\EntityHelperTrait;
use Drupal\taxonomy\TermInterface;

/**
 * Provides an attachment select element.
 */
#[FormElement('article_select')]
class ArticleSelect extends FormElementBase {

  use EntityHelperTrait;

  const ENTITY_BROWSER_ID = 'articles';

  /**
   * {@inheritdoc}
   */
  public function getInfo() {
    $class = get_class($this);
    return [
      '#default_value' => NULL,
      '#tree' => TRUE,
      '#process' => [
        [$class, 'processArticleSelect'],
        [$class, 'processAjaxForm'],
      ],
      '#pre_render' => [
        [$class, 'preRenderArticleSelect'],
      ],
      '#element_validate' => [
        [$class, 'elementValidate'],
      ],
      '#theme_wrappers' => ['form_element'],
    ];
  }

  /**
   * Element submit callback.
   *
   * @param array $element
   *   The base element.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   * @param array $form
   *   The full form.
   */
  public static function elementValidate(array &$element, FormStateInterface $form_state, array $form) {
    $values = $form_state->getValue($element['#parents']);
    $selected_items = $values['container']['selected'] ?? [];
    $entity_keys = $selected_items ? array_keys($selected_items) : [];
    $form_state->setValueForElement($element, ['entity_ids' => is_array($entity_keys) ? $entity_keys : explode(' ', $entity_keys)]);
  }

  /**
   * {@inheritdoc}
   */
  public static function valueCallback(&$element, $input, FormStateInterface $form_state) {
    if ($input) {
      // Make sure input is returned as normal during item configuration.
      return $input;
    }
    $values = $form_state->getValue($element['#parents']);
    $entity_ids = array_keys($values['container']['selected'] ?? []);
    return ['entity_ids' => $entity_ids];
  }

  /**
   * Process the attachment select form element.
   *
   * This is called during form build. Note that it is not possible to store
   * any arbitrary data inside the form_state object.
   */
  public static function processArticleSelect(array &$element, FormStateInterface $form_state) {
    $values = $form_state->getValue($element['#parents']);
    $selected_items = $values['entity_ids'] ?? explode(' ', $values['container']['browser']['entity_ids']);
    $entity_ids = $selected_items ? $selected_items : $element['#default_value'];
    $selected_articles = self::loadEntitiesByCompositeIds($entity_ids);

    // We need a wrapping container for AJAX operations.
    $wrapper_id = Html::getUniqueId('article-select-wrapper');
    $element['container'] = [
      '#type' => 'container',
      '#attributes' => [
        'id' => $wrapper_id,
      ],
    ];

    $entity_browser = self::getEntityBrowser();
    $add_more_button_label = $entity_browser->getDisplay()->getConfiguration()['link_text'];
    $empty_text_args = [
      '@type' => strtolower($entity_browser->label()),
      '@button_label' => $add_more_button_label,
    ];

    $element['container']['selected'] = [
      '#type' => 'table',
      '#header' => [
        t('Title'),
        t('Tags'),
        t('Status'),
        t('Password required'),
        t('Updated'),
        t('Operations'),
        t('Weight'),
      ],
      '#empty' => t('No articles added yet. Use the <em>@button_label</em> button to add articles.', $empty_text_args),
      '#process' => [
        [self::class, 'processEntityBrowserSelected'],
      ],
      '#wrapper_id' => $wrapper_id,
      '#entities' => $selected_articles,
      '#tabledrag' => [
        [
          'action' => 'order',
          'relationship' => 'sibling',
          'group' => 'weight',
        ],
      ],
    ];

    $element['container']['browser'] = [
      '#type' => 'entity_browser',
      '#entity_browser' => self::ENTITY_BROWSER_ID,
      '#process' => [
        [self::class, 'processEntityBrowser'],
      ],
      '#wrapper_id' => $wrapper_id,
      '#default_value' => $selected_articles,
      '#widget_context' => [
        // This is used to exclude already selected articles from the selection
        // view. Technically, this feeds into the default value for a
        // contextual filter.
        // @see https://www.drupal.org/project/entity_browser/issues/2865928
        'selected_ids' => array_map(function (EntityInterface $entity) {
          return $entity->id();
        }, $selected_articles),
      ],
    ];

    $element['#attached']['library'][] = 'ghi_form_elements/entity_browser';
    $element['#attached']['library'][] = 'ghi_form_elements/article_select';
    return $element;
  }

  /**
   * Render API callback: Processes the entity browser element.
   */
  public static function processEntityBrowser(&$element, FormStateInterface $form_state, &$complete_form) {
    $element = EntityBrowserElement::processEntityBrowser($element, $form_state, $complete_form);
    $element['entity_ids']['#ajax'] = [
      'callback' => [self::class, 'updateEntityBrowserContainer'],
      'wrapper' => $element['#wrapper_id'],
      'event' => 'entity_browser_value_updated',
    ];
    $element['entity_ids']['#default_value'] = implode(' ', array_keys($element['#default_value']));

    // Move the entity browser trigger into a container to that we can show the
    // button as a second-level control.
    $element['actions'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => [
          'second-level-actions-wrapper',
        ],
      ],
      [
        $element['entity_browser'],
      ],
    ];
    unset($element['entity_browser']);
    return $element;
  }

  /**
   * Render API callback: Processes the table element.
   */
  public static function processEntityBrowserSelected(&$element, FormStateInterface $form_state, &$complete_form) {
    $entities = $element['#entities'];

    foreach ($entities as $id => $entity) {
      if (!$entity instanceof Article) {
        continue;
      }
      $tags = array_map(function (TermInterface $tag) {
        return $tag->label();
      }, $entity->getTags());
      sort($tags);

      $delta = count(Element::children($element));
      $element[$id] = [
        '#attributes' => [
          'data-entity-id' => $id,
          'class' => ['draggable'],
        ],
        '#weight' => $delta,
        'title' => ['#markup' => $entity->label()],
        'tags' => ['#markup' => Markup::create(implode(', ', $tags))],
        'status' => [
          '#type' => 'html_tag',
          '#tag' => 'span',
          '#attributes' => [
            'class' => [
              'gin-status',
              $entity->isPublished() ? 'gin-status--success' : 'gin-status--danger',
            ],
          ],
          ['#markup' => $entity->isPublished() ? t('Displayed') : t('Not displayed')],
        ],
        'protected' => [
          '#type' => 'html_tag',
          '#tag' => 'span',
          '#attributes' => [
            'class' => array_filter([
              'gin-status',
              $entity->isProtected() ? 'gin-status--danger' : NULL,
            ]),
          ],
          ['#markup' => $entity->isProtected() ? t('Yes') : t('No')],
        ],
        'updated' => ['#markup' => self::dateFormatter()->format($entity->getChangedTime(), 'short')],
        'operations' => [
          'remove' => [
            '#type' => 'submit',
            '#value' => t('Remove'),
            '#op' => 'remove',
            '#name' => 'remove_' . $id,
            '#ajax' => [
              'callback' => [self::class, 'updateEntityBrowserContainer'],
              'wrapper' => $element['#wrapper_id'],
            ],
          ],
        ],
        'weight' => [
          '#type' => 'weight',
          '#title' => t('Weight for @title', ['@title' => $entity->label()]),
          '#title_display' => 'invisible',
          '#default_value' => $delta,
          '#attributes' => [
            'class' => ['weight'],
          ],
        ],
      ];
    }
    return $element;
  }

  /**
   * AJAX callback: Re-renders the Entity Browser button/table.
   */
  public static function updateEntityBrowserContainer(array &$form, FormStateInterface $form_state) {
    $trigger = $form_state->getTriggeringElement();
    if (isset($trigger['#op']) && $trigger['#op'] === 'remove') {
      $array_parents = array_slice($trigger['#array_parents'], 0, -4);
      // $value_parents = array_slice($trigger['#parents'], 0, -4);
      $container = NestedArray::getValue($form, $array_parents);
      $id = str_replace('remove_', '', $trigger['#name']);
      unset($container['selected'][$id]);

      $value = explode(' ', $container['browser']['entity_ids']['#value']);
      $container['browser']['entity_ids']['#value'] = array_diff($value, [$id]);
    }
    else {
      $array_parents = array_slice($trigger['#array_parents'], 0, -2);
      $container = NestedArray::getValue($form, $array_parents);
    }
    return $container;
  }

  /**
   * Prerender callback.
   */
  public static function preRenderArticleSelect(array $element) {
    $element['#attributes']['type'] = 'article_select';
    Element::setAttributes($element, ['id', 'name', 'value']);
    // Sets the necessary attributes, such as the error class for validation.
    // Without this line the field will not be hightlighted, if an error
    // occurred.
    static::setAttributes($element, ['form-article-select']);
    return $element;
  }

  /**
   * Get the entity browser used with this widget.
   *
   * @return \Drupal\entity_browser\EntityBrowserInterface
   *   The entity browser object.
   */
  private static function getEntityBrowser() {
    return self::entityTypeManager()->getStorage('entity_browser')->load(self::ENTITY_BROWSER_ID);
  }

  /**
   * Get the entity type manager service.
   *
   * @return \Drupal\Core\Entity\EntityTypeManagerInterface
   *   The entity type manager.
   */
  private static function entityTypeManager() {
    return \Drupal::entityTypeManager();
  }

  /**
   * Get the date formatter service.
   *
   * @return \Drupal\Core\Datetime\DateFormatterInterface
   *   The date formatter service.
   */
  private static function dateFormatter() {
    return \Drupal::service('date.formatter');
  }

}
