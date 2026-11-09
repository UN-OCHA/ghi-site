<?php

namespace Drupal\Tests\ghi_templates\Unit;

use Drupal\Component\Uuid\UuidInterface;
use Drupal\Core\DependencyInjection\ClassResolver;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormState;
use Drupal\Core\Plugin\Context\ContextHandlerInterface;
use Drupal\ghi_blocks\LayoutBuilder\AllowedBlockDefinitions;
use Drupal\ghi_templates\LayoutBuilder\ApplyPageTemplateForm;
use Drupal\ghi_templates\LayoutBuilder\ImportPageConfigForm;
use Drupal\ghi_templates\PageTemplateManager;
use Drupal\layout_builder\SectionStorageInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Tests restrictions on imports and template application before layout changes.
 */
#[Group('ghi_templates')]
class PageTemplatePlacementTest extends UnitTestCase {

  /**
   * The mocked restriction lookup.
   */
  protected AllowedBlockDefinitions $allowedBlocks;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->allowedBlocks = $this->createMock(AllowedBlockDefinitions::class);
    $this->allowedBlocks->method('getDefinitions')->willReturn([]);
    $container = new ContainerBuilder();
    $container->set(AllowedBlockDefinitions::class, $this->allowedBlocks);
    $container->set('class_resolver', new ClassResolver($container));
    \Drupal::setContainer($container);
  }

  /**
   * Tests crafted selections cannot bypass disabled import/template rows.
   */
  #[DataProvider('formProvider')]
  public function testSelectedRestrictedElement(string $form_class): void {
    $storage = $this->createMock(SectionStorageInterface::class);
    $storage->method('getPluginId')->willReturn('overrides');
    $entity = $this->createMock(EntityInterface::class);
    $this->allowedBlocks->expects($this->once())->method('getDefinitions')->with($storage, $entity);
    $form = new $form_class();
    (new \ReflectionProperty($form, 'classResolver'))->setValue($form, \Drupal::classResolver());
    $form->setStringTranslation($this->getStringTranslationStub());
    $form_state = new FormState();
    $form_state->setTriggeringElement(['#parents' => ['submit']]);
    $form_state->set('section_storage', $storage);
    $form_state->set('entity', $entity);
    $form_state->set('page_config', $this->createRestrictedPageConfig());
    $form_state->setValue('table', ['0--carousel' => '0--carousel']);
    $form_array = [];

    $form->validateForm($form_array, $form_state);

    $this->assertArrayHasKey('table', $form_state->getErrors());
  }

  /**
   * Tests Back preserves wizard data without re-validating or importing it.
   */
  #[DataProvider('formProvider')]
  public function testBackPreservesWizardState(string $form_class): void {
    $this->allowedBlocks->expects($this->never())->method('getDefinitions');
    $manager = $this->createMock(PageTemplateManager::class);
    $manager->expects($this->never())->method('loadPageTemplate');
    $manager->expects($this->never())->method('exportSectionStorage');
    $manager->expects($this->never())->method('buildSectionStorageFromPageConfig');
    $form = new $form_class();
    (new \ReflectionProperty($form, 'pageTemplateManager'))->setValue($form, $manager);
    $wizard_data = [
      'page_template' => 123,
      'page_config' => $this->createRestrictedPageConfig(),
      'config' => ['original' => 'Imported configuration'],
    ];
    $form_state = new FormState();
    $form_state->setStorage($wizard_data + ['current_import_step' => 'confirm']);
    $form_state->setTriggeringElement(['#parents' => ['actions', 'back']]);
    // A stale input must not replace the already validated wizard snapshot.
    $form_state->setValue('config', 'invalid: [yaml');
    $form_state->setValue('table', []);
    $form_array = [];

    $form->validateForm($form_array, $form_state);
    $form->submitForm($form_array, $form_state);

    $this->assertFalse($form_state->hasAnyErrors());
    $this->assertTrue($form_state->isRebuilding());
    $this->assertSame($form_class === ImportPageConfigForm::class ? 'import' : 'select_template', $form_state->get('current_import_step'));
    foreach ($wizard_data as $key => $value) {
      $this->assertSame($value, $form_state->get($key));
    }
  }

  /**
   * Provides the two forms that share the import path.
   */
  public static function formProvider(): array {
    return [
      'page configuration import' => [ImportPageConfigForm::class],
      'template application' => [ApplyPageTemplateForm::class],
    ];
  }

  /**
   * Tests the manager rejects invalid imports before clearing the destination.
   */
  public function testManagerValidatesBeforeOverwrite(): void {
    $storage = $this->createMock(SectionStorageInterface::class);
    $storage->method('getPluginId')->willReturn('overrides');
    $storage->expects($this->never())->method('getSection');
    $storage->expects($this->never())->method('removeSection');
    $entity = $this->createMock(EntityInterface::class);
    $this->allowedBlocks->expects($this->once())->method('getDefinitions')->with($storage, $entity);
    $manager = new PageTemplateManager(
      $this->createMock(EntityTypeManagerInterface::class),
      $this->createMock(ContextHandlerInterface::class),
      $this->createMock(UuidInterface::class),
      \Drupal::classResolver(),
    );

    $this->expectException(\InvalidArgumentException::class);
    $manager->buildSectionStorageFromPageConfig($storage, $entity, $this->createRestrictedPageConfig(), TRUE, ['0--carousel' => '0--carousel']);
  }

  /**
   * Creates page configuration containing a legacy carousel component.
   */
  private function createRestrictedPageConfig(): array {
    return [
      ['components' => ['carousel' => ['configuration' => ['id' => 'generic_link_carousel']]]],
    ];
  }

  /**
   * {@inheritdoc}
   */
  protected function tearDown(): void {
    (new FormState())->clearErrors();
    parent::tearDown();
  }

}
