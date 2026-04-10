<?php

namespace Drupal\ghi_blocks\Plugin\Block\Plan;

use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\Unicode;
use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\Context\EntityContextDefinition;
use Drupal\ghi_base_objects\ApiObjects\Location;
use Drupal\ghi_blocks\Interfaces\MultiStepFormBlockInterface;
use Drupal\ghi_blocks\Interfaces\OverrideDefaultTitleBlockInterface;
use Drupal\ghi_blocks\MapObjects\BaseMapObjectInterface;
use Drupal\ghi_blocks\MapObjects\ClusterMapObject;
use Drupal\ghi_blocks\MapObjects\OrganizationMapObject;
use Drupal\ghi_blocks\MapObjects\ProjectMapObject;
use Drupal\ghi_blocks\Plugin\Block\GHIBlockBase;
use Drupal\ghi_blocks\Traits\GlobalMapTrait;
use Drupal\ghi_blocks\Traits\OrganizationsBlockTrait;
use Drupal\ghi_plans\ApiObjects\Organization;
use Drupal\ghi_plans\ApiObjects\Partials\PlanProjectCluster;
use Drupal\ghi_plans\ApiObjects\Project;
use Drupal\ghi_plans\Traits\FtsLinkTrait;
use Drupal\hpc_common\Helpers\ThemeHelper;
use Drupal\hpc_downloads\Interfaces\HPCDownloadPNGInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ghi_plans\Entity\Plan;
use Drupal\hpc_common\Plugin\HPCBlockMetadata;

/**
 * Provides a 'PlanOperationalPresenceMap' block.
 */
#[Block(
  id: 'plan_operational_presence_map',
  admin_label: new TranslatableMarkup('Operational Presence Map'),
  category: new TranslatableMarkup('Plan elements'),
  context_definitions: [
    'node' => new EntityContextDefinition('entity:node', new TranslatableMarkup('Node')),
    'plan' => new EntityContextDefinition('entity:base_object', new TranslatableMarkup('Plan'), constraints: ['Bundle' => 'plan']),
    'plan_cluster' => new EntityContextDefinition('entity:base_object', new TranslatableMarkup('Cluster'), required: FALSE, constraints: ['Bundle' => 'governing_entity']),
  ],
)]
class PlanOperationalPresenceMap extends GHIBlockBase implements MultiStepFormBlockInterface, OverrideDefaultTitleBlockInterface, HPCDownloadPNGInterface {

  use OrganizationsBlockTrait;
  use FtsLinkTrait;
  use GlobalMapTrait;

  const DEFAULT_VIEWS = [
    'organization' => 'organization',
    'cluster' => 'cluster',
    'project' => 'project',
  ];

  /**
   * The icon query.
   *
   * @var \Drupal\hpc_api\Plugin\EndpointQuery\IconQuery
   */
  public $iconQuery;

  /**
   * {@inheritdoc}
   */
  public static function metadata(): ?HPCBlockMetadata {
    return new HPCBlockMetadata(
      defaultTitle: 'Operations by admin area',
      dataSources: [
        'locations' => 'fabric_query:location',
      ],
      configForms: [
        'organizations' => [
          'title' => 'Organizations',
          'callback' => 'organizationsForm',
        ],
        'display' => [
          'title' => 'Display',
          'callback' => 'displayForm',
        ],
      ]
    );
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    /** @var \Drupal\ghi_blocks\Plugin\Block\Plan\PlanOperationalPresenceMap $instance */
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);

    // Set our own properties.
    $instance->iconQuery = $instance->endpointQueryManager->createInstance('icon_query');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function isEmpty(): bool {
    $locations = $this->getLocations();
    return empty($locations);
  }

