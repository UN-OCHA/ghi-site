<?php

namespace Drupal\Tests\ghi_blocks\Unit;

use Drupal\Core\Form\FormState;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\ghi_blocks\Plugin\ConfigurationContainerItem\ProjectFunding;
use Drupal\ghi_plans\ApiObjects\Organization;
use Drupal\ghi_plans\ApiObjects\Project;
use Drupal\ghi_plans\Entity\Plan;
use Drupal\ghi_plans\Helpers\FinancialLabel;
use Drupal\ghi_plans\Plugin\EndpointQuery\PlanProjectFundingQuery;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the project funding configuration item.
 */
#[CoversMethod(ProjectFunding::class, 'getCurrentRequirementsForOrganization')]
#[CoversMethod(ProjectFunding::class, 'getValue')]
#[Group('ghi_blocks')]
class ProjectFundingTest extends UnitTestCase {

  /**
   * Tests plan-aware current-requirements default labels.
   */
  #[DataProvider('defaultLabelProvider')]
  public function testCurrentRequirementsDefaultLabel(string $langcode, string $expected): void {
    $translation = $this->mockTranslation();
    $project_funding = $this->createProjectFunding($langcode, $translation);

    $this->assertSame($expected, (string) $project_funding->getDefaultLabel());
  }

  /**
   * Provides plan languages and their financial table labels.
   */
  public static function defaultLabelProvider(): array {
    return [
      'English' => ['en', 'Requirements'],
      'French' => ['fr', 'Besoins financiers'],
      'Spanish' => ['es', 'Requerimientos financieros'],
    ];
  }

  /**
   * Tests that the funding default also uses the plan language.
   */
  public function testFundingDefaultLabelUsesPlanLanguage(): void {
    $project_funding = $this->createProjectFunding('fr', $this->mockTranslation());
    $project_funding->setConfig(['data_type' => 'total_funding']);

    $this->assertSame('Financement', (string) $project_funding->getDefaultLabel());
  }

  /**
   * Tests that only the frontend default uses the plan language in the form.
   */
  public function testFormKeepsInterfaceStringsSeparateFromPlanDefault(): void {
    $translation_options = [];
    $translation = $this->mockTranslation($translation_options);
    $project_funding = $this->createProjectFunding('fr', $translation, ['value_preview' => FALSE]);
    $form = $project_funding->buildForm([
      '#parents' => ['columns', 0],
      '#array_parents' => ['columns', 0],
    ], new FormState());

    $this->assertNull($form['label']['#default_value']);
    $this->assertSame('Besoins financiers', (string) $form['label']['#placeholder']);
    $this->assertStringContainsString('<br>', (string) $form['label']['#description']);
    $this->assertStringContainsString('Do not include a currency unit such as "($)" in a table column label.', (string) $form['label']['#description']);
    $this->assertSame('Current requirements', (string) $form['data_type']['#options']['current_requirements']);
    $this->assertArrayNotHasKey('langcode', $translation_options['Current requirements'][0]);
    $this->assertSame([
      'langcode' => 'fr',
      'context' => FinancialLabel::TRANSLATION_CONTEXT,
    ], $translation_options['Requirements'][0]);
    $this->assertSame('currency', $project_funding->getColumnType());
    $project_funding->setConfig(['data_type' => 'coverage']);
    $this->assertSame('percentage', $project_funding->getColumnType());
  }

  /**
   * Tests that missing plan context uses the English frontend default.
   */
  public function testDefaultLabelFallsBackToEnglishWithoutPlan(): void {
    $project_funding = new ProjectFunding([], 'project_funding', []);
    $project_funding->setConfig(['data_type' => 'current_requirements']);
    $project_funding->setStringTranslation($this->mockTranslation());

    $this->assertSame('Requirements', (string) $project_funding->getDefaultLabel());
  }

