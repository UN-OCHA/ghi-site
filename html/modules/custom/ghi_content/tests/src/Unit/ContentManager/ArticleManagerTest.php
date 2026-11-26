<?php

namespace Drupal\Tests\ghi_content\Unit\ContentManager;

use Drupal\Core\Entity\EntityFormInterface;
use Drupal\Core\Form\FormState;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\ghi_content\ContentManager\ArticleManager;
use Drupal\ghi_content\Entity\Article;
use Drupal\Tests\hpc_api\Traits\PrivateAccessorTrait;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests manual article refresh failures.
 */
#[Group('ghi_content')]
class ArticleManagerTest extends UnitTestCase {

  use PrivateAccessorTrait;

  /**
   * Provides the manual refresh submit handlers.
   */
  public static function refreshSubmitProvider(): array {
    return [
      'apply changes' => ['applyChangesSubmit', FALSE],
      'reset' => ['formResetSubmit', TRUE],
    ];
  }

  /**
   * Tests manual refreshes do not save or report success after a fetch failure.
   */
  #[DataProvider('refreshSubmitProvider')]
  public function testFailedRefreshDoesNotSave(string $handler, bool $reset): void {
    $node = $this->createMock(Article::class);
    $manager = $this->getMockBuilder(ArticleManager::class)
      ->disableOriginalConstructor()
      ->onlyMethods(['updateNodeFromRemote', 'saveContentNode'])
      ->getMock();
    $manager->setStringTranslation($this->getStringTranslationStub());
    $manager->expects($this->once())->method('updateNodeFromRemote')->willReturnCallback(function ($actual_node, $dry_run = FALSE, $actual_reset = FALSE) use ($node, $reset): bool {
      $this->assertSame($node, $actual_node);
      $this->assertFalse($dry_run);
      $this->assertSame($reset, $actual_reset);
      return FALSE;
    });
    $manager->expects($this->never())->method('saveContentNode');
    $messenger = $this->createMock(MessengerInterface::class);
    $messenger->expects($this->once())->method('addError')->with($this->callback(fn ($message) => (string) $message === 'Remote content could not be loaded. No changes were saved.'));
    $messenger->expects($this->never())->method('addStatus');
    $this->setPrivateProperty($manager, 'messenger', $messenger);

    $form = $this->createMock(EntityFormInterface::class);
    $form->method('getEntity')->willReturn($node);
    $form_state = (new FormState())->setFormObject($form);
    $manager->{$handler}([], $form_state);
    $this->assertTrue($form_state->isRebuilding());
  }

}
