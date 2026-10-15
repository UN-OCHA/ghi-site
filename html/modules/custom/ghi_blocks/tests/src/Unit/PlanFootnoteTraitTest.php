<?php

namespace Drupal\Tests\ghi_blocks\Unit;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Render\RendererInterface;
use Drupal\ghi_blocks\Traits\PlanFootnoteTrait;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversTrait;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Twig\Environment;

/**
 * Tests rendering present and absent plan footnotes.
 */
#[CoversTrait(PlanFootnoteTrait::class)]
#[Group('ghi_blocks')]
class PlanFootnoteTraitTest extends UnitTestCase {

  /**
   * Tests that absent footnotes are not passed to the renderer.
   */
  #[DataProvider('absentFootnoteProvider')]
  public function testAbsentFootnote(?object $footnotes): void {
    $consumer = new class() {
      use PlanFootnoteTrait;
    };

    // No Drupal container is needed when there is nothing to render.
    $this->assertNull($consumer->getRenderedFootnoteTooltip($footnotes, 'target'));
  }

  /**
   * Provides absent footnote values.
   */
  public static function absentFootnoteProvider(): array {
    return [
      'no footnotes' => [NULL],
      'missing property' => [(object) []],
      'null footnote' => [(object) ['target' => NULL]],
      'empty footnote' => [(object) ['target' => '']],
    ];
  }

  /**
   * Tests that a present footnote is rendered as a tooltip.
   */
  public function testPresentFootnote(): void {
    $consumer = new class() {
      use PlanFootnoteTrait;
    };

    $renderer = $this->createMock(RendererInterface::class);
    $renderer->method('hasRenderContext')->willReturn(FALSE);
    $renderer->expects($this->once())->method('renderInIsolation')->with([
      '#theme' => 'hpc_tooltip',
      '#tooltip' => ['#plain_text' => 'Example footnote'],
    ])->willReturn('Rendered tooltip');

    $container = new ContainerBuilder();
    $container->set('renderer', $renderer);
    $container->set('twig', $this->createMock(Environment::class));
    \Drupal::setContainer($container);

    $this->assertSame('Rendered tooltip', $consumer->getRenderedFootnoteTooltip((object) ['target' => 'Example footnote'], 'target'));
  }

}