  /**
   * {@inheritdoc}
   */
  public function buildContent() {
    $available_views = $this->getAvailableViews();
    $map_data = $this->getMapData();
    if (empty($map_data) || empty($available_views)) {
      return;
    }

    $conf = $this->getBlockConfig();
    $chart_id = Html::getUniqueId('plan-operational-presence-map');
    $chart_class = Html::getClass('plan-operational-presence-map');
    $selected_view = $this->getSelectedView();

    $outline_country = NULL;
    $focus_country = $this->getCurrentPlanObject()->getFocusCountry();
    if ($focus_country) {
      /** @var \Drupal\ghi_base_objects\Plugin\FabricQuery\LocationQuery $location_query */
      $location_query = $this->getQueryHandler('locations');
      $country = $location_query->getLocation($focus_country->getSourceId());
      $outline_country = $country?->toArray();
    }

    $map_settings = [
      'json' => $map_data,
      'id' => $chart_id,
      'disclaimer' => $conf['display']['disclaimer'] ?: $this->getDefaultMapDisclaimer($this->getCurrentPlanObject()->getPlanLanguage()),
      'pcodes_enabled' => $conf['display']['pcodes_enabled'] ?? TRUE,
      'outline_country' => $outline_country,
    ];

    return [
      '#theme' => 'plan_operational_presence_map',
      '#chart_id' => $chart_id,
      '#chart_class' => $chart_class,
      '#view_switcher' => $this->getViewSwitcher($selected_view),
      '#object_switcher' => $this->getObjectSwitcher($selected_view),
      '#attached' => [
        'library' => ['ghi_blocks/map.gl.operational_presence'],
        'drupalSettings' => [
          'plan_operational_presence_map' => [
            $chart_id => $map_settings,
          ],
        ],
      ],
      '#cache' => [
        'tags' => Cache::mergeTags($this->getCurrentBaseObject()->getCacheTags(), $this->getMapConfigCacheTags()),
      ],
    ];
  }

  /**
   * Get the currently selected view.
   *
   * @return string
   *   The selected view as a string.
   */
  private function getSelectedView(): string {
    $conf = $this->getBlockConfig();
    $available_views = $this->getViewOptions();
    $requested_view = $this->requestStack->getCurrentRequest()->get('view') ?? NULL;
    $default_view = $conf['display']['default_view'] ?? reset($available_views);
    $selected_view = (string) ($requested_view && array_key_exists($requested_view, $available_views) ? $requested_view : $default_view);
    return $selected_view;
  }

  /**
   * Get the currently selected object id.
   *
   * @param string $selected_view
   *   The currently selected view.
   *
   * @return int|null
   *   The selected object id if any.
   */
  private function getSelectedObjectId($selected_view) {
    $requested_object_id = $this->requestStack->getCurrentRequest()->get('object_id') ?? NULL;
    $objects = $this->getMapObjects($selected_view);
    $selected_object_id = $requested_object_id && array_key_exists($requested_object_id, $objects) ? $requested_object_id : NULL;
    return $selected_object_id;
  }

  /**
   * Get the data for the map.
   *
   * @return array
   *   The map data.
   */
  private function getMapData() {
    $map_data = [
      'locations' => [],
    ];

    $selected_view = $this->getSelectedView();
    $objects_by_location = $this->getMapObjectsByLocation($selected_view);
    if (empty($objects_by_location)) {
      return NULL;
    }

    $plan_object = $this->getCurrentPlanObject();

    $fts_link = NULL;
    if ($selected_view == 'project' || $selected_view == 'organization') {
      $data_page = $selected_view == 'project' ? 'projects' : 'recipients';
      $link_title = $this->t('For more details, view on <img src="@logo_url" />', [
        '@logo_url' => ThemeHelper::getUriToFtsIcon(),
      ], [
        'langcode' => $plan_object?->getPlanLanguage(),
      ]);
      $fts_link_build = self::buildFtsLink($link_title, $plan_object, $data_page, $this->getCurrentBaseObject());
      $fts_link = ThemeHelper::render($fts_link_build, FALSE);
    }

    // Process the locations.
    foreach ($objects_by_location as $location_id => $location) {
      $objects = $location->objects ?? [];
      $location_data = (object) $location->location->getGeoJsonLocationData();
      $location_data->location_id = $location->location->id();
      $location_data->location_name = $location->location->getName();
      $location_data->object_id = $location_id;
      $location_data->object_count = count($objects);
      $location_data->modal_content = $this->buildModalContent($location->location, $objects, $selected_view, $fts_link);

      if (empty($location_data->filepath)) {
        continue;
      }

      $map_data['locations'][] = clone $location_data;
    }
    return !empty($map_data['locations']) ? $map_data : NULL;
  }

