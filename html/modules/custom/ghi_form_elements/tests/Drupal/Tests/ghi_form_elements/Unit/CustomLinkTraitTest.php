<?php

namespace Drupal\Tests\ghi_form_elements\Unit;

use Drupal\Core\Access\AccessManagerInterface;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Link;
use Drupal\Core\Path\PathValidatorInterface;
use Drupal\Core\Session\AnonymousUserSession;
use Drupal\Core\Url;
use Drupal\Core\Utility\UnroutedUrlAssemblerInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\Tests\ghi_form_elements\CustomLinkTestClass;
use Drupal\ghi_sections\Entity\SectionNodeInterface;
use Drupal\ghi_subpages\Entity\FinancialsSubpage;
use Drupal\ghi_subpages\Entity\PopulationSubpage;
use Drupal\ghi_subpages\SubpageManager;
use Drupal\node\NodeInterface;
use Drupal\path_alias\AliasManagerInterface;
use Prophecy\Argument;

/**
 * @covers Drupal\ghi_form_elements\Traits\CustomLinkTrait
 */
class CustomLinkTraitTest extends UnitTestCase {

  /**
   * A section node.
   *
   * @var \Drupal\ghi_sections\Entity\SectionNodeInterface
   */
  protected $sectionNode;

  /**
   * A population node.
   *
   * @var \Drupal\ghi_subpages\Entity\PopulationSubpage
   */
  protected $populationNode;

  /**
   * A financials node.
   *
   * @var \Drupal\ghi_subpages\Entity\FinancialsSubpage
   */
  protected $financialsNode;

  /**
   * A default node.
   *
   * @var \Drupal\node\NodeInterface
   */
  protected $otherNode;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $section_node = $this->prophesize(SectionNodeInterface::class);
    $section_node->bundle()->willReturn('section');
    $section_node->id()->willReturn(1);
    $section_node->getBaseObject()->willReturn(NULL);
    $section_node->toUrl()->willReturn($this->mockEntityUrl($section_node->reveal())->reveal());
    $this->sectionNode = $section_node->reveal();

    $population_node = $this->prophesize(PopulationSubpage::class);
    $population_node->bundle()->willReturn('population');
    $population_node->id()->willReturn(2);
    $population_node->toUrl()->willReturn($this->mockEntityUrl($population_node->reveal())->reveal());
    $this->populationNode = $population_node->reveal();

    $financials_node = $this->prophesize(FinancialsSubpage::class);
    $financials_node->bundle()->willReturn('financials');
    $financials_node->id()->willReturn(3);
    $financials_node->toUrl()->willReturn($this->mockEntityUrl($financials_node->reveal())->reveal());
    $this->financialsNode = $financials_node->reveal();

    $other_node = $this->prophesize(NodeInterface::class);
    $other_node->bundle()->willReturn('other_node');
    $other_node->id()->willReturn(10);
    $this->otherNode = $other_node->reveal();

