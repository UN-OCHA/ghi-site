<?php

namespace Drupal\Tests\ghi_templates\Unit;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Form\FormState;
use Drupal\ghi_templates\Form\PageTemplateForm;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Tests page template form save results and redirects.
 *
 * @group ghi_templates
 */
class PageTemplateFormTest extends UnitTestCase {

  /**
   * Tests that the save result is returned for both redirect destinations.
   */
  #[DataProvider('saveProvider')]
  public function testSave(bool $new, bool $can_view): void {
    require_once $this->root . '/core/includes/common.inc';
    $status = $new ? SAVED_NEW : SAVED_UPDATED;
    $entity = $this->createMock(EntityInterface::class);
    $entity->expects($this->once())->method('save')->willReturn($status);
    $entity->method('id')->willReturn(1);
    $entity->method('access')->with('view')->willReturn($can_view);
    $form = new PageTemplateForm(
      $this->createMock(EntityRepositoryInterface::class),
      $this->createMock(EntityTypeBundleInfoInterface::class),
      $this->createMock(TimeInterface::class),
    );
    $form->setEntity($entity);
    $form_state = new FormState();

    $this->assertSame($status, $form->save([], $form_state));
    $route = $can_view ? 'entity.page_template.canonical' : 'entity.page_template.collection';
    $this->assertSame($route, $form_state->getRedirect()->getRouteName());
  }

  /**
   * Provides save results with and without access to the saved entity.
   */
  public static function saveProvider(): array {
    return [
      'new, viewable' => [TRUE, TRUE],
      'updated, viewable' => [FALSE, TRUE],
      'new, not viewable' => [TRUE, FALSE],
      'updated, not viewable' => [FALSE, FALSE],
    ];
  }

}
