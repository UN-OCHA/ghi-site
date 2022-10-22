<?php

namespace Drupal\ghi_plans\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\ghi_base_objects\Entity\BaseObjectInterface;
use Drupal\hpc_api\Query\EndpointQueryManager;
use Drupal\hpc_common\Helpers\ThemeHelper;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Controller for project related modals.
 */
class ProjectModalController extends ControllerBase {

  /**
   * The endpoint query manager.
   *
   * @var \Drupal\hpc_api\Query\EndpointQueryManager
   */
  public $endpointQueryManager;

  /**
   * Public constructor.
   */
  public function __construct(EndpointQueryManager $endpoint_query_manager) {
    $this->endpointQueryManager = $endpoint_query_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('plugin.manager.endpoint_query_manager'),
    );
  }

  /**
   * Build a project table.
   *
   * @param \Drupal\ghi_base_objects\Entity\BaseObjectInterface $base_object
   *   The base object.
   *
   * @return array
   *   A render array.
   */
  public function buildProjectTable(BaseObjectInterface $base_object) {
    $project_search_query = $this->getProjectSearchQuery($base_object);
    $projects = $project_search_query->getProjects($base_object);
    $build = $this->getProjectTable($projects, $this->getDecimalFormat($base_object));
    return $build;
  }

  /**
   * Build an organization list.
   *
   * @param \Drupal\ghi_base_objects\Entity\BaseObjectInterface $base_object
   *   The base object.
   *
   * @return array
   *   A render array.
   */
  public function buildOrganizationList(BaseObjectInterface $base_object) {
    $project_search_query = $this->getProjectSearchQuery($base_object);
    $organizations = $project_search_query->getOrganizations($base_object);
    $build = [
      '#type' => 'container',
    ];
    $needs_fts_link = $base_object->bundle() == 'governing_entity';
    if ($needs_fts_link) {
      $build[] = [
        '#markup' => $this->t('For more details, view on <img src="@logo_url" />', [
          '@logo_url' => ThemeHelper::getUriToFtsIcon(),
        ]),
      ];
    }

    $build[] = $this->getOrganizationList($organizations);
    return $build;
  }

  /**
   * Build a project table for an organization.
   *
   * @param \Drupal\ghi_base_objects\Entity\BaseObjectInterface $base_object
   *   The base object.
   * @param int $organization_id
   *   The id of the organization for which to display the projects.
   *
   * @return array
   *   A render array.
   */
  public function buildOrganizationProjectTable(BaseObjectInterface $base_object, $organization_id) {
    $organization = $this->getOrganization($organization_id);
    $project_search_query = $this->getProjectSearchQuery($base_object);
    $projects = $project_search_query->getOrganizationProjects($organization, $base_object);
    $build = $this->getOrganizationProjectTable($projects, $this->getDecimalFormat($base_object));
    return $build;
  }

  /**
   * Get the popover content for project items.
   *
   * @param \Drupal\ghi_plans\ApiObjects\Project[] $projects
   *   The projects to include in the table.
   * @param string|null $decimal_format
   *   The decimal format to use.
   *
   * @return array
   *   A render array.
   */
  private function getProjectTable(array $projects, $decimal_format = NULL) {
    $header = [
      $this->t('Project code'),
      $this->t('Project name'),
      $this->t('Organizations'),
      $this->t('Project Target'),
      $this->t('Requirements'),
    ];

    $rows = [];
    foreach ($projects as $project) {
      $row = [];
      $row[] = [
        'data' => [
          '#type' => 'link',
          '#title' => $project->version_code,
          '#url' => Url::fromUri('https://projects.hpc.tools/project/' . $project->id . '/view'),
        ],
      ];
      $row[] = $project->name;
      $row[] = [
        'data' => [
          '#theme' => 'item_list',
          '#items' => $this->getOrganizationLinks($project->getOrganizations()),
          '#gin_lb_theme_suggestions' => FALSE,
        ],
      ];
      $row[] = [
        'data' => [
          '#theme' => 'hpc_amount',
          '#amount' => $project->target,
          '#scale' => 'full',
          '#decimal_format' => $decimal_format,
        ],
      ];
      $row[] = [
        'data' => [
          '#theme' => 'hpc_currency',
          '#value' => $project->requirements,
          '#decimal_format' => $decimal_format,
        ],
      ];
      $rows[] = $row;
    }

    return [
      '#theme' => 'table',
      '#header' => $header,
      '#rows' => $rows,
    ];
  }

  /**
   * Get the popover content for project items.
   *
   * @param array $projects
   *   The projects to include in the table.
   *
   * @return array
   *   A render array.
   */
  private function getOrganizationProjectTable(array $projects) {
    $header = [
      $this->t('Project code'),
      $this->t('Project name'),
      $this->t('Requirements'),
    ];

    $rows = [];
    foreach ($projects as $project) {
      $row = [];
      $row[] = [
        'data' => [
          '#type' => 'link',
          '#title' => $project->version_code,
          '#url' => Url::fromUri('https://projects.hpc.tools/project/' . $project->id . '/view'),
        ],
      ];
      $row[] = $project->name;
      $row[] = [
        'data' => [
          '#theme' => 'hpc_currency',
          '#value' => $project->requirements,
        ],
      ];
      $rows[] = $row;
    }

    return [
      '#theme' => 'table',
      '#header' => $header,
      '#rows' => $rows,
    ];
  }

  /**
   * Get the popover content for oragnization items.
   *
   * @param \Drupal\ghi_plans\ApiObjects\Organization[] $organizations
   *   The organizations to include in the table.
   *
   * @return array
   *   A table render array.
   */
  private function getOrganizationList(array $organizations) {
    $links = $this->getOrganizationLinks($organizations);
    $popover_content = [
      '#theme' => 'item_list',
      '#items' => $links,
      '#list_type' => 'ol',
      '#gin_lb_theme_suggestions' => FALSE,
    ];
    return $popover_content;
  }

  /**
   * Get organization links when available.
   *
   * @param \Drupal\ghi_plans\ApiObjects\Organization[] $objects
   *   The organization objects.
   *
   * @return array
   *   An array of organization links, or their names if no url is set.
   */
  private function getOrganizationLinks(array $objects) {
    return array_values(array_map(function ($object) {
      return $object->url ? Link::fromTextAndUrl($object->name, Url::fromUri($object->url)) : $object->name;
    }, $objects));
  }

  /**
   * Get the decimal format to use for number formatting.
   *
   * @param \Drupal\ghi_base_objects\Entity\BaseObjectInterface $base_object
   *   The base object.
   *
   * @return string|null
   *   Either 'comma', 'point' or NULL.
   */
  private function getDecimalFormat(BaseObjectInterface $base_object) {
    $plan_object = $this->getPlanObject($base_object);
    return $plan_object ? $plan_object->getDecimalFormat() : NULL;
  }

  /**
   * Get the plan object for the current request.
   *
   * @param \Drupal\ghi_base_objects\Entity\BaseObjectInterface $base_object
   *   The base object.
   *
   * @return \Drupal\ghi_plans\Entity\Plan|null
   *   The plan object.
   */
  private function getPlanObject(BaseObjectInterface $base_object) {
    if ($base_object->bundle() == 'plan') {
      return $base_object;
    }
    /** @var \Drupal\ghi_plans\Entity\Plan $plan_object */
    return $base_object->get('field_plan')->entity ?? NULL;
  }

  /**
   * Get an initialized project search query.
   *
   * @param \Drupal\ghi_base_objects\Entity\BaseObjectInterface $base_object
   *   The base object for which to retrieve the project search query.
   *
   * @return \Drupal\ghi_plans\Plugin\EndpointQuery\PlanProjectSearchQuery
   *   An instance of the project search query.
   */
  private function getProjectSearchQuery(BaseObjectInterface $base_object) {
    $plan_object = $this->getPlanObject($base_object);
    $project_search_query = $this->endpointQueryManager->createInstance('plan_project_search_query');
    $project_search_query->getData(['plan_id' => $plan_object->getSourceId()]);
    if ($base_object->bundle() == 'governing_entity') {
      $project_search_query->setFilterByClusterIds([$base_object->getSourceId()]);
    }
    return $project_search_query;
  }

  /**
   * Load an organization object.
   *
   * @param int $organization_id
   *   The id of the organization.
   *
   * @return \Drupal\ghi_plans\ApiObjects\Organization|null
   *   The organization object or NULL.
   */
  private function getOrganization($organization_id) {
    /** @var \Drupal\ghi_plans\Plugin\EndpointQuery\OrganizationQuery $organization_query */
    $organization_query = $this->endpointQueryManager->createInstance('organization_query');
    return $organization_query->getOrganization($organization_id) ?? NULL;
  }

}
