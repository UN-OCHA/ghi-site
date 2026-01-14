<?php

namespace Drupal\ghi_plans\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Render\Markup;
use Drupal\ghi_base_objects\Entity\BaseObjectInterface;
use Drupal\ghi_plans\ApiObjects\Entities\EntityObjectInterface;
use Drupal\ghi_plans\Entity\Plan;
use Drupal\ghi_plans\Helpers\PlanStructureHelper;
use Drupal\hpc_common\Helpers\ArrayHelper;

/**
 * Controller for autocomplete plan loading.
 */
class PlanStructureController extends ControllerBase {

  /**
   * Page callback for the plan structure page.
   *
   * @param \Drupal\ghi_base_objects\Entity\BaseObjectInterface $base_object
   *   The base object.
   */
  public function showPage(BaseObjectInterface $base_object) {
    if (!$base_object instanceof Plan) {
      return $this->t('The structure callback is available for base objects of type plan');
    }
    $plan_id = $base_object->getSourceId();
    $ple_structure = PlanStructureHelper::getPlanEntityStructure($plan_id);
    $plan_structure = PlanStructureHelper::getPlanStructure($base_object);

    $items = [];
    foreach (array_merge($plan_structure['plan_entities'], $plan_structure['governing_entities']) as $plan_object) {
      $group_items = [
        '#theme' => 'item_list',
        '#title' => $plan_object->label,
        '#items' => [],
      ];
      foreach ($ple_structure as $entity) {
        if ($plan_object->entity_prototype_id != $entity->entity_prototype_id) {
          continue;
        }
        $title = $entity->getName() . ' ' . $entity->getCustomReference() . ' (' . $entity->getComposedReference() . ')';
        $title_tooltip = $entity->getName() . ' ' . $entity->getCustomReference() . ' (' . $entity->getComposedReference() . ', ' . $entity->id() . ')';
        $item_title = Markup::create('<span title="' . $title_tooltip . '">' . $title . '</span>');

        if (!empty($entity->getChildren())) {
          $item = [
            '#theme' => 'item_list',
            '#title' => $item_title,
            '#items' => [],
          ];
          $this->addChildren($entity, $item);
          $group_items['#items'][] = $item;
        }
        else {
          $group_items['#items'][] = $item_title;
        }
      }

      if (!empty($group_items['#items'])) {
        $items[] = $group_items;
      }
    }

    return [
      '#theme' => 'item_list',
      '#items' => $items,
      '#attributes' => ['class' => 'plan-structure'],
      '#attached' => [
        'library' => ['ghi_plans/ghi_plans.admin.plan_structure'],
      ],
    ];
  }

  /**
   * Add child elements to plan structure page output.
   *
   * @param \Drupal\ghi_plans\ApiObjects\Entities\EntityObjectInterface $entity
   *   The API entity object holding the children.
   * @param array $item
   *   The item to which the children should be added.
   */
  private function addChildren(EntityObjectInterface $entity, array &$item) {
    $last_group_name = NULL;
    if (!empty($entity->getChildren())) {
      $children = $entity->getChildren();
      ArrayHelper::sortObjectsByStringProperty($children, 'display_name');
      $group_items = NULL;

      foreach ($children as $child) {
        $current_group_name = $child->group_name;
        if ($current_group_name != $last_group_name) {
          if ($group_items && !empty($group_items['#items'])) {
            $item['#items'][] = $group_items;
          }
          $group_items = [
            '#theme' => 'item_list',
            '#title' => $current_group_name,
            '#items' => [],
          ];
        }
        $last_group_name = $current_group_name;

        $title = $child->getDisplayName() . ' (' . $child->getComposedReference() . ')';
        $title_tooltip = $child->getDisplayName() . ' (' . $child->getComposedReference() . ', ' . $child->id() . ')';
        $item_title = Markup::create('<span title="' . $title_tooltip . '">' . $title . '</span>');

        if (!empty($child->getChildren())) {
          $sub_item = [
            '#theme' => 'item_list',
            '#title' => $item_title,
            '#items' => [],
          ];
          $this->addChildren($child, $sub_item);
          $group_items['#items'][] = $sub_item;
        }
        else {
          $group_items['#items'][] = $item_title;
        }
      }

      if (!empty($group_items['#items'])) {
        $item['#items'][] = $group_items;
      }
    }
  }

}
