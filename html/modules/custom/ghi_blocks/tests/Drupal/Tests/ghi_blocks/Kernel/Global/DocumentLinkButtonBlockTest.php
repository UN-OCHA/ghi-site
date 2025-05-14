<?php

namespace Drupal\Tests\ghi_blocks\Kernel\Global;

use Drupal\Core\Form\FormState;
use Drupal\ghi_blocks\Element\DocumentLink;
use Drupal\ghi_blocks\Plugin\Block\Generic\DocumentLinkButton;
use Drupal\Tests\ghi_blocks\Kernel\BlockKernelTestBase;

/**
 * Tests the document link button block plugin.
 *
 * @group ghi_blocks
 */
class DocumentLinkButtonBlockTest extends BlockKernelTestBase {

  /**
   * Tests the block properties.
   */
  public function testBlockProperties() {
    $plugin = $this->getBlockPlugin();
    $this->assertInstanceOf(DocumentLinkButton::class, $plugin);

    $definition = $plugin->getPluginDefinition();
    $this->assertFalse($definition['title']);
  }

  /**
   * Tests the block build.
   */
  public function testBlockBuild() {
    $document_link = $this->buildDocument('https://reliefweb.int/attachments/6dd62008-8c17-4c33-ba23-d2eeedbde292/Sudan%20Regional%20RRP%202024.pdf');
    $plugin = $this->getBlockPlugin($document_link);
    $build = $plugin->buildContent();
    $this->assertIsArray($build);
    $this->assertEquals('document_link_button', $build['#theme']);
    $this->assertEquals('Download report', $build['#button_label']);
    $this->assertEquals($document_link, $build['#document']);

    $button_label = $this->randomString();
    $plugin = $this->getBlockPlugin($document_link, $button_label);
    $build = $plugin->buildContent();
    $this->assertIsArray($build);
    $this->assertEquals('document_link_button', $build['#theme']);
    $this->assertEquals($button_label, $build['#button_label']);
    $this->assertEquals($document_link, $build['#document']);
  }

  /**
   * Tests the block forms.
   */
  public function testBlockForms() {
    $plugin = $this->getBlockPlugin();

    $form_state = new FormState();
    $form_state->set('block', $plugin);
    $form = $plugin->getConfigForm(['#parents' => []], $form_state);
    $this->assertArrayHasKey('button_label', $form);
    $this->assertArrayHasKey('document', $form);
  }

  /**
   * Get a block plugin.
   *
   * @param array $document_link
   *   The document link configuration to add to the plugin.
   * @param string $button_label
   *   The label for the button.
   *
   * @return \Drupal\ghi_blocks\Plugin\Block\Generic\DocumentLinkButton
   *   The block plugin.
   */
  private function getBlockPlugin($document_link = NULL, $button_label = NULL) {
    $configuration = [
      'document' => $document_link,
      'button_label' => $button_label,
    ];
    return $this->createBlockPlugin('generic_document_link_button', $configuration);
  }

  /**
   * Build a widget configuration.
   *
   * @param string $url
   *   The document url.
   *
   * @return array
   *   The configuration array for the document link.
   */
  private function buildDocument($url = NULL) {
    if (empty($url)) {
      return NULL;
    }
    $document_link = [
      'date' => '2024-02-06',
      'file_details' => array_map(function ($language) {
        return [
          'target_url' => '',
          'filetype' => '',
          'mimetype' => '',
          'filesize' => '',
          'disabled' => FALSE,
        ];
      }, DocumentLink::LANGUAGES),
    ];
    $document_link['file_details']['English']['target_url'] = $url;
    $document_link['file_details']['English']['filetype'] = 'pdf';
    $document_link['file_details']['English']['mimetype'] = 'application/pdf';
    $document_link['file_details']['English']['filesize'] = rand(100000, 100000000);
    return $document_link;
  }

}
