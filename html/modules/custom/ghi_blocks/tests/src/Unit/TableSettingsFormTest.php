<?php

namespace Drupal\Tests\ghi_blocks\Unit;

use Drupal\Core\Config\Config;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Form\FormState;
use Drupal\ghi_blocks\Form\TableSettingsForm;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the table settings form.
 */
#[Group('ghi_blocks')]
class TableSettingsFormTest extends UnitTestCase {

  /**
   * Tests that table settings use override-free configuration defaults.
   */
  public function testOverrideFreeDefaults(): void {
    $table_config = $this->createMock(Config::class);
    $table_config->expects($this->once())->method('get')->with('scroll_indicator')->willReturn(TRUE);
    $display_config = $this->createMock(Config::class);
    $display_config->expects($this->once())->method('get')->with('numeric_display_style')->willReturn('standard');
    $config_factory = $this->createMock(ConfigFactoryInterface::class);
    $config_factory->expects($this->exactly(2))->method('getEditable')->willReturnMap([
      ['ghi_blocks.table_settings', $table_config],
      ['hpc_common.settings', $display_config],
    ]);
    $config_factory->expects($this->never())->method('get');

    $container = new ContainerBuilder();
    $container->set('config.factory', $config_factory);
    $container->set('string_translation', $this->getStringTranslationStub());
    \Drupal::setContainer($container);

    $form = $this->getMockBuilder(TableSettingsForm::class)
      ->disableOriginalConstructor()
      ->onlyMethods([])
      ->getMock();
    $form->setConfigFactory($config_factory);
    $form->setStringTranslation($this->getStringTranslationStub());

    $build = $form->buildForm([], new FormState());

    $this->assertSame('standard', $build['numeric_display_style']['#default_value']);
    $this->assertTrue($build['scroll_indicator']['#default_value']);
  }

}
