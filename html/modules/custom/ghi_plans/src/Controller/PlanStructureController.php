<?php

namespace Drupal\ghi_plans\Controller;

use Drupal\Core\Access\CsrfTokenGenerator;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Render\Markup;
use Drupal\Core\Routing\RedirectDestinationInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\ghi_base_objects\Entity\BaseObjectInterface;
use Drupal\ghi_plans\ApiObjects\Entities\EntityObjectInterface;
use Drupal\ghi_plans\Helpers\PlanStructureHelper;
use Drupal\hpc_api\Query\EndpointQueryManager;
use Drupal\hpc_common\Helpers\ArrayHelper;
use Drupal\publishcontent\Access\PublishContentAccess;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Controller for autocomplete plan loading.
 */
class PlanStructureController extends ControllerBase {

  /**
   * The plan entities query handler.
   *
   * @var \Drupal\ghi_plans\Plugin\EndpointQuery\PlanEntitiesQuery
   */
  protected $planEntitiesQuery;

  /**
   * The plan prototype query handler.
   *
   * @var \Drupal\ghi_plans\Plugin\EndpointQuery\PlanPrototypeQuery
   */
  protected $planPrototypeQuery;

  /**
   * The redirect destination service.
   *
   * @var \Drupal\Core\Routing\RedirectDestinationInterface
   */
  protected $redirectDestination;

  /**
   * The publish content access service.
   *
   * @var \Drupal\publishcontent\Access\PublishContentAccess
   */
  protected $publishContentAccess;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * The CSRF token generator.
   *
   * @var \Drupal\Core\Access\CsrfTokenGenerator
   */
  protected $csrfToken;

  /**
   * Public constructor.
   */
  public function __construct(EndpointQueryManager $endpoint_query_manager, RedirectDestinationInterface $redirect_destination, PublishContentAccess $publish_content_access, AccountProxyInterface $user, CsrfTokenGenerator $csrf_token) {
    $this->planEntitiesQuery = $endpoint_query_manager->createInstance('plan_entities_query');
    $this->planPrototypeQuery = $endpoint_query_manager->createInstance('plan_prototype_query');
    $this->redirectDestination = $redirect_destination;
    $this->publishContentAccess = $publish_content_access;
    $this->currentUser = $user;
    $this->csrfToken = $csrf_token;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('plugin.manager.endpoint_query_manager'),
      $container->get('redirect.destination'),
      $container->get('publishcontent.access'),
      $container->get('current_user'),
      $container->get('csrf_token'),
    );
  }

  /**
   * Page callback for the plan structure page.
   *
   * @param \Drupal\ghi_base_objects\Entity\BaseObjectInterface $base_object
   *   The base object.
   */
  public function showPage(BaseObjectInterface $base_object) {

    $plan_original_id = $base_object->field_original_id->value;

    $plan_data = $this->getPlanEntitiesData($plan_original_id);
    $ple_structure = PlanStructureHelper::getPlanEntityStructure($plan_data);

    $prototype_data = $this->getPrototypeData($plan_original_id);
    $plan_structure = PlanStructureHelper::getPlanStructureFromPrototype($prototype_data, $base_object);

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
        $title = $entity->name . ' ' . $entity->custom_reference . ' (' . $entity->composed_reference . ')';
        $title_tooltip = $entity->name . ' ' . $entity->custom_reference . ' (' . $entity->composed_reference . ', ' . $entity->id . ')';
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

        $title = $child->display_name . ' (' . $child->composed_reference . ')';
        $title_tooltip = $child->display_name . ' (' . $child->composed_reference . ', ' . $child->id . ')';
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
    return $this->planEntitiesQuery->getData(['plan_id' => $plan_id]);
  }

  /**
   * Get the prototype data from the API.
   *
   * @param int $plan_id
   *   The plan id for which to retrieve the data.
   *
   * @return array
   *   Array with the plans prototype objects.
   */
  private function getPrototypeData($plan_id) {
    return $this->planPrototypeQuery->getPrototype($plan_id);
  }

}
