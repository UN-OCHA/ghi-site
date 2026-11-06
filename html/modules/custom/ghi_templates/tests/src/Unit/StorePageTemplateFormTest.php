<?php

namespace Drupal\Tests\ghi_templates\Unit;

use Drupal\Core\Block\BlockManagerInterface;
use Drupal\Core\Controller\ControllerResolverInterface;
use Drupal\Core\DependencyInjection\ClassResolver;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormState;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\TempStore\SharedTempStore;
use Drupal\Core\TempStore\SharedTempStoreFactory;
use Drupal\Core\Url;
use Drupal\ghi_templates\Entity\PageTemplateInterface;
use Drupal\ghi_templates\LayoutBuilder\StorePageTemplateForm;
use Drupal\ghi_templates\PageTemplateManager;
use Drupal\layout_builder\LayoutTempstoreRepository;
use Drupal\layout_builder\Section;
use Drupal\layout_builder\SectionComponent;
use Drupal\layout_builder\SectionStorageInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Tests saving templates from pending Layout Builder changes.
 */
#[Group('ghi_templates')]
class StorePageTemplateFormTest extends UnitTestCase {

  /**
   * Tests the form supplies the pending snapshot before the initial save.
   */
  public function testPendingLayoutSnapshot(): void {
    $source = $this->createMock(EntityInterface::class);
    $source->expects($this->never())->method('save');
    $supplied_storage = $this->createMock(SectionStorageInterface::class);
    $supplied_storage->method('getStorageId')->willReturn('node.123');
    $supplied_storage->method('getStorageType')->willReturn('overrides');
    $supplied_storage->expects($this->never())->method('getSections');
    $supplied_storage->expects($this->never())->method('save');
    $sections = [
      new Section('layout_onecol', ['label' => 'Pending layout'], [
        new SectionComponent('pending-component', 'content', ['id' => 'uninstalled_legacy_block']),
      ]),
    ];
    $pending_storage = $this->createMock(SectionStorageInterface::class);
    $pending_storage->expects($this->once())->method('getSections')->willReturn($sections);
    $pending_storage->expects($this->never())->method('save');
    $tempstore = $this->createMock(SharedTempStore::class);
    $tempstore->expects($this->once())->method('get')->with('node.123')->willReturn(['section_storage' => $pending_storage]);
    $tempstore->expects($this->never())->method('set');
    $tempstore->expects($this->never())->method('delete');
    $tempstore_factory = $this->createMock(SharedTempStoreFactory::class);
    $tempstore_factory->expects($this->once())->method('get')->with('layout_builder.section_storage.overrides')->willReturn($tempstore);

    $snapshot_supplied = FALSE;
    $page_template = $this->createMock(PageTemplateInterface::class);
    $page_template->expects($this->once())->method('setSourceSections')->with($sections)->willReturnCallback(function () use (&$snapshot_supplied, $page_template) {
      $snapshot_supplied = TRUE;
      return $page_template;
    });
    $page_template->expects($this->once())->method('save')->willReturnCallback(function () use (&$snapshot_supplied) {
      $this->assertTrue($snapshot_supplied, 'The insert hook receives the snapshot during the first save.');
      return 1;
    });
    $page_template->method('label')->willReturn('Pending template');
    $url = $this->createMock(Url::class);
    $url->method('toString')->willReturn('https://example.org/template');
    $page_template->method('toUrl')->with('canonical')->willReturn($url);
    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->expects($this->once())->method('create')->with([
      'title' => 'Pending template',
      'field_entity_reference' => $source,
    ])->willReturn($page_template);
    $entity_type_manager = $this->createMock(EntityTypeManagerInterface::class);
    $entity_type_manager->method('getStorage')->with('page_template')->willReturn($storage);
    $messenger = $this->createMock(MessengerInterface::class);
    $messenger->expects($this->once())->method('addStatus');
    $container = new ContainerBuilder();
    $container->set('entity_type.manager', $entity_type_manager);
    $container->set('layout_builder.tempstore_repository', new LayoutTempstoreRepository($tempstore_factory));
    $container->set('controller_resolver', $this->createMock(ControllerResolverInterface::class));
    $container->set('ghi_templates.manager', $this->createMock(PageTemplateManager::class));
    $container->set('class_resolver', new ClassResolver($container));
    $container->set('plugin.manager.block', $this->createMock(BlockManagerInterface::class));
    \Drupal::setContainer($container);
    $form = StorePageTemplateForm::create($container);
    $form->setStringTranslation($this->getStringTranslationStub());
    $form->setMessenger($messenger);
    $form_state = new FormState();
    $form_state->set('entity', $source);
    $form_state->set('section_storage', $supplied_storage);
    $form_state->setValue('name', 'Pending template');
    $form_array = [];

    $form->submitForm($form_array, $form_state);
  }

}
