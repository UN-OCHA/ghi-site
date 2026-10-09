<?php

namespace Drupal\Tests\ghi_subpages\FunctionalJavascript;

use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use Drupal\Tests\ghi_subpages\Traits\SubpageTestTrait;

/**
 * Tests structural confirmations in the Subpages operations dropbuttons.
 *
 * @group ghi_subpages
 */
class SubpagesModalTest extends WebDriverTestBase {

  use SubpageTestTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['ghi_subpages'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'claro';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->createSubpageContentTypes();
    $this->drupalLogin($this->drupalCreateUser([
      'administer nodes',
      'bypass node access',
      'manage operation page structure',
      'create needs content',
      'delete any needs content',
    ]));
  }

  /**
   * Tests both single-item structural actions without leaving the listing.
   */
  public function testCreateAndDeleteModals(): void {
    $section = $this->createSection(['status' => 1, 'title' => '2027 Example Plan']);
    $pages_url = '/node/' . $section->id() . '/pages';
    $this->drupalGet($pages_url);

    // This Selenium driver rejects native clicks on these dropbutton links;
    // DOM clicks exercise the same AJAX dialog behavior without that failure.
    $this->getSession()->getDriver()->executeScript('document.querySelector("#edit-subpages-standard a[href$=\'/create/needs\']").click();');
    $this->assertSession()->waitForElement('css', '#drupal-modal form');
    $this->assertSession()->elementContains('css', '.ui-dialog-title', 'Create the needs subpage?');
    $this->assertSession()->elementContains('css', '#drupal-modal', 'Please confirm that the Needs subpage for 2027 Example Plan should be created');
    $this->assertSession()->elementContains('css', '.ui-dialog-buttonpane', 'Cancel');
    $this->assertSession()->elementNotExists('css', '#drupal-modal a.dialog-cancel');
    // Let Drupal's initial debounced dialog resize finish before closing it.
    usleep(100000);
    $this->getSession()->getDriver()->executeScript('Array.from(document.querySelectorAll(".ui-dialog-buttonpane button")).find((button) => button.textContent.trim() === "Cancel").click();');
    $this->assertSession()->waitForElementRemoved('css', '#drupal-modal');
    $this->assertSame($pages_url, parse_url($this->getSession()->getCurrentUrl(), PHP_URL_PATH));

    $this->getSession()->getDriver()->executeScript('document.querySelector("#edit-subpages-standard a[href$=\'/create/needs\']").click();');
    $this->assertSession()->waitForElement('css', '#drupal-modal form');
    $this->getSession()->getDriver()->executeScript('document.querySelector(".ui-dialog-buttonpane button").click();');
    $this->assertSession()->waitForElement('css', '#edit-subpages-standard a[href*="' . $pages_url . '/delete/"]');
    $this->assertSame($pages_url, parse_url($this->getSession()->getCurrentUrl(), PHP_URL_PATH));

    $this->getSession()->getDriver()->executeScript('document.querySelector("#edit-subpages-standard a[href*=\'/pages/delete/\']").click();');
    $this->assertSession()->waitForElement('css', '#drupal-modal form');
    $this->assertSession()->elementContains('css', '.ui-dialog-title', 'Delete 1 standard subpages?');
    $this->assertSession()->elementContains('css', '.ui-dialog-buttonpane', 'Cancel');
    $this->assertSession()->elementNotExists('css', '#drupal-modal a.dialog-cancel');
    usleep(100000);
    $this->getSession()->getDriver()->executeScript('Array.from(document.querySelectorAll(".ui-dialog-buttonpane button")).find((button) => button.textContent.trim() === "Cancel").click();');
    $this->assertSession()->waitForElementRemoved('css', '#drupal-modal');
    $this->assertSame($pages_url, parse_url($this->getSession()->getCurrentUrl(), PHP_URL_PATH));

    $this->getSession()->getDriver()->executeScript('document.querySelector("#edit-subpages-standard a[href*=\'/pages/delete/\']").click();');
    $this->assertSession()->waitForElement('css', '#drupal-modal form');
    $this->getSession()->getDriver()->executeScript('document.querySelector(".ui-dialog-buttonpane button").click();');
    $this->assertSession()->waitForElement('css', '#edit-subpages-standard a[href="' . $pages_url . '/create/needs"]');
    $this->assertSame($pages_url, parse_url($this->getSession()->getCurrentUrl(), PHP_URL_PATH));
  }

}
