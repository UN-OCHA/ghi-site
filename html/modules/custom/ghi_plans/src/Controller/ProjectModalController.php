<?php

namespace Drupal\ghi_plans\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Link;
use Drupal\Core\Render\Markup;
use Drupal\Core\Url;
use Drupal\ghi_base_objects\Entity\BaseObjectChildInterface;
use Drupal\ghi_base_objects\Entity\BaseObjectInterface;
use Drupal\ghi_plans\Entity\GoverningEntity;
use Drupal\ghi_plans\Entity\Plan;
use Drupal\ghi_plans\Traits\FtsLinkTrait;
use Drupal\ghi_plans\Traits\PlanQueryTrait;
use Drupal\hpc_common\Helpers\ThemeHelper;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Controller for project related modals.
 */
class ProjectModalController extends ControllerBase {

  use FtsLinkTrait;
  use PlanQueryTrait;

  /**
   * The endpoint query manager.
   *
   * @var \Drupal\hpc_api\Query\FabricQueryManager
   */
  public $fabricQueryManager;

  /**
   * The endpoint query manager.
   *
   * @var \Drupal\hpc_api\Query\EndpointQueryManager
   */
  public $endpointQueryManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): ProjectModalController {
    $instance = new static();
    $instance->fabricQueryManager = $container->get('plugin.manager.fabric_query_manager');
    $instance->endpointQueryManager = $container->get('plugin.manager.endpoint_query_manager');
    return $instance;
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
    $plan_object = $this->getPlanObject($base_object);
    $cluster_context = $base_object instanceof BaseObjectChildInterface ? $base_object : NULL;
    $project_query = $this->getProjectQuery();
    $projects = $project_query->getProjectsForPlanId($plan_object->getSourceId(), $cluster_context);
    $build = $this->getProjectTable($projects, $plan_object);
    return $this->returnBuild($build, $this->modalTitleBaseObject($base_object, $this->t('Projects', [], ['langcode' => $plan_object?->getPlanLanguage()])));
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
    $plan_object = $this->getPlanObject($base_object);
    $cluster_context = $base_object instanceof BaseObjectChildInterface ? $base_object : NULL;
    $project_query = $this->getProjectQuery();
    $organizations = $project_query->getProjectOrganizationsForPlan($plan_object, $cluster_context);

    $t_options = [
      'langcode' => $plan_object?->getPlanLanguage(),
    ];
    $build = $this->getOrganizationList($organizations);
    $fts_link = NULL;
    if ($base_object instanceof GoverningEntity) {
      $link_title = $this->t('For more details, view on <img src="@logo_url" />', [
        '@logo_url' => ThemeHelper::getUriToFtsIcon(),
      ], $t_options);
      $fts_link = self::buildFtsLink($link_title, $plan_object, 'recipients', $base_object);
    }
    return $this->returnBuild($build, $this->modalTitleBaseObject($base_object, $this->t('Organizations', [], $t_options)), $fts_link);
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
    $plan_object = $this->getPlanObject($base_object);
    $organization = $this->getOrganizationQuery()->getOrganization($organization_id);
    $t_options = [
      'langcode' => $plan_object?->getPlanLanguage(),
    ];
    if (!$organization) {
      $build = [
        '#markup' => $this->t('An error occured. The requested ressource is not available.', [], $t_options),
      ];
      return $this->returnBuild($build, $this->t('Error', [], $t_options));
    }

    $cluster_context = $base_object instanceof BaseObjectChildInterface ? $base_object : NULL;
    $project_query = $this->getProjectQuery();
    $projects = $project_query->getProjectsForPlanId($plan_object->getSourceId(), $cluster_context, $organization_id);

    $build = $this->getOrganizationProjectTable($projects, $plan_object);
    $title = $this->t('@organization_name | Projects', [
      '@organization_name' => $organization->getName(),
    ], $t_options);
    return $this->returnBuild($build, $title);
  }

  /**
   * Get the popover content for project items.
   *
   * @param \Drupal\ghi_plans\ApiObjects\Project[] $projects
   *   The projects to include in the table.
   * @param \Drupal\ghi_plans\Entity\Plan|null $plan_object
   *   The plan object for context.
   *
   * @return array
   *   A render array.
   */
  private function getProjectTable(array $projects, ?Plan $plan_object = NULL) {
    $decimal_format = $plan_object?->getDecimalFormat();
    $t_options = [
      'langcode' => $plan_object?->getPlanLanguage(),
    ];
    $header = [
      [
        'data' => $this->t('Project code', [], $t_options),
        'data-sort-type' => 'alfa',
        'data-sort-order' => 'ASC',
        'data-column-type' => 'string',
      ],
      [
        'data' => $this->t('Project name', [], $t_options),
        'data-sort-type' => 'alfa',
        'data-column-type' => 'string',
      ],
      [
        'data' => $this->t('Organizations', [], $t_options),
        'data-sort-type' => 'alfa',
        'data-column-type' => 'string',
      ],
      [
        'data' => $this->t('Project Target', [], $t_options),
        'data-sort-type' => 'numeric',
        'data-column-type' => 'amount',
        'data-formatting' => 'numeric-full',
      ],
      [
        'data' => $this->t('Requirements', [], $t_options),
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
      $totals['requirements'] += $project->getRequirements() ?? 0;

      $row = [];
      $row[] = [
        'data' => [
          '#type' => 'link',
          '#title' => $project->getProjectCode(),
          '#url' => Url::fromUri('https://projects.hpc.tools/project/' . $project->id() . '/view'),
          '#attributes' => [
            'target' => '_blank',
          ],
        ],
        'data-sort-value' => $project->getProjectCode(),
        'data-sort-type' => 'alfa',
        'data-column-type' => 'string',
      ];
      $row[] = $project->getName();
      $row[] = [
        'data' => [
          '#markup' => Markup::create(implode(' | ', $this->getOrganizationLinks($organinizations))),
        ],
        'data-sort-value' => implode(' | ', $this->getOrganizationNames($organinizations)),
        'data-sort-type' => 'alfa',
        'data-column-type' => 'string',
      ];
      $row[] = [
        'data' => [
          '#theme' => 'hpc_amount',
          '#amount' => $project->getTarget(),
          '#scale' => 'full',
          '#decimal_format' => $decimal_format,
        ],
        'data-sort-value' => $project->getTarget(),
        'data-sort-type' => 'numeric',
        'data-column-type' => 'amount',
      ];
      $row[] = [
        'data' => [
          '#theme' => 'hpc_currency',
          '#value' => $project->getRequirements(),
          '#scale' => 'full',
          '#decimal_format' => $decimal_format,
        ],
        'data-sort-value' => $project->getRequirements(),
        'data-sort-type' => 'numeric',
        'data-column-type' => 'amount',
      ];
      $rows[] = $row;
    }

    $total_rows = [];
    $total_rows[] = [
      'data' => [
        $this->t('Total', [], $t_options),
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
   * @param \Drupal\ghi_plans\ApiObjects\Project[] $projects
   *   The projects to include in the table.
   * @param \Drupal\ghi_plans\Entity\Plan|null $plan_object
   *   The plan object for context.
   *
   * @return array
   *   A render array.
   */
  private function getOrganizationProjectTable(array $projects, ?Plan $plan_object = NULL) {
    $decimal_format = $plan_object?->getDecimalFormat();
    $t_options = [
      'langcode' => $plan_object?->getPlanLanguage(),
    ];
    $header = [
      $this->t('Project code', [], $t_options),
      $this->t('Project name', [], $t_options),
      [
        'data' => $this->t('Requirements', [], $t_options),
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
      $totals['requirements'] += $project->getRequirements() ?? 0;
      $row = [];
      $row[] = [
        'data' => [
          '#type' => 'link',
          '#title' => $project->getProjectCode(),
          '#url' => Url::fromUri('https://projects.hpc.tools/project/' . $project->id() . '/view'),
          '#attributes' => [
            'target' => '_blank',
          ],
        ],
      ];
      $row[] = $project->getName();
      $row[] = [
        'data' => [
          '#theme' => 'hpc_currency',
          '#value' => $project->getRequirements(),
          '#scale' => 'full',
          '#decimal_format' => $decimal_format,
        ],
        'data-sort-value' => $project->getRequirements(),
        'data-sort-type' => 'numeric',
        'data-column-type' => 'amount',
      ];
      $rows[] = $row;
    }

    $total_rows = [];
    $total_rows[] = [
      'data' => [
        $this->t('Total', [], $t_options),
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
      $url = $object->getUrl($link_options);
      return $url ? Link::fromTextAndUrl($object->getName(), $url)->toString() : $object->getName();
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
  private function getOrganizationNames(array $objects): array {
    return array_values(array_map(function ($object) {
      return $object->getName();
    }, $objects));
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
  private function getPlanObject(BaseObjectInterface $base_object): ?Plan {
    if ($base_object instanceof Plan) {
      return $base_object;
    }
    if ($base_object instanceof BaseObjectChildInterface && $base_object->getParentBaseObject() instanceof Plan) {
      return $base_object->getParentBaseObject();
    }
    return NULL;
  }

}
