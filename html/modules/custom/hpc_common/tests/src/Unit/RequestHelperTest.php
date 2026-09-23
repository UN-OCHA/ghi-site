<?php

namespace Drupal\Tests\hpc_common\Unit;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Form\FormState;
use Drupal\Core\Form\SubformState;
use Drupal\Core\Plugin\Context\Context;
use Drupal\Core\Plugin\Context\ContextDefinition;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\TypedData\DataDefinition;
use Drupal\Core\TypedData\TypedDataInterface;
use Drupal\Core\TypedData\TypedDataManagerInterface;
use Drupal\node\Entity\Node;
use Drupal\Tests\UnitTestCase;
use Drupal\hpc_common\Helpers\RequestHelper;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\HttpFoundation\ParameterBag;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Tests the request helper.
 */
#[CoversClass(RequestHelper::class)]
class RequestHelperTest extends UnitTestCase {

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

    // Set container.
    $container = new ContainerBuilder();
    \Drupal::setContainer($container);
  }

  /**
   * Data provider for getCurrentRouteArguments.
   */
  public static function getCurrentRouteArgumentsDataProvider() {
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
   */
  #[Group('RequestHelper')]
  #[DataProvider('getCurrentRouteArgumentsDataProvider')]
  public function testGetCurrentRouteArguments($result) {
    // Mock route method.
    $route = $this->prophesize(RouteMatchInterface::class);
    $route->getParameters()->willReturn(new ParameterBag($result));

    // Add to container.
    \Drupal::getContainer()->set('current_route_match', $route->reveal());
    $this->assertEquals($result, RequestHelper::getCurrentRouteArguments());
  }

  /**
   * Data provider for getQueryArgument.
   */
  public static function getQueryArgumentDataProvider() {
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
   */
  #[Group('RequestHelper')]
  #[DataProvider('getQueryArgumentDataProvider')]
  public function testGetQueryArgument($name, $arguments, $query_args, $result) {
    $request = new Request($query_args ?? []);

    $request_stack = $this->prophesize(RequestStack::class);
    $request_stack->getCurrentRequest()
      ->willReturn($request);

    // Add to container.
    \Drupal::getContainer()->set('request_stack', $request_stack->reveal());

    $this->assertEquals($result, RequestHelper::getQueryArgument($name, $arguments));
  }

  /**
   * Test flattenQuery method.
   */
  #[Group('RequestHelper')]
  public function testFlattenQuery() {
    $query = ['name' => 'test', 'page' => 1];
    $result = RequestHelper::flattenQuery($query);
    $this->assertSame('name=test&page=1', $result);
  }

  /**
   * Test flattenQuery with empty array.
   */
  #[Group('RequestHelper')]
  public function testFlattenQueryEmptyArray() {
    $result = RequestHelper::flattenQuery([]);
    $this->assertSame('', $result);
  }

  /**
   * Tests extracting plan contexts from Layout Builder forms and subforms.
   */
  #[DataProvider('formContextProvider')]
  public function testGetContextsFromFormState(bool $subform): void {
    $request_stack = new RequestStack();
    $request_stack->push(new Request());
    \Drupal::getContainer()->set('request_stack', $request_stack);
    \Drupal::getContainer()->set('string_translation', $this->getStringTranslationStub());
    $typed_data_manager = $this->createMock(TypedDataManagerInterface::class);
    $typed_data_manager->method('getDefaultConstraints')->willReturn([]);
    $typed_data_manager->method('createDataDefinition')->willReturnCallback(fn($type) => new DataDefinition(['type' => $type]));
    $typed_data_manager->method('create')->willReturnCallback(function ($definition, $value) {
      $data = $this->createMock(TypedDataInterface::class);
      $data->method('getValue')->willReturn($value);
      return $data;
    });
    $typed_data_manager->method('getCanonicalRepresentation')->willReturnCallback(fn($data) => $data->getValue());
    \Drupal::getContainer()->set('typed_data_manager', $typed_data_manager);
    $node = $this->createMock(Node::class);
    $node->method('get')->willReturnMap([
      ['field_plan_year', (object) ['value' => 2026]],
      ['field_original_id', (object) ['value' => 42]],
    ]);
    $form_state = new FormState();
    $form_state->setBuildInfo(['args' => ['layout_builder']]);
    $form_state->setTemporaryValue('gathered_contexts', [
      'layout_builder.entity' => new Context(new ContextDefinition('any'), $node),
    ]);
    if ($subform) {
      $form = ['#parents' => []];
      $complete_form = ['#parents' => []];
      $form_state = SubformState::createForSubform($form, $complete_form, $form_state);
    }

    $contexts = RequestHelper::getContextsFromFormState($form_state);
    $this->assertSame(2026, $contexts['year']->getContextValue());
    $this->assertSame(42, $contexts['plan_id']->getContextValue());
  }

  /**
   * Provides complete forms and Layout Builder subforms.
   */
  public static function formContextProvider(): array {
    return [[FALSE], [TRUE]];
  }

}
