<?php

namespace Drupal\Tests\ghi_content\Traits;

use Drupal\Component\Plugin\PluginInspectionInterface;
use Drupal\Tests\pathauto\Functional\PathautoTestHelperTrait;
use Drupal\ghi_content\ContentManager\ArticleManager;
use Drupal\ghi_content\ContentManager\DocumentManager;
use Drupal\ghi_content\Entity\Article;
use Drupal\ghi_content\Entity\ContentBase;
use Drupal\ghi_content\Entity\Document;
use Drupal\ghi_content\Plugin\Block\Paragraph;
use Drupal\ghi_content\Plugin\RemoteSource\HpcContentModule;
use Drupal\ghi_content\RemoteContent\HpcContentModule\RemoteArticle;
use Drupal\ghi_content\RemoteContent\HpcContentModule\RemoteDocument;
use Drupal\ghi_content\RemoteContent\RemoteParagraphInterface;
use Drupal\layout_builder\Section;
use Drupal\layout_builder\SectionComponent;
use Drupal\node\Entity\NodeType;
use Prophecy\Argument;

/**
 * Provides methods to create content in tests.
 *
 * This trait is meant to be used only by test classes.
 */
trait ContentTestTrait {

  use PathautoTestHelperTrait;

  /**
   * Setup an article content type.
   */
  private function createArticleContentType() {
    $bundle = ArticleManager::ARTICLE_BUNDLE;
    NodeType::create([
      'type' => $bundle,
      'name' => 'Article page',
    ])->save();
    $this->createField('node', $bundle, 'ghi_remote_article', ArticleManager::REMOTE_ARTICLE_FIELD, 'Remote Article');
    $this->createEntityReferenceField('node', $bundle, 'field_tags', 'Tags', 'taxonomy_term');
    $pattern = $this->createPattern('node', '/article/[node:title]');
    $this->addBundleCondition($pattern, 'node', $bundle);
    $pattern->save();
  }

  /**
   * Setup a document content type.
   */
  private function createDocumentContentType() {
    $bundle = DocumentManager::DOCUMENT_BUNDLE;
    NodeType::create([
      'type' => $bundle,
      'name' => 'Document page',
    ])->save();
    $this->createField('node', $bundle, 'ghi_remote_document', DocumentManager::REMOTE_DOCUMENT_FIELD, 'Remote Document');
    $this->createEntityReferenceField('node', $bundle, 'field_tags', 'Tags', 'taxonomy_term');
    $pattern = $this->createPattern('node', '/document/[node:title]');
    $this->addBundleCondition($pattern, 'node', $bundle);
    $pattern->save();
  }

  /**
   * Create an article.
   */
  public function createArticle(array $values = []) {
    $values += [
      'type' => ArticleManager::ARTICLE_BUNDLE,
      'title' => $this->randomString(),
    ];
    $article = Article::create($values);
    $this->assertSame(SAVED_NEW, $article->save());
    $this->assertInstanceOf(ContentBase::class, $article);
    return $article;
  }

  /**
   * Create a document.
   */
  private function createDocument(array $values = []) {
    $values += [
      'type' => DocumentManager::DOCUMENT_BUNDLE,
      'title' => $this->randomString(),
    ];
    $document = Document::create($values);
    $this->assertSame(SAVED_NEW, $document->save());
    $this->assertInstanceOf(ContentBase::class, $document);
    return $document;
  }

  /**
   * Mock a remote document.
   *
   * @param array $data
   *   Optional document data.
   *
   * @return \Drupal\ghi_content\RemoteContent\RemoteDocumentInterface
   *   A remote document object.
   */
  public function mockRemoteDocument(?array $data = []) {
    // Mock the remote source.
    $remote_source = $this->createMock(HpcContentModule::class);

    // Mock the document to be imported.
    return new RemoteDocument((object) ($data + [
      'id' => 42,
      'title' => 'Nigeria',
      'title_short' => 'Nigeria',
    ]), $remote_source);
  }