  /**
   * Get objects for the selected view.
   *
   * @param string $selected_view
   *   One of 'organization', 'cluster' or 'project'.
   *   See self::getViewOptions().
   *
   * @return \Drupal\ghi_blocks\MapObjects\BaseMapObjectInterface[]
   *   An array of objects for display in the map.
   */
  private function getMapObjects($selected_view) {
    /** @var \Drupal\ghi_blocks\MapObjects\BaseMapObjectInterface[] $objects */
    $objects = &drupal_static(__FUNCTION__, []);
    if (array_key_exists($selected_view, $objects)) {
      return $objects[$selected_view];
    }

    $organizations = $this->getConfiguredOrganizations();
    $organization_projects = $this->getProjectsByOrganization();

    $locations = $this->getLocations();

    switch ($selected_view) {
      case 'organization':
        /** @var \Drupal\ghi_blocks\MapObjects\BaseMapObjectInterface[] $objects */
        $objects = [];

        foreach ($organizations as $organization) {
          /** @var \Drupal\ghi_plans\ApiObjects\Project[] $projects */
          $projects = $organization_projects[$organization->id()] ?? [];
          $location_ids = [];
          $clusters = [];
          foreach ($projects as $project) {
            $project_location_ids = array_intersect($project->getLocationIds(), array_keys($locations));
            if (empty($project_location_ids)) {
              continue;
            }
            $location_ids = array_merge($location_ids, $project_location_ids);
            $clusters = array_merge($clusters, $project->getClusters());
          }
          $location_ids = array_unique($location_ids);
          $objects[$organization->id()] = new OrganizationMapObject($organization->id(), $organization->getName(), $location_ids, [
            'clusters' => $clusters,
            'projects' => $this->getOrganizationProjects($organization),
          ]);
        }
        break;

      case 'cluster':
        /** @var \Drupal\ghi_blocks\MapObjects\BaseMapObjectInterface[] $objects */
        $objects = [];

        // Build a list of clusters with location_ids.
        foreach ($organizations as $organization) {
          /** @var \Drupal\ghi_plans\ApiObjects\Project[] $projects */
          $projects = $organization_projects[$organization->id()] ?? [];
          foreach ($projects as $project) {
            if (empty($project->getClusters())) {
              continue;
            }
            foreach ($project->getClusters() as $cluster) {
              $location_ids = $project->getLocationIds();
              if (array_key_exists($cluster->id(), $objects)) {
                $location_ids = array_unique(array_merge($objects[$cluster->id()]->getLocationIds(), $project->getLocationIds()));
              }
              $location_ids = array_intersect($location_ids, array_keys($locations));
              if (empty($location_ids)) {
                continue;
              }
              $objects[$cluster->id()] = new ClusterMapObject($cluster->id(), $cluster->getName(), $location_ids, [
                'icon' => $cluster->getIcon(),
              ]);
            }
          }
        }
        break;

      case 'project':
        /** @var \Drupal\ghi_blocks\MapObjects\BaseMapObjectInterface[] $objects */
        $objects = [];
        // Build a list of projects.
        foreach ($organizations as $organization) {
          /** @var \Drupal\ghi_plans\ApiObjects\Project[] $projects */
          $projects = $organization_projects[$organization->id()] ?? [];
          if (empty($projects)) {
            continue;
          }
          foreach ($projects as $project) {
            $location_ids = array_intersect($project->getLocationIds(), array_keys($locations));
            if (empty($location_ids)) {
              continue;
            }
            $objects[$project->id()] = new ProjectMapObject($project->id(), $project->getName(), $location_ids, [
              'clusters' => $project->getClusters(),
            ]);
          }
        }
        break;
    }

    // Filter objects for those with empty locations.
    $objects[$selected_view] = array_filter($objects, function ($item) {
      return count($item->getLocationIds()) > 0;
    });
    return $objects[$selected_view];
  }

