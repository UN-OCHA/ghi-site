<?php

namespace Drupal\ghi_plans\Controller;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\CsrfTokenGenerator;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Link;
use Drupal\Core\Render\Markup;
use Drupal\Core\Url;
use Drupal\Core\Routing\RedirectDestinationInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\ghi_plans\Helpers\PlanStructureHelper;
use Drupal\hpc_api\Query\EndpointQuery;
use Drupal\hpc_common\Helpers\ArrayHelper;
use Drupal\hpc_common\Helpers\NodeHelper;
use Drupal\node\NodeInterface;
use Drupal\publishcontent\Access\PublishContentAccess;
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
  public function __construct(EndpointQuery $endpoint_query, RedirectDestinationInterface $redirect_destination, PublishContentAccess $publish_content_access, AccountProxyInterface $user, CsrfTokenGenerator $csrf_token) {
    $this->endpointQuery = $endpoint_query;
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
      $container->get('hpc_api.endpoint_query'),
      $container->get('redirect.destination'),
      $container->get('publishcontent.access'),
      $container->get('current_user'),
      $container->get('csrf_token'),
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

    $edit_icon = Markup::create('<i class="material-icons edit-icon">edit</i>');
    $published_icon = Markup::create('<i class="material-icons published">toggle_off</i>');
    $unpublished_icon = Markup::create('<i class="material-icons unpublished">toggle_on</i>');

    $link_options = [
      'query' => $this->redirectDestination->getAsArray(),
      'html' => TRUE,
    ];

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

        // Get the node for url building.
        $entity_node = NodeHelper::getNodeFromOriginalId($entity->id, $plan_object->drupal_entity_type);

        if ($entity_node) {
          // The token for the publishing links need to be generated manually
          // here.
          $token = $this->csrfToken->get('node/' . $entity_node->id() . '/toggleStatus');

          $route_args = ['node' => $entity_node->id()];

          // Add some quick action links.
          $operations = [];

          // An edit link for the entity.
          $options = $link_options + [
            'attributes' => [
              'title' => $this->t('Edit this entity'),
            ],
          ];
          $operations[] = Link::fromTextAndUrl($edit_icon, Url::fromRoute('entity.node.edit_form', $route_args, $options))->toString();

          // And a toggle for the publishing state.
          if ($this->publishContentAccess->access($this->currentUser, $entity_node)->isAllowed()) {
            $link_options['query']['token'] = $token;
            if ($entity->published) {
              $options = $link_options + [
                'attributes' => [
                  'title' => $this->t('This entity is currently published. Click to unpublish.'),
                ],
              ];
              $operations[] = Link::fromTextAndUrl($published_icon, Url::fromRoute('entity.node.publish', $route_args, $options))->toString();
            }
            elseif ($node->isPublished()) {
              $options = $link_options + [
                'attributes' => [
                  'title' => $this->t('This entity is currently unpublished. Click to publish.'),
                ],
              ];
              $operations[] = Link::fromTextAndUrl($unpublished_icon, Url::fromRoute('entity.node.publish', $route_args, $options))->toString();
            }
          }

          $options = ['attributes' => ['title' => $title_tooltip]];
          $item_title = Link::fromTextAndUrl($title, Url::fromRoute('entity.node.canonical', $route_args, $options))->toString() . implode('', $operations);
        }
        else {
          $item_title = Markup::create('<span title="' . $title_tooltip . '">' . $title . '</span>');
        }

        if (!empty($entity->children)) {
          $item = [
            '#theme' => 'item_list',
            '#title' => Markup::create($item_title),
            '#items' => [],
          ];
          $this->addChildren($entity, $item);
          $group_items['#items'][] = $item;
        }
        else {
          $group_items['#items'][] = Markup::create($item_title);
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
   *   The API entity object holding the children.
   * @param array $item
   *   The item to which the children should be added.
   */
  private function addChildren($entity, array &$item) {
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
