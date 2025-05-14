<?php

namespace Drupal\Tests\ghi_content\Functional;

use Drupal\Component\Serialization\Json;
use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\field\Traits\EntityReferenceFieldCreationTrait;
use Drupal\Tests\ghi_base_objects\Traits\FieldTestTrait;
use Drupal\Tests\ghi_content\Traits\ContentTestTrait;
use Drupal\Tests\taxonomy\Traits\TaxonomyTestTrait;
use Drupal\ghi_content\ContentManager\ArticleManager;
use Drupal\node\Entity\Node;

/**
 * Tests the article wizard pages.
 *
 * @group ghi_sections
 */
class ArticleWizardTest extends BrowserTestBase {

  use EntityReferenceFieldCreationTrait;
  use TaxonomyTestTrait;
  use FieldTestTrait;
  use ContentTestTrait;

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

    $this->createArticleContentType();

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
    $autocomplete_url = $this->getAbsoluteUrl('/content/remote/hpc_content_module_test/search-article');
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
    $this->assertSession()->pageTextNotContains('No remote sources found. You must create at least one remote source before creating an Article page.');
    $this->assertSession()->pageTextNotContains('Type the title of an article to see suggestions.');
    $this->assertSession()->pageTextNotContains('Optional: Change the title for this article page.');

    $this->assertSession()->elementExists('css', 'select[data-drupal-selector="edit-source"]')->selectOption('hpc_content_module_test');
    $this->assertSession()->buttonExists('Next')->click();

    $this->assertSession()->pageTextContains('Type the title of an article to see suggestions.');
    $this->assertSession()->elementExists('css', 'input[data-drupal-selector="edit-article"]');

    $article_input = $this->getSession()->getPage()->findField('article');
    $this->assertEquals($autocomplete_url, $this->getAbsoluteUrl($article_input->getAttribute('data-autocomplete-path')));

    $this->getSession()->getPage()->fillField('article', $data[0]['value']);
    $this->assertSession()->buttonExists('Next')->click();

    $this->assertSession()->pageTextContains('Optional: Change the title for this Article page.');
    $this->assertSession()->elementExists('css', 'input[data-drupal-selector="edit-title"]');
    $this->assertSession()->buttonExists('Create Article page')->click();

    $this->assertSession()->pageTextContains('Created Article page for ' . $data[0]['label']);
  }

  /**
   * Tests that the wizard pages can be accessed.
   */
  public function testArticleDuplicateRejected() {

    // Fetch the autocomplete results first.
    $autocomplete_url = $this->getAbsoluteUrl('/content/remote/hpc_content_module_test/search-article');
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
          'remote_source' => 'hpc_content_module_test',
          'article_id' => 1,
        ],
      ],
    ])->save();

    $this->drupalGet('/node/add/article');

    $this->assertSession()->elementExists('css', 'select[data-drupal-selector="edit-source"]')->selectOption('hpc_content_module_test');
    $this->assertSession()->buttonExists('Next')->click();

    $this->getSession()->getPage()->fillField('article', $data[0]['value']);
    $this->assertSession()->buttonExists('Next')->click();

    $this->assertSession()->pageTextContains('An article page for the selected article already exists');
  }

}
