<?php

namespace Drupal\Tests\ghi_sections\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\ghi_sections\Traits\SectionTestTrait;
use Drupal\Tests\user\Traits\UserCreationTrait;

/**
 * Base class for section menu tests.
 *
 * @group ghi_sections
 */
abstract class SectionMenuTestBase extends KernelTestBase {

  use UserCreationTrait;
  use SectionTestTrait;

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = [
    'system',
    'user',
    'node',
    'field',
    'text',
    'filter',
    'token',
    'path',
    'path_alias',
    'pathauto',
    'layout_builder',
    'layout_discovery',
    'ghi_sections',
  ];

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The section menu item plugin manager.
   *
   * @var \Drupal\ghi_sections\Menu\SectionMenuPluginManager
   */
  protected $sectionMenuPluginManager;

  /**
   * The section menu storage.
   *
   * @var \Drupal\ghi_sections\Menu\SectionMenuStorage
   */
  protected $sectionMenuStorage;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installEntitySchema('taxonomy_term');
    $this->installEntitySchema('base_object');
    $this->installEntitySchema('path_alias');
    $this->installSchema('system', 'sequences');
    $this->installSchema('node', ['node_access']);
    $this->installConfig(['system', 'node', 'field']);

    $this->entityTypeManager = $this->container->get('entity_type.manager');
    $this->sectionMenuPluginManager = $this->container->get('plugin.manager.section_menu');
    $this->sectionMenuStorage = $this->container->get('ghi_sections.section_menu.storage');

    $this->createSectionType();

    $this->setUpCurrentUser(['uid' => 1]);
  }

}
