<?php

namespace Drupal\Tests\ghi_embargoed_access\Unit;

use Drupal\Core\Config\Config;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Routing\RedirectDestinationInterface;
use Drupal\Core\Url;
use Drupal\search_api\Plugin\search_api\datasource\ContentEntityTrackingManager;
use Drupal\Core\Access\CsrfTokenGenerator;
use Drupal\Tests\UnitTestCase;
use Drupal\Core\DependencyInjection\Container;
use Drupal\entity_access_password\Service\PasswordAccessManagerInterface;
use Drupal\entity_access_password\Service\RouteParserInterface;
use Drupal\ghi_embargoed_access\EmbargoedAccessManager;
use Drupal\node\NodeInterface;
use Drupal\ghi_subpages\Entity\SubpageNodeInterface;
use Drupal\node\NodeForm;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\ParameterBag;

/**
 * Unit tests for the EmbargoedAccessManager alter methods.
 *
 * @coversDefaultClass \Drupal\ghi_embargoed_access\EmbargoedAccessManager
 * @group ghi_embargoed_access
 */
class EmbargoedAccessManagerAlterTest extends UnitTestCase {

  /**
   * The mocked config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $configFactory;

  /**
   * The embargoed access manager under test.
   *
   * @var \Drupal\ghi_embargoed_access\EmbargoedAccessManager
   */
  protected $embargoedAccessManager;

  /**
   * The mocked password access manager.
   *
   * @var \Drupal\entity_access_password\Service\PasswordAccessManagerInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $passwordAccessManager;

  /**
   * The mocked entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $entityTypeManager;

  /**
   * Mock container.
   *
   * @var \Drupal\Core\DependencyInjection\Container
   */
  protected $container;

  /**
   * Mock request object.
   *
   * @var \Symfony\Component\HttpFoundation\Request|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $request;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Set up a mock container to handle static calls.
    $this->container = new Container();
    $cache_tags_invalidator = $this->createMock('Drupal\Core\Cache\CacheTagsInvalidatorInterface');
    $this->container->set('cache_tags.invalidator', $cache_tags_invalidator);

    // Mock request for static calls.
    $this->request = $this->createMock(Request::class);
    $this->request->method('getPathInfo')->willReturn('/test/path');
    $this->request->attributes = new ParameterBag();

    $requestStack = $this->createMock('Symfony\Component\HttpFoundation\RequestStack');
    $requestStack->method('getCurrentRequest')->willReturn($this->request);
    $this->container->set('request_stack', $requestStack);

    // Add string translation service for tests that use $this->t().
    $string_translation = $this->createMock('Drupal\Core\StringTranslation\TranslationInterface');
    $string_translation->method('translate')->willReturnArgument(0);
    $this->container->set('string_translation', $string_translation);

    // Add cache contexts manager for addCacheContextsToThemeVariables method.
    $cache_contexts_manager = $this->createMock('Drupal\Core\Cache\Context\CacheContextsManager');
    $cache_contexts_manager->method('assertValidTokens')->willReturn(TRUE);
    $this->container->set('cache_contexts_manager', $cache_contexts_manager);

    \Drupal::setContainer($this->container);

    $this->entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $entityFieldManager = $this->createMock(EntityFieldManagerInterface::class);
    $this->configFactory = $this->createMock(ConfigFactoryInterface::class);
    $searchApiTrackingManager = $this->createMock(ContentEntityTrackingManager::class);
    $csrfToken = $this->createMock(CsrfTokenGenerator::class);
    $redirectDestination = $this->createMock(RedirectDestinationInterface::class);
    $routeParser = $this->createMock(RouteParserInterface::class);
    $this->passwordAccessManager = $this->createMock(PasswordAccessManagerInterface::class);

    $this->embargoedAccessManager = new EmbargoedAccessManager(
      $this->entityTypeManager,
      $entityFieldManager,
      $this->configFactory,
      $searchApiTrackingManager,
      $csrfToken,
      $redirectDestination,
      $routeParser,
      $this->passwordAccessManager
    );
  }

  /**
   * Setup embargo as enabled in config.
   */
  protected function setupEmbargoEnabled() {
    $config = $this->createMock(Config::class);
    $config->expects($this->any())
      ->method('get')
      ->with('enabled')
      ->willReturn(TRUE);

    $this->configFactory->expects($this->any())
      ->method('get')
      ->with('ghi_embargoed_access.settings')
      ->willReturn($config);
  }