    $container = new ContainerBuilder();
    $container->set('string_translation', $this->getStringTranslationStub());
    $container->set('unrouted_url_assembler', $this->mockUrlAssembler());
    $container->set('ghi_subpages.manager', $this->mockSubpagesManager());
    $container->set('access_manager', $this->mockAccessManager());
    $container->set('path_alias.manager', $this->mockPathAliasManager());
    $container->set('path.validator', $this->mockPathValidator());
    \Drupal::setContainer($container);
  }

  /**
   * Test getLinkFromConfiguration for external links.
   */
  public function testGetLinkFromUriBasics() {
    $class = new CustomLinkTestClass();

    $conf = [
      'add_link' => FALSE,
    ];
    $this->assertNull($class->getLinkFromConfiguration($conf, []));

    $conf = [
      'add_link' => TRUE,
    ];
    $this->assertNull($class->getLinkFromConfiguration($conf, []));

    $conf = [
      'add_link' => TRUE,
      'link_type' => 'custom',
    ];
    $this->assertNull($class->getLinkFromConfiguration($conf, []));

  }

  /**
   * Test getLinkFromConfiguration for external links.
   */
  public function testGetLinkFromUriExternal() {
    $class = new CustomLinkTestClass();
    $conf = [
      'add_link' => TRUE,
      'link_type' => 'custom',
      'link_custom' => [
        'url' => 'https://google.com',
      ],
    ];
    $contexts = [];
    $link = $class->getLinkFromConfiguration($conf, $contexts);
    $this->assertInstanceOf(Link::class, $link);
    $this->assertEquals('https://google.com', $link->getUrl()->toString());
    $this->assertEquals('Open', $link->getText());

    $conf = [
      'add_link' => TRUE,
      'label' => 'Open this link',
      'link_type' => 'custom',
      'link_custom' => [
        'url' => 'https://google.com',
      ],
    ];
    $link = $class->getLinkFromConfiguration($conf, $contexts);
    $this->assertInstanceOf(Link::class, $link);
    $this->assertEquals('https://google.com', $link->getUrl()->toString());
    $this->assertEquals('Open this link', $link->getText());

    $conf = [
      'add_link' => TRUE,
      'link_type' => 'custom',
      'link_custom' => [
        'url' => '/admin/reports',
      ],
    ];
    $link = $class->getLinkFromConfiguration($conf, $contexts);
    $this->assertNull($link);
  }

  /**
   * Test getLinkFromConfiguration for internal links.
   */
  public function testGetLinkFromUriInternal() {
    $class = new CustomLinkTestClass();
    $contexts = [];
    $conf = [
      'add_link' => TRUE,
      'link_type' => 'custom',
      'link_custom' => [
        'url' => 'internal:/node/' . $this->financialsNode->id(),
      ],
    ];
    $link = $class->getLinkFromConfiguration($conf, $contexts);
    $this->assertInstanceOf(Link::class, $link);
    $this->assertEquals('/node/' . $this->financialsNode->id(), $link->getUrl()->getOption('custom_path'));
    $this->assertEquals('Go to page', $link->getText());
  }

  /**
   * Test getLinkFromConfiguration for related target links.
   */
  public function testGetLinkFromRelatedTarget() {
    $class = new CustomLinkTestClass();
    $conf = [
      'add_link' => TRUE,
      'link_type' => 'related',
      'link_related' => [
        'target' => 'population',
      ],
    ];
    $contexts = [
      'section_node' => NULL,
      'page_node' => $this->prophesize(NodeInterface::class)->reveal(),
    ];
    $link = $class->getLinkFromConfiguration($conf, $contexts);
    $this->assertNull($link);

    $contexts = [
      'section_node' => $this->prophesize(NodeInterface::class)->reveal(),
      'page_node' => NULL,
    ];
    $link = $class->getLinkFromConfiguration($conf, $contexts);
    $this->assertNull($link);

    $contexts = [
      'section_node' => $this->sectionNode,
      'page_node' => $this->prophesize(NodeInterface::class)->reveal(),
    ];
    $link = $class->getLinkFromConfiguration($conf, $contexts);
    $this->assertInstanceOf(Link::class, $link);

    $contexts = [
      'section_node' => $this->sectionNode,
      'page_node' => $this->populationNode,
    ];
    $link = $class->getLinkFromConfiguration($conf, $contexts);
    $this->assertNull($link);

    $contexts = [
      'section_node' => $this->sectionNode,
      'page_node' => $this->financialsNode,
    ];
    $link = $class->getLinkFromConfiguration($conf, $contexts);
    $this->assertInstanceOf(Link::class, $link);
  }

  /**
   * Test getLinkTargetOptions.
   */
  public function testGetLinkTargetOptions() {
    $class = new CustomLinkTestClass();

    $target_options = $class->getLinkTargetOptions($this->sectionNode, $this->financialsNode);
    $expected_options = [
      'Internal pages' => [
        'section' => 'Section page (/node/1)',
        'population' => 'Population page (/node/2)',
      ],
      'External pages' => [],
    ];
    $this->assertEquals($expected_options, $target_options);

    $target_options = $class->getLinkTargetOptions($this->sectionNode, $this->populationNode);
    $expected_options = [
      'Internal pages' => [
        'section' => 'Section page (/node/1)',
        'financials' => 'Financials page (/node/3)',
      ],
      'External pages' => [],
    ];
    $this->assertEquals($expected_options, $target_options);

    $target_options = $class->getLinkTargetOptions($this->sectionNode, $this->sectionNode);
    $expected_options = [
      'Internal pages' => [
        'population' => 'Population page (/node/2)',
        'financials' => 'Financials page (/node/3)',
      ],
      'External pages' => [],
    ];
    $this->assertEquals($expected_options, $target_options);

    $target_options = $class->getLinkTargetOptions($this->sectionNode, $this->otherNode);
    $expected_options = [
      'Internal pages' => [
        'section' => 'Section page (/node/1)',
        'population' => 'Population page (/node/2)',
        'financials' => 'Financials page (/node/3)',
      ],
      'External pages' => [],
    ];
    $this->assertEquals($expected_options, $target_options);
  }

  /**
   * Test getLinkTargetOptions.
   */
  public function testGetLinkTargetUrls() {
    $class = new CustomLinkTestClass();
    $target_urls = $class->getLinkTargetUrls($this->sectionNode, $this->populationNode);
    $expected_urls = [
      'section' => $this->sectionNode->toUrl(),
      'financials' => $this->financialsNode->toUrl(),
    ];
    $this->assertEquals($expected_urls, $target_urls);

    $target_urls = $class->getLinkTargetUrls($this->sectionNode, $this->financialsNode);
    $expected_urls = [
      'section' => $this->sectionNode->toUrl(),
      'population' => $this->populationNode->toUrl(),
    ];
    $this->assertEquals($expected_urls, $target_urls);

    $target_urls = $class->getLinkTargetUrls($this->sectionNode, $this->sectionNode);
    $expected_urls = [
      'population' => $this->populationNode->toUrl(),
      'financials' => $this->financialsNode->toUrl(),
    ];
    $this->assertEquals($expected_urls, $target_urls);

    $target_urls = $class->getLinkTargetUrls($this->sectionNode, $this->otherNode);
    $expected_urls = [
      'section' => $this->sectionNode->toUrl(),
      'population' => $this->populationNode->toUrl(),
      'financials' => $this->financialsNode->toUrl(),
    ];
    $this->assertEquals($expected_urls, $target_urls);
  }

  /**
   * Test getUriAsDisplayableString.
   */
  public function testGetUriAsDisplayableString() {
    $class = new CustomLinkTestClass();
    // Make the protected method callable.
    $method = (new \ReflectionClass(CustomLinkTestClass::class))->getMethod('getUriAsDisplayableString');

    $this->assertEquals('/node/1', $method->invokeArgs($class, ['internal:/node/1']));
    $this->assertEquals('<front>', $method->invokeArgs($class, ['internal:/']));
    $this->assertEquals('<no-route>', $method->invokeArgs($class, ['route:<no-route>']));
  }

  /**
   * Test transformUrl.
   */
  public function testTransformUrl() {
    $class = new CustomLinkTestClass();
    // Make the protected method callable.
    $method = (new \ReflectionClass(CustomLinkTestClass::class))->getMethod('transformUrl');

    $this->assertEquals(FALSE, $method->invokeArgs($class, [NULL]));
    $this->assertEquals(FALSE, $method->invokeArgs($class, [NULL, NULL]));
    $this->assertEquals(FALSE, $method->invokeArgs($class, [NULL, FALSE]));
    $this->assertEquals('https://google.com', $method->invokeArgs($class, ['https://google.com', 'http://localhost']));
    $this->assertEquals('google.com', $method->invokeArgs($class, ['google.com', 'http://localhost']));
    $this->assertEquals(FALSE, $method->invokeArgs($class, ['/', 'http://localhost']));
    $this->assertEquals(FALSE, $method->invokeArgs($class, ['http://localhost/', 'http://localhost']));
    $this->assertEquals('http://localhost', $method->invokeArgs($class, ['http://localhost', 'http://localhost']));
    $this->assertEquals('', $method->invokeArgs($class, ['', 'http://localhost']));
    $this->assertEquals(FALSE, $method->invokeArgs($class, ['http://localhost/#test', 'http://localhost']));
  }

  /**
   * Mock an entity url.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity object for which to mock the url.
   *
   * @return \Drupal\Core\Url
   *   A url object.
   */
  private function mockEntityUrl(EntityInterface $entity) {
    $path = '/node/' . $entity->id();
    $url = $this->prophesize(Url::class);
    $url->toString()->willReturn($path);
    // $url->toUriString()->willReturn('internal:' . ltrim($path, '/'));
    $url->toUriString()->willReturn('route:entity.node.canonical;node=' . $entity->id());
    $url->isRouted()->willReturn(TRUE);
    $url->getRouteParameters()->willReturn(['node' => $entity]);
    $url->access()->willReturn(TRUE);
    $url->access(new AnonymousUserSession())->willReturn(TRUE);
    $url->isExternal()->willReturn(FALSE);
    $url->getOption('attributes')->willReturn([]);
    $url->setOption('attributes', ["class" => ["cd-button", "read-more"]])->willReturn();
    $url->setOption('custom_path', ltrim($path, '/'))->willReturn();
    $url->getOption('custom_path')->willReturn(ltrim($path, '/'));
    return $url;
  }

  /**
   * Mock the url assembler.
   *
   * @return \Drupal\Core\Utility\UnroutedUrlAssemblerInterface
   *   A url assembler object.
   */
  private function mockUrlAssembler() {
    $url_assembler = $this->createMock(UnroutedUrlAssemblerInterface::class);
    $url_assembler->expects($this->any())
      ->method('assemble')
      ->willReturnArgument(0);
    return $url_assembler;
  }

  /**
   * Mock the subpages manager.
   *
   * @return \Drupal\ghi_subpages\SubpageManager
   *   A subpage manager object.
   */
  private function mockSubpagesManager() {
    $subpage_manager = $this->prophesize(SubpageManager::class);
    $subpage_manager->loadSubpagesForBaseNode($this->sectionNode)->willReturn([
      $this->populationNode,
      $this->financialsNode,
    ]);
    return $subpage_manager->reveal();
  }

  /**
   * Mock the access manager.
   *
   * @return \Drupal\Core\Access\AccessManagerInterface
   *   An access manager object.
   */
  private function mockAccessManager() {
    $access_manager = $this->prophesize(AccessManagerInterface::class);
    $access_manager->checkNamedRoute($this->any())->willReturn(TRUE);
    return $access_manager->reveal();
  }

  /**
   * Mock the path alias manager.
   *
   * @return \Drupal\path_alias\AliasManagerInterface
   *   A path alias manager object.
   */
  private function mockPathAliasManager() {
    $path_alias_manager = $this->prophesize(AliasManagerInterface::class);
    $path_alias_manager->getPathByAlias(Argument::any())->willReturnArgument(0);
    return $path_alias_manager->reveal();
  }

  /**
   * Mock the path validator.
   *
   * @return \Drupal\Core\Path\PathValidatorInterface
   *   A path validator object.
   */
  private function mockPathValidator() {
    $path_validator = $this->prophesize(PathValidatorInterface::class);
    return $path_validator->reveal();
  }

}
