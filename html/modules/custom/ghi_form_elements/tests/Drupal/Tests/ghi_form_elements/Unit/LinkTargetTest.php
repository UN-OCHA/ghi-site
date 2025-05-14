<?php

namespace Drupal\Tests\ghi_form_elements\Unit;

use Drupal\Core\Url;
use Drupal\Tests\UnitTestCase;
use Drupal\ghi_form_elements\LinkTarget\ExternalLinkTarget;
use Drupal\ghi_form_elements\LinkTarget\InternalLinkTarget;
use Drupal\node\NodeInterface;

/**
 * Test link target classes.
 */
class LinkTargetTest extends UnitTestCase {

  /**
   * Test internal link targets.
   *
   * @covers Drupal\ghi_form_elements\LinkTarget\InternalLinkTarget
   */
  public function testInternalLinkTarget() {
    $node = $this->prophesize(NodeInterface::class);
    $node->bundle()->willReturn('Article');
    $node->toUrl()->willReturn('URL OBJECT');
    $link_target = new InternalLinkTarget($node->reveal());
    $this->assertEquals('article', $link_target->getAdminLabel());
    $this->assertEquals('URL OBJECT', $link_target->getUrl());
  }

  /**
   * Test external link targets.
   *
   * @covers Drupal\ghi_form_elements\LinkTarget\ExternalLinkTarget
   */
  public function testExternalLinkTarget() {
    $url = $this->prophesize(Url::class);
    $admin_label = 'Admin label';
    $link_target = new ExternalLinkTarget($admin_label, $url->reveal());
    $this->assertEquals('Admin label', $link_target->getAdminLabel());
    $this->assertEquals($url->reveal(), $link_target->getUrl());
  }

}
