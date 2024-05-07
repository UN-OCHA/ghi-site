<?php

namespace Drupal\ghi_plans\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Link;
use Drupal\Core\Render\Markup;
use Drupal\Core\Url;
use Drupal\ghi_base_objects\Entity\BaseObjectInterface;
use Drupal\ghi_blocks\Traits\FtsLinkTrait;
use Drupal\ghi_plans\Entity\GoverningEntity;
use Drupal\hpc_api\Query\EndpointQueryManager;
use Drupal\hpc_common\Helpers\ThemeHelper;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Controller for project related modals.
 */
class ProjectModalController extends ControllerBase {

  use FtsLinkTrait;

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
   * Get the title for the modal.
   *
   * This will prefix the build title with the base object label (and icon).
   *
   * @param \Drupal\ghi_base_objects\Entity\BaseObjectInterface $base_object
   *   The base object.
   * @param string $build_title
   *   A title for the build.
   */
  private function modalTitleBaseObject(BaseObjectInterface $base_object, $build_title) {
    $title = '';
    if ($base_object instanceof GoverningEntity && $icon = $base_object->getIconEmbedCode()) {
      $title = $icon;
    }
    $title .= $base_object->label();
    return Markup::create($title . ' | ' . $build_title);
  }

  /**
   * Enhance the build array.
   *
   * @param array $_build
   *   The original build array.
   * @param string $title
   *   A title for the build.
   * @param string $caption
   *   An optional caption to add before the actual build.
   *
   * @return array
   *   A render array.
   */
  private function returnBuild(array $_build, $title, $caption = NULL) {
    $build = [
      '#type' => 'container',
    ];

    if ($caption) {
      $build[] = $caption;
    }
    $build[] = $_build;

    $build['#attached'] = [
      'library' => ['ghi_blocks/modal'],
      'drupalSettings' => [
        'ghi_modal_title' => $title,
      ],
    ];
    return $build;
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
    return $this->returnBuild($build, $this->modalTitleBaseObject($base_object, $this->t('Projects')));
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
    $build = $this->getOrganizationList($organizations);
    $fts_link = NULL;
    if ($base_object instanceof GoverningEntity) {
      $link_title = $this->t('For more details, view on <img src="@logo_url" />', [
        '@logo_url' => ThemeHelper::getUriToFtsIcon(),
      ]);
      $fts_link = self::buildFtsLink($link_title, $this->getPlanObject($base_object), 'recipients', $base_object);
    }
    return $this->returnBuild($build, $this->modalTitleBaseObject($base_object, $this->t('Organizations')), $fts_link);
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
    if (!$organization) {
      return NULL;
    }
    $project_search_query = $this->getProjectSearchQuery($base_object);
    $projects = $project_search_query->getOrganizationProjects($organization, $base_object);
    $build = $this->getOrganizationProjectTable($projects, $this->getDecimalFormat($base_object));
    $title = $organization->getName() . ' | ' . $this->t('Projects');
    return $this->returnBuild($build, $title);
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
      [
        'data' => $this->t('Project code'),
        'data-sort-type' => 'alfa',
        'data-sort-order' => 'ASC',
        'data-column-type' => 'string',
      ],
      [
        'data' => $this->t('Project name'),
        'data-sort-type' => 'alfa',
        'data-column-type' => 'string',
      ],
      [
        'data' => $this->t('Organizations'),
        'data-sort-type' => 'alfa',
        'data-column-type' => 'string',
      ],
      [
        'data' => $this->t('Project Target'),
        'data-sort-type' => 'numeric',
        'data-column-type' => 'amount',
        'data-formatting' => 'numeric-full',
      ],
      [
        'data' => $this->t('Requirements'),
        'data-sort-type' => 'numeric',
        'data-column-type' => 'amount',
        'data-formatting' => 'numeric-full',
      ],
    ];

    $totals = [
      'targets' => 0,
      'requirements' => 0,
    ];
    $organization_ids_unique = [];

    $rows = [];
    foreach ($projects as $project) {
      $organinizations = $project->getOrganizations();
      $organization_ids_unique = array_unique(array_merge($organization_ids_unique, array_keys($organinizations)));

      $totals['targets'] += $project->target ?? 0;
      $totals['requirements'] += $project->requirements ?? 0;

      $row = [];
      $row[] = [
        'data' => [
          '#type' => 'link',
          '#title' => $project->version_code,
          '#url' => Url::fromUri('https://projects.hpc.tools/project/' . $project->id . '/view'),
          '#attributes' => [
            'target' => '_blank',
          ],
        ],
        'sorttable_customkey' => $project->version_code,
        'data-sort-type' => 'alfa',
        'data-column-type' => 'string',
      ];
      $row[] = $project->name;
      $row[] = [
        'data' => [
          '#markup' => Markup::create(implode(' | ', $this->getOrganizationLinks($organinizations))),
        ],
        'sorttable_customkey' => implode(' | ', $this->getOrganizationNames($organinizations)),
        'data-sort-type' => 'alfa',
        'data-column-type' => 'string',
      ];
      $row[] = [
        'data' => [
          '#theme' => 'hpc_amount',
          '#amount' => $project->target,
          '#scale' => 'full',
          '#decimal_format' => $decimal_format,
        ],
        'sorttable_customkey' => $project->target,
        'data-sort-type' => 'numeric',
        'data-column-type' => 'amount',
      ];
      $row[] = [
        'data' => [
          '#theme' => 'hpc_currency',
          '#value' => $project->requirements,
          '#scale' => 'full',
          '#decimal_format' => $decimal_format,
        ],
        'sorttable_customkey' => $project->requirements,
        'data-sort-type' => 'numeric',
        'data-column-type' => 'amount',
      ];
      $rows[] = $row;
    }

