<?php

namespace Drupal\ghi_blocks\Plugin\Block\Plan;

use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\Unicode;
use Drupal\Core\Form\FormStateInterface;
use Drupal\ghi_blocks\Interfaces\MultiStepFormBlockInterface;
use Drupal\ghi_blocks\Interfaces\OverrideDefaultTitleBlockInterface;
use Drupal\ghi_blocks\Plugin\Block\GHIBlockBase;
use Drupal\ghi_blocks\Traits\FtsLinkTrait;
use Drupal\ghi_blocks\Traits\OrganizationsBlockTrait;
use Drupal\ghi_element_sync\IncompleteElementConfigurationException;
use Drupal\ghi_element_sync\SyncableBlockInterface;
use Drupal\ghi_plans\ApiObjects\Organization;
use Drupal\ghi_plans\ApiObjects\Partials\PlanProjectCluster;
use Drupal\ghi_plans\Helpers\PlanStructureHelper;
use Drupal\hpc_common\Helpers\ThemeHelper;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a 'PlanOperationalPresenceMap' block.
 *
 * @Block(
 *  id = "plan_operational_presence_map",
 *  admin_label = @Translation("Operational Presence Map"),
 *  category = @Translation("Plan elements"),
 *  data_sources = {
 *    "entities" = "plan_entities_query",
 *    "project_search" = "plan_project_search_query",
 *    "attachment_search" = "attachment_search_query",
 *    "locations" = "locations_query",
 *  },
 *  default_title = @Translation("Operations by admin area"),
 *  context_definitions = {
 *    "node" = @ContextDefinition("entity:node", label = @Translation("Node")),
 *    "plan" = @ContextDefinition("entity:base_object", label = @Translation("Plan"), constraints = { "Bundle": "plan" })
 *  },
 *  config_forms = {
 *    "organizations" = {
 *      "title" = @Translation("Organizations"),
 *      "callback" = "organizationsForm"
 *    },
 *    "display" = {
 *      "title" = @Translation("Display"),
 *      "callback" = "displayForm"
 *    }
 *  }
 * )
 */
class PlanOperationalPresenceMap extends GHIBlockBase implements MultiStepFormBlockInterface, SyncableBlockInterface, OverrideDefaultTitleBlockInterface {

  use OrganizationsBlockTrait;
  use FtsLinkTrait;

  const DEFAULT_DISCLAIMER = 'The boundaries and names shown and the designations used on this map do not imply official endorsement or acceptance by the United Nations.';

