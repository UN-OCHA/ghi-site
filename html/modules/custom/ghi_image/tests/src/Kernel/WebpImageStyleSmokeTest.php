<?php

namespace Drupal\Tests\ghi_image\Kernel;

use Drupal\Core\File\FileExists;
use Drupal\Core\File\FileSystemInterface;
use Drupal\image\Entity\ImageStyle;
use Drupal\imageapi_optimize\Entity\ImageAPIOptimizePipeline;
use Drupal\KernelTests\KernelTestBase;

/**
 * Smoke tests for WebP sidecars created through image styles.
 *
 * @group ghi_image
 */
class WebpImageStyleSmokeTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   *
   * @var array
   */
  protected static $modules = [
    'system',
    'file',
    'image',
    'imageapi_optimize',
    'webp',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installConfig(['system', 'image', 'imageapi_optimize', 'webp']);
  }

  /**
   * Reveals when image style generation does not create the final WebP sidecar.
   */
  public function testImageStyleGenerationCreatesFinalWebpSidecar(): void {
    ImageAPIOptimizePipeline::create([
      'name' => 'webp',
      'label' => 'WebP',
      'processors' => [
        'webp-smoke' => [
          'id' => 'webp_webp',
          'data' => [
            'quality' => '100',
          ],
          'weight' => 1,
          'uuid' => 'webp-smoke',
        ],
      ],
    ])->save();
    \Drupal::configFactory()
      ->getEditable('imageapi_optimize.settings')
      ->set('default_pipeline', 'webp')
      ->save();

    $style = ImageStyle::create([
      'name' => 'webp_smoke',
      'label' => 'WebP smoke',
      'pipeline' => '__default__',
    ]);
    $style->save();

    $directory = 'public://webp-smoke';
    \Drupal::service('file_system')->prepareDirectory($directory, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS);
    $source_uri = $directory . '/source.png';
    \Drupal::service('file_system')->saveData($this->getTinyPngData(), $source_uri, FileExists::Replace);

    $derivative_uri = $style->buildUri($source_uri);
    $this->assertTrue($style->createDerivative($source_uri, $derivative_uri));
    $this->assertFileExists($derivative_uri);
    $this->assertFileExists($derivative_uri . '.webp');
  }

  /**
   * Get a tiny valid PNG for image style smoke tests.
   *
   * @return string
   *   Binary PNG data.
   */
  private function getTinyPngData(): string {
    return file_get_contents($this->root . '/core/tests/fixtures/files/image-test.png');
  }

}