  /**
   * Get the map objects grouped by location.
   *
   * @param string|null $selected_view
   *   The currently selected view.
   * @param int|null $object_id
   *   Optional: Restrict to the given object id.
   *
   * @return array
   *   An array of map objects.
   */
  private function getMapObjectsByLocation(?string $selected_view = NULL, ?int $object_id = NULL) {
    $selected_view = $selected_view ?? $this->getSelectedView();
    $object_id = $object_id ?? $this->getSelectedObjectId($selected_view);
    $objects = $this->getMapObjects($selected_view);
    $objects_by_location = [];
    foreach ($objects as $object) {
      if (!empty($object_id) && $object_id != $object->id()) {
        // A specific object has been requested, this is not it.
        continue;
      }
      if (empty($object->getLocationIds())) {
        // No location ids means there is nothing to map.
        continue;
      }
      foreach ($object->getLocationIds() as $location_id) {
        $objects_by_location[$location_id] = $objects_by_location[$location_id] ?? (object) [
          'location' => NULL,
          'objects' => [],
        ];
        if (!empty($objects_by_location[$location_id]->objects[$object->id()])) {
          continue;
        }
        $objects_by_location[$location_id]->objects[$object->id()] = $object;
      }
    }

    $plan_object = $this->getCurrentPlanObject();
    $max_admin_level = max($plan_object->getMaxAdminLevel(), 3);

    /** @var \Drupal\ghi_base_objects\Plugin\FabricQuery\LocationQuery $location_query */
    $location_query = $this->getQueryHandler('locations');
    $locations = $location_query->getLocationsById(array_keys($objects_by_location));
    foreach ($locations as $location) {
      if (empty($objects_by_location[$location->id()])) {
        continue;
      }
      if ($location->isCountry() || $location->getAdminLevel() > $max_admin_level) {
        unset($objects_by_location[$location->id()]);
      }
      $objects_by_location[$location->id()]->location = $location;
    }

    return $objects_by_location;
  }

