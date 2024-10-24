<?php

namespace Drupal\Tests\ghi_form_elements\Unit;

use Drupal\Core\Access\AccessManagerInterface;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Link;
use Drupal\Core\Session\AnonymousUserSession;
use Drupal\Core\Url;
use Drupal\Core\Utility\UnroutedUrlAssemblerInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\ghi_form_elements\Traits\CustomLinkTrait;
use Drupal\ghi_sections\Entity\SectionNodeInterface;
use Drupal\ghi_subpages\Entity\FinancialsSubpage;
use Drupal\ghi_subpages\Entity\PopulationSubpage;
use Drupal\ghi_subpages\SubpageManager;
use Drupal\node\NodeInterface;

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
    $section_node->toUrl()->willReturn($this->mockEntityUrl('/node/1')->reveal());
    $this->sectionNode = $section_node->reveal();

    $population_node = $this->prophesize(PopulationSubpage::class);
    $population_node->bundle()->willReturn('population');
    $population_node->id()->willReturn(2);
    $population_node->toUrl()->willReturn($this->mockEntityUrl('/node/2')->reveal());
    $this->populationNode = $population_node->reveal();

    $financials_node = $this->prophesize(FinancialsSubpage::class);
    $financials_node->bundle()->willReturn('financials');
    $financials_node->id()->willReturn(3);
    $financials_node->toUrl()->willReturn($this->mockEntityUrl('/node/3')->reveal());
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
    // $container->set('current_user', $this->mockCurrentUser());
    \Drupal::setContainer($container);
  }

  /**
   * Test getLinkFromUri for external links.
   */
  public function testGetLinkFromUriBasics() {
    /** @var \Drupal\ghi_form_elements\Traits\CustomLinkTrait $trait */
    $trait = $this->getObjectForTrait(CustomLinkTrait::class);

    $conf = [
      'add_link' => FALSE,
    ];
    $this->assertNull($trait->getLinkFromConfiguration($conf, []));

    $conf = [
      'add_link' => TRUE,
    ];
    $this->assertNull($trait->getLinkFromConfiguration($conf, []));

    $conf = [
      'add_link' => TRUE,
      'link_type' => 'custom',
    ];
    $this->assertNull($trait->getLinkFromConfiguration($conf, []));

  }

  /**
   * Test getLinkFromUri for external links.
   */
  public function testGetLinkFromUriExternal() {
    /** @var \Drupal\ghi_form_elements\Traits\CustomLinkTrait $trait */
    $trait = $this->getObjectForTrait(CustomLinkTrait::class);
    $conf = [
      'add_link' => TRUE,
      'link_type' => 'custom',
      'link_custom' => [
        'url' => 'https://google.com',
      ],
    ];
    $contexts = [];
    $link = $trait->getLinkFromConfiguration($conf, $contexts);
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
    $link = $trait->getLinkFromConfiguration($conf, $contexts);
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
    $link = $trait->getLinkFromConfiguration($conf, $contexts);
    $this->assertNull($link);
  }

  /**
   * Test getLinkFromUri for internal links.
   */
  public function testGetLinkFromUriInternal() {
    /** @var \Drupal\ghi_form_elements\Traits\CustomLinkTrait $trait */
    $trait = $this->getObjectForTrait(CustomLinkTrait::class);
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
    $link = $trait->getLinkFromConfiguration($conf, $contexts);
    $this->assertNull($link);

    $contexts = [
      'section_node' => $this->prophesize(NodeInterface::class)->reveal(),
      'page_node' => NULL,
    ];
    $link = $trait->getLinkFromConfiguration($conf, $contexts);
    $this->assertNull($link);

    $contexts = [
      'section_node' => $this->sectionNode,
      'page_node' => $this->prophesize(NodeInterface::class)->reveal(),
    ];
    $link = $trait->getLinkFromConfiguration($conf, $contexts);
    $this->assertInstanceOf(Link::class, $link);

    $contexts = [
      'section_node' => $this->sectionNode,
      'page_node' => $this->populationNode,
    ];
    $link = $trait->getLinkFromConfiguration($conf, $contexts);
    $this->assertInstanceOf(Link::class, $link);
  }

  /**
   * Test getLinkTargetOptions.
   */
  public function testGetLinkTargetOptions() {
    /** @var \Drupal\ghi_form_elements\Traits\CustomLinkTrait $trait */
    $trait = $this->getObjectForTrait(CustomLinkTrait::class);

    $target_options = $trait->getLinkTargetOptions($this->sectionNode, $this->financialsNode);
    $expected_options = [
      'Internal pages' => [
        'section' => 'Section page (/node/1)',
        'population' => 'Population page (/node/2)',
      ],
      'External pages' => [],
    ];
    $this->assertEquals($expected_options, $target_options);

    $target_options = $trait->getLinkTargetOptions($this->sectionNode, $this->populationNode);
    $expected_options = [
      'Internal pages' => [
        'section' => 'Section page (/node/1)',
        'financials' => 'Financials page (/node/3)',
      ],
      'External pages' => [],
    ];
    $this->assertEquals($expected_options, $target_options);

    $target_options = $trait->getLinkTargetOptions($this->sectionNode, $this->sectionNode);
    $expected_options = [
      'Internal pages' => [
        'population' => 'Population page (/node/2)',
        'financials' => 'Financials page (/node/3)',
      ],
      'External pages' => [],
    ];
    $this->assertEquals($expected_options, $target_options);

    $target_options = $trait->getLinkTargetOptions($this->sectionNode, $this->otherNode);
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
    /** @var \Drupal\ghi_form_elements\Traits\CustomLinkTrait $trait */
    $trait = $this->getObjectForTrait(CustomLinkTrait::class);
    $target_urls = $trait->getLinkTargetUrls($this->sectionNode, $this->populationNode);
    $expected_urls = [
      'section' => $this->sectionNode->toUrl(),
      'financials' => $this->financialsNode->toUrl(),
    ];
    $this->assertEquals($expected_urls, $target_urls);

    $target_urls = $trait->getLinkTargetUrls($this->sectionNode, $this->financialsNode);
    $expected_urls = [
      'section' => $this->sectionNode->toUrl(),
      'population' => $this->populationNode->toUrl(),
    ];
    $this->assertEquals($expected_urls, $target_urls);

    $target_urls = $trait->getLinkTargetUrls($this->sectionNode, $this->sectionNode);
    $expected_urls = [
      'population' => $this->populationNode->toUrl(),
      'financials' => $this->financialsNode->toUrl(),
    ];
    $this->assertEquals($expected_urls, $target_urls);

    $target_urls = $trait->getLinkTargetUrls($this->sectionNode, $this->otherNode);
    $expected_urls = [
      'section' => $this->sectionNode->toUrl(),
      'population' => $this->populationNode->toUrl(),
      'financials' => $this->financialsNode->toUrl(),
    ];
    $this->assertEquals($expected_urls, $target_urls);
  }

  /**
   * Mock an entity url.
   *
   * @param string $path
   *   The url string for the url object.
   *
   * @return \Drupal\Core\Url
   *   A url object.
   */
  private function mockEntityUrl($path) {
    $url = $this->prophesize(Url::class);
    $url->toString()->willReturn($path);
    $url->toUriString()->willReturn('entity:' . ltrim($path, '/'));
    $url->access()->willReturn(TRUE);
    $url->access(new AnonymousUserSession())->willReturn(TRUE);
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

}
