<?php

namespace Drupal\ghi_content_test\Plugin\RemoteSource;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\ghi_content\RemoteContent\HpcContentModule\RemoteArticle;
use Drupal\ghi_content\RemoteSource\RemoteSourceInterface;
use Drupal\ghi_content\RemoteSource\RemoteSourceBaseHpcContentModule;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Mocks a remote source for the HPC Content Module.
 *
 * @RemoteSource(
 *   id = "hpc_content_module_test",
 *   label = @Translation("HPC Content Module (for tests)"),
 *   description = @Translation("Import data directly from the HPC Content Module."),
 * )
 */
class HpcContentModuleTest extends RemoteSourceBaseHpcContentModule implements RemoteSourceInterface, ContainerFactoryPluginInterface {

  /**
   * The extension path resolver.
   *
   * @var \Drupal\Core\Extension\ExtensionPathResolver
   */
  protected $extensionPathResolver;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->extensionPathResolver = $container->get('extension.path.resolver');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'base_url' => NULL,
      'basic_auth' => NULL,
      'endpoint' => NULL,
      'access_key' => NULL,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getArticle($id) {
    $json = $this->getFixture('article', $id);
    return $json->article ? new RemoteArticle($json->article, $this) : NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getArticleTitle($id) {
    $article = $this->getArticle($id);
    return $article->getTitle();
  }

  /**
   * {@inheritdoc}
   */
  public function getParagraph($id) {
    // @todo Implement this.
    return (object) [];
  }

  /**
   * {@inheritdoc}
   */
  public function searchArticlesByTitle($title) {
    $json = $this->getFixture('articleSearch', 'global');
    $json->articleSearch->items = array_filter($json->articleSearch->items, function ($item) use ($title) {
      return stripos($item->title, $title) !== FALSE;
    });
    return array_map(function ($item) {
      $this->getArticle($item->id)->getSource()->getPluginId();
      return $this->getArticle($item->id);
    }, $json->articleSearch->items);
  }

  /**
   * Get the content of a fixture.
   *
   * @param string $type
   *   The type of fixture.
   * @param string $name
   *   The name of the fixture.
   *
   * @return mixed
   *   The json decoded content of the fixture.
   */
  private function getFixture($type, $name) {
    $file_path = $this->extensionPathResolver->getPath('module', 'ghi_content_test') . '/fixtures/' . $type . '/' . $name . '.json';
    return json_decode(file_get_contents($file_path));
  }

}