  /**
   * The icon query.
   *
   * @var \Drupal\hpc_api\Plugin\EndpointQuery\IconQuery
   */
  public $iconQuery;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    /** @var \Drupal\ghi_blocks\Plugin\Block\GHIBlockBase $instance */
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);

    // Set our own properties.
    $instance->iconQuery = $instance->endpointQueryManager->createInstance('icon_query');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public static function mapConfig($config, NodeInterface $node, $element_type, $dry_run = FALSE) {
    if (empty($config->organization_ids)) {
      throw new IncompleteElementConfigurationException('Incomplete configuration for "plan_operational_presence_map"');
    }
    return [
      'label' => property_exists($config, 'widget_title') ? $config->widget_title : NULL,
      'label_display' => TRUE,
      'hpc' => [
        'organizations' => [
          'organization_ids' => (array) $config->organization_ids ?? [],
        ],
        'display' => [
          'available_views' => (array) $config->available_views,
          'default_view' => $config->default_view,
          'disclaimer' => $config->map_disclaimer,
          'pcodes_enabled' => $config->pcodes_enabled,
        ],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildContent() {
    $available_views = $this->getAvailableViews();
    $selected_view = $this->getSelectedView();
    $map_data = $this->getMapData();
    if (empty($map_data) || empty($available_views)) {
      return;
    }

    $conf = $this->getBlockConfig();
    $chart_id = Html::getUniqueId('plan-operational-presence-map');
    $chart_class = Html::getClass('plan-operational-presence-map');
    $selected_view = $this->getSelectedView();

    $map_settings = [
      // If the map data is empty, it is important to set it to NULL,
      // otherwhise the empty array is simply ignored due to the way that
      // Drupal merges the given settings into the existing ones.
      'json' => $map_data,
      'id' => $chart_id,
      'disclaimer' => $conf['display']['disclaimer'] ?? self::DEFAULT_DISCLAIMER,
      'pcodes_enabled' => $conf['display']['pcodes_enabled'] ?? TRUE,
    ];

    return [
      '#theme' => 'plan_operational_presence_map',
      '#chart_id' => $chart_id,
      '#chart_class' => $chart_class,
      '#view_switcher' => $this->getViewSwitcher($selected_view),
      '#object_switcher' => $this->getObjectSwitcher($selected_view),
      '#attached' => [
        'library' => ['ghi_blocks/map.chloropleth'],
        'drupalSettings' => [
          'plan_operational_presence_map' => [
            $chart_id => $map_settings,
          ],
        ],
      ],
    ];
  }

  /**
   * Get the currently selected view.
   *
   * @return string
   *   The selected view as a string.
   */
  private function getSelectedView() {
    $conf = $this->getBlockConfig();
    $available_views = $this->getViewOptions();
    $requested_view = $this->requestStack->getCurrentRequest()->get('view') ?? NULL;
    $default_view = $conf['display']['default_view'] ?? reset($available_views);
    $selected_view = $requested_view && array_key_exists($requested_view, $available_views) ? $requested_view : $default_view;
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

    $locations = $this->getLocations();
    if (empty($locations)) {
      // No locations, so we can abort here.
      return NULL;
    }

    $selected_view = $this->getSelectedView();
    $object_id = $this->getSelectedObjectId($selected_view);
    $objects_by_location = $this->getMapObjectsByLocation($selected_view, $object_id);
    if (empty($objects_by_location)) {
      return NULL;
    }

    // Process the GEOJSON files and add in the organization counts.
    foreach ($locations as $_location) {
      $geo_data = $_location->getGeoJson();
      if (empty($geo_data)) {
        continue;
      }

      $_objects = !empty($objects_by_location[$_location->location_id]) ? $objects_by_location[$_location->location_id] : [];
      $location_data = (object) $_location->toArray();

      $geo_data->properties->location_id = $_location->location_id;
      $geo_data->properties->location_name = $_location->location_name;
      $geo_data->properties->admin_level = $_location->admin_level;
      $geo_data->properties->object_count = count($_objects);

      $location_data->object_count = count($_objects);
      $location_data->modal_content = $this->prepareModalContent($location_data, $_objects, $selected_view);
      $location_data->geojson = json_encode($geo_data);

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
   * @return array
   *   An array of objects for display in the map.
   */
  private function getMapObjects($selected_view) {
    $objects = [];

    switch ($this->getSelectedView()) {
      case 'organization':
        $organizations = $this->getConfiguredOrganizations();
        $objects = array_map(function (Organization $organization) {
          $projects = $this->getOrganizationProjects($organization);
          $clusters = $this->getOrganizationClusters($organization);
          $location_ids = [];
          foreach ($projects as $project) {
            $location_ids = array_merge($location_ids, $project->location_ids);
          }
          $location_ids = array_unique($location_ids);
          return (object) ($organization->toArray() + [
            'clusters' => $clusters,
            'location_ids' => $location_ids,
          ]);
        }, $organizations);
        break;

      case 'cluster':
        $objects = [];
        $organizations = $this->getConfiguredOrganizations();
        foreach ($organizations as $organization) {
          $projects = $this->getOrganizationProjects($organization);
          foreach ($projects as $project) {
            if (empty($project->global_clusters)) {
              continue;
            }
            foreach ($project->clusters as $cluster) {
              $_cluster = (object) $cluster->toArray();
              $_cluster->location_ids = $project->location_ids;
              $objects[$_cluster->id] = $_cluster;
            }
          }
        }
        break;

      case 'project':
        $objects = [];
        $organizations = $this->getConfiguredOrganizations();
        foreach ($organizations as $organization) {
          $projects = $this->getOrganizationProjects($organization);
          if (empty($projects)) {
            continue;
          }
          $objects += $projects;
        }
        break;
    }

    // Filter objects for those with empty locations.
    $filtered_objects = array_filter($objects, function ($item) {
      return count($item->location_ids) > 0;
    });

    return $filtered_objects;
  }

  /**
   * Get the map objects grouped by location.
   *
   * @param string $selected_view
   *   The currently selected view.
   * @param int $object_id
   *   Optional: Restrict to the given object id.
   *
   * @return array
   *   An array of map objects.
   */
  private function getMapObjectsByLocation($selected_view, $object_id = NULL) {
    $objects = $this->getMapObjects($selected_view);
    $objects_by_location = [];
    foreach ($objects as $object) {
      if (!empty($object_id) && $object_id != $object->id) {
        // A specific organization has been requested, this is not it.
        continue;
      }
      if (empty($object->location_ids)) {
        continue;
      }
      foreach ($object->location_ids as $location_id) {
        if (empty($objects_by_location[$location_id]) || !is_array($objects_by_location[$location_id])) {
          $objects_by_location[$location_id] = [];
        }
        if (!empty($objects_by_location[$location_id][$object->id])) {
          continue;
        }
        $object_data = clone $object;
        unset($object_data->location_ids);
        $objects_by_location[$location_id][$object->id] = (object) [
          'id' => $object_data->id,
          'name' => $object_data->name,
        ];
        $optional_properties = [
          'icon',
          'code',
          'objective',
          'clusters',
          'location_ids',
          'organization_ids',
        ];
        foreach ($optional_properties as $optional_property) {
          if (!empty($object_data->$optional_property)) {
            $objects_by_location[$location_id][$object->id]->$optional_property = $object_data->$optional_property;
          }
        }
        unset($object_data);
      }
    }
    return $objects_by_location;
  }

  /**
   * Prepare the content for the modal screens.
   */
  private function prepareModalContent($location, $objects, $selected_view) {
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
        // Group organizations by clusters.
        $clusters = [];
        foreach ($objects as $object) {
          if (empty($object->clusters)) {
            continue;
          }
          foreach ($object->clusters as $cluster) {
            if (empty($clusters[$cluster->id])) {
              $clusters[$cluster->id] = [
                'icon' => $this->iconQuery->getIconEmbedCode($cluster->icon),
                'name' => $cluster->name,
                'organizations' => [],
              ];
            }
            $clusters[$cluster->id]['organizations'][$object->id] = $object->name;
          }
        }
        $clusters = array_filter($clusters, function ($item) {
          return !empty($item['organizations']);
        });

        $cluster_toggle = ThemeHelper::render([
          '#theme' => 'hpc_toggle',
          '#parent_selector' => '.cluster-wrapper',
          '#target_selector' => '.organizations-wrapper',
        ], FALSE);

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
        uasort($objects, function ($a, $b) {
          return strnatcmp($a->name, $b->name);
        });
        foreach ($objects as $object) {
          $icon = $this->iconQuery->getIconEmbedCode($object->icon);
          $content .= '<div class="cluster-wrapper"><div class="cluster-icon-wrapper">' . $icon . '</div>' . $object->name;
          $content .= '</div>';
        }
      }
      if ($selected_view == 'project') {
        // Group projects by clusters.
        $clusters = [];
        foreach ($objects as $object) {
          if (empty($object->clusters)) {
            continue;
          }
          foreach ($object->clusters as $cluster) {
            if (empty($clusters[$cluster->id])) {
              $clusters[$cluster->id] = [
                'icon' => $this->iconQuery->getIconEmbedCode($cluster->icon),
                'name' => $cluster->name,
                'projects' => [],
              ];
            }
            $clusters[$cluster->id]['projects'][$object->id] = $object->name;
          }
        }
        $clusters = array_filter($clusters, function ($item) {
          return !empty($item['projects']);
        });

        $cluster_toggle = ThemeHelper::render([
          '#theme' => 'hpc_toggle',
          '#parent_selector' => '.cluster-wrapper',
          '#target_selector' => '.projects-wrapper',
        ]);

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

    $fts_link = NULL;
    if (!empty($objects) && ($selected_view == 'project' || $selected_view == 'organization')) {
      $data_page = $selected_view == 'project' ? 'projects' : 'recipients';
      $link_title = $this->t('For more details, view on <img src="@logo_url" />', [
        '@logo_url' => ThemeHelper::getUriToFtsIcon(),
      ]);
      $fts_link = self::buildFtsLink($link_title, $this->getCurrentPlanObject(), $data_page, $this->getCurrentBaseObject());
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
      'location_id' => $location->location_id,
      'location_name' => $location->location_name,
      'admin_level' => $location->admin_level,
      'pcode' => $location->pcode,
      'title_heading' => $this->t('Admin area @admin_level', [
        '@admin_level' => $location->admin_level,
      ]),
      'title' => $location->location_name,
      'content' => ThemeHelper::render($fts_link, FALSE) . $content,
      'object_count_label' => $object_count_label_map[$selected_view],
    ];
    return $modal_content;
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
    return [
      '#theme' => 'ajax_switcher',
      '#element_key' => 'view',
      '#options' => $this->getViewOptions(),
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
        foreach ($object->clusters ?? [] as $cluster) {
          if (empty($object_options[$cluster->name])) {
            $object_options[$cluster->name] = [];
          }
          $object_options[$cluster->name][$object->id] = Unicode::truncate($object->name, 60, TRUE, TRUE);
        }
      }
      ksort($object_options);
    }
    else {
      $object_options = array_map(function ($item) {
        return Unicode::truncate($item->name, 60, TRUE, TRUE);
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
        'available_views' => [
          'organization' => 'organization',
          'cluster' => 'cluster',
          'project' => 'project',
        ],
        'default_view' => NULL,
        'disclaimer' => self::DEFAULT_DISCLAIMER,
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

    $form['select_organizations'] = [
      '#type' => 'button',
      '#value' => $this->t('Use selected organizations'),
      '#name' => 'submit-organizations',
      '#ajax' => [
        'event' => 'click',
        'callback' => [static::class, 'updateAjax'],
        'wrapper' => $this->wrapperId,
      ],
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
      '#default_value' => $this->getDefaultFormValueFromFormState($form_state, 'available_views') ?? self::DEFAULT_DISCLAIMER,
    ];

    $form['default_view'] = [
      '#type' => 'select',
      '#title' => $this->t('Default view'),
      '#description' => '',
      '#options' => $view_options,
      '#default_value' => $this->getDefaultFormValueFromFormState($form_state, 'default_view') ?? self::DEFAULT_DISCLAIMER,
    ];

    $form['disclaimer'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Map disclaimer'),
      '#description' => $this->t('You can override the default map disclaimer for this widget.'),
      '#rows' => 4,
      '#default_value' => $this->getDefaultFormValueFromFormState($form_state, 'disclaimer') ?? self::DEFAULT_DISCLAIMER,
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
    $plan_object = $this->getCurrentPlanObject();
    $ple_structure = PlanStructureHelper::getRpmPlanStructure($plan_object);
    $gve_item = reset($ple_structure[$group]);
    return $gve_item->$property;
  }

  /**
   * Get the organization options available in the current context.
   *
   * @return array
   *   An array of organizations, keyed by id.
   */
  private function getAvailableOrganizationOptions() {
    $organizations = $this->getOrganizations();
    $plan_location_ids = array_keys($this->getLocations());

    return array_map(function (Organization $organization) use ($plan_location_ids) {
      $projects = $this->getOrganizationProjects($organization);
      $clusters = $this->getOrganizationClusters($organization);
      $location_ids = [];
      foreach ($projects as $project) {
        $location_ids = array_merge($location_ids, $project->location_ids);
      }
      $location_ids = array_unique($location_ids);
      return [
        'id' => $organization->id,
        'name' => $organization->name,
        'projects' => count($projects),
        'clusters' => implode(', ', array_map(function (PlanProjectCluster $cluster) {
          return $cluster->name;
        }, $clusters)),
        'locations' => count(array_intersect($location_ids, $plan_location_ids)),
      ];
    }, $organizations);
  }

  /**
   * Get the available locations for the current context.
   *
   * @return \Drupal\hpc_api\ApiObjects\Location[]
   *   A flat array of location objects.
   */
  private function getLocations() {
    $plan_object = $this->getCurrentPlanObject();
    $plan_locations = &drupal_static(__FUNCTION__, []);
    if (empty($plan_locations[$plan_object->id()])) {
      // Prepare the locations.
      $country_id = $plan_object->field_country->entity->field_original_id->value ?? NULL;
      // Mock a country object.
      $country = (object) [
        'id' => $country_id,
      ];
      $max_admin_level = $plan_object->field_max_admin_level->value;
      /** @var \Drupal\hpc_api\Plugin\EndpointQuery\LocationsQuery $locations_query */
      $locations_query = $this->getQueryHandler('locations');
      $locations = $locations_query->getCountryLocations($country, $max_admin_level);

      // Filter out all locations which do not have a GEOJSON file.
      $locations = array_filter($locations, function ($location) {
        return !empty($location->filepath);
      });

      // Done.
      $plan_locations[$plan_object->id()] = $locations;
    }
    return $plan_locations[$plan_object->id()];
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
