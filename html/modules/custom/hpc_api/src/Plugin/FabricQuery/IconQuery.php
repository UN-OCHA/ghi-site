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

  const MONOCHROME_ICON_COLOR = '#4d4d4d';

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
   * Get the URI of a locally imported icon file.
   *
   * @param string|null $icon
   *   The icon name.
   *
   * @return string|null
   *   The URI of the SVG file or NULL when no icon is available.
   */
  public function getIconUri(?string $icon): ?string {
    if (empty($icon) || $icon == 'blank_icon') {
      return NULL;
    }
    $file_uri = self::IMPORT_DIR . '/' . $icon . '.svg';
    if (file_exists($file_uri)) {
      return $file_uri;
    }
    $svg_content = $this->fetchIconEmbedCode($icon);
    if (!$svg_content || !$this->fileSystem->saveData($svg_content, $file_uri, FileExists::Replace)) {
      return NULL;
    }
    return $file_uri;
  }

  /**
   * Get the URI of a monochrome icon file for use in an image element.
   *
   * SVGs embedded in the page inherit their fill color from the theme. An SVG
   * loaded through an image element cannot inherit that CSS, so cache a
   * monochrome derivative with the same fill color used by the theme.
   *
   * @param string|null $icon
   *   The icon name.
   *
   * @return string|null
   *   The URI of the monochrome SVG file or NULL when no icon is available.
   */
  public function getMonochromeIconUri(?string $icon): ?string {
    $icon_uri = $this->getIconUri($icon);
    if (!$icon_uri) {
      return NULL;
    }
    $monochrome_uri = self::IMPORT_DIR . '/' . $icon . '.monochrome.svg';
    if (file_exists($monochrome_uri)) {
      return $monochrome_uri;
    }
    $svg_content = file_get_contents($icon_uri);
    $closing_svg_position = $svg_content ? strripos($svg_content, '</svg>') : FALSE;
    if ($closing_svg_position === FALSE) {
      return NULL;
    }
    $svg_content = substr_replace($svg_content, '<style>svg * { fill: ' . self::MONOCHROME_ICON_COLOR . ' !important; }</style>', $closing_svg_position, 0);
    return $this->fileSystem->saveData($svg_content, $monochrome_uri, FileExists::Replace) ? $monochrome_uri : NULL;
  }

  /**
   * Get the icon embed code.
   *
   * @param string|null $icon
   *   The icon name.
   *
   * @return string|null
   *   A markup string with the SVG embed code.
   */
  public function getIconEmbedCode(?string $icon): ?string {
    $file_uri = $this->getIconUri($icon);
    $svg_content = $file_uri ? file_get_contents($file_uri) : NULL;
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
