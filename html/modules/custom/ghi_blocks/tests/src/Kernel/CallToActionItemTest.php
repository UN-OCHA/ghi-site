<?php

namespace Drupal\Tests\ghi_blocks\Kernel;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Access\AccessManagerInterface;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Form\FormElementHelper;
use Drupal\Core\Form\FormState;
use Drupal\Core\Url;
use Drupal\ghi_blocks\Plugin\ConfigurationContainerItem\CallToAction;
use Drupal\ghi_blocks_test\Form\CallToActionItemTestForm;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests call-to-action availability and non-blocking item editing.
 */
#[Group('ghi_blocks')]
class CallToActionItemTest extends BlockKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system', 'user', 'node', 'field', 'text', 'filter', 'path_alias', 'ghi_blocks_test'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    foreach (['user', 'node', 'path_alias'] as $type) {
      $this->installEntitySchema($type);
    }
    $this->installSchema('node', ['node_access']);
    $this->installConfig(['system', 'node', 'filter']);
    NodeType::create(['type' => 'page', 'name' => 'Page'])->save();
  }

  /**
   * Tests completeness, destination rules and denied-target cache metadata.
   */
  public function testAvailabilityReportsFieldIssuesAndPublicAccess(): void {
    $configuration = $this->createConfiguration();
    $cache = new CacheableMetadata();
    $availability = CallToAction::getAvailability($configuration, $cache);
    $this->assertInstanceOf(Url::class, $availability['url']);
    $this->assertSame([], $availability['issues']);
    $this->assertContains('route_match', $cache->getCacheTags());
    foreach (['label', 'description', 'button_label'] as $key) {
      $incomplete = $configuration;
      if ($key === 'label') {
        $incomplete['label'] = ' ';
      }
      else {
        $incomplete['value'][$key] = ' ';
      }
      $this->assertSame([$key], array_keys(CallToAction::getAvailability($incomplete)['issues']));
    }
    foreach (['javascript:alert(1)', 'entity:node', 'internal:/missing-cta'] as $uri) {
      $incomplete = $configuration;
      $incomplete['value']['url'] = $uri;
      $availability = CallToAction::getAvailability($incomplete);
      $this->assertNull($availability['url']);
      $this->assertSame(['url'], array_keys($availability['issues']));
    }
    $draft = Node::create(['type' => 'page', 'title' => 'Future campaign', 'status' => FALSE]);
    $draft->save();
    $configuration['value']['url'] = 'entity:node/' . $draft->id();
    $cache = new CacheableMetadata();
    $availability = CallToAction::getAvailability($configuration, $cache);
    $this->assertNull($availability['url']);
    $this->assertStringContainsString('unpublished content', (string) $availability['issues']['url'][0]);
    $this->assertContains('node:' . $draft->id(), $cache->getCacheTags());

    $access_manager = $this->createMock(AccessManagerInterface::class);
    $access_manager->method('checkNamedRoute')->willReturn(AccessResult::forbidden()->cachePerPermissions());
    $this->container->set('access_manager', $access_manager);
    $configuration['value']['url'] = 'internal:/admin';
    $cache = new CacheableMetadata();
    $availability = CallToAction::getAvailability($configuration, $cache);
    $this->assertNull($availability['url']);
    $this->assertStringContainsString('anonymous visitors', (string) $availability['issues']['url'][0]);
    $this->assertContains('user.permissions', $cache->getCacheContexts());
    $item = $this->container->get('plugin.manager.configuration_container_item_manager')->createInstance('call_to_action');
    $item->setConfig(['label' => '', 'value' => []]);
    $this->assertCount(4, $item->getConfigurationErrors());
    $this->assertSame('', $item->getStatus());
  }

  /**
   * Tests incomplete values remain savable and valid URLs are normalized.
   */
  public function testIncompleteItemCanBeSavedWithoutLosingClearedValues(): void {
    $cases = [
      'javascript:alert(1)' => 'javascript:alert(1)',
      ' https://example.org/updated ' => 'https://example.org/updated',
    ];
    foreach ($cases as $input_url => $expected_url) {
      $state = new FormState();
      $configuration = $this->createConfiguration();
      $fixture = new CallToActionItemTestForm($configuration, $this->container->get('plugin.manager.configuration_container_item_manager'));
      $builder = $this->container->get('form_builder');
      $form = $builder->buildForm($fixture, $state);
      $editor = $form['editor']['plugin_config'];
      $this->assertSame('Title', (string) $editor['label']['#title']);
      $this->assertFalse((bool) ($editor['label']['#required'] ?? FALSE));
      foreach (['description', 'url', 'button_label'] as $key) {
        $this->assertFalse((bool) ($editor['value'][$key]['#required'] ?? FALSE));
      }
      $this->assertSame([], FormElementHelper::getElementByName('nonexistent', $form));
      $incomplete = [
        'label' => '',
        'value' => ['description' => '', 'url' => $input_url, 'button_label' => '', 'show_heart' => FALSE],
      ];
      $values = ['op' => 'Save CTA', 'link_carousel_cta_feedback' => 'url'];
      NestedArray::setValue($values, CallToActionItemTestForm::CONFIG_PARENTS, $incomplete);
      $state->setTriggeringElement($form['actions']['submit']);
      $state->setValues($values);
      $builder->submitForm($fixture, $state);
      $this->assertSame([], $state->getErrors());
      $saved = $state->get('saved_cta');
      $this->assertSame('', $saved['label']);
      $this->assertSame('', $saved['value']['description']);
      $this->assertSame('', $saved['value']['button_label']);
      $this->assertFalse($saved['value']['show_heart']);
      $this->assertSame($expected_url, $saved['value']['url']);
      $this->assertArrayNotHasKey('link_carousel_cta_feedback', $saved);
      $this->assertArrayNotHasKey('feedback', $saved);
    }
  }

  /**
   * Tests native hidden-field requests refresh only the requested feedback.
   */
  public function testFeedbackReadsCurrentNestedInputWithoutSavingTheItem(): void {
    $state = new FormState();
    $configuration = $this->createConfiguration();
    $fixture = new CallToActionItemTestForm($configuration, $this->container->get('plugin.manager.configuration_container_item_manager'));
    $form = $this->container->get('form_builder')->buildForm($fixture, $state);
    $editor = $form['editor']['plugin_config'];
    $fields = ['label' => $editor['label']] + $editor['value'];
    foreach (['label', 'description', 'url', 'button_label'] as $key) {
      $this->assertArrayNotHasKey('#ajax', $fields[$key]);
      $this->assertSame($key, $fields[$key]['#wrapper_attributes']['data-link-carousel-cta-field']);
      $description = $fields[$key]['#description'];
      $description = is_array($description) ? $this->container->get('renderer')->renderInIsolation($description) : $description;
      $this->assertStringContainsString('data-link-carousel-cta-feedback', (string) $description);
    }
    $this->assertSame(['link_carousel_cta_feedback'], $editor['feedback']['#parents']);
    $this->assertSame('linkCarouselCtaFeedback', $editor['feedback']['#ajax']['event']);
    $this->assertSame('Show heart icon', (string) $editor['value']['show_heart']['#title']);
    $configuration['value']['description'] = 'A pending description';
    $changes = [
      ['label', '', TRUE],
      ['label', 'A corrected title', FALSE],
      ['url', 'javascript:alert(1)', TRUE],
      ['url', 'https://example.org/repaired', FALSE],
    ];
    foreach ($changes as [$key, $value, $unavailable]) {
      if ($key === 'label') {
        $configuration['label'] = $value;
      }
      else {
        $configuration['value'][$key] = $value;
      }
      $form = $this->processFeedbackRequest($fixture, $state, $configuration, $key);
      $this->assertFalse($state->isSubmitted());
      $this->assertFalse($state->isExecuted());
      $this->assertNull($state->get('saved_cta'));
      $this->assertSame([], $state->getErrors());
      $current = $state->getValue(CallToActionItemTestForm::CONFIG_PARENTS);
      $this->assertSame('A pending description', $current['value']['description']);
      $before_callback = $state->getValues();
      $this->assertFalse($state->isCached());
      $this->assertTrue($state->getRebuildInfo()['copy']['#build_id']);
      $commands = CallToAction::updateFeedback($form, $state)->getCommands();
      $this->assertCount(2, $commands);
      $this->assertSame('invoke', $commands[0]['command']);
      $this->assertSame('toggleClass', $commands[0]['method']);
      $this->assertSame(['link-carousel-editor__field--unavailable', $unavailable], $commands[0]['args']);
      $this->assertStringContainsString('#' . $form['#id'] . ':has(input[name="form_build_id"][value="' . $form['#build_id'] . '"]) #', $commands[0]['selector']);
      $this->assertStringContainsString('[data-link-carousel-cta-field="' . $key . '"]', $commands[0]['selector']);
      $this->assertSame('html', $commands[1]['method']);
      $this->assertSame($commands[0]['selector'] . ' [data-link-carousel-cta-feedback]', $commands[1]['selector']);
      $this->assertSame($unavailable, str_contains($commands[1]['data'], 'Not available:'));
      $this->assertStringNotContainsString('<input', $commands[1]['data']);
      $this->assertSame($before_callback, $state->getValues());
    }
    $form = $this->processFeedbackRequest($fixture, $state, $configuration, 'unknown');
    $this->assertSame([], CallToAction::updateFeedback($form, $state)->getCommands());
    $this->assertArrayNotHasKey('link_carousel_cta_feedback', $state->cleanValues()->getValues());
  }

  /**
   * Creates a complete CTA record without component selection metadata.
   */
  private function createConfiguration(): array {
    return [
      'label' => 'Support relief work',
      'value' => [
        'description' => 'Help organizations reach people affected by crises.',
        'url' => 'https://example.org/donate',
        'button_label' => 'Support now',
        'show_heart' => TRUE,
      ],
    ];
  }

  /**
   * Processes the same non-button request shape used by native AJAX.
   */
  private function processFeedbackRequest(CallToActionItemTestForm $fixture, FormState &$state, array $configuration, string $key): array {
    $previous = $state->getCompleteForm();
    $trigger = $previous['editor']['plugin_config']['feedback'];
    $next = new FormState();
    $next->setFormState($state->getCacheableArray());
    $next->setTemporary($state->getTemporary());
    $next->setProgrammed(FALSE)->setRequestMethod('POST')->disableRedirect();
    $input = [
      'form_id' => 'call_to_action_item_test',
      '_triggering_element_name' => $trigger['#name'],
      'link_carousel_cta_feedback' => $key,
    ];
    NestedArray::setValue($input, CallToActionItemTestForm::CONFIG_PARENTS, $configuration);
    if (isset($previous['form_token']['#value'])) {
      $input['form_token'] = $previous['form_token']['#value'];
    }
    $next->setUserInput($input);
    $builder = $this->container->get('form_builder');
    $form_id = $builder->getFormId($fixture, $next);
    $form = $builder->retrieveForm($form_id, $next);
    $form['#id'] = $previous['#id'];
    $builder->prepareForm($form_id, $form, $next);
    $builder->processForm($form_id, $form, $next);
    $state = $next;
    return $form;
  }

}
