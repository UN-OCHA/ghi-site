<?php

namespace Drupal\ghi_blocks\Metatags;

use Drupal\Core\File\FileUrlGeneratorInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Theme\ThemeManagerInterface;
use Drupal\ghi_blocks\Plugin\Block\ImageProviderBlockInterface;
use Drupal\page_manager\Entity\PageVariant;

/**
 * Service class for altering metatags.
 */
class Metatags {

  /**
   * The route match.
   *
   * @var \Drupal\Core\Routing\RouteMatchInterface
   */
  protected $routeMatch;

  /**
   * The theme manager.
   *
   * @var \Drupal\Core\Theme\ThemeManagerInterface
   */
  protected $themeManager;

  /**
   * The file URL generator.
   *
   * @var \Drupal\Core\File\FileUrlGeneratorInterface
   */
  protected $fileUrlGenerator;

  /**
   * Constructs a new RouteCacheContext class.
   *
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The route match.
   * @param \Drupal\Core\Theme\ThemeManagerInterface $theme_manager
   *   The theme manager.
   * @param \Drupal\Core\File\FileUrlGeneratorInterface $file_url_generator
   *   The file URL generator.
   */
  public function __construct(RouteMatchInterface $route_match, ThemeManagerInterface $theme_manager, FileUrlGeneratorInterface $file_url_generator) {
    $this->routeMatch = $route_match;
    $this->themeManager = $theme_manager;
    $this->fileUrlGenerator = $file_url_generator;
  }

  /**
   * Alter metatags for the current page.
   *
   * @param array $metatags
   *   The special meta tags to be added to the page.
   * @param array $context
   *   The context for the current meta tags being generated. Will contain the
   *   following:
   *   'entity' - The entity being processed; passed by reference.
   */
  public function alter(array &$metatags, array &$context) {
    $page_variant = $this->routeMatch->getParameter('page_manager_page_variant');
    if ($page_variant) {
      $this->pageVariantMetatagAlter($page_variant, $metatags, $context);
    }
  }

  /**
   * Alter the final metatag page attachments.
   *
   * This is used to capture empty images and provide a default image.
   *
   * @param array $metatag_attachments
   *   An array of metatag objects to be attached to the current page.
   */
  public function alterAttachments(array &$metatag_attachments) {
    $metatag_types = array_flip(array_map(function ($attachment) {
      return $attachment[1];
    }, $metatag_attachments['#attached']['html_head']));

    if (empty($metatag_types['image_src'])) {
      // If no image source is set, that also means that no social images are
      // set, so let's do this here.
      $image_url = $this->getDefaultSocialImage();
      $metatag_attachments['#attached']['html_head'][] = [
        0 => [
          '#tag' => 'link',
          '#attributes' => [
            'rel' => 'image_src',
            'href' => $image_url,
          ],
        ],
        1 => 'image_src',
      ];
      $metatag_attachments['#attached']['html_head'][] = $this->setImageMetatag('og_image', $image_url);
      $metatag_attachments['#attached']['html_head'][] = $this->setImageMetatag('twitter_image', $image_url);
    }
  }

  /**
   * Alter meta tags for a page variant.
   *
   * Used to extract images for social networks from individual block plugins on
   * the front page.
   *
   * @param \Drupal\page_manager\Entity\PageVariant $page_variant
   *   The page variant currently processed.
   * @param array $metatags
   *   The special meta tags to be added to the page.
   * @param array $context
   *   The context for the current meta tags being generated. Will contain the
   *   following:
   *   'entity' - The entity being processed; passed by reference.
   */
  private function pageVariantMetatagAlter(PageVariant $page_variant, array &$metatags, array &$context) {
    $plugin_collections = $page_variant->getPluginCollections();
    $variant_settings = $plugin_collections['variant_settings'];

    /** @var \Drupal\layout_builder\Section $section */
    $section = $variant_settings->getConfiguration()['sections'][0] ?? [];
    if (!$section) {
      return;
    }
    foreach ($section->getComponents() as $component) {
      /** @var \Drupal\layout_builder\SectionComponent $component */
      $plugin = $component->getPlugin();
      if (!$plugin instanceof ImageProviderBlockInterface) {
        continue;
      }
      $image_uri = $plugin->provideImageUri();
      if (!$image_uri) {
        continue;
      }
      $image_url = $this->fileUrlGenerator->generateAbsoluteString($image_uri);
      $metatags['og_image'] = $image_url;
      $metatags['twitter_image'] = $image_url;
      return;
    }
  }

  /**
   * Get the default social image.
   *
   * @return string
   *   The url to the logo as a string.
   */
  private function getDefaultSocialImage() {
    $logo_path = $this->themeManager->getActiveTheme()->getLogo();
    $pathinfo = pathinfo($logo_path);
    $social_logo = implode(DIRECTORY_SEPARATOR, [
      $pathinfo['dirname'],
      $pathinfo['filename'] . '-social.png',
    ]);
    if (file_exists($social_logo)) {
      $logo_path = $social_logo;
    }
    return $this->fileUrlGenerator->generateAbsoluteString($logo_path);
  }

  /**
   * Create a metatag for a social image.
   *
   * @param string $name
   *   The name of the metatag.
   * @param string $image_url
   *   The url to the image.
   *
   * @return array
   *   A metatag array.
   */
  private function setImageMetatag($name, $image_url) {
    return [
      0 => [
        '#tag' => 'meta',
        '#attributes' => [
          'property' => str_replace('_', ':', $name),
          'content' => $image_url,
        ],
      ],
      1 => $name,
    ];
  }

}
