<?php

namespace Drupal\Tests\ghi_content\Functional;

use Drupal\Component\Serialization\Json;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\ghi_content\ContentManager\ArticleManager;
use Drupal\node\Entity\Node;
use Drupal\taxonomy\Entity\Term;
use Drupal\taxonomy\Entity\Vocabulary;
use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\field\Traits\EntityReferenceTestTrait;
use Drupal\Tests\taxonomy\Traits\TaxonomyTestTrait;

/**
 * Tests the node wizard pages.
 *
 * @group ghi_sections
 */
class ArticleWizardTest extends BrowserTestBase {

  use EntityReferenceTestTrait;
  use TaxonomyTestTrait;

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = [
    'ghi_content_test',
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

    $this->setupContent();

    // Create a user with permission to view the actions administration pages.
    $this->drupalLogin($this->drupalCreateUser([
      'administer nodes',
      'bypass node access',
      'access remote content',
    ]));
  }

  /**
   * Tests that the wizard pages can be accessed.
   */
  public function testArticleWizard() {
    // Fetch the autocomplete results first.
    $autocomplete_url = $this->getAbsoluteUrl('/content/remote/gho_ncms_test/search-article');
    $autocomplete_result = $this->drupalGet($autocomplete_url, [
      'query' => [
        'q' => 'Global',
        '_format' => 'json',
      ],
    ]);
    $this->assertNotEmpty($autocomplete_result);
    $data = Json::decode($autocomplete_result);
    $this->assertNotEmpty($data);

    $this->drupalGet('/node/add/article');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextNotContains('No remote sources found. You must create at least one remote source before articles can be created.');
    $this->assertSession()->pageTextNotContains('No teams found. You must import teams before sections can be created.');
    $this->assertSession()->pageTextNotContains('Type the title of an article to see suggestions.');
    $this->assertSession()->pageTextNotContains('Select the team that will be responsible for this article.');
    $this->assertSession()->pageTextNotContains('Optional: Change the title for this article.');

    $this->assertSession()->elementExists('css', 'select[data-drupal-selector="edit-source"]')->selectOption('gho_ncms_test');
    $this->assertSession()->buttonExists('Next')->click();

    $this->assertSession()->pageTextContains('Type the title of an article to see suggestions.');
    $this->assertSession()->elementExists('css', 'input[data-drupal-selector="edit-article"]');

    $article_input = $this->getSession()->getPage()->findField('article');
    $this->assertEquals($autocomplete_url, $this->getAbsoluteUrl($article_input->getAttribute('data-autocomplete-path')));

    $this->getSession()->getPage()->fillField('article', $data[0]['value']);
    $this->assertSession()->buttonExists('Next')->click();

    $this->assertSession()->pageTextContains('Select the team that will be responsible for this article.');
    $this->assertSession()->elementExists('css', 'select[data-drupal-selector="edit-team"]');
    $this->assertSession()->buttonExists('Next')->click();

    $this->assertSession()->pageTextContains('Optional: Change the title for this article.');
    $this->assertSession()->elementExists('css', 'input[data-drupal-selector="edit-title"]');
    $this->assertSession()->buttonExists('Create article')->click();

    $this->assertSession()->pageTextContains('Created Article for ' . $data[0]['label']);
  }

  /**
   * Tests that the wizard pages can be accessed.
   */
  public function testArticleDuplicateRejected() {

    // Fetch the autocomplete results first.
    $autocomplete_url = $this->getAbsoluteUrl('/content/remote/gho_ncms_test/search-article');
    $autocomplete_result = $this->drupalGet($autocomplete_url, [
      'query' => [
        'q' => 'Global',
        '_format' => 'json',
      ],
    ]);
    $this->assertNotEmpty($autocomplete_result);
    $data = Json::decode($autocomplete_result);
    $this->assertNotEmpty($data);

    Node::create([
      'type' => ArticleManager::ARTICLE_BUNDLE,
      'title' => $data[0]['label'],
      ArticleManager::REMOTE_ARTICLE_FIELD => [
        0 => [
          'remote_source' => 'gho_ncms_test',
          'article_id' => 1,
        ],
      ],
    ])->save();

    $this->drupalGet('/node/add/article');

    $this->assertSession()->elementExists('css', 'select[data-drupal-selector="edit-source"]')->selectOption('gho_ncms_test');
    $this->assertSession()->buttonExists('Next')->click();

    $this->getSession()->getPage()->fillField('article', $data[0]['value']);
    $this->assertSession()->buttonExists('Next')->click();

    $this->assertSession()->pageTextContains('An article page for the selected article already exists');
  }

  /**
   * Creates the testing fields.
   */
  protected function createField($entity_type, $bundle, $field_type, $field_name, $field_label) {
    if (!FieldStorageConfig::loadByName($entity_type, $field_name)) {
      $field_storage = FieldStorageConfig::create([
        'type' => $field_type,
        'entity_type' => $entity_type,
        'field_name' => $field_name,
      ]);
      $field_storage->save();
    }
    FieldConfig::create([
      'field_name' => $field_name,
      'entity_type' => $entity_type,
      'bundle' => $bundle,
      'label' => $field_label,
    ])->save();

  }

  /**
   * Setup content types and content for these tests.
   */
  private function setupContent() {
    $this->drupalCreateContentType([
      'type' => ArticleManager::ARTICLE_BUNDLE,
      'name' => 'Article',
    ]);
    $this->createField('node', ArticleManager::ARTICLE_BUNDLE, 'ghi_remote_article', ArticleManager::REMOTE_ARTICLE_FIELD, 'Remote Article');

    // Create team vocabulary and fields.
    Vocabulary::create([
      'vid' => 'team',
      'name' => 'Team',
    ])->save();
    $handler_settings = [
      'target_bundles' => [
        'team' => 'team',
      ],
    ];
    $this->createEntityReferenceField('node', ArticleManager::ARTICLE_BUNDLE, 'field_team', 'Team', 'taxonomy_term', 'default', $handler_settings);
    Term::create([
      'name' => $this->randomMachineName(),
      'vid' => 'team',
    ])->save();
  }

}