  /**
   * Build the content for a modal screen.
   *
   * @param \Drupal\ghi_base_objects\ApiObjects\Location $location
   *   The location for which the modal should be prepared.
   * @param \Drupal\ghi_blocks\MapObjects\BaseMapObjectInterface[] $objects
   *   The map objects for which to prepare the modal.
   * @param string $selected_view
   *   The view identifier.
   * @param string $fts_link
   *   A link to FTS, string that is already rendered.
   *
   * @return array
   *   An array describing the modal content.
   */
  private function buildModalContent($location, $objects, $selected_view, $fts_link) {
    if (empty($objects)) {
      $empty_map = [
        'organization' => $this->t('There are no organizations in this area'),
        'cluster' => $this->t('There are no active @type_label in this area', [
          '@type_label' => strtolower($this->getEntityGroupLabel('governing_entities', 'label')),
        ]),
        'project' => $this->t('There are no projects in this area'),
      ];
      $content = $empty_map[$selected_view];
    }
    else {
      if ($selected_view == 'organization') {
        /** @var \Drupal\ghi_blocks\MapObjects\OrganizationMapObject[] $objects */
        $objects = array_filter($objects, function ($object) {
          return $object instanceof OrganizationMapObject;
        });
        // Group organizations by clusters.
        $clusters = [];
        foreach ($objects as $object) {
          $object_clusters = $this->getClustersByOrganizationAndLocation($object, $location);
          if (empty($object_clusters)) {
            continue;
          }
          foreach ($object_clusters as $cluster) {
            if (empty($clusters[$cluster->id()])) {
              $clusters[$cluster->id()] = [
                'icon' => $this->iconQuery->getIconEmbedCode($cluster->getIcon()),
                'name' => $cluster->getName(),
                'organizations' => [],
              ];
            }
            $clusters[$cluster->id()]['organizations'][$object->id()] = $object->getName();
          }
        }
        $clusters = array_filter($clusters, function ($item) {
          return !empty($item['organizations']);
        });

        $cluster_toggle = $this->getClusterToggle('.organizations-wrapper');

        $content = '<div class="title">' . $this->t('@type_label (@count)', [
          '@type_label' => $this->getEntityGroupLabel('governing_entities', 'label'),
          '@count' => count($clusters),
        ]) . '</div>';
        uasort($clusters, function ($a, $b) {
          return strnatcmp($a['name'], $b['name']);
        });
        foreach ($clusters as $cluster) {
          if (empty($cluster['organizations'])) {
            continue;
          }
          $content .= '<div class="cluster-wrapper"><div class="cluster-icon-wrapper">' . $cluster['icon'] . '</div>' . $cluster['name'] . $cluster_toggle;
          $content .= '<div class="organizations-wrapper"><div class="title">' . $this->t('Organizations (@count)', [
            '@count' => count($cluster['organizations']),
          ]) . '</div>';
          sort($cluster['organizations']);
          $content .= ThemeHelper::render([
            '#theme' => 'item_list',
            '#items' => $cluster['organizations'],
            '#gin_lb_theme_suggestions' => FALSE,
          ]);
          $content .= '</div></div>';
        }
      }
      if ($selected_view == 'cluster') {
        $content = '';
        /** @var \Drupal\ghi_blocks\MapObjects\ClusterMapObject[] $objects */
        $objects = array_filter($objects, function ($object) {
          return $object instanceof ClusterMapObject;
        });
        uasort($objects, function (ClusterMapObject $a, ClusterMapObject $b) {
          return strnatcmp($a->getName(), $b->getName());
        });
        foreach ($objects as $object) {
          $icon = $this->iconQuery->getIconEmbedCode($object->getIcon());
          $content .= '<div class="cluster-wrapper"><div class="cluster-icon-wrapper">' . $icon . '</div>' . $object->getName();
          $content .= '</div>';
        }
      }
      if ($selected_view == 'project') {
        /** @var \Drupal\ghi_blocks\MapObjects\ProjectMapObject[] $objects */
        $objects = array_filter($objects, function ($object) {
          return $object instanceof ProjectMapObject;
        });
        // Group projects by clusters.
        $clusters = [];
        foreach ($objects as $object) {
          if (empty($object->getClusters())) {
            continue;
          }
          foreach ($object->getClusters() as $cluster) {
            if (empty($clusters[$cluster->id()])) {
              $clusters[$cluster->id()] = [
                'icon' => $this->iconQuery->getIconEmbedCode($cluster->getIcon()),
                'name' => $cluster->getName(),
                'projects' => [],
              ];
            }
            $clusters[$cluster->id()]['projects'][$object->id()] = $object->getName();
          }
        }
        $clusters = array_filter($clusters, function ($item) {
          return !empty($item['projects']);
        });

        $cluster_toggle = $this->getClusterToggle('.projects-wrapper');

        $content = '<div class="title">' . $this->t('Clusters (@count)', ['@count' => count($clusters)]) . '</div>';
        uasort($clusters, function ($a, $b) {
          return strnatcmp($a['name'], $b['name']);
        });
        foreach ($clusters as $cluster) {
          if (empty($cluster['projects'])) {
            continue;
          }
          $content .= '<div class="cluster-wrapper"><div class="cluster-icon-wrapper">' . $cluster['icon'] . '</div>' . $cluster['name'] . $cluster_toggle;
          $content .= '<div class="projects-wrapper"><div class="title">' . $this->t('Projects (@count)', [
            '@count' => count($cluster['projects']),
          ]) . '</div>';
          sort($cluster['projects']);
          $content .= ThemeHelper::render([
            '#theme' => 'item_list',
            '#items' => $cluster['projects'],
            '#gin_lb_theme_suggestions' => FALSE,
          ]);
          $content .= '</div></div>';
        }
      }
    }

    $object_count_label_map = [
      'organization' => $this->t('Organizations: @count', ['@count' => count($objects)]),
      'cluster' => $this->t('@type_label: @count', [
        '@count' => count($objects),
        '@type_label' => $this->getEntityGroupLabel('governing_entities', 'label'),
      ]),
      'project' => $this->t('Projects: @count', ['@count' => count($objects)]),
    ];

    $modal_content = [
      'id' => $location->id(),
      'name' => $location->getName(),
      'location_id' => $location->id(),
      'location_name' => $location->getName(),
      'admin_level' => $location->getAdminLevel(),
      'pcode' => $location->getPcode(),
      'title_heading' => $this->t('Admin area @admin_level', [
        '@admin_level' => $location->getAdminLevel(),
      ]),
      'title' => $location->getName(),
      'content' => (!empty($objects) ? $fts_link : NULL) . $content,
      'object_count_label' => $object_count_label_map[$selected_view],
    ];
    return $modal_content;
  }

