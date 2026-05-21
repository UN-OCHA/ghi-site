<?php

namespace Drupal\hpc_api\Plugin\FabricQuery;

use Drupal\Core\File\FileExists;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\hpc_api\Attribute\FabricQuery;
use Drupal\hpc_api\Query\FabricQueryBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of the 'icon' fabric query.
 */
#[FabricQuery(
  id: 'icon',
  label: new TranslatableMarkup('Icon query'),
)]
class IconQuery extends FabricQueryBase {

  const IMPORT_DIR = 'public://icons';

  /**
   * The file system service.
   *
   * @var \Drupal\Core\File\FileSystem
   */
  protected $fileSystem;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): self {
    /** @var self $instance */
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->fileSystem = $container->get('file_system');
    return $instance;
  }

  /**
   * Get the icon embed code.
   *
   * @param string $icon
   *   The icon name.
   *
   * @return string|null
   *   A markup string with the svg embed code.
   */
  public function getIconEmbedCode(?string $icon) {
    if (empty($icon) || $icon == 'blank_icon') {
      return NULL;
    }
    $file_uri = self::IMPORT_DIR . '/' . $icon . '.svg';
    $svg_content = file_exists($file_uri) ? file_get_contents($file_uri) : NULL;
    if (!$svg_content) {
      $svg_data = $this->fetchIconEmbedCode($icon);
      $svg_content = $svg_data ?? NULL;
      $this->fileSystem->saveData($svg_content, $file_uri, FileExists::Replace);
    }
    return $svg_content ? '<span class="cluster-icon icon">' . $svg_content . '</span>' : NULL;
  }

  /**
   * Fetch the icon embed code for the given icon.
   *
   * @param string $icon
   *   The icon name.
   *
   * @return string|null
   *   The svg string or NULL.
   */
  private function fetchIconEmbedCode(string $icon): ?string {
    // Get the resource data.
    $items = $this->fabricClient->createQuery('icons', ['Svg'], NULL, 1)
      ->setFilter('Name', $icon)
      ->execute() ?: [];
    $item = count($items) == 1 ? reset($items) : NULL;
    return $item?->Svg ?? NULL;
  }

}
