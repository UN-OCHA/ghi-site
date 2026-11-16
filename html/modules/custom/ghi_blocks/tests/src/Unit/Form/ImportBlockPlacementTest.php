<?php

namespace Drupal\Tests\ghi_blocks\Unit\Form;

use Drupal\Component\Serialization\Yaml;
use Drupal\Component\Uuid\UuidInterface;
use Drupal\Core\Block\BlockManagerInterface;
use Drupal\Core\Block\BlockPluginInterface;
use Drupal\Core\DependencyInjection\ClassResolver;
use Drupal\Core\Form\FormState;
use Drupal\Core\Plugin\Context\ContextRepositoryInterface;
use Drupal\Core\Plugin\PluginFormFactoryInterface;
use Drupal\ghi_blocks\Access\BlockPlacementAccess;
use Drupal\ghi_blocks\Form\ImportBlockForm;
use Drupal\ghi_blocks\LayoutBuilder\AllowedBlockDefinitions;
use Drupal\layout_builder\LayoutTempstoreRepositoryInterface;
use Drupal\layout_builder\SectionStorageInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Tests that adding and importing blocks obey the same placement restrictions.
 */
#[Group('ghi_blocks')]
class ImportBlockPlacementTest extends UnitTestCase {

  /**
   * Tests imports and direct add URLs follow the configured placement policy.
   */
  #[DataProvider('placementProvider')]
  public function testPlacement(string $plugin_id, string $storage_type, bool $allowed): void {
    $storage = $this->createMock(SectionStorageInterface::class);
    $storage->method('getPluginId')->willReturn($storage_type);
    $storage->expects($this->never())->method('getSection');
    $allowed_blocks = $this->createMock(AllowedBlockDefinitions::class);
    $allowed_blocks->method('getDefinitions')->with($storage)->willReturn($allowed ? [$plugin_id => []] : []);
    $form = $this->createImportForm($storage, $allowed_blocks);

    $configuration = ['id' => $plugin_id];
    $form_state = new FormState();
    $form_state->setTriggeringElement(['#parents' => ['import']]);
    $form_state->setValue('config', Yaml::encode([
      'element' => $plugin_id,
      'conf' => $configuration,
      'hash' => md5($plugin_id . '|' . Yaml::encode($configuration)),
    ]));
    $form_array = [];
    $form->validateForm($form_array, $form_state);

    $this->assertSame(!$allowed, $form_state->hasAnyErrors());
    $this->assertSame($allowed, BlockPlacementAccess::access($storage, $plugin_id)->isAllowed());
  }

  /**
   * Tests final validation rechecks policy before validating plugin fields.
   */
  #[DataProvider('finalPlacementProvider')]
  public function testFinalPlacement(bool $allowed): void {
    $plugin_id = 'generic_simple_text';
    $storage = $this->createMock(SectionStorageInterface::class);
    $storage->expects($this->never())->method('getSection');
    $definitions = [$plugin_id => []];
    $allowed_blocks = $this->createMock(AllowedBlockDefinitions::class);
    $allowed_blocks->expects($this->exactly(2))->method('getDefinitions')->with($storage)->willReturnCallback(function () use (&$definitions) {
      return $definitions;
    });
    $form = $this->createImportForm($storage, $allowed_blocks);
    $block = $this->createMock(BlockPluginInterface::class);
    $block->method('getPluginId')->willReturn($plugin_id);
    $block->expects($allowed ? $this->once() : $this->never())->method('validateConfigurationForm');
    (new \ReflectionProperty($form, 'block'))->setValue($form, $block);
    $configuration = ['id' => $plugin_id];
    $form_state = new FormState();
    $form_state->setTriggeringElement(['#parents' => ['import']]);
    $form_state->setValue('config', Yaml::encode([
      'element' => $plugin_id,
      'conf' => $configuration,
      'hash' => md5($plugin_id . '|' . Yaml::encode($configuration)),
    ]));
    $form_array = ['#parents' => [], '#array_parents' => [], '#ghi_inline_errors_only' => TRUE, 'settings' => []];
    $form->validateForm($form_array, $form_state);
    $this->assertFalse($form_state->hasAnyErrors());

    // The component was prepared while allowed; only the final policy changes.
    $definitions = $allowed ? $definitions : [];
    $form_state->setTriggeringElement(['#parents' => ['actions', 'submit']]);
    $form_state->setValues(['settings' => []]);
    $form->validateForm($form_array, $form_state);

    $this->assertSame(!$allowed, $form_state->hasAnyErrors());
    $this->assertSame($allowed, $form_array['#ghi_inline_errors_only']);
    if ($allowed) {
      $this->assertArrayNotHasKey('status_messages', $form_array);
    }
    else {
      $this->assertSame('This element is not allowed in this layout.', (string) $form_state->getErrors()['']);
      $this->assertSame('status_messages', $form_array['status_messages']['#type']);
      $this->assertSame(['status_messages'], $form_array['status_messages']['#parents']);
      $this->assertSame(['status_messages'], $form_array['status_messages']['#array_parents']);
    }
  }

  /**
   * Provides final policies after a successful initial import validation.
   */
  public static function finalPlacementProvider(): array {
    return [
      'unchanged policy' => [TRUE],
      'newly restricted plugin' => [FALSE],
    ];
  }

  /**
   * Provides destinations for restricted and ordinary blocks.
   */
  public static function placementProvider(): array {
    return [
      'node override' => ['generic_link_carousel', 'overrides', FALSE],
      'node or template defaults' => ['generic_link_carousel', 'defaults', FALSE],
      'Page Manager' => ['generic_link_carousel', 'page_manager', TRUE],
      'ordinary entity block' => ['generic_simple_text', 'overrides', TRUE],
      'configuration allows carousel on entity' => ['generic_link_carousel', 'overrides', TRUE],
      'configuration disallows ordinary block' => ['generic_simple_text', 'overrides', FALSE],
    ];
  }

  /**
   * Creates the import form with controlled lookup and no tempstore writes.
   */
  private function createImportForm(SectionStorageInterface $storage, AllowedBlockDefinitions $allowed_blocks): ImportBlockForm {
    $block_manager = $this->createMock(BlockManagerInterface::class);
    $context_repository = $this->createMock(ContextRepositoryInterface::class);
    $context_repository->method('getAvailableContexts')->willReturn([]);
    $context_repository->method('getRuntimeContexts')->willReturn([]);
    $container = new ContainerBuilder();
    $container->set(AllowedBlockDefinitions::class, $allowed_blocks);
    $container->set('plugin.manager.block', $block_manager);
    $container->set('context.repository', $context_repository);
    $container->set('class_resolver', new ClassResolver($container));
    \Drupal::setContainer($container);
    $repository = $this->createMock(LayoutTempstoreRepositoryInterface::class);
    $repository->expects($this->never())->method('set');
    $form = new ImportBlockForm(
      $repository,
      $context_repository,
      $block_manager,
      $this->createMock(UuidInterface::class),
      $this->createMock(PluginFormFactoryInterface::class),
    );
    $form->setStringTranslation($this->getStringTranslationStub());
    (new \ReflectionProperty($form, 'sectionStorage'))->setValue($form, $storage);
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  protected function tearDown(): void {
    (new FormState())->clearErrors();
    parent::tearDown();
  }

}
