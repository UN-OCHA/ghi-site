<?php

namespace Drupal\Tests\common_design_subtheme\Unit;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Form\FormState;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the theme integration of the header search dropdown.
 */
#[Group('common_design_subtheme')]
class SearchHeaderThemeTest extends UnitTestCase {

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    require_once __DIR__ . '/../../../common_design_subtheme.theme';

    $container = new ContainerBuilder();
    $container->set('string_translation', $this->getStringTranslationStub());
    \Drupal::setContainer($container);
  }

  /**
   * Keeps the dropdown on the block, outside the form and contextual links.
   */
  public function testSearchDropdownWrapper(): void {
    $form = [
      '#id' => 'views-exposed-form-search-solr-page-search-results',
      '#attributes' => ['class' => ['views-exposed-form']],
    ];
    \common_design_subtheme_form_alter($form, new FormState(), 'views_exposed_form');

    $variables = [
      'plugin_id' => 'views_exposed_filter_block:search_solr-page_search_results',
      'attributes' => [
        'id' => 'block-exposedformsearch-solrpage-search-results',
        'class' => ['contextual-region'],
      ],
      'title_suffix' => ['contextual_links' => ['#type' => 'contextual_links']],
      'content' => $form,
    ];
    $title_suffix = $variables['title_suffix'];
    \common_design_subtheme_preprocess_block($variables);

    // The block ID also identifies the generated toggle on search result pages.
    $this->assertSame('block-exposedformsearch-solrpage-search-results', $variables['attributes']['id']);
    $this->assertContains('cd-search__form', $variables['attributes']['class']);
    $this->assertContains('contextual-region', $variables['attributes']['class']);
    $this->assertSame('Search', (string) $variables['attributes']['data-cd-toggable']);
    $this->assertSame('cd-search', $variables['attributes']['data-cd-component']);
    $this->assertSame('search', $variables['attributes']['role']);
    $this->assertSame($title_suffix, $variables['title_suffix']);
    $this->assertSame($form, $variables['content']);
    $this->assertNotContains('cd-search__form', $form['#attributes']['class']);
    $this->assertArrayNotHasKey('data-cd-toggable', $form['#attributes']);
    $this->assertContains('cd-search__input', $form['keywords']['#attributes']['class']);
    $this->assertContains('cd-search__submit', $form['actions']['submit']['#attributes']['class']);
  }

  /**
   * Leaves other exposed filter blocks without search dropdown attributes.
   */
  public function testOtherExposedFilterBlock(): void {
    $variables = [
      'plugin_id' => 'views_exposed_filter_block:other-page',
      'attributes' => ['class' => []],
      'content' => [],
    ];
    \common_design_subtheme_preprocess_block($variables);

    $this->assertNotContains('cd-search__form', $variables['attributes']['class']);
    $this->assertArrayNotHasKey('data-cd-toggable', $variables['attributes']);
  }

}
