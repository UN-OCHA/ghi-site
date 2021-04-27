<?php

namespace Drupal\ghi_plans\Controller;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Link;
use Drupal\Core\Render\Markup;
use Drupal\Core\Url;
use Drupal\ghi_plans\Helpers\PlanStructureHelper;
use Drupal\hpc_api\Query\EndpointQuery;
use Drupal\hpc_common\Helpers\ArrayHelper;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Controller for autocomplete plan loading.
 */
class PlanStructureController extends ControllerBase {

  /**
   * The endpoint query handler.
   *
   * @var \Drupal\hpc_api\Query\EndpointQuery
   */
  protected $endpointQuery;

  /**
   * Public constructor.
   */
  public function __construct(EndpointQuery $endpoint_query) {
    $this->endpointQuery = $endpoint_query;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('hpc_api.endpoint_query'),
    );
  }

  /**
   * Access callback for the plan structure page.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node object.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public function access(NodeInterface $node) {
    return AccessResult::allowedIf($node->bundle() == 'plan');
  }

  /**
   * Page callback for the plan structure page.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node object.
   */
  public function showPage(NodeInterface $node) {

    $plan_original_id = $node->field_original_id->value;

    $plan_data = $this->getPlanEntitiesData($plan_original_id);
    $ple_structure = PlanStructureHelper::getPlanEntityStructure($plan_data);

    $prototype_data = $this->getPrototypeData($plan_original_id);
    $plan_structure = PlanStructureHelper::getPlanStructureFromPrototype($prototype_data, $node);

    $edit_icon = '<i class="material-icons edit-icon">edit</i>';
    $published_icon = '<i class="material-icons published">toggle_off</i>';
    $unpublished_icon = '<i class="material-icons unpublished">toggle_on</i>';

    $items = [];

    foreach (array_merge($plan_structure['plan_entities'], $plan_structure['governing_entities']) as $plan_object) {
      $group_items = [
        '#theme' => 'item_list',
        '#title' => Markup::create('<h3>' . $plan_object->label . '</h3>'),
        '#items' => [],
      ];
      foreach ($ple_structure as $entity) {
        if ($plan_object->entity_prototype_id != $entity->entity_prototype_id) {
          continue;
        }
        $title = $entity->name . ' ' . $entity->custom_reference . ' (' . $entity->composed_reference . ')';
        $title_tooltip = $entity->name . ' ' . $entity->custom_reference . ' (' . $entity->composed_reference . ', ' . $entity->id . ')';

        if (!empty($entity->path)) {
          // $operations = [];
          // $operations[] = l($edit_icon, $entity->path . '/edit', $link_options + array('attributes' => array(
          //   'title' => t('Edit this entity'),
          // )));
          // if ($entity->published) {
          //   $operations[] = l($published_icon, $entity->path . '/unpublish/' . drupal_get_token(), $link_options + array(
          //     'attributes' => array(
          //       'title' => t('This entity is currently published. Click to unpublish.'),
          //     )
          //   ));
          // }
          // elseif ($plan_node->status == NODE_PUBLISHED) {
          //   $operations[] = l($unpublished_icon, $entity->path . '/publish/' . drupal_get_token(), $link_options + array(
          //     'attributes' => array(
          //       'title' => t('This entity is currently unpublished. Click to publish.'),
          //     ),
          //   ));
          // }

          $item_title = Link::fromTextAndUrl($title, Url::fromUserInput('/' . $entity->path, ['attributes' => ['title' => $title_tooltip]]))->toString();
        }
        else {
          $item_title = Markup::create('<span title="' . $title_tooltip . '">' . $title . '</span>');
        }

        $item = [
          '#theme' => 'item_list',
          '#title' => $item_title,
          '#items' => [],
        ];

        $this->addChildren($entity, $item);
        if (!empty($item['#items'])) {
          $group_items['#items'][] = $item;
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
   * @param object $entity
   * @param array $item
   */
  private function addChildren($entity, &$item) {
    if (!empty($entity->children)) {
      ArrayHelper::sortObjectsByStringProperty($entity->children, 'display_name');
      foreach ($entity->children as $child) {
        $title = $child->display_name . ' (' . $child->composed_reference . ')';
        $title_tooltip = $child->display_name . ' (' . $child->composed_reference . ', ' . $child->id . ')';
        $item_title = Markup::create('<span title="' . $title_tooltip . '">' . $title . '</span>');

        if (!empty($child->children)) {
          $sub_item = [
            '#theme' => 'item_list',
            '#title' => $item_title,
            '#items' => [],
          ];
          $this->addChildren($child, $sub_item);
          $item['#items'][] = $sub_item;
        }
        else {
          $item['#items'][] = $item_title;
        }
      }
    }
  }

  /**
   * Get the plan entities data from the API.
   *
   * @param int $plan_id
   *   The plan id for which to retrieve the data.
   *
   * @return object
   *   The structured data object.
   */
  private function getPlanEntitiesData($plan_id) {
    $this->endpointQuery->setArguments([
      'endpoint' => 'plan/' . $plan_id,
      'api_version' => 'v2',
      'auth_method' => EndpointQuery::AUTH_METHOD_API_KEY,
      'query_args' => [
        'content' => 'entities',
        'addPercentageOfTotalTarget' => 'true',
        'disaggregation' => 'false',
        'version' => 'latest',
      ],
    ]);
    return $this->endpointQuery->getData();
  }

  /**
   * Get the prototype data from the API.
   *
   * @param int $plan_id
   *   The plan id for which to retrieve the data.
   *
   * @return object
   *   The structured data object.
   */
  private function getPrototypeData($plan_id) {
    // Query the API for the prototype of this plan.
    $this->endpointQuery->setArguments([
      'endpoint' => 'plan/' . $plan_id . '/entity-prototype',
      'api_version' => 'v2',
      'auth_method' => EndpointQuery::AUTH_METHOD_API_KEY,
      'sort' => 'orderNumber',
    ]);
    return $this->endpointQuery->getData();
  }

}
