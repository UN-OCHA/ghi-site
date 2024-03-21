<?php

namespace Drupal\Tests\ghi_blocks\FunctionalJavascript;

use Drupal\block_content\Entity\BlockContentType;

/**
 * Tests the GHI specific block UI.
 *
 * @group ghi_blocks
 */
class BlockUiTest extends BlockUiBase {

  /**
   * Tests the block configuration page.
   */
  public function testBlockConfiguration() {
    $node = $this->createNode([
      'type' => 'page',
    ]);

    $this->loginEditor();

    $assert_session = $this->assertSession();
    $page = $this->getSession()->getPage();

    // Go to node view page.
    $this->drupalGet($node->toUrl()->toString());

    // Click the customize link.
    $assert_session->elementExists('css', '#layout-builder-ipe-wrapper');
    $assert_session->elementExists('css', '.layout-builder-ipe-actions');
    $assert_session->linkExists('Customize');
    $page->clickLink('Customize');
    $assert_session->assertWaitOnAjaxRequest();
    $assert_session->waitForElementVisible('css', '#layout-builder-ipe-wrapper.edit-layout');
    $assert_session->elementExists('css', '#layout-builder-ipe-wrapper.edit-layout');

    // Check that the form and the two main buttons are there.
    $assert_session->elementExists('css', 'form.layout-builder-form');
    $assert_session->buttonExists('Discard changes');
    $assert_session->buttonExists('Save layout');

    // Open the "add new block" dialog.
    $assert_session->linkExists('Add block');
    $page->clickLink('Add block');
    $assert_session->assertWaitOnAjaxRequest();

    // Confirm that block selection opens in a modal.
    $assert_session->elementTextContains('css', '#layout-builder-modal', 'Choose a block type from the following categories');
    $assert_session->linkExists('Admin restricted');
    $page->clickLink('Admin restricted');
    $assert_session->linkExists('Generic HTML');
    $page->clickLink('Generic HTML');
    $assert_session->assertWaitOnAjaxRequest();

    // Confirm modal and UI details.
    $assert_session->elementExists('css', '#layout-builder-modal');
    $assert_session->elementExists('css', '.glb-canvas-form__actions a.dialog-cancel.use-ajax');
    $assert_session->elementExists('css', '.glb-canvas-form__actions input[data-drupal-selector="edit-actions-submit"]');

    // Submit the block data.
    $page->findField('Title')->setValue('Hello World');
    $page->findField('Display title')->setValue(1);
    $page->findField('Body')->setValue('Body says hello');
    $button = $assert_session->elementExists('css', '#layout-builder-add-block .glb-button--primary');
    $button->press();

    // Confirm that it closes the modal and that the submitted data displays.
    $assert_session->assertWaitOnAjaxRequest();
    $assert_session->waitForElementRemoved('css', '#layout-builder-modal');
    $assert_session->elementNotExists('css', '#layout-builder-modal');
    $assert_session->elementContains('css', '.block-inline-blockbasic h2.cd-block-title', 'Hello World');
    $assert_session->elementContains('css', '.block-inline-blockbasic .field--name-body', 'Body says hello');
    $assert_session->pageTextContains('You have unsaved changes.');
    $this->htmlOutput(NULL);

    // Confirm contextual block links.
    $assert_session->elementExists('css', '.block-inline-blockbasic ul.contextual-links li.layout-builder-block-remove');
    $assert_session->elementExists('css', '.block-inline-blockbasic ul.contextual-links li.layout-builder-block-hide');
    $assert_session->elementExists('css', '.block-inline-blockbasic ul.contextual-links li.layout-builder-block-unhide');
    $configure_link = $assert_session->elementExists('css', '.block-inline-blockbasic ul.contextual-links li.layout-builder-block-update');

    // Confirm opening configuration and closing without changes works.
    $configure_link->click();
    $assert_session->waitForElement('css', '#layout-builder-modal');
    $assert_session->elementExists('css', '#layout-builder-modal');
    $cancel_link = $assert_session->elementExists('css', '#layout-builder-modal a.dialog-cancel');
    $this->htmlOutput(NULL);

    $cancel_link->click();
    $assert_session->assertWaitOnAjaxRequest();
    $assert_session->waitForElementRemoved('css', '#layout-builder-modal');
    $this->htmlOutput(NULL);
  }

  /**
   * Creates a block content type.
   *
   * @param string $id
   *   The block type id.
   * @param string $label
   *   The block type label.
   */
  protected function createBlockContentType($id, $label) {
    $bundle = BlockContentType::create([
      'id' => $id,
      'label' => $label,
      'revision' => 1,
    ]);
    $bundle->save();
    block_content_add_body_field($bundle->id());
  }

}
