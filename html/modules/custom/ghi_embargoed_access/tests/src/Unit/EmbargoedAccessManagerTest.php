<?php

namespace Drupal\Tests\ghi_ambargoed_access\Unit;

use Drupal\Core\Access\CsrfTokenGenerator;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Routing\RedirectDestinationInterface;
use Drupal\entity_access_password\Service\RouteParserInterface;
use Drupal\ghi_content\Entity\ContentBase;
use Drupal\ghi_embargoed_access\EmbargoedAccessManager;
use Drupal\ghi_sections\Entity\SectionNodeInterface;
use Drupal\node\NodeInterface;
use Drupal\search_api\Plugin\search_api\datasource\ContentEntityTrackingManager;
use Drupal\Tests\UnitTestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Symfony\Component\HttpFoundation\ParameterBag;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * @coversDefaultClass \Drupal\ghi_embargoed_access\EmbargoedAccessManager
 * @group layout_builder
 *
 * @todo Check if this is a usable approach. It looks like this needs too much mocking.
 */
class EmbargoedAccessManagerTest extends UnitTestCase {

  use ProphecyTrait;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $entity_type_manager = $this->prophesize(EntityTypeManagerInterface::class);
    $entity_field_manager = $this->prophesize(EntityFieldManagerInterface::class);
    $config_factory = $this->prophesize(ConfigFactoryInterface::class);
    $search_api_tracking_manager = $this->prophesize(ContentEntityTrackingManager::class);
    $csrf_token = $this->prophesize(CsrfTokenGenerator::class);
    $redirect_destination = $this->prophesize(RedirectDestinationInterface::class);
    $route_parser = $this->prophesize(RouteParserInterface::class);

    $container = new ContainerBuilder();
    $container->set('entity_type.manager', $entity_type_manager->reveal());
    $container->set('entity_field.manager', $entity_field_manager->reveal());
    $container->set('config.factory', $config_factory->reveal());
    $container->set('search_api.entity_datasource.tracking_manager', $search_api_tracking_manager->reveal());
    $container->set('csrf_token', $csrf_token->reveal());
    $container->set('redirect.destination', $redirect_destination->reveal());
    $container->set('entity_access_password.route_parser', $route_parser->reveal());
    $container->set('entity_access_password.password_access_manager', NULL);
    \Drupal::setContainer($container);

    $this->mockRequest('/');
  }

  /**
   * Mock a request with the given path and node.
   *
   * @param string $path
   *   The path that the request should report.
   * @param \Drupal\node\NodeInterface|null $node
   *   An optional node object to set as a request attribute.
   */
  private function mockRequest($path = '/', ?NodeInterface $node = NULL) {
    $attributes = $this->prophesize(ParameterBag::class);
    if ($node) {
      $attributes->get('node')->willReturn($node);
    }
    $request = $this->prophesize(Request::class);
    $request->getPathInfo()->willReturn('/');
    $request = $request->reveal();
    $request->attributes = $attributes->reveal();
    $request_stack = $this->prophesize(RequestStack::class);
    $request_stack->getCurrentRequest()->willReturn($request);
    $container = \Drupal::getContainer();
    $container->set('request_stack', $request_stack->reveal());
    \Drupal::setContainer($container);
  }

  /**
   * Helpder function to set the embargoed status.
   *
   * @param bool $status
   *   The enabled state of the embargoed access.
   */
  private function setEmbargoedAccessStatus($status) {
    $container = \Drupal::getContainer();
    $config = $this->prophesize(ImmutableConfig::class);
    $config->get('enabled')->willReturn($status);
    $config_factory = $this->prophesize(ConfigFactoryInterface::class);
    $config_factory->get('ghi_embargoed_access.settings')->willReturn($config->reveal());
    $container->set('config.factory', $config_factory->reveal());
    \Drupal::setContainer($container);
  }

  /**
   * Tests the embargoedAccessEnabled method.
   */
  public function testEmbargoedAccessEnabled() {
    $this->setEmbargoedAccessStatus(FALSE);
    $embargoed_access_manager = EmbargoedAccessManager::create(\Drupal::getContainer());
    $this->assertFalse($embargoed_access_manager->embargoedAccessEnabled());

    $this->setEmbargoedAccessStatus(TRUE);
    $embargoed_access_manager = EmbargoedAccessManager::create(\Drupal::getContainer());
    $this->assertTrue($embargoed_access_manager->embargoedAccessEnabled());
  }

  /**
   * Tests the entityAccess method.
   */
  public function testEntityAccess() {
    // Create a node that cannot be protected.
    $node = $this->prophesize(ContentBase::class);
    $node->hasField(EmbargoedAccessManager::PROTECTED_FIELD)->willReturn(FALSE);

    // Create a section node that can be protected.
    $section = $this->prophesize(SectionNodeInterface::class);
    $section->hasField(EmbargoedAccessManager::PROTECTED_FIELD)->willReturn(TRUE);

    $this->setEmbargoedAccessStatus(TRUE);
    $embargoed_access_manager = EmbargoedAccessManager::create(\Drupal::getContainer());
    $this->assertTrue($embargoed_access_manager->entityAccess($node->reveal()));

    // Set the section to protected.
    $section->get(EmbargoedAccessManager::PROTECTED_FIELD)->willReturn((object) ['is_protected' => TRUE]);
    // Confirm that access is denied.
    $this->assertFalse($embargoed_access_manager->entityAccess($section->reveal()));

    // Now unprotect the section node and confirm that access is granted.
    $section->get(EmbargoedAccessManager::PROTECTED_FIELD)->willReturn((object) ['is_protected' => FALSE]);
    $this->assertTrue($embargoed_access_manager->entityAccess($section->reveal()));

    // Set the node to not be protectable and make it part of the section.
    $node->hasField(EmbargoedAccessManager::PROTECTED_FIELD)->willReturn(FALSE);
    $node->isPartOfSection($section->reveal())->willReturn(TRUE);
    $this->mockRequest('/', $section->reveal());
    $embargoed_access_manager = EmbargoedAccessManager::create(\Drupal::getContainer());
    // Confirm that acess is granted as the section is still  unprotected.
    $this->assertTrue($embargoed_access_manager->entityAccess($node->reveal()));

    // Protect the section again and confirm that access is not denied..
    $section->get(EmbargoedAccessManager::PROTECTED_FIELD)->willReturn((object) ['is_protected' => TRUE]);
    $this->assertFalse($embargoed_access_manager->entityAccess($section->reveal()));

    // Disable global embargoe and confirm that both the section and the node
    // can now be accessed.
    $this->setEmbargoedAccessStatus(FALSE);
    $embargoed_access_manager = EmbargoedAccessManager::create(\Drupal::getContainer());
    $this->assertTrue($embargoed_access_manager->entityAccess($section->reveal()));
    $this->assertTrue($embargoed_access_manager->entityAccess($node->reveal()));
  }

  /**
   * Tests the entityAccess method.
   */
  public function testSupportsProtections() {
    $embargoed_access_manager = EmbargoedAccessManager::create(\Drupal::getContainer());
    $node = $this->prophesize(ContentBase::class);
    $node->hasField(EmbargoedAccessManager::PROTECTED_FIELD)->willReturn(FALSE);
    $this->assertFalse($embargoed_access_manager->supportsProtections($node->reveal()));
    $node->hasField(EmbargoedAccessManager::PROTECTED_FIELD)->willReturn(TRUE);
    $this->assertTrue($embargoed_access_manager->supportsProtections($node->reveal()));
  }

}
