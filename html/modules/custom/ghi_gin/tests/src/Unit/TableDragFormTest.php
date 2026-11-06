<?php

namespace Drupal\Tests\ghi_gin\Unit;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Form\FormState;
use Drupal\Core\Routing\AdminContext;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the scoped tabledrag form attachments.
 */
#[Group('ghi_gin')]
class TableDragFormTest extends UnitTestCase {

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    require_once $this->root . '/core/includes/bootstrap.inc';
    require_once DRUPAL_ROOT . '/modules/custom/ghi_gin/ghi_gin.module';
  }

  /**
   * Tests that the form alter preserves existing callbacks and form metadata.
   */
  public function testFormAlterRegistersAfterBuild(): void {
    $form = [
      '#attributes' => ['class' => ['existing-form']],
      '#after_build' => ['existing_after_build'],
      '#attached' => ['library' => ['existing/library']],
      'value' => ['#type' => 'textfield'],
    ];
    $expected = $form;
    $expected['#after_build'][] = 'ghi_gin_tabledrag_after_build';

    \ghi_gin_form_alter($form, new FormState(), 'test_form');

    $this->assertSame($expected, $form);
  }

  /**
   * Tests eligibility and preservation of existing attributes and attachments.
   */
  #[DataProvider('formEligibilityProvider')]
  public function testScopedLibraryPreservesForm(bool $is_admin_route, bool $gin_lb_form, bool $ghi_modal_form, bool $eligible): void {
    $container = new ContainerBuilder();
    $container->set('router.admin_context', $this->mockAdminContextWithRouteStatus($is_admin_route));
    \Drupal::setContainer($container);
    $form = [
      '#type' => 'form',
      '#gin_lb_form' => $gin_lb_form,
      '#ghi_modal_form' => $ghi_modal_form,
      '#attributes' => [
        'id' => 'existing-form',
        'class' => ['existing-class'],
        'data-existing' => 'retained',
      ],
      '#attached' => [
        'library' => ['core/drupal.tabledrag', 'existing/library'],
        'drupalSettings' => ['existing' => ['enabled' => TRUE]],
      ],
      'value' => ['#type' => 'textfield', '#default_value' => 'retained'],
    ];
    $expected = $form;
    if ($eligible) {
      $expected['#attributes']['data-ghi-tabledrag'] = '';
      $expected['#attached']['library'][] = 'ghi_gin/tabledrag';
    }

    $this->assertSame($expected, \ghi_gin_tabledrag_after_build($form, new FormState()));
  }

  /**
   * Provides all combinations of route and modal eligibility.
   */
  public static function formEligibilityProvider(): array {
    return [
      'public' => [FALSE, FALSE, FALSE, FALSE],
      'Gin LB modal' => [FALSE, TRUE, FALSE, TRUE],
      'GHI modal' => [FALSE, FALSE, TRUE, TRUE],
      'both modal markers' => [FALSE, TRUE, TRUE, TRUE],
      'admin' => [TRUE, FALSE, FALSE, TRUE],
      'admin Gin LB modal' => [TRUE, TRUE, FALSE, TRUE],
      'admin GHI modal' => [TRUE, FALSE, TRUE, TRUE],
      'admin both modal markers' => [TRUE, TRUE, TRUE, TRUE],
    ];
  }

  /**
   * Tests that eligible forms need no pre-existing attributes or attachments.
   */
  public function testEligibleFormInitializesMetadata(): void {
    $container = new ContainerBuilder();
    $container->set('router.admin_context', $this->mockAdminContextWithRouteStatus(TRUE));
    \Drupal::setContainer($container);

    $this->assertSame([
      '#attributes' => ['data-ghi-tabledrag' => ''],
      '#attached' => ['library' => ['ghi_gin/tabledrag']],
    ], \ghi_gin_tabledrag_after_build([], new FormState()));
  }

  /**
   * Tests that empty modal markers do not opt public forms into the library.
   */
  #[DataProvider('emptyModalMarkersProvider')]
  public function testPublicFormWithEmptyMarkersIsUnchanged(array $markers): void {
    $container = new ContainerBuilder();
    $container->set('router.admin_context', $this->mockAdminContextWithRouteStatus(FALSE));
    \Drupal::setContainer($container);
    $form = $markers + [
      '#attributes' => ['class' => ['public-form']],
      '#attached' => ['library' => ['core/drupal.tabledrag']],
    ];

    $this->assertSame($form, \ghi_gin_tabledrag_after_build($form, new FormState()));
  }

  /**
   * Provides absent and empty modal markers.
   */
  public static function emptyModalMarkersProvider(): array {
    return [
      'absent' => [[]],
      'false' => [['#gin_lb_form' => FALSE, '#ghi_modal_form' => FALSE]],
      'null' => [['#gin_lb_form' => NULL, '#ghi_modal_form' => NULL]],
      'zero' => [['#gin_lb_form' => 0, '#ghi_modal_form' => 0]],
      'empty string' => [['#gin_lb_form' => '', '#ghi_modal_form' => '']],
      'empty array' => [['#gin_lb_form' => [], '#ghi_modal_form' => []]],
    ];
  }

  /**
   * Mocks an admin context with the given route status.
   */
  private function mockAdminContextWithRouteStatus(bool $is_admin_route): AdminContext {
    $admin_context = $this->createMock(AdminContext::class);
    $admin_context->method('isAdminRoute')->willReturn($is_admin_route);
    return $admin_context;
  }

}
