<?php

namespace Drupal\Tests\ghi_blocks\Unit;

use Drupal\Core\Config\Config;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Form\FormState;
use Drupal\ghi_blocks\Form\BlockSettingsForm;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the block settings form.
 */
#[Group('ghi_blocks')]
class BlockSettingsFormTest extends UnitTestCase {

  /**
   * Tests that the global safety switch is exposed with its stored value.
   */
  public function testCaseloadTrendsSetting(): void {
    $config = $this->createMock(Config::class);
    $config->expects($this->exactly(2))->method('get')->willReturnMap([
      ['lazy_load', TRUE],
      ['caseload_trends_include_unreleased_gho', FALSE],
    ]);
    $config_factory = $this->createMock(ConfigFactoryInterface::class);
    $config_factory->expects($this->once())->method('getEditable')->with('ghi_blocks.block_settings')->willReturn($config);

    $container = new ContainerBuilder();
    $container->set('config.factory', $config_factory);
    $container->set('string_translation', $this->getStringTranslationStub());
    \Drupal::setContainer($container);

    $form = $this->getMockBuilder(BlockSettingsForm::class)
      ->disableOriginalConstructor()
      ->onlyMethods([])
      ->getMock();
    $form->setConfigFactory($config_factory);
    $form->setStringTranslation($this->getStringTranslationStub());

    $build = $form->buildForm([], new FormState());

    $this->assertTrue($build['lazy_load']['#default_value']);
    $this->assertFalse($build['caseload_trends_include_unreleased_gho']['#default_value']);
  }

}