  /**
   * Tests current requirements are summed from the loaded projects.
   */
  public function testCurrentRequirementsUseLoadedProjects(): void {
    $organization = $this->createOrganization(1, 'Organization one');
    $other_organization = $this->createOrganization(2, 'Organization two');
    $projects = [
      $this->createProject(1, 100.0, [$organization]),
      $this->createProject(2, 250.0, [$organization, $other_organization]),
      $this->createProject(3, 500.0, [$other_organization]),
    ];

    $project_funding = new ProjectFunding([], 'project_funding', []);
    $project_funding->set('data_type', 'current_requirements');
    $project_funding->setContextValue('organization', $organization);
    $project_funding->setContextValue('projects', $projects);
    $project_funding->projectFundingQuery = $this->createMock(PlanProjectFundingQuery::class);
    $project_funding->projectFundingQuery->expects($this->never())
      ->method('getSumForOrganization');

    $this->assertSame(350.0, $project_funding->getValue());
  }

  /**
   * Creates an organization API object.
   *
   * @param int $id
   *   The organization ID.
   * @param string $name
   *   The organization name.
   *
   * @return \Drupal\ghi_plans\ApiObjects\Organization
   *   The organization API object.
   */
  private function createOrganization(int $id, string $name): Organization {
    return new Organization((object) [
      'Id' => $id,
      'Name' => $name,
    ]);
  }

  /**
   * Creates a project API object.
   *
   * @param int $id
   *   The project ID.
   * @param float $requirements
   *   The current project requirements.
   * @param \Drupal\ghi_plans\ApiObjects\Organization[] $organizations
   *   The project's organizations.
   *
   * @return \Drupal\ghi_plans\ApiObjects\Project
   *   The project API object.
   */
  private function createProject(int $id, float $requirements, array $organizations): Project {
    $organization_ids = array_map(fn (Organization $organization): int => $organization->id(), $organizations);
    return new Project((object) [
      'Id' => $id,
      'PlanId' => 1021,
      'Name' => 'Project ' . $id,
      'ProjectCode' => (string) $id,
      'IsPublished' => TRUE,
      'TotalProjectTarget' => 0,
      'CurrentRequestedFunds' => $requirements,
      'clusters' => [],
      'locationIds' => [],
      'organizations' => array_combine($organization_ids, $organizations),
    ]);
  }

  /**
   * Creates a project funding configuration item with plan context.
   */
  private function createProjectFunding(string $langcode, TranslationInterface $translation, array $configuration = []): ProjectFunding {
    $plan = $this->getMockBuilder(Plan::class)
      ->disableOriginalConstructor()
      ->onlyMethods(['getPlanLanguage'])
      ->getMock();
    $plan->method('getPlanLanguage')->willReturn($langcode);

    $project_funding = new ProjectFunding($configuration, 'project_funding', []);
    $project_funding->setConfig(['data_type' => 'current_requirements']);
    $project_funding->setContext(['plan_object' => $plan]);
    $project_funding->setStringTranslation($translation);
    return $project_funding;
  }

  /**
   * Mocks translations while recording the supplied translation options.
   */
  private function mockTranslation(?array &$translation_options = NULL): TranslationInterface {
    $translations = [
      'en' => 'Requirements',
      'fr' => 'Besoins financiers',
      'es' => 'Requerimientos financieros',
    ];
    $translation = $this->createMock(TranslationInterface::class);
    $resolve_translation = function ($string, array $args = [], array $options = []) use (&$translation_options, $translations) {
      $translation_options[$string][] = $options;
      if ($string === 'Requirements' && ($options['context'] ?? NULL) === FinancialLabel::TRANSLATION_CONTEXT) {
        return $translations[$options['langcode'] ?? 'en'];
      }
      if ($string === 'Funding') {
        return [
          'fr' => 'Financement',
          'es' => 'Financiación',
        ][$options['langcode'] ?? 'en'] ?? 'Funding';
      }
      return strtr($string, $args);
    };
    $translation->method('translate')->willReturnCallback(function ($string, array $args = [], array $options = []) use ($translation) {
      // phpcs:ignore Drupal.Semantics.FunctionT.NotLiteralString
      return new TranslatableMarkup($string, $args, $options, $translation);
    });
    $translation->method('translateString')->willReturnCallback(function (TranslatableMarkup $markup) use ($resolve_translation) {
      return $resolve_translation($markup->getUntranslatedString(), $markup->getArguments(), $markup->getOptions());
    });
    return $translation;
  }

}