    $total_rows = [];
    $total_rows[] = [
      'data' => [
        $this->t('Total'),
        NULL,
        count($organization_ids_unique),
        [
          'data' => [
            '#theme' => 'hpc_amount',
            '#amount' => $totals['targets'],
            '#scale' => 'full',
            '#decimal_format' => $decimal_format,
          ],
          'data-column-type' => 'amount',
        ],
        [
          'data' => [
            '#theme' => 'hpc_currency',
            '#value' => $totals['requirements'],
            '#scale' => 'full',
            '#decimal_format' => $decimal_format,
          ],
          'data-column-type' => 'amount',
        ],
      ],
      'class' => 'totals-row',
    ];

    return [
      '#theme' => 'table',
      '#cell_wrapping' => FALSE,
      '#header' => $header,
      '#sticky_rows' => $total_rows,
      '#rows' => $rows,
      '#sortable' => TRUE,
    ];
  }

  /**
   * Get the popover content for project items.
   *
   * @param array $projects
   *   The projects to include in the table.
   * @param string|null $decimal_format
   *   The decimal format to use.
   *
   * @return array
   *   A render array.
   */
  private function getOrganizationProjectTable(array $projects, $decimal_format) {
    $header = [
      $this->t('Project code'),
      $this->t('Project name'),
      [
        'data' => $this->t('Requirements'),
        'data-sort-type' => 'numeric',
        'data-column-type' => 'amount',
        'data-formatting' => 'numeric-full',
      ],
    ];

    $totals = [
      'requirements' => 0,
    ];

    $rows = [];
    foreach ($projects as $project) {
      $totals['requirements'] += $project->requirements ?? 0;
      $row = [];
      $row[] = [
        'data' => [
          '#type' => 'link',
          '#title' => $project->version_code,
          '#url' => Url::fromUri('https://projects.hpc.tools/project/' . $project->id . '/view'),
          '#attributes' => [
            'target' => '_blank',
          ],
        ],
      ];
      $row[] = $project->name;
      $row[] = [
        'data' => [
          '#theme' => 'hpc_currency',
          '#value' => $project->requirements,
          '#scale' => 'full',
          '#decimal_format' => $decimal_format,
        ],
        'sorttable_customkey' => $project->requirements,
        'data-sort-type' => 'numeric',
        'data-column-type' => 'amount',
      ];
      $rows[] = $row;
    }

    $total_rows = [];
    $total_rows[] = [
      'data' => [
        $this->t('Total'),
        NULL,
        [
          'data' => [
            '#theme' => 'hpc_currency',
            '#value' => $totals['requirements'],
            '#scale' => 'full',
            '#decimal_format' => $decimal_format,
          ],
          'data-column-type' => 'amount',
        ],
      ],
      'class' => 'totals-row',
    ];

    return [
      '#theme' => 'table',
      '#cell_wrapping' => FALSE,
      '#header' => $header,
      '#sticky_rows' => $total_rows,
      '#rows' => $rows,
      '#sortable' => TRUE,
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
   * @return \Drupal\Core\Link[]|string[]
   *   An array of organization links, or their names if no url is set.
   */
  private function getOrganizationLinks(array $objects) {
    $link_options = [
      'attributes' => [
        'target' => '_blank',
      ],
    ];
    return array_values(array_map(function ($object) use ($link_options) {
      return $object->url ? Link::fromTextAndUrl($object->name, Url::fromUri($object->url, $link_options))->toString() : $object->name;
    }, $objects));
  }

  /**
   * Get organization names when available.
   *
   * @param \Drupal\ghi_plans\ApiObjects\Organization[] $objects
   *   The organization objects.
   *
   * @return string[]
   *   An array of organization names.
   */
  private function getOrganizationNames(array $objects) {
    return array_values(array_map(function ($object) {
      return $object->name;
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
    /** @var \Drupal\ghi_plans\Plugin\EndpointQuery\PlanProjectSearchQuery $project_search_query */
    $project_search_query = $this->endpointQueryManager->createInstance('plan_project_search_query');
    $project_search_query->setPlaceholder('plan_id', $plan_object->getSourceId());
    if ($base_object instanceof GoverningEntity) {
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
