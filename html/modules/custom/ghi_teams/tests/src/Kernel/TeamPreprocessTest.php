<?php

namespace Drupal\Tests\ghi_teams\Kernel;

use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\CoversFunction;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests some preprocess functions in ghi_teams.
 */
#[CoversFunction('ghi_teams_preprocess_taxonomy_term')]
#[Group('ghi_teams')]
class TeamPreprocessTest extends KernelTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = [
    'ghi_teams',
  ];

  /**
   * Tests ghi_teams_preprocess_taxonomy_term marks term pages as admin pages.
   */
  public function testPreprocessTaxonomyTerm() {
    $variables = [];
    ghi_teams_preprocess_taxonomy_term($variables);
    $this->assertTrue($variables['#attached']['drupalSettings']['path']['currentPathIsAdmin']);
  }

}
