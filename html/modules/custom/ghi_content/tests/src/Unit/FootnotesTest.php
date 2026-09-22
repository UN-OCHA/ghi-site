<?php

namespace Drupal\Tests\ghi_content\Unit;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Tests\UnitTestCase;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;
use Twig\TwigFilter;

/**
 * Tests isolated footnote rendering and inline link whitespace.
 *
 * @group ghi_content
 * @coversNothing
 */
class FootnotesTest extends UnitTestCase {

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    require_once dirname(__DIR__, 3) . '/modules/gho_footnotes/gho_footnotes.module';
  }

  /**
   * Tests isolated rendering of the text and footnote fields.
   */
  public function testPrepareBuild() {
    $renderer = $this->createMock(RendererInterface::class);
    $renderer->expects($this->exactly(2))->method('renderInIsolation')
      ->willReturnCallback(static fn(array $build) => $build['#markup']);
    $container = new ContainerBuilder();
    $container->set('renderer', $renderer);
    $container->set('current_route_match', $this->createMock(RouteMatchInterface::class));
    \Drupal::setContainer($container);

    $entity = $this->createMock(EntityInterface::class);
    $entity->method('id')->willReturn(1);
    $entity->method('getEntityTypeId')->willReturn('node');
    $build = [
      '#view_mode' => 'full',
      'field_text' => [['#markup' => '<p>Text [1]</p>']],
      'field_footnotes' => [['#markup' => 'Footnote text']],
    ];
    gho_footnotes_prepare_build($build, $entity);

    $this->assertSame('<gho-footnotes-text data-id="node-1"><p>Text [1]</p></gho-footnotes-text>', $build['field_text'][0]['#template']);
    $this->assertSame('<gho-footnotes-list id="gho-footnotes-list-node-1">Footnote text</gho-footnotes-list>', $build['field_footnotes']['#template']);
  }

  /**
   * Tests that reference replacement preserves spaces in the surrounding text.
   */
  public function testUpdateText() {
    $renderer = $this->createMock(RendererInterface::class);
    $renderer->expects($this->once())->method('renderInIsolation')
      ->willReturn(' <sup>1</sup> ');
    $container = new ContainerBuilder();
    $container->set('renderer', $renderer);
    \Drupal::setContainer($container);

    $text = 'Before [1] after';
    $references = gho_footnotes_extract_references($text);
    $footnotes = ['[1]' => ['#id' => 'footnote-1', '#index' => 1]];
    $this->assertSame('Before <sup>1</sup> after', gho_footnotes_update_text('node-1', $text, $references, $footnotes));
  }

  /**
   * Tests that inline links have no whitespace between their tags.
   */
  public function testLinkTemplates() {
    $loader = new FilesystemLoader(dirname(__DIR__, 3) . '/modules/gho_footnotes/templates');
    $twig = new Environment($loader, ['autoescape' => 'html']);
    $twig->addFilter(new TwigFilter('t', static fn($text, array $args) => strtr($text, $args)));
    $variables = ['id' => 'reference-1', 'target' => 'footnote-1', 'index' => 1];

    $reference = trim($twig->render('gho-footnote-reference.html.twig', $variables));
    $this->assertSame('<sup id="reference-1" class="gho-footnote-reference"><a href="#footnote-1" aria-label="Jump to footnote 1"><span title="Jump to footnote 1" aria-hidden="true">1</span></a></sup>', $reference);
    $backlink = trim($twig->render('gho-footnote-backlink.html.twig', $variables));
    $this->assertSame('<a class="gho-footnote-backlink" href="#footnote-1"><span class="visually-hidden">Jump to reference 1</span><span title="Jump to reference 1" aria-hidden="true"></span></a>', $backlink);
  }

}
