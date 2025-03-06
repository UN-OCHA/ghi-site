<?php

namespace Drupal\Tests\ghi_blocks\Kernel\Global;

use Drupal\Core\Form\FormState;
use Drupal\Core\Url;
use Drupal\ghi_blocks\Element\DocumentLink;
use Drupal\ghi_blocks\Interfaces\ConfigurableTableBlockInterface;
use Drupal\ghi_blocks\Interfaces\MultiStepFormBlockInterface;
use Drupal\ghi_blocks\Interfaces\OverrideDefaultTitleBlockInterface;
use Drupal\ghi_blocks\Plugin\Block\Generic\DocumentLinks;
use Drupal\Tests\ghi_blocks\Kernel\BlockKernelTestBase;

/**
 * Tests the document links block plugin.
 *
 * @group ghi_blocks
 */
class DocumentLinksBlockTest extends BlockKernelTestBase {

  /**
   * Tests the block properties.
   */
  public function testBlockProperties() {
    $plugin = $this->getBlockPlugin();
    $this->assertInstanceOf(DocumentLinks::class, $plugin);
    $this->assertInstanceOf(MultiStepFormBlockInterface::class, $plugin);
    $this->assertInstanceOf(OverrideDefaultTitleBlockInterface::class, $plugin);
    $this->assertInstanceOf(ConfigurableTableBlockInterface::class, $plugin);

    $allowed_item_types = $plugin->getAllowedItemTypes();
    $this->assertCount(2, $allowed_item_types);
    $this->assertArrayHasKey('item_group', $allowed_item_types);
    $this->assertArrayHasKey('document_link', $allowed_item_types);

    $definition = $plugin->getPluginDefinition();
    $this->assertArrayHasKey($plugin->getDefaultSubform(), $definition['config_forms']);
    $this->assertArrayHasKey($plugin->getTitleSubform(), $definition['config_forms']);
  }

  /**
   * Tests the block build.
   */
  public function testBlockBuild() {
    $documents = $this->buildDocuments(['https://reliefweb.int/attachments/6dd62008-8c17-4c33-ba23-d2eeedbde292/Sudan%20Regional%20RRP%202024.pdf']);
    $plugin = $this->getBlockPlugin($documents);
    $build = $plugin->buildContent();
    $this->assertCount(1, $build);
    $this->assertEquals('tab_container', $build[0]['#theme']);
    $this->assertEquals('Related documents', $build[0]['#tabs'][0]['title']['#markup']);

    $plugin = $this->getBlockPlugin($documents, 'https://reliefweb.int');
    $build = $plugin->buildContent();
    $this->assertCount(2, $build);
    $this->assertIsArray($build[1]);
    $this->assertEquals('link', $build[1]['#type']);
    $this->assertEquals('View all publications', $build[1]['#title']);
    $this->assertInstanceOf(Url::class, $build[1]['#url']);
  }

  /**
   * Tests the block build with no documents.
   */
  public function testBlockBuildNoDocuments() {
    $plugin = $this->getBlockPlugin();
    $build = $plugin->buildContent();
    $this->assertNull($build);

    $plugin = $this->getBlockPlugin($this->buildDocuments());
    $build = $plugin->buildContent();
    $this->assertNull($build);
  }

  /**
   * Tests the block forms.
   */
  public function testBlockForms() {
    $plugin = $this->getBlockPlugin();

    $form_state = new FormState();
    $form_state->set('block', $plugin);
    $form = $plugin->documentsForm(['#parents' => []], $form_state);
    $this->assertArrayHasKey('documents', $form);

    $form = $plugin->displayForm(['#parents' => []], $form_state);
    $this->assertArrayHasKey('publications_url', $form);
  }

  /**
   * Get a block plugin.
   *
   * @param array $documents
   *   The documents configuration to add to the plugin.
   * @param string $publications_url
   *   The url where external publications can be found.
   *
   * @return \Drupal\ghi_blocks\Plugin\Block\Generic\DocumentLinks
   *   The block plugin.
   */
  private function getBlockPlugin($documents = [], $publications_url = '') {
    $configuration = [
      'documents' => [
        'documents' => $documents,
      ],
      'display' => [
        'publications_url' => $publications_url,
      ],
    ];
    return $this->createBlockPlugin('generic_document_links', $configuration);
  }

  /**
   * Build a widget configuration.
   *
   * @param string[] $urls
   *   The document urls.
   *
   * @return array
   *   The configuration array for the documents.
   */
  private function buildDocuments($urls = []) {
    if (empty($urls)) {
      return [];
    }
    $documents = [
      [
        'item_type' => 'item_group',
        'id' => 0,
        'config' => [
          'label' => 'Related documents',
        ],
        'weight' => 0,
        'pid' => NULL,
      ],
    ];
    foreach ($urls as $url) {
      $document_link = [
        'item_type' => 'document_link',
        'id' => count($documents),
        'weight' => count($documents),
        'pid' => 0,
        'config' => [
          'label' => $this->randomString(),
          'value' => [
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
          ],
        ],
      ];
      $document_link['config']['value']['file_details']['English']['target_url'] = $url;
      $document_link['config']['value']['file_details']['English']['filetype'] = 'pdf';
      $document_link['config']['value']['file_details']['English']['mimetype'] = 'application/pdf';
      $document_link['config']['value']['file_details']['English']['filesize'] = rand(100000, 100000000);
      $documents[] = $document_link;
    }
    return $documents;
  }

}
