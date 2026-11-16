<?php

namespace Drupal\Tests\ghi_blocks\Unit;

use Drupal\Component\Plugin\PluginManagerInterface;
use Drupal\Component\Utility\Html;
use Drupal\Core\Form\FormState;
use Drupal\Core\Render\Element\HtmlTag;
use Drupal\ghi_blocks\Element\CarouselItem as CarouselItemElement;
use Drupal\ghi_blocks\Plugin\ConfigurationContainerItem\CarouselItem;
use Drupal\ghi_form_elements\Element\ConfigurationContainer;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Tests accessible availability explanations and accurate editorial guidance.
 */
#[Group('ghi_blocks')]
class LinkCarouselEditorialAccessibilityTest extends UnitTestCase {

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $container = new ContainerBuilder();
    $container->set('string_translation', $this->getStringTranslationStub());
    \Drupal::setContainer($container);
    require_once dirname(__DIR__, 4) . '/hpc_common/hpc_common.theme.inc';
  }

  /**
   * Tests focused tooltip triggers expose every reason without relying on JS.
   */
  #[DataProvider('issueProvider')]
  public function testUnavailableReasonsAreAccessibleAndEscaped(array $issues): void {
    $element = CarouselItem::decoratePlacementTable($this->createSummaryElement(issues: $issues), new FormState());

    $placement = $element['summary_table'][1]['placement'];
    $this->assertSame('Not available', (string) $placement['label']['#value']);
    $tooltip = $placement['tooltip'];
    $this->assertSame('true', $tooltip['#tag_content']['icon']['#attributes']['aria-hidden']);
    $label = $tooltip['#tag_content']['label'];
    $this->assertContains('visually-hidden', $label['#attributes']['class']);
    $rendered_label = HtmlTag::preRenderHtmlTag($label);
    $html = $rendered_label['#prefix'] . $rendered_label['#markup'] . $rendered_label['#suffix'];
    foreach ($issues as $issue) {
      $this->assertStringContainsString(Html::escape($issue), $html);
    }
    $this->assertStringNotContainsString('<svg', $html);
    $this->assertStringNotContainsString('<script', $html);

    $variables = [
      'tag' => $tooltip['#tag'],
      'class' => $tooltip['#class'],
      'tag_content' => $tooltip['#tag_content'],
    ];
    hpc_common_preprocess_hpc_tooltip($variables);
    $this->assertSame('0', (string) $variables['attributes']['tabindex']);
    $this->assertContains('hpc_common/tooltip', $variables['#attached']['library']);
    $this->assertArrayHasKey('save_order', $element['actions']);
  }

  /**
   * Provides independent availability issues, including markup-like content.
   */
  public static function issueProvider(): array {
    return [
      'multiple repair reasons' => [['Add an image for this item.', 'Enter a valid URL or an existing content link.']],
      'markup-like reason stays text' => [['Check <svg onload="alert(1)"> and <script>alert(1)</script>.']],
    ];
  }

  /**
   * Tests available items retain their placement without an availability icon.
   */
  public function testAvailableItemDoesNotShowTooltip(): void {
    $element = CarouselItem::decoratePlacementTable($this->createSummaryElement(), new FormState());

    $placement = $element['summary_table'][1]['placement'];
    $this->assertSame('Main news', (string) $placement['label']['#value']);
    $this->assertArrayNotHasKey('tooltip', $placement);
  }

  /**
   * Tests a draggable separator is not an editable configuration item.
   */
  public function testDraggableDisplayBoundaryKeepsPlainPlacementLabels(): void {
    $element = CarouselItem::decoratePlacementTable($this->createSummaryElement(3, range(0, 5)), new FormState());
    $table = $element['summary_table'];
    $separator = $table['_display_end'];
    $this->assertContains('draggable', $separator['#attributes']['class']);
    $this->assertArrayHasKey('data-link-carousel-display-end', $separator['#attributes']);
    $this->assertContains('row-weight', $separator['weight']['#attributes']['class']);
    $this->assertSame('', $separator['operations']['#markup']);
    $this->assertSame('', $separator['id']['#markup']);
    $this->assertArrayNotHasKey('#type', $separator['id']);
    $this->assertSame(3, (int) $separator['weight']['#default_value']);
    $placements = [
      0 => 'Main news',
      1 => 'Featured 1',
      2 => 'Featured 2',
      3 => 'Not displayed',
      4 => 'Not displayed',
      5 => 'Not displayed',
    ];
    foreach ($placements as $id => $label) {
      $placement = $table[$id]['placement'];
      $this->assertSame($label, (string) $placement['label']['#value']);
      $this->assertArrayNotHasKey('control', $placement);
    }
  }

  /**
   * Tests actions are preserved outside the summary-table mode.
   */
  public function testActionsArePreservedWithoutSummaryTable(): void {
    $element = ['actions' => ['save_order' => ['#type' => 'submit'], 'add_new_item' => ['#type' => 'submit']]];

    $result = CarouselItem::decoratePlacementTable($element, new FormState());

    $this->assertSame($element, $result);
  }

  /**
   * Tests guidance matches the main-news and featured Twig presentation.
   */
  public function testMainNewsFieldGuidance(): void {
    $element = ['#default_value' => []];

    $element = CarouselItemElement::processCarouselItem($element, new FormState());

    foreach (['description', 'image_credit', 'image_caption', 'button_label'] as $key) {
      $description = (string) $element[$key]['#description'];
      $this->assertStringContainsString('Main news', $description);
      $this->assertStringNotContainsString('on top of', $description);
    }
    foreach (['image_credit', 'image_caption'] as $key) {
      $this->assertStringContainsString('information icon', (string) $element[$key]['#description']);
      $this->assertStringNotContainsString('below the image', (string) $element[$key]['#description']);
    }
    $this->assertStringContainsString('Read more', (string) $element['button_label']['#description']);
    $this->assertStringContainsString('Featured items link from their titles', (string) $element['button_label']['#description']);
  }

  /**
   * Tests the placement decorator reuses the table's availability assessment.
   */
  public function testAvailabilityIsAssessedOncePerRow(): void {
    $issues = [$this->getStringTranslationStub()->translate('Add an image for this item.')];
    $item = $this->getMockBuilder(CarouselItem::class)
      ->setConstructorArgs([[], 'carousel_item', []])
      ->onlyMethods(['getAvailabilityIssues'])
      ->getMock();
    $item->expects($this->once())->method('getAvailabilityIssues')->willReturn($issues);
    $manager = $this->createMock(PluginManagerInterface::class);
    $manager->expects($this->once())->method('createInstance')->with('carousel_item', [])->willReturn($item);
    \Drupal::getContainer()->set('plugin.manager.configuration_container_item_manager', $manager);
    $element = $this->createSummaryElement();
    $element['#allowed_item_types'] = ['carousel_item' => []];
    $element['#preview']['columns'] = ['placement' => 'Placement'];
    $element['#default_value'][1]['item_type'] = 'carousel_item';
    $form_state = new FormState();
    ConfigurationContainer::set($element, $form_state, 'items', $element['#default_value']);

    $element['summary_table'] = ConfigurationContainer::buildTableRows($element, $form_state, FALSE);
    $this->assertSame($issues, $element['summary_table'][1]['#configuration_errors']);
    $this->assertSame('Add an image for this item.', (string) $element['summary_table'][1]['issues']['#markup']);
    $element = CarouselItem::decoratePlacementTable($element, $form_state);

    $this->assertSame('Not available', (string) $element['summary_table'][1]['placement']['label']['#value']);
    $this->assertSame('Add an image for this item.', $element['summary_table'][1]['placement']['tooltip']['#tooltip']);
    $this->assertArrayNotHasKey('issues', $element['summary_table'][1]);
  }

  /**
   * Creates the configuration-container shape before child processing.
   */
  private function createSummaryElement(?int $display_end_weight = NULL, array $ids = [1], array $issues = []): array {
    $element = [
      '#parents' => ['links', 'items'],
      '#array_parents' => ['settings', 'container', 'items'],
      '#display_end_weight' => $display_end_weight,
      'actions' => ['save_order' => ['#type' => 'submit']],
    ];
    foreach ($ids as $id) {
      $weight = $id + ($display_end_weight !== NULL && $id >= $display_end_weight ? 1 : 0);
      $element['#default_value'][$id] = ['id' => $id, 'weight' => $weight, 'config' => []];
      $element['summary_table'][$id] = [
        '#weight' => $weight,
        '#configuration_errors' => $issues,
        'placement' => [],
        'weight' => ['#type' => 'weight', '#default_value' => $weight],
      ];
    }
    return $element;
  }

}
