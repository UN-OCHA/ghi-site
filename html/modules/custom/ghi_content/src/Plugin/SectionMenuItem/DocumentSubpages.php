<?php

namespace Drupal\ghi_content\Plugin\SectionMenuItem;

use Drupal\Core\Form\FormStateInterface;
use Drupal\ghi_content\Entity\Document;
use Drupal\ghi_sections\Entity\SectionNodeInterface;
use Drupal\ghi_sections\Menu\OptionalSectionMenuPluginInterface;
use Drupal\ghi_sections\Menu\SectionMenuItem;
use Drupal\ghi_sections\Menu\SectionMenuPluginBase;
use Drupal\ghi_sections\MenuItemType\SectionDropdown;
use Drupal\ghi_sections\MenuItemType\SectionMegaMenu;
use Drupal\hpc_common\Helpers\RequestHelper;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a document subpages item for section menus.
 *
 * @SectionMenuPlugin(
 *   id = "document_subpages",
 *   label = @Translation("Document subpages"),
 *   description = @Translation("This item links to a document of a section."),
 *   weight = 3,
 * )
 */
class DocumentSubpages extends SectionMenuPluginBase implements OptionalSectionMenuPluginInterface {

  /**
   * The subpage manager.
   *
   * @var \Drupal\ghi_content\ContentManager\DocumentManager
   */
  protected $documentManager;

  /**
   * The node type.
   *
   * @var string
   */
  protected $documentId;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->documentManager = $container->get('ghi_content.manager.document');
    $instance->documentId = $configuration['document_id'] ?? NULL;
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function getLabel() {
    $document = $this->getDocument();
    return $document?->getShortTitle() ?? $document?->label();
  }

  /**
   * {@inheritdoc}
   */
  public function getItem() {
    $document = $this->getDocument();
    if (!$document) {
      return NULL;
    }
    $item = new SectionMenuItem($this->getPluginId(), $this->getSection()->id(), $this->getLabel(), $this->getPluginConfiguration()['configuration'] ?? []);
    return $item;
  }

  /**
   * {@inheritdoc}
   */
  public function getWidget() {
    $document = $this->getDocument();
    $current_page_node = RequestHelper::getCurrentNodeObject();
    if (!$document) {
      return NULL;
    }
    $item = $this->getItem();
    $widget = NULL;
    $chapters = $document->getChapters(FALSE);
    if (count($chapters) == 1) {
      $chapter = reset($chapters);
      $nodes = $document->getChapterArticles($chapter);
      $widget = new SectionDropdown($item->getLabel(), $nodes, $document->toLink($this->t('Outline')));
    }
    else {
      $nodes_grouped = [];
      foreach ($chapters as $chapter) {
        $chapter_link = $document->toLink($chapter->getShortTitle(), 'canonical', [
          'fragment' => 'chapter-' . $document->getChapterNumber($chapter),
        ])->toString();
        $nodes_grouped[(string) $chapter_link] = $document->getChapterArticles($chapter);
      }
      $widget_header = [
        [
          '#type' => 'html_tag',
          '#tag' => 'h3',
          'document' => $document->toLink($document->label())->toRenderable(),
        ],
        [
          '#theme' => 'item_list',
          '#attributes' => ['class' => ['metadata']],
          '#items' => $document->getPageMetaData(FALSE),
          '#full_width' => TRUE,
        ],
      ];
      $widget = new SectionMegaMenu($item->getLabel(), $nodes_grouped, $widget_header, $item->getConfiguration() ?? []);
      if ($current_page_node) {
        $same_page = $current_page_node->toUrl() == $document->toUrl();
        $contained_page = strpos($current_page_node->toUrl()->toString(), $document->toUrl()->toString()) === 0;
        $widget->setActive($same_page || $contained_page);
      }
    }
    $widget->setCacheTags($document->getCacheTags());
    $widget->setProtected($document->isProtected());
    return $widget;
  }

  /**
   * {@inheritdoc}
   */
  public function getStatus() {
    return $this->getDocument()?->isPublished();
  }

  /**
   * {@inheritdoc}
   */
  public function isValid() {
    return $this->getDocument() instanceof Document;
  }

  /**
   * Get the document for the current menu item.
   *
   * @return \Drupal\ghi_content\Entity\Document|null
   *   The document node if found, or NULL otherwise.
   */
  private function getDocument() {
    if (!$this->documentId) {
      return NULL;
    }
    $document = $this->entityTypeManager->getStorage('node')->load($this->documentId);
    if (!$document instanceof Document) {
      return NULL;
    }
    $section = $this->getSection();
    if ($section && $section instanceof SectionNodeInterface) {
      $document->setContextNode($section);
    }
    return $document;
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm($form, FormStateInterface $form_state) {
    $options = $this->getDocumentOptions();
    $form['document_id'] = [
      '#type' => 'select',
      '#title' => $this->t('Document'),
      '#options' => $options,
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function isAvailable() {
    return !empty($this->getDocumentOptions());
  }

  /**
   * Get the options for the document select.
   *
   * @return string[]
   *   An array with document labels as options, keyed by the document id.
   */
  private function getDocumentOptions() {
    $section = $this->getSection();
    $documents = $this->documentManager->loadNodesForSection($section);

    $options = array_map(function (Document $document) {
      return $document->getShortTitle() ?? $document->label();
    }, $documents);

    /** @var \Drupal\ghi_sections\Field\SectionMenuItemList $menu_item_list */
    $menu_item_list = clone $this->sectionMenuStorage->getSectionMenuItems();
    $exclude_document_ids = [];
    foreach ($menu_item_list->getAll() as $menu_item) {
      $plugin = $menu_item->getPlugin();
      if (!$plugin instanceof self || !$plugin->getDocument()) {
        continue;
      }
      if ($plugin->getSection()->id() == $section->id() && array_key_exists($plugin->getDocument()->id(), $documents)) {
        $data = $menu_item->toArray();
        $document_id = $data['configuration']['document_id'];
        $exclude_document_ids[$document_id] = $document_id;
      }
    }
    $options = array_diff_key($options, $exclude_document_ids);

    return $options;
  }

}
