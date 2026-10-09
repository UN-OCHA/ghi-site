<?php

namespace Drupal\Tests\ghi_embargoed_access\Functional;

use Drupal\node\NodeInterface;

/**
 * Tests embargoed node access.
 *
 * @group ghi_embargoed_access
 */
class EmbargoedNodeAccessTest extends EmbargoedAccessTestBase {

  // Only install the bundles needed by these scenarios in each isolated site.
  const BUNDLES = [self::BUNDLE_PAGE];

  /**
   * Test protection of a simple page.
   */
  public function testProtectSimplePage() {
    $node = $this->drupalCreateNode([
      'type' => self::BUNDLE_PAGE,
      'title' => $this->randomString(),
      'status' => NodeInterface::PUBLISHED,
      self::FIELD_NAME_PROTECTED => [
        'is_protected' => TRUE,
        'show_title' => FALSE,
        'hint' => '',
        'password' => '',
      ],
    ]);

    // Open page node and confirm it's protected.
    $this->drupalGet($node->toUrl());
    $this->assertSession()->pageTextContains('Help text: ' . self::BUNDLE_PAGE);
    $this->assertSession()->elementExists('css', '#entity-access-password-password-node-' . $node->id());

    // Enter password and confirm access.
    $this->submitForm(
      ['form_password' => self::PASSWORD],
      'Submit',
      'entity-access-password-password-node-' . $node->id()
    );
    $this->assertSession()->pageTextNotContains('Help text: ' . self::BUNDLE_PAGE);
    $this->assertSession()->elementNotExists('css', '#entity-access-password-password-node-' . $node->id());
  }

  /**
   * Test global embargo switch with a simple page.
   */
  public function testGlobalEmbargoSwitchSimple() {
    $node = $this->drupalCreateNode([
      'type' => self::BUNDLE_PAGE,
      'title' => $this->randomString(),
      'status' => NodeInterface::PUBLISHED,
      self::FIELD_NAME_PROTECTED => [
        'is_protected' => TRUE,
        'show_title' => FALSE,
        'hint' => '',
        'password' => '',
      ],
    ]);

    // Open page node and confirm it's protected.
    $this->drupalGet($node->toUrl());
    $this->assertSession()->pageTextContains('Help text: ' . self::BUNDLE_PAGE);
    $this->assertSession()->elementExists('css', '#entity-access-password-password-node-' . $node->id());

    // Switch global embargo contronl off.
    $this->drupalLogin($this->adminUser);
    $this->drupalGet('/admin/config/ghi/embargoed-access');
    $this->getSession()->getPage()->uncheckField('enabled');
    $this->getSession()->getPage()->pressButton('Save configuration');
    $this->drupalLogout();

    // Open the same page node again and confirm the protection is disabled.
    $this->drupalGet($node->toUrl());
    $this->assertSession()->pageTextNotContains('Help text: ' . self::BUNDLE_PAGE);
    $this->assertSession()->elementNotExists('css', '#entity-access-password-password-node-' . $node->id());
  }

}