  /**
   * Mock a remote article.
   *
   * @param array $data
   *   Optional article data.
   *
   * @return \Drupal\ghi_content\RemoteContent\RemoteArticleInterface
   *   A remote article object.
   */
  public function mockRemoteArticle(?array $data = []) {
    // Mock the remote source.
    $remote_source = $this->createMock(HpcContentModule::class);

    // Mock the article to be imported.
    return new RemoteArticle((object) ($data + [
      'id' => 42,
      'title' => 'Nigeria',
      'title_short' => 'Nigeria',
    ]), $remote_source);
  }

  /**
   * Mock a remote article with paragraphs.
   *
   * @param int $count_paragraphs
   *   The number of paragraphs to create.
   *
   * @return \Drupal\ghi_content\RemoteContent\RemoteArticleInterface
   *   A remote article object.
   */
  public function mockRemoteArticleWithParagraphs(int $count_paragraphs) {
    // Mock the remote source.
    $remote_source = $this->createMock(HpcContentModule::class);

    // Mock the article to be imported.
    return new RemoteArticle((object) [
      'id' => 42,
      'title' => 'Nigeria',
      'title_short' => 'Nigeria',
      'content' => array_filter([
        $count_paragraphs >= 1 ? (object) [
          'id' => 163,
          'uuid' => 'b02368e8-e310-4415-af81-feeacb8314c7',
          'type' => 'bottom_figure_row',
          'typeLabel' => 'Bottom figure row',
          'rendered' => "\n  <div class=\"paragraph paragraph--type--bottom-figure-row paragraph--view-mode--top-figures gho-needs-and-requirements-paragraph content-width\">\n          <div class=\"gho-needs-and-requirements gho-figures gho-figures--large\">\n  <div class=\"gho-needs-and-requirements-figure gho-figure\">\n    <div class=\"gho-needs-and-requirements-figure__label gho-figure__label\">People in need</div>\n    <div class=\"gho-needs-and-requirements-figure__value gho-figure__value\">8.3 million</div>\n  </div>\n  <div class=\"gho-needs-and-requirements-figure gho-figure\">\n    <div class=\"gho-needs-and-requirements-figure__label gho-figure__label\">People targeted</div>\n    <div class=\"gho-needs-and-requirements-figure__value gho-figure__value\">5.4 million</div>\n  </div>\n  <div class=\"gho-needs-and-requirements-figure gho-figure\">\n    <div class=\"gho-needs-and-requirements-figure__label gho-figure__label\">Requirements (US$)</div>\n    <div class=\"gho-needs-and-requirements-figure__value gho-figure__value\">1.1 billion</div>\n  </div>\n</div>\n\n      </div>\n",
        ] : NULL,
        $count_paragraphs >= 2 ? (object) [
          'id' => 548,
          'uuid' => '2e959116-5a44-4271-9070-e44de5d0f32f',
          'type' => 'text',
          'typeLabel' => 'Text',
          'rendered' => "\n  <div class=\"paragraph paragraph--type--text paragraph--view-mode--default gho-text content-width\">\n          <div class=\"gho-text__text\"><gho-footnotes-text data-id=\"paragraph-548\"><p>1</p>\n</gho-footnotes-text></div>\n\n      </div>\n",
        ] : NULL,
        $count_paragraphs >= 3 ? (object) [
          'id' => 734,
          'uuid' => '00ff8416-6571-4bfe-aaec-1d522a0bcc67',
          'type' => 'text',
          'typeLabel' => 'Text',
          'rendered' => "\n  <div class=\"paragraph paragraph--type--text paragraph--view-mode--default gho-text content-width\">\n          <div class=\"gho-text__text\"><gho-footnotes-text data-id=\"paragraph-734\"><p>2</p>\n</gho-footnotes-text></div>\n\n      </div>\n",
        ] : NULL,
        $count_paragraphs >= 4 ? (object) [
          'id' => 821,
          'uuid' => 'af69b1b3-977d-420f-8092-a47fd45f7884',
          'type' => 'text',
          'typeLabel' => 'Text',
          'rendered' => "\n  <div class=\"paragraph paragraph--type--text paragraph--view-mode--default gho-text content-width\">\n          <div class=\"gho-text__text\"><gho-footnotes-text data-id=\"paragraph-821\"><p>3</p>\n</gho-footnotes-text></div>\n\n      </div>\n",
        ] : NULL,
      ]),
    ], $remote_source);
  }

