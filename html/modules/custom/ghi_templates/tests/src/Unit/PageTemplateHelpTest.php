<?php

namespace Drupal\Tests\ghi_templates\Unit;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\ghi_templates\Entity\PageTemplateInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Tests explanations for templates whose source page has been deleted.
 */
#[Group('ghi_templates')]
class PageTemplateHelpTest extends UnitTestCase {

  /**
   * Tests that a missing source explains why new placements are unavailable.
   */
  #[DataProvider('sourceProvider')]
  public function testSourceHelp(bool $source_exists): void {
    require_once $this->root . '/modules/custom/ghi_templates/ghi_templates.module';
    $source = NULL;
    if ($source_exists) {
      $source = $this->createMock(EntityInterface::class);
      $source->method('getCacheTags')->willReturn(['node:1']);
      $source->method('getCacheContexts')->willReturn([]);
      $source->method('getCacheMaxAge')->willReturn(Cache::PERMANENT);
    }
    $template = $this->createMock(PageTemplateInterface::class);
    $template->method('getSourceEntity')->willReturn($source);
    $template->method('getSourceSummary')->willReturn('Template source summary');
    $template->method('getCacheTags')->willReturn(['page_template:7']);
    $template->method('getCacheContexts')->willReturn([]);
    $template->method('getCacheMaxAge')->willReturn(Cache::PERMANENT);
    $route_match = $this->createMock(RouteMatchInterface::class);
    $route_match->method('getRouteName')->willReturn('entity.page_template.canonical');
    $route_match->method('getParameter')->with('page_template')->willReturn($template);
    $container = new ContainerBuilder();
    $container->set('current_route_match', $route_match);
    $container->set('string_translation', $this->getStringTranslationStub());
    \Drupal::setContainer($container);
    $variables = ['page' => []];

    ghi_templates_preprocess_page($variables);

    $help = $variables['page']['help'];
    $this->assertArrayHasKey('message', $help);
    if ($source_exists) {
      $this->assertArrayNotHasKey('source_missing', $help);
      $this->assertContains('node:1', $help['#cache']['tags']);
    }
    else {
      $this->assertStringContainsString('You cannot add or import new elements', (string) $help['source_missing']['#message']);
      $this->assertStringContainsString('Existing elements are retained', (string) $help['source_missing']['#message']);
    }
    $this->assertContains('page_template:7', $help['#cache']['tags']);
    $this->assertSame($help['#cache'], $variables['page']['page_subtitle']['#cache']);
  }

  /**
   * Provides available and deleted source pages.
   */
  public static function sourceProvider(): array {
    return [
      'source available' => [TRUE],
      'source deleted' => [FALSE],
    ];
  }

}
