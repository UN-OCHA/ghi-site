<?php

namespace Drupal\ghi_plans\Plugin\ParagraphHandler;

use Drupal\ghi_paragraph_handler\Plugin\ParagraphHandlerBase;

/**
 * Base class for paragraph handlers.
 *
 * @ParagraphHandler(
 *   id = "plan_tab_switcher",
 *   label = @Translation("Plan tab switcher")
 * )
 */
class PlanTabSwitcher extends ParagraphHandlerBase {

  /**
   * Key used for storage
   */
  const KEY = 'plan_tab_switcher';

  /**
   * {@inheritdoc}
   */
  public function preprocess(array &$variables, array $element) {
    parent::preprocess($variables, $element);
    $config = $this->getConfig();

    /** @var \Drupal\node\Entity\Node $plan */
    $plan = $this->parentEntity;
    if ($plan->getType() !== 'plan' && $plan->getType() !== 'plan_para' && $plan->getType() !== 'plan_entity') {
      return;
    }

    // Get parent if needed.
    if ($plan->getType() === 'plan_entity') {
      /** @var \Drupal\node\Entity\Node $plan */
      $plan = $plan->field_plan->first->entity;
    }

    $overview_link = t('Overview');
    if (!$plan->isPublished()) {
      if ($plan->access('view')) {
        $overview_link = $plan->toLink($overview_link);
      }
    }
    else {
      $overview_link = $plan->toLink($overview_link);
    }

    $tabs = [
      [
        'title' => t('Overview'),
        'url' => $plan->toUrl(),
        'attributes' => [
          'class' => ['active'],
        ],
      ],
    ];

    // Get all plan entity nodes with plan as parent.
    $plan_entities = \Drupal::entityTypeManager()->getStorage('node')->loadByProperties([
      'type' => 'plan_entity',
      'field_plan_id' => $plan->id()
    ]);

    if ($plan_entities) {
      $suffix = '';
      if ($config['show_count']) {
        $suffix = ' <span class="counter">' . count($plan_entities)  . '</span>';
      }

      $tabs[] = [
        'title' => t('Clusters') . $suffix,
        'url' => $plan_entities[0]->toUrl(),
        'attributes' => [
          'class' => [],
        ],
      ];
    }

    $variables['content'][] = [
      '#theme' => 'links',
      '#links' => $tabs,
      '#attributes' => [
        'class' => [
          'links--plan-overview',
        ],
      ],
    ];

    // @todo: use cluster icons.
    if ($config['render_all_children']) {
      $children = [];
      foreach ($plan_entities as $plan_entity) {
        $children[] = [
          'title' => t('Clusters') . $suffix,
          'url' => $plan_entities[0]->toUrl(),
          'attributes' => [
            'class' => [],
          ],
        ];
      }

      $variables['content'][] = [
        '#theme' => 'links',
        '#links' => $children,
        '#attributes' => [
          'class' => [
            'links--plan-entities',
          ],
        ],
      ];
    }
  }

  /**
   * Return behavior settings.
   */
  protected function getConfig() {
    $settings = $this->paragraph->getAllBehaviorSettings();
    $config = $settings[static::KEY] ?? [
      'show_count' => TRUE,
      'render_all_children' => FALSE,
    ];

    return $config;
  }

}
