<?php

namespace Drupal\Tests\ghi_base_objects\Functional;

use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\field\Traits\EntityReferenceFieldCreationTrait;
use Drupal\Tests\ghi_base_objects\Traits\BaseObjectTestTrait;
use Drupal\Tests\ghi_base_objects\Traits\FieldTestTrait;
use Drupal\Tests\taxonomy\Traits\TaxonomyTestTrait;
use Drupal\ghi_base_objects\Form\BaseObjectForm;

/**
 * Tests the node wizard pages.
 *
 * @group ghi_base_objects
 * @covers \Drupal\ghi_base_objects\Form\BaseObjectForm
 */
class BaseObjectFormTest extends BrowserTestBase {

  use BaseObjectTestTrait;
  use EntityReferenceFieldCreationTrait;
  use TaxonomyTestTrait;
  use FieldTestTrait;

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = [
    'ghi_base_objects',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->createBaseObjectType([
      'id' => 'plan',
    ]);

    // Create a user with permission to view the actions administration pages.
    $this->drupalLogin($this->drupalCreateUser([
      'administer base object entities',
    ]));
  }

  /**
   * Tests the edit form for base objects.
   */
  public function testBaseObjectEditForm() {
    $base_object = $this->createBaseObject([
      'type' => 'plan',
    ]);
    $this->drupalGet($base_object->toUrl('edit-form')->toString());
    $assert_session = $this->assertSession();
    $assert_session->statusCodeEquals(200);
    $assert_session->pageTextContains('Most of the data in this form is imported automatically from the HPC API and cannot be changed here.');

    $form_object = $this->container->get('entity_type.manager')->getFormObject('base_object', 'edit');
    $this->assertInstanceOf(BaseObjectForm::class, $form_object);

    $page = $this->getSession()->getPage();
    $name = $page->find('css', 'input[name="name[0][value]"]')->getValue();
    $page->pressButton('Save');
    $assert_session->pageTextContains('Saved the ' . $name . ' Base object.');
  }

}
