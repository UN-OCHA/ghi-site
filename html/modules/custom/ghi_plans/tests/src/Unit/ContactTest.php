<?php

namespace Drupal\Tests\ghi_plans\Unit;

use Drupal\ghi_plans\ApiObjects\Contact;

/**
 * Tests the PlanEntity API object.
 *
 * @group ghi_plans
 */
class ContactTest extends ApiObjectTestBase {

  /**
   * Test PlanEntity constructor and basic mapping.
   */
  public function testPlanEntityConstructorAndMapping(): void {
    $contact = new Contact((object) [
      'Id' => 1,
      'Name' => 'John Smith',
      'Email' => 'john.smith@anything-goes.com',
      'LeadAgency' => 'OCHA',
    ]);
    assert($contact instanceof Contact);

    $this->assertEquals(1, $contact->id());
    $this->assertEquals('John Smith', $contact->getName());
    $this->assertEquals('john.smith@anything-goes.com', $contact->getMail());
    $this->assertEquals('OCHA', $contact->getAgency());
    $this->assertIsObject($contact->getRawData());
    $this->assertIsArray($contact->toArray());
    $this->assertIsArray($contact->getCacheTags());
    $this->assertIsArray($contact->getCacheContexts());
    $this->assertIsInt($contact->getCacheMaxAge());
  }

}
