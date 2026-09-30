<?php

namespace Drupal\Tests\hpc_security\Unit;

use Drupal\Core\Asset\AssetResolverInterface;
use Drupal\Core\Asset\AttachedAssets;
use Drupal\Core\Language\Language;
use Drupal\hpc_security\Asset\HpcAssetResolver;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests delegation of font assets through the security decorator.
 */
#[Group('hpc_security')]
class HpcAssetResolverTest extends UnitTestCase {

  /**
   * Tests that font assets and the requested language are passed unchanged.
   */
  #[DataProvider('providerFontAssets')]
  public function testGetFontAssets(?string $language_id): void {
    $assets = new AttachedAssets();
    $language = $language_id === NULL ? NULL : new Language(['id' => $language_id]);
    $fonts = [['url' => 'fonts/example.woff2']];
    $inner = $this->createMock(AssetResolverInterface::class);
    $inner->expects($this->once())
      ->method('getFontAssets')
      ->with($this->identicalTo($assets), $this->identicalTo($language))
      ->willReturn($fonts);

    $resolver = new HpcAssetResolver($inner);
    $this->assertSame($fonts, $resolver->getFontAssets($assets, $language));
  }

  /**
   * Provides default and explicit asset languages.
   */
  public static function providerFontAssets(): array {
    return [
      'default language' => [NULL],
      'explicit language' => ['fr'],
    ];
  }

}