  /**
   * Get the cluster toggle markup.
   *
   * Prevent repeated rendering.
   *
   * @return string
   *   The rendered markup for a cluster toggle.
   */
  private function getClusterToggle($target_selector) {
    $cluster_toggle = &drupal_static(__FUNCTION__, []);
    if (!array_key_exists($target_selector, $cluster_toggle)) {
      $cluster_toggle[$target_selector] = ThemeHelper::render([
        '#theme' => 'hpc_toggle',
        '#parent_selector' => '.cluster-wrapper',
        '#target_selector' => $target_selector,
      ], FALSE);
    }
    return $cluster_toggle[$target_selector];
  }

  /**
   * Get the view switcher.
   *
   * @param string $selected_view
   *   The currently selected view.
   *
   * @return array
   *   A render array for the view switcher.
   */
  private function getViewSwitcher($selected_view) {
    $available_views = $this->getAvailableViews();
    if (count($available_views) <= 1) {
      return NULL;
    }
    $view_options = $this->getViewOptions();
    return [
      '#theme' => 'ajax_switcher',
      '#element_key' => 'view',
      '#options' => array_map(function ($view) use ($view_options) {
        return $view_options[$view];
      }, $available_views),
      '#default_value' => $selected_view,
      '#wrapper_id' => Html::getId('block-' . $this->getUuid()),
      '#plugin_id' => $this->getPluginId(),
      '#block_uuid' => $this->getUuid(),
      '#uri' => $this->getCurrentUri(),
    ];
  }

  /**
   * Get the object switcher.
   *
   * @param string $selected_view
   *   The currently selected view.
   *
   * @return array
   *   A render array for the object switcher.
   */
  private function getObjectSwitcher($selected_view) {
    $objects = $this->getMapObjects($selected_view);
    $object_id = $this->getSelectedObjectId($selected_view);

    $type_map = [
      'organization' => $this->t('All Organizations'),
      'cluster' => $this->t('All @type_label', ['@type_label' => $this->getEntityGroupLabel('governing_entities', 'label')]),
      'project' => $this->t('All Projects'),
    ];
    if ($selected_view == 'project') {
      // We want the projects to be grouped by cluster.
      $object_options = [];
      foreach ($objects as $object) {
        /** @var \Drupal\ghi_blocks\MapObjects\ProjectMapObject $object */
        foreach ($object->getClusters() ?? [] as $cluster) {
          if (empty($object_options[$cluster->getName()])) {
            $object_options[$cluster->getName()] = [];
          }
          $object_options[$cluster->getName()][$object->id()] = Unicode::truncate($object->getName(), 60, TRUE, TRUE);
        }
      }
      ksort($object_options);
    }
    else {
      $object_options = array_map(function (BaseMapObjectInterface $item) {
        return Unicode::truncate($item->getName(), 60, TRUE, TRUE);
      }, $objects);
      asort($object_options);
    }
    return [
      '#theme' => 'ajax_switcher',
      '#element_key' => 'object_id',
      '#options' => ['' => $type_map[$selected_view]] + $object_options,
      '#default_value' => $object_id,
      '#wrapper_id' => Html::getId('block-' . $this->getUuid()),
      '#plugin_id' => $this->getPluginId(),
      '#block_uuid' => $this->getUuid(),
      '#uri' => $this->getCurrentUri(),
      '#query' => array_filter([
        'view' => $selected_view ?? NULL,
      ]),
    ];
  }

