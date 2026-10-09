<?php

namespace Drupal\Tests\ghi_embargoed_access\Functional;

use Drupal\node\NodeInterface;

/**
 * Tests embargoed subpage access.
 *
 * @group ghi_embargoed_access
 */
class EmbargoedSubpageAccessTest extends EmbargoedAccessTestBase {

  // Only install the bundles needed by these scenarios in each isolated site.
  const BUNDLES = [self::BUNDLE_SECTION, self::BUNDLE_SUBPAGE];

  /**
   * Test global embargo switch with a section page and subpages.
   */
  public function testGlobalEmbargoSwitchSubpages() {
    // Create protected section node.
    $section_node = $this->drupalCreateNode([
      'type' => self::BUNDLE_SECTION,
      'title' => $this->randomString(),
      'status' => NodeInterface::PUBLISHED,
      self::FIELD_NAME_PROTECTED => [
        'is_protected' => TRUE,
        'show_title' => FALSE,
        'hint' => '',
        'password' => '',
      ],
    ]);
    // Create non-protected subpage node.
    $subpage_node = $this->drupalCreateNode([
      'type' => self::BUNDLE_SUBPAGE,
      'title' => $this->randomString(),
      'status' => NodeInterface::PUBLISHED,
      self::FIELD_NAME_SECTION_REFERENCE => [
        'target_id' => $section_node->id(),
      ],
      self::FIELD_NAME_PROTECTED => [
        'is_protected' => FALSE,
        'show_title' => FALSE,
        'hint' => '',
        'password' => '',
      ],
    ]);

    // Open section node and confirm it's protected, don't enter password yet.
    $this->drupalGet($section_node->toUrl());
    $this->assertSession()->pageTextContains('Help text: ' . self::BUNDLE_SECTION);
    $this->assertSession()->elementExists('css', '#entity-access-password-password-node-' . $section_node->id());

    // Open subpage node and confirm it's protected by the section.
    $this->drupalGet($subpage_node->toUrl());
    $this->assertSession()->pageTextContains('Help text: ' . self::BUNDLE_SUBPAGE);
    $this->assertSession()->elementExists('css', '#entity-access-password-password-node-' . $section_node->id());

    // Switch global embargo contronl off.
    $this->drupalLogin($this->adminUser);
    $this->drupalGet('/admin/config/ghi/embargoed-access');
    $this->getSession()->getPage()->uncheckField('enabled');
    $this->getSession()->getPage()->pressButton('Save configuration');
    $this->drupalLogout();

    // Open the same section node again and confirm the protection is disabled.
    $this->drupalGet($section_node->toUrl());
    $this->assertSession()->pageTextNotContains('Help text: ' . self::BUNDLE_SECTION);
    $this->assertSession()->elementNotExists('css', '#entity-access-password-password-node-' . $section_node->id());

    // Open the same subpage node again and confirm the protection is disabled.
    $this->drupalGet($subpage_node->toUrl());
    $this->assertSession()->pageTextNotContains('Help text: ' . self::BUNDLE_SUBPAGE);
    $this->assertSession()->elementNotExists('css', '#entity-access-password-password-node-' . $subpage_node->id());
  }

  /**
   * Test protection of a section and it's subpages.
   */
  public function testProtectSectionSubpage() {
    // Create protected section node.
    $section_node = $this->drupalCreateNode([
      'type' => self::BUNDLE_SECTION,
      'title' => $this->randomString(),
      'status' => NodeInterface::PUBLISHED,
      self::FIELD_NAME_PROTECTED => [
        'is_protected' => TRUE,
        'show_title' => FALSE,
        'hint' => '',
        'password' => '',
      ],
    ]);
    // Create non-protected subpage node.
    $subpage_node = $this->drupalCreateNode([
      'type' => self::BUNDLE_SUBPAGE,
      'title' => $this->randomString(),
      'status' => NodeInterface::PUBLISHED,
      self::FIELD_NAME_SECTION_REFERENCE => [
        'target_id' => $section_node->id(),
      ],
      self::FIELD_NAME_PROTECTED => [
        'is_protected' => FALSE,
        'show_title' => FALSE,
        'hint' => '',
        'password' => '',
      ],
    ]);

    // Open section node and confirm it's protected, don't enter password yet.
    $this->drupalGet($section_node->toUrl());
    $this->assertSession()->pageTextContains('Help text: ' . self::BUNDLE_SECTION);
    $this->assertSession()->elementExists('css', '#entity-access-password-password-node-' . $section_node->id());

    // Open subpage node and confirm it's protected by the section.
    $this->drupalGet($subpage_node->toUrl());
    $this->assertSession()->pageTextContains('Help text: ' . self::BUNDLE_SUBPAGE);
    $this->assertSession()->elementExists('css', '#entity-access-password-password-node-' . $section_node->id());

    // Enter password and confirm access.
    $this->submitForm(
      ['form_password' => self::PASSWORD],
      'Submit',
      'entity-access-password-password-node-' . $section_node->id()
    );
    $this->assertSession()->pageTextNotContains('Help text: ' . self::BUNDLE_SUBPAGE);
    $this->assertSession()->elementNotExists('css', '#entity-access-password-password-node-' . $section_node->id());
  }

}