  /**
   * Mock a paragraph plugin.
   *
   * @param \Drupal\ghi_content\RemoteContent\RemoteParagraphInterface $remote_paragraph
   *   A remote paragraph object.
   *
   * @return \Drupal\ghi_content\Plugin\Block\Paragraph
   *   A paragraph block plugin.
   */
  private function mockParagraphPlugin(RemoteParagraphInterface $remote_paragraph) {
    // Paragraph element.
    $paragraph = $this->prophesize(Paragraph::class);
    $paragraph->getPluginId()->willReturn('paragraph');
    $paragraph->getParagraph()->willReturn($remote_paragraph);
    return $paragraph->reveal();
  }

  /**
   * Mock a remote paragraph object.
   *
   * @param int $paragraph_id
   *   The paragraph id.
   *
   * @return \Drupal\ghi_content\RemoteContent\RemoteParagraphInterface
   *   A remote paragraph object.
   */
  private function mockRemoteParagraph($paragraph_id) {
    $remote_paragraph = $this->prophesize(RemoteParagraphInterface::class);
    $remote_paragraph->getId()->willReturn($paragraph_id);
    return $remote_paragraph->reveal();
  }

  /**
   * Add a component for a plugin to a section.
   *
   * @param \Drupal\Component\Plugin\PluginInspectionInterface $plugin
   *   The plugin for the section component.
   * @param string $uuid
   *   The UUID for the new component.
   * @param \Drupal\layout_builder\SectionComponent[] $section_components
   *   The stored components for the section.
   *
   * @return \Drupal\layout_builder\Section
   *   The section object.
   */
  private function addPluginComponentToSection($plugin, $uuid, &$section_components = []) {
    if (is_array($plugin)) {
      $weight = $plugin['weight'];
      $plugin = $plugin['plugin'];
    }
    else {
      $weight = count($section_components);
    }
    $component = $this->mockSectionComponentWithPlugin($uuid, $weight, $plugin);
    $section_components[$component->getUuid()] = $component;
    return $this->mockSectionWithComponents($section_components);
  }

  /**
   * Mock a section object with components.
   *
   * @param \Drupal\layout_builder\SectionComponent[] $section_components
   *   The components for the section.
   *
   * @return \Drupal\layout_builder\Section
   *   The section object.
   */
  private function mockSectionWithComponents(array $section_components) {
    $section = $this->prophesize(Section::class);
    $section->getComponents()->willReturn($section_components);
    foreach ($section_components as $section_component) {
      $section->getComponent($section_component->getUuid())->willReturn($section_component);
    }
    return $section->reveal();
  }

  /**
   * Mock a section component with a plugin.
   *
   * @param string $uuid
   *   The UUID for the new component.
   * @param int $weight
   *   The weight for the component.
   * @param \Drupal\Component\Plugin\PluginInspectionInterface $plugin
   *   The plugin for the section component.
   *
   * @return \Drupal\layout_builder\SectionComponent
   *   A section component object.
   */
  private function mockSectionComponentWithPlugin($uuid, $weight, PluginInspectionInterface $plugin) {
    $uuid = $uuid ?? $weight . '-' . $this->randomString();
    $component = $this->prophesize(SectionComponent::class);
    $component->getWeight()->willReturn($weight);
    $component->getUuid()->willReturn($uuid);
    $component->getPlugin()->willReturn($plugin);
    $component->setWeight(Argument::any())->shouldBeCalled();
    return $component->reveal();
  }

  /**
   * Mock a section object with components.
   *
   * @param \Drupal\Component\Plugin\PluginInspectionInterface[] $plugins
   *   The plugins for the section.
   * @param \Drupal\layout_builder\SectionComponent[] $section_components
   *   The stored components for the section.
   *
   * @return \Drupal\layout_builder\Section
   *   The section object.
   */
  private function mockSectionWithPlugins($plugins, &$section_components = []) {
    $section = NULL;
    foreach ($plugins as $uuid => $plugin) {
      $section = $this->addPluginComponentToSection($plugin, $uuid, $section_components);
    }
    return $section;
  }

}