  /**
   * Returns generic default configuration for block plugins.
   *
   * @return array
   *   An associative array with the default configuration.
   */
  protected function getConfigurationDefaults() {
    return [
      'organizations' => [
        // It is important to set the default value to NULL, otherwhise the
        // array keys, which are integers will be renumbered by
        // NestedArray::mergeDeep() which is called from
        // BlockPluginTrait::setConfiguration().
        'organization_ids' => NULL,
      ],
      'display' => [
        'available_views' => [],
        'default_view' => NULL,
        'disclaimer' => NULL,
        'pcodes_enabled' => FALSE,
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function canShowSubform(array $form, FormStateInterface $form_state, $subform_key) {
    $conf = $this->getBlockConfig();
    if ($subform_key == 'display') {
      return !empty($conf['organizations']['organization_ids']);
    }
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultSubform($is_new = FALSE) {
    $conf = $this->getBlockConfig();
    if (empty($conf['organizations']['organization_ids'])) {
      return 'organizations';
    }
    return 'display';
  }

  /**
   * {@inheritdoc}
   */
  public function getTitleSubform() {
    return 'display';
  }

  /**
   * Form callback for the organizations form.
   */
  public function organizationsForm(array $form, FormStateInterface $form_state) {
    $organization_options = $this->getAvailableOrganizationOptions();
    $organization_options_disabled = array_filter($organization_options, function ($option) {
      return empty($option['locations']);
    });

    if (empty($organization_options_disabled)) {
      $header_text = $this->t('Found @count organizations with projects.', [
        '@count' => count($organization_options),
      ]);
    }
    else {
      $header_text = $this->t('Found @count organizations. @disabled organizations have been disabled due to missing location data.', [
        '@count' => count($organization_options),
        '@disabled' => count($organization_options_disabled),
      ]);
    }
    $form['organization_ids_header'] = [
      '#type' => 'markup',
      '#markup' => $header_text,
      '#prefix' => '<div>',
      '#suffix' => '</div><br />',
    ];

    $form['organization_ids'] = [
      '#type' => 'tableselect',
      '#header' => [
        'id' => $this->t('ID'),
        'name' => $this->t('Organization'),
        'projects' => $this->t('Projects'),
        'clusters' => $this->t('Clusters'),
        'locations' => $this->t('Locations'),
      ],
      '#options' => $organization_options,
      '#default_value' => $this->getDefaultFormValueFromFormState($form_state, 'organization_ids') ?: [],
      '#empty' => $this->t('No organizations found.'),
    ];

    if (!empty($organization_options_disabled)) {
      foreach ($organization_options_disabled as $option) {
        // Disable the checkbox and mark the option as disabled.
        $form['organization_ids']['#options'][$option['id']]['#attributes']['checked'] = FALSE;
        $form['organization_ids']['#options'][$option['id']]['#disabled'] = TRUE;
      }
    }

    $form['actions'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => [
          'second-level-actions-wrapper',
        ],
      ],
    ];

    $form['actions']['select_organizations'] = [
      '#type' => 'submit',
      '#value' => $this->t('Use selected organizations'),
      '#element_submit' => [get_class($this) . '::ajaxMultiStepSubmit'],
      '#ajax' => [
        'callback' => [$this, 'navigateFormStep'],
        'wrapper' => $this->getContainerWrapper(),
        'effect' => 'fade',
        'method' => 'replace',
        'parents' => ['settings', 'container'],
      ],
      '#next_step' => 'display',
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function displayForm(array $form, FormStateInterface $form_state) {
    $view_options = $this->getViewOptions();
    $form['available_views'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Available views'),
      '#options' => $view_options,
      '#required' => TRUE,
      '#default_value' => $this->getDefaultFormValueFromFormState($form_state, 'available_views') ?: self::DEFAULT_VIEWS,
    ];

    $form['default_view'] = [
      '#type' => 'select',
      '#title' => $this->t('Default view'),
      '#description' => '',
      '#options' => $view_options,
      '#default_value' => $this->getDefaultFormValueFromFormState($form_state, 'default_view') ?? array_key_first($view_options),
    ];

    $form['disclaimer'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Map disclaimer'),
      '#description' => $this->t('You can override the default map disclaimer for this widget.'),
      '#rows' => 4,
      '#default_value' => $this->getDefaultFormValueFromFormState($form_state, 'disclaimer') ?? '',
    ];

    $form['pcodes_enabled'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable pcodes'),
      '#description' => $this->t('If checked, the map will list pcodes alongside location names and enable pcodes for the location filtering.'),
      '#default_value' => $this->getDefaultFormValueFromFormState($form_state, 'pcodes_enabled') ?? FALSE,
    ];

    return $form;
  }

  /**
   * Get the view options for this element.
   *
   * @return array
   *   An array of options, to be used in a Form API element.
   */
  private function getViewOptions() {
    return [
      'organization' => $this->t('View by Organization'),
      'cluster' => $this->t('View by @type', [
        '@type' => $this->getEntityGroupLabel('governing_entities'),
      ]),
      'project' => $this->t('View by Project'),
    ];
  }

  /**
   * Get the available views that have been configured.
   *
   * @return array
   *   An array of the selected view options.
   */
  private function getAvailableViews() {
    $conf = $this->getBlockConfig();
    $available_views = $conf['display']['available_views'] ?? [];
    return $available_views;
  }

  /**
   * Retrieve the label for a group of plan entities in the structure.
   */
  private function getEntityGroupLabel($group, $property = 'label_singular') {
    $group_labels = &drupal_static(__FUNCTION__, []);
    $key = $group . '--' . $property;
    if (!array_key_exists($key, $group_labels)) {
      $plan_object = $this->getCurrentPlanObject();

      $t_options = ['langcode' => $plan_object->getPlanLanguage()];
      $cluster_label_map = [
        Plan::CLUSTER_TYPE_CLUSTER => [
          'label_singular' => $this->t('Cluster', [], $t_options),
          'label' => $this->t('Clusters', [], $t_options),
        ],
        Plan::CLUSTER_TYPE_SECTOR => [
          'label_singular' => $this->t('Sector', [], $t_options),
          'label' => $this->t('Sectors', [], $t_options),
        ],
      ];
      $group_labels[$key] = $cluster_label_map[$plan_object->getPlanClusterType()][$property] ?? NULL;
    }
    return $group_labels[$key];
  }

  /**
   * Get the organization options available in the current context.
   *
   * @return array
   *   An array of organizations, keyed by id.
   */
  private function getAvailableOrganizationOptions() {
    $organizations = $this->getOrganizations();
    $plan_location_ids = array_keys($this->getMapObjectsByLocation());

    return array_map(function (Organization $organization) use ($plan_location_ids) {
      $projects = $this->getOrganizationProjects($organization);
      $clusters = $this->getOrganizationClusters($organization);
      $location_ids = [];
      foreach ($projects as $project) {
        $location_ids = array_merge($location_ids, $project->getLocationIds());
      }
      $location_ids = array_unique($location_ids);
      return [
        'id' => $organization->id(),
        'name' => $organization->getName(),
        'projects' => count($projects),
        'clusters' => implode(', ', array_map(function (PlanProjectCluster $cluster) {
          return $cluster->getName();
        }, $clusters)),
        'locations' => count(array_intersect($location_ids, $plan_location_ids)),
      ];
    }, $organizations);
  }

  /**
   * Get the clusters that are valid for the given organization and location.
   *
   * @param \Drupal\ghi_blocks\MapObjects\OrganizationMapObject $organization
   *   The organization.
   * @param \Drupal\ghi_base_objects\ApiObjects\Location $location
   *   The location.
   *
   * @return \Drupal\ghi_plans\ApiObjects\Partials\PlanProjectCluster[]
   *   An array of project cluster objects.
   */
  private function getClustersByOrganizationAndLocation(OrganizationMapObject $organization, Location $location) {
    $projects = $organization->getProjects();
    $projects = array_filter($projects, function (Project $project) use ($location) {
      return in_array($location->id(), $project->getLocationIds());
    });
    $clusters = [];
    foreach ($projects as $project) {
      $clusters = array_merge($clusters, $project->getClusters());
    }
    return $clusters;
  }

  /**
   * Get the available locations for the current context.
   *
   * @return \Drupal\ghi_base_objects\ApiObjects\Location[]
   *   A flat array of location objects.
   */
  private function getLocations() {
    /** @var \Drupal\ghi_base_objects\Plugin\FabricQuery\LocationQuery $location_query */
    $location_query = $this->getQueryHandler('locations');

    $plan_object = $this->getCurrentPlanObject();
    $max_admin_level = max($plan_object->getMaxAdminLevel(), 3);

    $locations_ids = [];
    foreach ($this->getProjects() as $project) {
      $locations_ids = array_merge($locations_ids, $project->getLocationIds());
    }
    sort($locations_ids);
    $locations_ids = array_unique($locations_ids);
    $locations = $location_query->getLocationsById($locations_ids);
    $locations = array_filter($locations, fn ($location) => !$location->isCountry() && $location->getAdminLevel() <= $max_admin_level && $location->hasGeoJsonFile());
    return $locations;
  }

  /**
   * Get the custom context for this block.
   *
   * @return array
   *   An array with context data or query handlers.
   */
  public function getBlockContext() {
    return [
      'page_node' => $this->getPageNode(),
      'plan_object' => $this->getCurrentPlanObject(),
      'base_object' => $this->getCurrentBaseObject(),
      'context_node' => $this->getPageNode(),
    ];
  }

}
