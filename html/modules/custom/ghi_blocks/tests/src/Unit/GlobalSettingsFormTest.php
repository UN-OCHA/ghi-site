<?php

namespace Drupal\Tests\ghi_blocks\Unit;

use Drupal\Core\Config\Config;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Form\FormState;
use Drupal\Core\Routing\UrlGeneratorInterface;
use Drupal\ghi_blocks\Form\GlobalSettingsForm;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests that global settings form defaults ignore configuration overrides.
 */
#[Group('ghi_blocks')]
class GlobalSettingsFormTest extends UnitTestCase {

  /**
   * Tests that editable defaults are read from override-free configuration.
   */
  public function testOverrideFreeDefaults(): void {
    $config = $this->createMock(Config::class);
    $config->expects($this->once())->method('get')->with('2026')->willReturn([
      'plan_type_icons' => FALSE,
    ]);
    $config_factory = $this->createMock(ConfigFactoryInterface::class);
    $config_factory->expects($this->once())->method('getEditable')->with('ghi_blocks.global_settings')->willReturn($config);
    $config_factory->expects($this->never())->method('get');

    $url_generator = $this->createMock(UrlGeneratorInterface::class);
    $url_generator->method('generateFromRoute')->willReturn('/admin/structure/taxonomy/manage/plan_type/overview');
    $container = new ContainerBuilder();
    $container->set('config.factory', $config_factory);
    $container->set('url_generator', $url_generator);
    \Drupal::setContainer($container);

    $form = $this->getMockBuilder(GlobalSettingsForm::class)
      ->disableOriginalConstructor()
      ->onlyMethods(['getHomepageYears', 'getPlanTypeOrderSummary'])
      ->getMock();
    $form->method('getHomepageYears')->willReturn(['2026' => '2026']);
    $form->method('getPlanTypeOrderSummary')->willReturn('HRP');
    $form->setConfigFactory($config_factory);
    $form->setStringTranslation($this->getStringTranslationStub());

    $build = $form->buildForm([], new FormState());
    $this->assertFalse($build['years']['2026']['plan_type_icons']['#default_value']);
  }

}
