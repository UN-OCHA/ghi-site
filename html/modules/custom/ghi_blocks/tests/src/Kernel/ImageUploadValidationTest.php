<?php

namespace Drupal\Tests\ghi_blocks\Kernel;

use Drupal\Core\Form\FormState;
use Drupal\file\Entity\File;
use Drupal\ghi_blocks\Element\CarouselItem;
use Drupal\ghi_blocks\Plugin\ConfigurationContainerItem\Link;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests image upload constraints in block configuration forms.
 */
#[Group('ghi_blocks')]
class ImageUploadValidationTest extends BlockKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system', 'user', 'file'];

  /**
   * Tests that both forms use constraints and preserve allowed extensions.
   */
  public function testImageUploadConstraints(): void {
    $form_state = new FormState();
    $form_state->setUserInput([]);
    $complete_form = [];
    $form_state->setCompleteForm($complete_form);
    $element = [
      '#parents' => ['item'],
      '#array_parents' => ['item'],
      '#default_value' => ['url' => 'https://example.com'],
    ];
    $carousel = CarouselItem::processCarouselItem($element, $form_state);
    $link = new Link([], 'link', ['label' => 'Link']);
    $link_form = $link->buildForm($element, $form_state);

    $validator = $this->container->get('file.validator');
    foreach ([$carousel['image'], $link_form['image']['image']] as $upload) {
      $constraints = $upload['#upload_validators'];
      $this->assertArrayHasKey('FileExtension', $constraints);
      $this->assertArrayNotHasKey('file_validate_extensions', $constraints);

      foreach (['jpg', 'jpeg', 'png', 'gif', 'pdf', 'php'] as $extension) {
        $file = File::create([
          'filename' => 'upload.' . $extension,
          'uri' => 'temporary://upload.' . $extension,
        ]);
        $violations = $validator->validate($file, $constraints);
        $expected_count = in_array($extension, ['pdf', 'php']) ? 1 : 0;
        $this->assertCount($expected_count, $violations, 'Extension: ' . $extension);
      }
    }
  }

}
