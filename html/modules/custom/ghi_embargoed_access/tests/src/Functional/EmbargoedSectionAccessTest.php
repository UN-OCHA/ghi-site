<?php

namespace Drupal\Tests\ghi_embargoed_access\Functional;

use Drupal\node\NodeInterface;

/**
 * Tests embargoed section access.
 *
 * @group ghi_embargoed_access
 */
class EmbargoedSectionAccessTest extends EmbargoedAccessTestBase {

  // Saving a section queries subpages, so their reference field must exist even
  // when the scenario does not create any subpage nodes.
  const BUNDLES = [self::BUNDLE_SECTION, self::BUNDLE_SUBPAGE, self::BUNDLE_ARTICLE];

  /**
   * Test protection of a single section page.
   */
  public function testProtectSectionPage() {
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

    // Open section node and confirm it's not protected.
    $this->drupalGet($section_node->toUrl());
    $this->assertSession()->pageTextContains('Help text: ' . self::BUNDLE_SECTION);
    $this->assertSession()->elementExists('css', '#entity-access-password-password-node-' . $section_node->id());

    // Enter password and confirm access.
    $this->submitForm(
      ['form_password' => self::PASSWORD],
      'Submit',
      'entity-access-password-password-node-' . $section_node->id()
    );
    $this->assertSession()->pageTextNotContains('Help text: ' . self::BUNDLE_SECTION);
    $this->assertSession()->elementNotExists('css', '#entity-access-password-password-node-' . $section_node->id());
  }

  /**
   * Test protection of a section and articles accessed via the section.
   */
  public function testProtectSectionArticlePage() {
    $tag = $this->createTerm($this->tagVocabulary);
    $alias_storage = $this->entityTypeManager->getStorage('path_alias');

    // Create protected section node.
    $section_node = $this->drupalCreateNode([
      'type' => self::BUNDLE_SECTION,
      'title' => $this->randomString(),
      'status' => NodeInterface::PUBLISHED,
      self::FIELD_NAME_TAG => [
        'target_id' => $tag->id(),
      ],
      self::FIELD_NAME_PROTECTED => [
        'is_protected' => TRUE,
        'show_title' => FALSE,
        'hint' => '',
        'password' => '',
      ],
    ]);
    // Create path aliases.
    $alias_storage->create([
      'path' => $section_node->toUrl()->toString(),
      'alias' => '/plan/1',
    ])->save();

    // Create non-protected subpage node.
    $article_node = $this->drupalCreateNode([
      'type' => self::BUNDLE_ARTICLE,
      'title' => $this->randomString(),
      'status' => NodeInterface::PUBLISHED,
      self::FIELD_NAME_TAG => [
        'target_id' => $tag->id(),
      ],
      self::FIELD_NAME_PROTECTED => [
        'is_protected' => FALSE,
        'show_title' => FALSE,
        'hint' => '',
        'password' => '',
      ],
    ]);
    // Create path aliases.
    $alias_storage->create([
      'path' => $article_node->toUrl()->toString(),
      'alias' => '/article/1',
    ])->save();

    // Open section node and confirm it's protected, don't enter password yet.
    $this->drupalGet($section_node->toUrl());
    $this->assertSession()->pageTextContains('Help text: ' . self::BUNDLE_SECTION);
    $this->assertSession()->elementExists('css', '#entity-access-password-password-node-' . $section_node->id());

    // Open standalone article node and confirm it's not protected.
    $this->drupalGet($article_node->toUrl());
    $this->assertSession()->pageTextNotContains('Help text: ' . self::BUNDLE_ARTICLE);
    $this->assertSession()->elementNotExists('css', '#entity-access-password-password-node-' . $article_node->id());

    // Open section specific article node and confirm it's protected by the
    // section.
    $this->drupalGet('/plan/1' . $article_node->toUrl()->toString());
    $this->assertSession()->pageTextContains('Help text: ' . self::BUNDLE_ARTICLE);
    $this->assertSession()->elementExists('css', '#entity-access-password-password-node-' . $section_node->id());

    // Enter password and confirm access.
    $this->submitForm(
      ['form_password' => self::PASSWORD],
      'Submit',
      'entity-access-password-password-node-' . $section_node->id()
    );
    $this->assertSession()->pageTextNotContains('Help text: ' . self::BUNDLE_ARTICLE);
    $this->assertSession()->elementNotExists('css', '#entity-access-password-password-node-' . $section_node->id());
  }

}