  /**
   * Setup embargo as disabled in config.
   */
  protected function setupEmbargoDisabled() {
    $config = $this->createMock(Config::class);
    $config->expects($this->any())
      ->method('get')
      ->with('enabled')
      ->willReturn(FALSE);

    $this->configFactory->expects($this->any())
      ->method('get')
      ->with('ghi_embargoed_access.settings')
      ->willReturn($config);
  }

  /**
   * Tests alterViewMode when embargo is disabled.
   *
   * @covers ::alterViewMode
   */
  public function testAlterViewModeEmbargoDisabled(): void {
    $this->setupEmbargoDisabled();

    $entity = $this->createMock(EntityInterface::class);
    $entity->expects($this->any())
      ->method('getCacheContexts')
      ->willReturn(['entity_access_password_entity_is_protected:node||1||full']);

    $view_mode = PasswordAccessManagerInterface::PROTECTED_VIEW_MODE;

    $this->embargoedAccessManager->alterViewMode($view_mode, $entity);

    $this->assertEquals('full', $view_mode);
  }

  /**
   * Tests alterViewMode with subpage that has protected parent.
   *
   * @covers ::alterViewMode
   */
  public function testAlterViewModeSubpageWithParent(): void {
    $this->setupEmbargoEnabled();

    $parent = $this->createMock(NodeInterface::class);
    $parent->method('getEntityTypeId')->willReturn('node');
    $parent->method('id')->willReturn(123);

    $entity = $this->createMock(SubpageNodeInterface::class);
    $entity->method('getParentBaseNode')->willReturn($parent);
    $entity->expects($this->once())
      ->method('addCacheContexts')
      ->with(['entity_access_password_entity_is_protected:node||123||full']);

    $this->passwordAccessManager->method('isEntityViewModeProtected')
      ->with('full', $parent)
      ->willReturn(TRUE);
    $this->passwordAccessManager->method('hasUserAccessToEntity')
      ->with($parent)
      ->willReturn(FALSE);

    $view_mode = 'full';
    $this->embargoedAccessManager->alterViewMode($view_mode, $entity);

    $this->assertEquals(PasswordAccessManagerInterface::PROTECTED_VIEW_MODE, $view_mode);
  }

  /**
   * Tests alterViewMode protected mode reset with cache context.
   *
   * @covers ::alterViewMode
   */
  public function testAlterViewModeProtectedModeReset(): void {
    $this->setupEmbargoDisabled();

    $entity = $this->createMock(EntityInterface::class);
    $entity->expects($this->once())
      ->method('getCacheContexts')
      ->willReturn(['entity_access_password_entity_is_protected:node||1||teaser']);

    $view_mode = PasswordAccessManagerInterface::PROTECTED_VIEW_MODE;

    $this->embargoedAccessManager->alterViewMode($view_mode, $entity);

    $this->assertEquals('teaser', $view_mode);
  }

  /**
   * Tests alterNodeThemeSuggestions with password protected view mode.
   *
   * @covers ::alterNodeThemeSuggestions
   */
  public function testAlterNodeThemeSuggestionsPasswordProtected(): void {
    $node = $this->createMock(NodeInterface::class);
    $node->method('bundle')->willReturn('article');

    $variables = [
      'elements' => [
        '#node' => $node,
        '#view_mode' => 'password_protected',
      ],
    ];

    $suggestions = [];

    $this->embargoedAccessManager->alterNodeThemeSuggestions($suggestions, $variables);

    $this->assertContains('node__article__full', $suggestions);
  }

