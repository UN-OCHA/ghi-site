<?php

namespace Drupal\Tests\hpc_common\Unit;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\hpc_common\Helpers\RequestHelper;
use Drupal\Tests\UnitTestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Symfony\Component\HttpFoundation\ParameterBag;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * @covers Drupal\hpc_common\Helpers\RequestHelper
 */
class RequestHelperTest extends UnitTestCase {

  use ProphecyTrait;

  /**
   * The request helper class.
   *
   * @var \Drupal\hpc_common\Helpers\RequestHelper
   */
  protected $requestHelper;

  /**
   * A route match object.
   *
   * @var \Drupal\Core\Routing\RouteMatchInterface
   */
  protected $route;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Mock route.
    $this->route = $this->prophesize(RouteMatchInterface::class);

    // Set container.
    $container = new ContainerBuilder();
    \Drupal::setContainer($container);

    $this->requestHelper = new RequestHelper();
  }

  /**
   * {@inheritdoc}
   */
  protected function tearDown(): void {
    parent::tearDown();
    unset($this->requestHelper);
    unset($this->route);

    $container = new ContainerBuilder();
    \Drupal::setContainer($container);
  }

  /**
   * Data provider for getCurrentRouteArguments.
   */
  public function getCurrentRouteArgumentsDataProvider() {
    return [
      [
        [
          'name' => 'Virat Kohli',
          'sport' => 'Cricket',
        ],
      ],
      [
        [
          'name' => 'Roger Federer',
          'sport' => 'Tennis',
          'country' => 'Switzerland',
        ],
      ],
    ];
  }

  /**
   * Test getting the current route arguments.
   *
   * @group RequestHelper
   * @dataProvider getCurrentRouteArgumentsDataProvider
   */
  public function testGetCurrentRouteArguments($result) {
    // Mock route method.
    $this->route->getParameters()->willReturn(new ParameterBag($result));

    // Add to container.
    \Drupal::getContainer()->set('current_route_match', $this->route->reveal());

    $this->assertEquals($result, $this->requestHelper->getCurrentRouteArguments());
  }

  /**
   * Data provider for getQueryArgument.
   */
  public function getQueryArgumentDataProvider() {
    return [
      [
        'country',
        [
          'name' => 'Tony Kroos',
          'country' => 'Germany',
        ],
        [],
        'Germany',
      ],
      [
        'name',
        NULL,
        [
          'name' => 'Tony Kroos',
          'country' => 'Germany',
        ],
        'Tony Kroos',
      ],
      [
        'fail',
        NULL,
        [
          'name' => 'Tony Kroos',
          'country' => 'Germany',
        ],
        NULL,
      ],
      [
        'none',
        [
          'name' => 'Tony Kroos',
          'country' => 'Germany',
        ],
        NULL,
        NULL,
      ],
    ];
  }

  /**
   * Test getting query arguments.
   *
   * @group RequestHelper
   * @dataProvider getQueryArgumentDataProvider
   */
  public function testGetQueryArgument($name, $arguments, $query_args, $result) {
    $request = new Request();

    if (!empty($query_args)) {
      $request->query = new ParameterBag($query_args);
    }

    $request_stack = $this->prophesize(RequestStack::class);
    $request_stack->getCurrentRequest()
      ->willReturn($request);

    // Add to container.
    \Drupal::getContainer()->set('request_stack', $request_stack->reveal());

    $this->assertEquals($result, $this->requestHelper->getQueryArgument($name, $arguments));
  }

}
