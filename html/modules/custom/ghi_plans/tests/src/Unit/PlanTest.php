<?php

namespace Drupal\Tests\ghi_plans\Unit;

use Drupal\ghi_plans\Entity\Plan;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests plan-owned translation options.
 */
#[CoversClass(Plan::class)]
#[Group('ghi_plans')]
class PlanTest extends UnitTestCase {

  /**
   * Tests that plan language and translation context remain distinct options.
   */
  public function testGetTranslationOptions(): void {
    $plan = $this->getMockBuilder(Plan::class)
      ->disableOriginalConstructor()
      ->onlyMethods(['getPlanLanguage'])
      ->getMock();
    $plan->method('getPlanLanguage')->willReturn('fr');

    $this->assertSame(['langcode' => 'fr'], $plan->getTranslationOptions());
    $this->assertSame([
      'langcode' => 'fr',
      'context' => 'Financial table label',
    ], $plan->getTranslationOptions('Financial table label'));
  }

  /**
   * Tests resolution with and without plan context.
   */
  public function testBuildTranslationOptions(): void {
    $plan = $this->getMockBuilder(Plan::class)
      ->disableOriginalConstructor()
      ->onlyMethods(['getPlanLanguage'])
      ->getMock();
    $plan->method('getPlanLanguage')->willReturn('es');

    $this->assertSame(['langcode' => 'es'], Plan::buildTranslationOptions($plan));
    $this->assertSame([
      'langcode' => 'es',
      'context' => 'Financial table label',
    ], Plan::buildTranslationOptions($plan, 'Financial table label'));
    $this->assertSame(['langcode' => 'en'], Plan::buildTranslationOptions(NULL));
    $this->assertSame([
      'langcode' => 'en',
      'context' => 'Financial table label',
    ], Plan::buildTranslationOptions(NULL, 'Financial table label'));
  }

}
