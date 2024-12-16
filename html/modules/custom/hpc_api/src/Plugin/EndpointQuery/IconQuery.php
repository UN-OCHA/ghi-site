<?php

namespace Drupal\hpc_api\Plugin\EndpointQuery;

use Drupal\hpc_api\Query\EndpointQueryBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a query plugin for attachments.
 *
 * @EndpointQuery(
 *   id = "icon_query",
 *   label = @Translation("Icon query"),
 *   endpoint = {
 *     "public" = "icon/{icon}",
 *     "version" = "v2"
 *   }
 * )
 */
class IconQuery extends EndpointQueryBase {

  const IMPORT_DIR = 'public://icons';

  /**
   * The file system service.
   *
   * @var \Drupal\Core\File\FileSystem
   */
  protected $fileSystem;

  /**
   * The fetched and processed plans.
   *
   * @var \Drupal\ghi_plans\ApiObjects\Partials\PlanOverviewPlan[]
   */
  private $plans = NULL;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->fileSystem = $container->get('file_system');
    return $instance;
  }

  /**
   * Get tagged clusters for the given plan id.
   *
   * @param string $icon
   *   The identifier of the icon.
   *
   * @return string|null
   *   An array of cluster objects, keyed by the cluster id.
   */
  public function getIconEmbedCode($icon) {
    if (empty($icon) || $icon == 'blank_icon') {
      return NULL;
    }
    $file_uri = self::IMPORT_DIR . '/' . $icon . '.svg';
    $svg_content = file_exists($file_uri) ? file_get_contents($file_uri) : NULL;
    if (!$svg_content) {
      $svg_data = $this->getData(['icon' => $icon]);
      $svg_content = $svg_data?->svg ?? NULL;
      $this->fileSystem->saveData($svg_content, $file_uri);
    }
    return '<span class="cluster-icon icon">' . $svg_content . '</span>';
  }

}
