<?php

namespace Drupal\Tests\ghi_blocks\Unit;

use Drupal\Core\Form\FormState;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\ghi_blocks\Plugin\ConfigurationContainerItem\FundingData;
use Drupal\ghi_blocks\Plugin\ConfigurationContainerItem\PlanMetricData;
use Drupal\ghi_plans\Entity\Plan;
use Drupal\ghi_plans\Helpers\FinancialLabel;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests plan-language defaults without changing editor-facing translations.
 */
#[Group('ghi_blocks')]
class PlanLanguageDefaultLabelsTest extends UnitTestCase {

  /**
   * Tests the financial data form's editor title and frontend default.
   */
  public function testFundingDataDefaultUsesPlanLanguage(): void {
    $translation_options = [];
    $plan = $this->mockPlan('fr');
    $item = new FundingData([
      'cluster_restrict' => FALSE,
      'value_preview' => FALSE,
    ], 'funding_data', []);
    $item->setConfig(['data_type' => 'current_requirements']);
    $item->setContext([
      'base_object' => $plan,
      'plan_object' => $plan,
    ]);
    $item->setStringTranslation($this->mockTranslation($translation_options));

    $form = $item->buildForm([
      '#parents' => ['columns', 0],
      '#array_parents' => ['columns', 0],
    ], new FormState());

    $this->assertSame('Current requirements', (string) $form['data_type']['#options']['current_requirements']);
    $this->assertSame('Besoins financiers', $form['label']['#placeholder']);
    $this->assertStringContainsString('<br>', (string) $form['label']['#description']);
    $this->assertStringContainsString('Do not include a currency unit such as "($)" in a table column label.', (string) $form['label']['#description']);
    $this->assertSame('currency', $item->getColumnType());
    $this->assertContains([], $translation_options['Current requirements']);
    $this->assertContains([
      'langcode' => 'fr',
      'context' => FinancialLabel::TRANSLATION_CONTEXT,
    ], $translation_options['Requirements']);

    $headline_form = $item->buildForm([
      '#parents' => ['items', 0],
      '#array_parents' => ['items', 0],
    ], new FormState());
    $this->assertStringNotContainsString('Do not include a currency unit', (string) $headline_form['label']['#description']);

    $item->setConfig(['data_type' => 'funding_coverage']);
    $this->assertSame('percentage', $item->getColumnType());
    $item->setConfig(['data_type' => 'funding_progress_bar']);
    $this->assertSame('percentage', $item->getColumnType());
  }

  /**
   * Tests plan metrics keep their editor options separate from defaults.
   */
  public function testPlanMetricDefaultUsesPlanLanguage(): void {
    $translation_options = [];
    $item = new PlanMetricData([], 'plan_metric_data', []);
    $item->setConfig(['metric' => 'requirements']);
    $item->setContext(['plan_object' => $this->mockPlan('fr')]);
    $item->setStringTranslation($this->mockTranslation($translation_options));

    $this->assertSame('Besoins financiers', (string) $item->getDefaultLabel());

    $method = new \ReflectionMethod(PlanMetricData::class, 'getMetricOptions');
    $metric_options = $method->invoke($item);
    $this->assertSame('Requirements ($)', (string) $metric_options['Financial data']['requirements']);
    $this->assertContains([
      'langcode' => 'fr',
      'context' => FinancialLabel::TRANSLATION_CONTEXT,
    ], $translation_options['Requirements']);
    $this->assertContains([
      'context' => FinancialLabel::TRANSLATION_CONTEXT,
    ], $translation_options['Requirements']);
  }

  /**
   * Mocks a plan with a language and plan bundle.
   */
  private function mockPlan(string $langcode): Plan {
    $plan = $this->getMockBuilder(Plan::class)
      ->disableOriginalConstructor()
      ->onlyMethods(['getPlanLanguage', 'bundle'])
      ->getMock();
    $plan->method('getPlanLanguage')->willReturn($langcode);
    $plan->method('bundle')->willReturn('plan');
    return $plan;
  }

  /**
   * Mocks translations while recording the supplied translation options.
   */
  private function mockTranslation(array &$translation_options): TranslationInterface {
    $translation = $this->createMock(TranslationInterface::class);
    $resolve_translation = function ($string, array $args = [], array $options = []) use (&$translation_options): string {
      $translation_options[$string][] = $options;
      if ($string === 'Requirements' && ($options['context'] ?? NULL) === FinancialLabel::TRANSLATION_CONTEXT && ($options['langcode'] ?? 'en') === 'fr') {
        return 'Besoins financiers';
      }
      return strtr($string, $args);
    };
    $translation->method('translate')->willReturnCallback(function ($string, array $args = [], array $options = []) use ($translation): TranslatableMarkup {
      // phpcs:ignore Drupal.Semantics.FunctionT.NotLiteralString
      return new TranslatableMarkup($string, $args, $options, $translation);
    });
    $translation->method('translateString')->willReturnCallback(function (TranslatableMarkup $markup) use ($resolve_translation): string {
      return $resolve_translation($markup->getUntranslatedString(), $markup->getArguments(), $markup->getOptions());
    });
    return $translation;
  }

}