  /**
   * Tests alterHtml adds protection library when embargo enabled.
   *
   * @covers ::alterHtml
   */
  public function testAlterHtmlAddsLibraryWhenEmbargoEnabled(): void {
    $this->setupEmbargoEnabled();

    $node = $this->createMock(NodeInterface::class);

    // Create manager with mocked getCurrentNode method.
    $manager = $this->getMockBuilder(EmbargoedAccessManager::class)
      ->setConstructorArgs([
        $this->entityTypeManager,
        $this->createMock(EntityFieldManagerInterface::class),
        $this->configFactory,
        $this->createMock(ContentEntityTrackingManager::class),
        $this->createMock(CsrfTokenGenerator::class),
        $this->createMock(RedirectDestinationInterface::class),
        $this->createMock(RouteParserInterface::class),
        $this->passwordAccessManager,
      ])
      ->onlyMethods(['getCurrentNode'])
      ->getMock();

    $manager->method('getCurrentNode')->willReturn($node);

    $variables = ['attributes' => ['class' => []], '#attached' => []];

    $manager->alterHtml($variables);

    $this->assertContains('ghi_embargoed_access/protect_nodes', $variables['#attached']['library']);
  }

  /**
   * Tests alterLink with routed URL and node parameter.
   *
   * @covers ::alterLink
   */
  public function testAlterLinkWithRoutedNode(): void {
    $node = $this->createMock(NodeInterface::class);
    $node->method('getEntityTypeId')->willReturn('node');
    $node->method('id')->willReturn(1);

    $url = $this->createMock(Url::class);
    $url->method('isRouted')->willReturn(TRUE);
    $url->method('getRouteParameters')->willReturn(['node' => 1]);
    $url->method('toString')->willReturn('/test/path');

    $nodeStorage = $this->createMock('Drupal\Core\Entity\EntityStorageInterface');
    $nodeStorage->method('load')->with(1)->willReturn($node);

    $this->entityTypeManager->method('getStorage')
      ->with('node')
      ->willReturn($nodeStorage);

    // Create manager with mocked methods.
    $manager = $this->getMockBuilder(EmbargoedAccessManager::class)
      ->setConstructorArgs([
        $this->entityTypeManager,
        $this->createMock(EntityFieldManagerInterface::class),
        $this->configFactory,
        $this->createMock(ContentEntityTrackingManager::class),
        $this->createMock(CsrfTokenGenerator::class),
        $this->createMock(RedirectDestinationInterface::class),
        $this->createMock(RouteParserInterface::class),
        $this->passwordAccessManager,
      ])
      ->onlyMethods(['isProtected', 'isProtectedUrl'])
      ->getMock();

    $manager->method('isProtected')->willReturn(TRUE);
    $manager->method('isProtectedUrl')->willReturn(FALSE);

    $variables = [
      'url' => $url,
      'options' => ['attributes' => ['class' => []]],
    ];

    $manager->alterLink($variables);

    $this->assertContains('protected', $variables['options']['attributes']['class']);
  }

