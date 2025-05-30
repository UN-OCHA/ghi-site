<?php

namespace Drupal\Tests\ghi_blocks\Unit;

use Drupal\Tests\UnitTestCase;
use Drupal\ghi_base_objects\Entity\BaseObjectInterface;
use Drupal\ghi_blocks\Controller\BaseObjectReferenceController;
use Drupal\ghi_blocks\Plugin\Block\GHIBlockBase;
use Drupal\layout_builder\Section;
use Drupal\layout_builder\SectionComponent;
use Drupal\layout_builder\SectionStorage\SectionStorageManagerInterface;
use Drupal\layout_builder\SectionStorageInterface;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;

/**
 * @coversDefaultClass \Drupal\layout_builder\SectionComponent
 * @group layout_builder
 */
class BaseObjectReferenceControllerTest extends UnitTestCase {

  use ProphecyTrait;

  /**
   * Tests that base object components can be retrieved from sections.
   */
  public function testExtractBaseObjectComponentsFromSection() {
    $base_object = $this->prophesize(BaseObjectInterface::class);
    $base_object->id()->willReturn(1);
    $base_object->getUniqueIdentifier()->willReturn('plan--1001');

    $block_1 = $this->prophesize(GHIBlockBase::class);
    $block_1->getPluginId()->willReturn('block_plugin_id');
    $block_1->getContextMapping()->willReturn([
      'plan' => 'plan--1001',
    ]);
    $block_1->getSelectedDataObjectId()->willReturn(1);

    $block_2 = $this->prophesize(GHIBlockBase::class);
    $block_2->getPluginId()->willReturn('block_plugin_id');
    $block_2->getContextMapping()->willReturn([
      'plan' => 'plan',
    ]);
    $block_2->getSelectedDataObjectId()->willReturn(NULL);

    $component_1 = $this->prophesize(SectionComponent::class);
    $component_1->getPlugin()->willReturn($block_1->reveal());
    $component_1->getWeight()->willReturn(1);
    $component_2 = $this->prophesize(SectionComponent::class);
    $component_2->getPlugin()->willReturn($block_2->reveal());
    $component_2->getWeight()->willReturn(2);
    $components = [$component_1->reveal(), $component_2->reveal()];

    $section = $this->prophesize(Section::class);
    $section->getComponents()->willReturn($components);

    $sections = [
      $section->reveal(),
    ];
    $section_storage = $this->prophesize(SectionStorageInterface::class);
    $section_storage->getSections()->willReturn($sections);
    $section_storage->count()->willReturn(1);

    $section_storage_manager = $this->prophesize(SectionStorageManagerInterface::class);
    $section_storage_manager->load('')->willReturn(NULL);
    $section_storage_manager->findByContext(Argument::cetera())->willReturn($section_storage->reveal());

    $controller = new BaseObjectReferenceController();
    $this->assertSame([$component_1->reveal()], $controller->extractBaseObjectComponentsFromSection($sections, $base_object->reveal()));

  }

  /**
   * Tests that orphaned base object components can be retrieved.
   */
  public function testExtractOrphanedBaseObjectComponentsFromSections() {
    $base_object = $this->prophesize(BaseObjectInterface::class);
    $base_object->getUniqueIdentifier()->willReturn('plan--1001');

    $block_1 = $this->prophesize(GHIBlockBase::class);
    $block_1->getPluginId()->willReturn('block_plugin_id');
    $block_1->getContextMapping()->willReturn([
      'plan' => 'plan--1001',
    ]);

    $block_2 = $this->prophesize(GHIBlockBase::class);
    $block_2->getPluginId()->willReturn('block_plugin_id');
    $block_2->getContextMapping()->willReturn([
      'plan' => 'plan--2002',
    ]);

    $component_1 = $this->prophesize(SectionComponent::class);
    $component_1->getPlugin()->willReturn($block_1->reveal());
    $component_2 = $this->prophesize(SectionComponent::class);
    $component_2->getPlugin()->willReturn($block_2->reveal());
    $components = [$component_1->reveal(), $component_2->reveal()];

    $section = $this->prophesize(Section::class);
    $section->getComponents()->willReturn($components);

    $sections = [
      $section->reveal(),
    ];
    $section_storage = $this->prophesize(SectionStorageInterface::class);
    $section_storage->getSections()->willReturn($sections);
    $section_storage->count()->willReturn(1);

    $section_storage_manager = $this->prophesize(SectionStorageManagerInterface::class);
    $section_storage_manager->load('')->willReturn(NULL);
    $section_storage_manager->findByContext(Argument::cetera())->willReturn($section_storage->reveal());

    $controller = new BaseObjectReferenceController();
    $this->assertSame([0 => [$component_2->reveal()]], $controller->extractOrphanedBaseObjectComponentsFromSections($sections, [$base_object->reveal()]));
  }

}
