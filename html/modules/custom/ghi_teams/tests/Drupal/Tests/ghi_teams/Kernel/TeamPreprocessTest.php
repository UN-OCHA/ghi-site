<?php

namespace Drupal\Tests\ghi_teams\Kernel;

use Drupal\KernelTests\KernelTestBase;

/**
 * Tests some preprocess functions in ghi_teams.
 *
 * @group ghi_teams
 */
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
   *
   * @covers ::ghi_teams_preprocess_taxonomy_term
   */
  public function testPreprocessTaxonomyTerm() {
    $variables = [];
    ghi_teams_preprocess_taxonomy_term($variables);
    $this->assertTrue($variables['#attached']['drupalSettings']['path']['currentPathIsAdmin']);
  }

}