  /**
   * Tests alterNode removes content when access denied.
   *
   * @covers ::alterNode
   */
  public function testAlterNodeRemovesContentWhenAccessDenied(): void {
    $node = $this->createMock(NodeInterface::class);
    $node->method('label')->willReturn('Test Node');
    $node->method('getEntityTypeId')->willReturn('node');
    $node->method('id')->willReturn(1);

    // Create manager with mocked entityAccess method.
    $manager = $this->getMockBuilder(EmbargoedAccessManager::class)
      ->setConstructorArgs([
        $this->entityTypeManager,
        $this->createMock(EntityFieldManagerInterface::class),
        $this->configFactory,
        $this->createMock(ContentEntityTrackingManager::class),
        $this->createMock(CsrfTokenGenerator::class),
        $this->createMock(RedirectDestinationInterface::class),
        $this->createMock(RouteParserInterface::class),
        $this->passwordAccessManager,
      ])
      ->onlyMethods(['entityAccess'])
      ->getMock();

    $manager->method('entityAccess')->willReturn(FALSE);

    $variables = [
      'node' => $node,
      'view_mode' => 'password_protected',
      'content' => [
        'field_summary' => ['#markup' => 'summary'],
        'other_field' => ['#markup' => 'other'],
      ],
      'document_summary' => ['#markup' => 'doc summary'],
      'attributes' => ['class' => []],
    ];

    $manager->alterNode($variables);

    $this->assertArrayNotHasKey('label', $variables);
    $this->assertArrayNotHasKey('field_summary', $variables['content']);
    $this->assertArrayNotHasKey('document_summary', $variables);
    $this->assertContains('content-width', $variables['attributes']['class']);
    $this->assertContains('node--view-mode-full', $variables['attributes']['class']);
    $this->assertContains('protected', $variables['attributes']['class']);
  }

  /**
   * Tests alterNodeForm modifies protected field form elements.
   *
   * @covers ::alterNodeForm
   */
  public function testAlterNodeFormModifiesProtectedField(): void {
    $node = $this->createMock(NodeInterface::class);

    $nodeForm = $this->createMock(NodeForm::class);
    $nodeForm->method('getEntity')->willReturn($node);

    $formState = $this->createMock(FormStateInterface::class);
    $formState->method('getFormObject')->willReturn($nodeForm);

    $form = [
      'field_protected' => [
        'widget' => [
          0 => [
            'show_title' => ['#access' => TRUE, '#default_value' => TRUE],
            'hint' => ['#access' => TRUE, '#default_value' => 'hint text'],
          ],
        ],
      ],
    ];

    $this->embargoedAccessManager->alterNodeForm($form, $formState);

    $this->assertFalse($form['field_protected']['widget'][0]['show_title']['#access']);
    $this->assertFalse($form['field_protected']['widget'][0]['show_title']['#default_value']);
    $this->assertFalse($form['field_protected']['widget'][0]['hint']['#access']);
    $this->assertNull($form['field_protected']['widget'][0]['hint']['#default_value']);
  }

  /**
   * Tests alterNodeForm with subpage node that has protected parent.
   *
   * @covers ::alterNodeForm
   */
  public function testAlterNodeFormSubpageWithProtectedParent(): void {
    $fieldItem = (object) ['is_protected' => TRUE];

    $parent = $this->createMock(NodeInterface::class);
    $parent->method('get')
      ->with('field_protected')
      ->willReturn($fieldItem);

    $subpage = $this->createMock(SubpageNodeInterface::class);
    $subpage->method('getParentBaseNode')->willReturn($parent);

    $nodeForm = $this->createMock(NodeForm::class);
    $nodeForm->method('getEntity')->willReturn($subpage);

    $formState = $this->createMock(FormStateInterface::class);
    $formState->method('getFormObject')->willReturn($nodeForm);

    $form = [
      'field_protected' => [
        'widget' => [
          0 => [
            'show_title' => ['#access' => TRUE, '#default_value' => TRUE],
            'hint' => ['#access' => TRUE, '#default_value' => 'hint text'],
            'is_protected' => ['#access' => TRUE],
          ],
        ],
      ],
    ];

    $this->embargoedAccessManager->alterNodeForm($form, $formState);

    $this->assertFalse($form['field_protected']['widget'][0]['is_protected']['#access']);
    $this->assertArrayHasKey('is_protected_parent', $form['field_protected']['widget'][0]);
    $this->assertTrue($form['field_protected']['widget'][0]['is_protected_parent']['#default_value']);
    $this->assertEquals('disabled', $form['field_protected']['widget'][0]['is_protected_parent']['#disabled']);
  }

}
