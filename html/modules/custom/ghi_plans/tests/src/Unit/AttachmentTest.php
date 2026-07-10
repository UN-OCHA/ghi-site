<?php

namespace Drupal\Tests\ghi_plans\Unit;

use Drupal\ghi_base_objects\Entity\BaseObjectChildInterface;
use Drupal\ghi_base_objects\Entity\BaseObjectInterface;
use Drupal\ghi_plans\ApiObjects\Prototypes\AttachmentPrototype;
use Drupal\ghi_plans\ApiObjects\Attachments\CaseloadAttachment;
use Drupal\ghi_plans\ApiObjects\Attachments\Attachment;
use Drupal\ghi_plans\ApiObjects\Attachments\CostAttachment;
use Drupal\ghi_plans\ApiObjects\Attachments\IndicatorAttachment;
use Drupal\ghi_plans\ApiObjects\Facts\AttachmentFact;
use Drupal\ghi_plans\ApiObjects\PlanReportingPeriod;
use Drupal\ghi_plans\Entity\Plan;
use Drupal\ghi_plans\Exceptions\InvalidAttachmentTypeException;
use Drupal\ghi_plans\Helpers\AttachmentHelper;
use Drupal\hpc_api\ApiObjects\Types\MetricType;

/**
 * Tests for API attachment objects.
 */
class AttachmentTest extends ApiObjectTestBase {

  /**
   * {@inheritdoc}
   */
  protected function tearDown(): void {
    drupal_static_reset('getBaseObjectsFromOriginalIds');
    drupal_static_reset('getQueryInstance');
    foreach ([Attachment::class, CaseloadAttachment::class, CostAttachment::class, IndicatorAttachment::class] as $class) {
      drupal_static_reset($class . '::cache');
    }
    parent::tearDown();
  }

  /**
   * Test data agnostic parts of Attachment.
   */
  public function testAttachmentGenericData() {
    /** @var \Drupal\ghi_plans\ApiObjects\Attachments\Attachment $attachment */
    $attachment = $this->getAttachmentFromFixture('caseload');
    $this->assertInstanceOf(Attachment::class, $attachment);

    // Null handling.
    $this->assertTrue($attachment->isNullValue(NULL));
    $this->assertTrue($attachment->isNullValue(FALSE));
    $this->assertTrue($attachment->isNullValue(''));
    $this->assertFalse($attachment->isNullValue(0));
    $this->assertFalse($attachment->isNullValue(0.0));
    $this->assertFalse($attachment->isNullValue('0'));

    $processing_options = Attachment::getProcessingOptions();
    $this->assertCount(2, $processing_options);
    $this->assertArrayHasKey('single', $processing_options);
    $this->assertArrayHasKey('calculated', $processing_options);

    $calculation_options = Attachment::getCalculationOptions();
    $this->assertCount(4, $calculation_options);
    $this->assertArrayHasKey('addition', $calculation_options);
    $this->assertArrayHasKey('substraction', $calculation_options);
    $this->assertArrayHasKey('division', $calculation_options);
    $this->assertArrayHasKey('percentage', $calculation_options);

    $formatting_options = Attachment::getFormattingOptions();
    $this->assertCount(6, $formatting_options);
    $this->assertArrayHasKey('auto', $formatting_options);
    $this->assertArrayHasKey('currency', $formatting_options);
    $this->assertArrayHasKey('amount', $formatting_options);
    $this->assertArrayHasKey('amount_rounded', $formatting_options);
    $this->assertArrayHasKey('percent', $formatting_options);
    $this->assertArrayHasKey('raw', $formatting_options);

    $widget_options = Attachment::getWidgetOptions();
    $this->assertCount(4, $widget_options);
    $this->assertArrayHasKey('none', $widget_options);
    $this->assertArrayHasKey('progressbar', $widget_options);
    $this->assertArrayHasKey('pie_chart', $widget_options);
    $this->assertArrayHasKey('spark_line', $widget_options);
  }

  /**
   * Test data agnostic parts of Attachment.
   */
  public function testAttachmentEmptyData() {
    /** @var \Drupal\ghi_plans\ApiObjects\Attachments\Attachment $attachment */
    $attachment = AttachmentHelper::processAttachment((object) [
      'Id' => 38529,
      'PlanId' => 1266,
      'AttachmentType' => 'Caseload',
      'AttachmentPrototypeId' => rand(1, 100),
    ]);
    $this->assertInstanceOf(Attachment::class, $attachment);
    $this->assertEmpty($attachment->getSourceEntity());
  }

  /**
   * Test that missing measurements on Attachment does not create a loop.
   *
   * This tests against a potential loop in Attachment::getMeasurements().
   */
  public function testAttachmentEmptyMeasurementLoop() {
    /** @var \Drupal\ghi_plans\ApiObjects\Attachments\Attachment $attachment */
    $attachment = AttachmentHelper::processAttachment((object) [
      'Id' => 38529,
      'PlanId' => 1266,
      'AttachmentType' => 'Caseload',
      'AttachmentPrototypeId' => rand(1, 100),
    ]);
    $this->assertInstanceOf(Attachment::class, $attachment);
    $this->assertEmpty($attachment->getSourceEntity());
  }

  /**
   * Test value extraction from Attachments.
   */
  public function testAttachmentExtractValues() {
    /** @var \Drupal\ghi_plans\ApiObjects\Attachments\CaseloadAttachment $attachment */
    $attachment = $this->getAttachmentFromFixture('caseload');
    $this->assertInstanceOf(CaseloadAttachment::class, $attachment);

    $totals = [
      $this->mockAttachmentFact(TRUE, 4648210, 'in_need'),
      $this->mockAttachmentFact(TRUE, 3124881, 'target'),
    ];

    $values = $this->callPrivateMethod($attachment, 'extractValues', [$totals]);
    $this->assertEquals([
      'in_need' => 4648210,
      'target' => 3124881,
    ], $values);
  }

  /**
   * Test value retrieval from Attachments.
   */
  public function testAttachmentGetDataValues() {
    /** @var \Drupal\ghi_plans\ApiObjects\Attachments\CaseloadAttachment $attachment */
    $attachment = $this->getAttachmentFromFixture('caseload');
    $this->assertInstanceOf(CaseloadAttachment::class, $attachment);

    $totals = [
      $this->mockAttachmentFact(TRUE, 4648210, 'in_need'),
      $this->mockAttachmentFact(TRUE, 3124881, 'target'),
    ];
    $this->setPrivateProperty($attachment, 'totals', $totals);
    $this->assertNotEmpty($attachment->getTotals());

    $conf = [
      'processing' => 'single',
      'data_points' => [['metric_type' => 'in_need']],
    ];
    $this->assertEquals(4648210, $attachment->getValue($conf));

    $conf = [
      'processing' => 'single',
      'data_points' => [['metric_type' => 'target']],
    ];
    $this->assertEquals(3124881, $attachment->getValue($conf));

    $conf = [
      'processing' => 'calculated',
      'calculation' => 'addition',
      'data_points' => [
        0 => ['metric_type' => 'in_need'],
        1 => ['metric_type' => 'target'],
      ],
    ];
    $this->assertEquals(4648210 + 3124881, $attachment->getValue($conf));
    $conf['calculation'] = 'substraction';
    $this->assertEquals(4648210 - 3124881, $attachment->getValue($conf));
    $conf['calculation'] = 'division';
    $this->assertEquals(3124881 / 4648210, $attachment->getValue($conf));
    $conf['calculation'] = 'percentage';
    $this->assertEquals(1 / 3124881 * 4648210, $attachment->getValue($conf));

    $conf['calculation'] = 'INVALID CALCULATION TYPE';
    $this->expectException(InvalidAttachmentTypeException::class);
    $attachment->getValue($conf);

    $conf = [
      'processing' => 'INVALID PROCESSING TYPE',
      'data_points' => [['metric_type' => 'in_need']],
    ];
    $this->expectException(InvalidAttachmentTypeException::class);
    $attachment->getValue($conf);
  }

  /**
   * Test that latest measurement values use the latest published period.
   */
  public function testLatestMeasurementValueUsesPublishedReportingPeriod() {
    /** @var \Drupal\ghi_plans\ApiObjects\Attachments\CaseloadAttachment $attachment */
    $attachment = $this->getAttachmentFromFixture('caseload');
    $this->assertInstanceOf(CaseloadAttachment::class, $attachment);
    $this->mockPlanWithLatestPublishedReportingPeriod($attachment->getPlanId(), 2388);
    $metric_type = $this->mockMetricType(20, 'covered');
    $location_id = 900001;

    $this->setPrivateProperty($attachment->getMeasurement(2388), 'disaggregated', $this->createDisaggregatedMetricData($metric_type, $location_id, 2388));
    $this->setPrivateProperty($attachment->getMeasurement(2389), 'disaggregated', $this->createDisaggregatedMetricData($metric_type, $location_id, 2389));

    $this->assertSame(2388, $attachment->getMeasurement('latest')?->getReportingPeriodId());
    $this->assertEquals(2883267, $attachment->getMeasurementMetricValue('cumulative_reach', 2389));
    $this->assertEquals(2314453, $attachment->getMeasurementMetricValue('cumulative_reach'));
    $this->assertEquals(2314453, $attachment->getValueByMetricType('cumulative_reach', 'latest'));
    $this->assertSame(2388, $attachment->getDisaggregatedData('latest', $metric_type)->locations[$location_id]->totals[$metric_type->id()]);

    $conf = [
      'processing' => 'single',
      'data_points' => [
        [
          'metric_type' => 'cumulative_reach',
          'monitoring_period' => 'latest',
        ],
      ],
    ];
    $this->assertEquals(2314453, $attachment->getValue($conf));
  }

  /**
   * Test that cumulative reach uses the last non-empty published period.
   */
  public function testCumulativeReachFallsBackToLastNonEmptyPublishedPeriod() {
    /** @var \Drupal\ghi_plans\ApiObjects\Attachments\CaseloadAttachment $attachment */
    $attachment = $this->getAttachmentFromFixture('caseload');
    $this->assertInstanceOf(CaseloadAttachment::class, $attachment);
    $reporting_periods = $this->mockCaseloadReportingPeriods([2386, 2387, 2388, 2389], $attachment->getPlanId());
    $this->mockPlanWithLatestPublishedReportingPeriod($attachment->getPlanId(), 2389, $reporting_periods);

    $latest_measurement = $attachment->getMeasurement(2389);
    $latest_values = $latest_measurement->getValues();
    $latest_values['cumulative_reach'] = NULL;
    $this->setPrivateProperty($latest_measurement, 'values', $latest_values);

    $this->assertNull($attachment->getMeasurementMetricValue('cumulative_reach', 2389));
    $this->assertCount(4, $attachment->getPlanReportingPeriods($attachment->getPlanId(), TRUE));
    $this->assertSame(2388, $attachment->getLastNonEmptyReportingPeriod('cumulative_reach', $reporting_periods)?->id());
    $this->assertEquals(2314453, $attachment->getValueByMetricType('cumulative_reach', 'latest'));
    $this->assertEquals(2314453, $attachment->getValueByMetricType('cumulative_reach', 2389));
  }

  /**
   * Test the getDataForAllReportingPeriods method.
   */
  public function testGetDataForAllReportingPeriods() {
    /** @var \Drupal\ghi_plans\ApiObjects\Attachments\CaseloadAttachment $attachment */
    $attachment = $this->getAttachmentFromFixture('caseload');
    $this->assertInstanceOf(CaseloadAttachment::class, $attachment);
    $reporting_periods = $this->mockCaseloadReportingPeriods([2386, 2387, 2388, 2389]);
    $values = $attachment->getValuesForAllReportingPeriods('in_need', TRUE, TRUE, $reporting_periods);
    $expected = [
      2386 => 4648210,
      2387 => 4648210,
      2388 => 4648210,
      2389 => 4648210,
    ];
    $this->assertEquals($expected, $values);

    $values = $attachment->getValuesForAllReportingPeriods('total_population', TRUE, TRUE, $reporting_periods);
    $expected = [
      2386 => 22100000,
      2387 => 22100000,
      2388 => 22100000,
      2389 => 22100000,
    ];
    $this->assertEquals($expected, $values);

    $values = $attachment->getValuesForAllReportingPeriods('target', TRUE, TRUE, $reporting_periods);
    $expected = [
      2386 => 3124881,
      2387 => 3124881,
      2388 => 3124881,
      2389 => 3124881,
    ];
    $this->assertEquals($expected, $values);

    $values = $attachment->getValuesForAllReportingPeriods('cumulative_reach', TRUE, TRUE, $reporting_periods);
    $expected = [
      2386 => 522701,
      2387 => 1659672,
      2388 => 2314453,
      2389 => 2883267,
    ];
    $this->assertEquals($expected, $values);

    $values = $attachment->getValuesForAllReportingPeriods('periodical_reach', FALSE, FALSE, $reporting_periods);
    $expected = [
      2386 => 522701,
      2387 => 0,
      2388 => 2314453,
      2389 => 2883267,
    ];
    $this->assertEquals($expected, $values);

    $zero_measurement = $attachment->getMeasurement(2387);
    $zero_values = $zero_measurement->getValues();
    $zero_values['periodical_reach'] = 0.0;
    $this->setPrivateProperty($zero_measurement, 'values', $zero_values);
    $values = $attachment->getValuesForAllReportingPeriods('periodical_reach', FALSE, TRUE, $reporting_periods);
    $this->assertEquals($expected, $values);
  }

  /**
   * Test the getLastNonEmptyReportingPeriod method.
   */
  public function testGetLastNonEmptyReportingPeriod() {
    /** @var \Drupal\ghi_plans\ApiObjects\Attachments\CaseloadAttachment $attachment */
    $attachment = $this->getAttachmentFromFixture('caseload');
    $this->assertInstanceOf(CaseloadAttachment::class, $attachment);
    $reporting_periods = $this->mockCaseloadReportingPeriods([2386, 2387, 2388, 2389]);
    $this->assertNull($attachment->getLastNonEmptyReportingPeriod(1, $reporting_periods));
    $this->assertEquals($reporting_periods[2389], $attachment->getLastNonEmptyReportingPeriod('in_need', $reporting_periods));
    $this->assertEquals($reporting_periods[2389], $attachment->getLastNonEmptyReportingPeriod('target', $reporting_periods));
  }

  /**
   * Test the getMeasurementComment method.
   */
  public function testGetMeasurementComment() {
    /** @var \Drupal\ghi_plans\ApiObjects\Attachments\CaseloadAttachment $attachment */
    $attachment = $this->getAttachmentFromFixture('caseload');
    $this->assertInstanceOf(CaseloadAttachment::class, $attachment);
    $this->assertNull($attachment->getMeasurementComment(2389));
    $this->assertEquals('Test comment', $attachment->getMeasurementComment(2388));
  }

  /**
   * Data provider for testAttachmentFormatDataValues.
   */
  public function dataProviderAttachmentFormatDataValues() {
    $test_cases = [];
    // Format as text.
    $test_cases['text_raw'] = [
      'conf' => [
        'processing' => 'single',
        'formatting' => 'raw',
        'data_points' => [
          0 => ['metric_type' => 'in_need'],
          1 => ['metric_type' => 'total_population'],
        ],
      ],
      'expected' => [
        '#markup' => 4648210,
      ],
    ];
    $test_cases['text_currency'] = [
      'conf' => [
        'processing' => 'single',
        'formatting' => 'currency',
        'data_points' => [
          0 => ['metric_type' => 'in_need'],
          1 => ['metric_type' => 'total_population'],
        ],
      ],
      'expected' => [
        '#theme' => 'hpc_currency',
        '#value' => 4648210,
        '#decimal_format' => NULL,
      ],
    ];
    $test_cases['text_amount'] = [
      'conf' => [
        'processing' => 'single',
        'formatting' => 'amount',
        'data_points' => [
          0 => ['metric_type' => 'in_need'],
          1 => ['metric_type' => 'total_population'],
        ],
      ],
      'expected' => [
        '#theme' => 'hpc_amount',
        '#amount' => 4648210,
        '#scale' => 'full',
        '#decimal_format' => NULL,
      ],
    ];
    $test_cases['text_amount_rounded'] = [
      'conf' => [
        'processing' => 'single',
        'formatting' => 'amount_rounded',
        'data_points' => [
          0 => ['metric_type' => 'in_need'],
          1 => ['metric_type' => 'total_population'],
        ],
      ],
      'expected' => [
        '#theme' => 'hpc_amount',
        '#amount' => 4648210,
        '#decimals' => 1,
        '#decimal_format' => NULL,
      ],
    ];
    $test_cases['text_auto_amount'] = [
      'conf' => [
        'processing' => 'single',
        'formatting' => 'auto',
        'data_points' => [
          0 => ['metric_type' => 'in_need'],
          1 => ['metric_type' => 'total_population'],
        ],
      ],
      'expected' => [
        '#theme' => 'hpc_autoformat_value',
        '#value' => 4648210,
        '#unit_type' => 'amount',
        '#unit_defaults' => [
          'amount' => [
            '#scale' => 'full',
          ],
        ],
        '#decimal_format' => NULL,
      ],
    ];
    $test_cases['text_auto__percentage'] = [
      'conf' => [
        'processing' => 'calculated',
        'calculation' => 'percentage',
        'formatting' => 'auto',
        'data_points' => [
          0 => ['metric_type' => 'in_need'],
          1 => ['metric_type' => 'target'],
        ],
      ],
      'expected' => [
        '#theme' => 'hpc_percent',
        '#ratio' => 1 / 3124881 * 4648210,
        '#decimals' => 1,
        '#decimal_format' => NULL,
      ],
    ];
    $test_cases['text_percent'] = [
      'conf' => [
        'processing' => 'calculated',
        'calculation' => 'percentage',
        'formatting' => 'percent',
        'data_points' => [
          0 => ['metric_type' => 'in_need'],
          1 => ['metric_type' => 'target'],
        ],
      ],
      'expected' => [
        '#theme' => 'hpc_percent',
        '#ratio' => 1 / 3124881 * 4648210,
        '#decimals' => 1,
        '#decimal_format' => NULL,
      ],
    ];
    $test_cases['text_empty'] = [
      'conf' => [
        'processing' => 'single',
        'formatting' => 'auto',
        'data_points' => [
          0 => ['metric_type' => 'periodical_reach_does_not_exist'],
          1 => ['metric_type' => 'total_population'],
        ],
      ],
      'expected' => [
        '#markup' => 'Pending',
      ],
    ];
    // Format as widgets.
    $test_cases['widget_progressbar'] = [
      'conf' => [
        'processing' => 'calculated',
        'calculation' => 'percentage',
        'formatting' => 'percent',
        'widget' => 'progressbar',
        'data_points' => [
          0 => ['metric_type' => 'in_need'],
          1 => ['metric_type' => 'target'],
        ],
      ],
      'expected' => [
        '#theme' => 'hpc_progress_bar',
        '#ratio' => 1 / 3124881 * 4648210,
      ],
    ];
    // Format as widgets.
    $test_cases['widget_pie_chart'] = [
      'conf' => [
        'processing' => 'calculated',
        'calculation' => 'percentage',
        'formatting' => 'percent',
        'widget' => 'pie_chart',
        'data_points' => [
          0 => ['metric_type' => 'in_need'],
          1 => ['metric_type' => 'target'],
        ],
      ],
      'expected' => [
        '#theme' => 'hpc_pie_chart',
        '#ratio' => 1 / 3124881 * 4648210,
      ],
    ];
    return $test_cases;
  }

  /**
   * Test value formatting from Attachments.
   *
   * @dataProvider dataProviderAttachmentFormatDataValues
   */
  public function testAttachmentFormatDataValues($conf, $expected) {
    /** @var \Drupal\ghi_plans\ApiObjects\Attachments\Attachment $attachment */
    $attachment = $this->getAttachmentFromFixture('caseload');
    $this->assertInstanceOf(Attachment::class, $attachment);
    $build = $attachment->formatValue($conf);
    $this->assertEquals('container', $build['#type']);
    $this->assertArrayHasKey(0, $build);
    $this->assertEquals($expected, $build[0]);
    $this->assertArrayHasKey('tooltips', $build);
  }

  /**
   * Test disaggregated data of Attachment.
   *
   * This test is not complete because it would require a more complex data
   * setup and mocking of database queries and/or API requests.
   */
  public function testAttachmentDisaggregatedData() {
    /** @var \Drupal\ghi_plans\ApiObjects\Attachments\Attachment $attachment */
    $attachment = $this->getAttachmentFromFixture('caseload');
    $this->assertInstanceOf(Attachment::class, $attachment);

    $disaggregated_data = $attachment->getDisaggregatedDataMultiple();
    $this->assertEmpty($disaggregated_data);

    $disaggregated_data = $attachment->getDisaggregatedDataMultiple([2387]);
    $this->assertEmpty($disaggregated_data);

    $disaggregated_data = $attachment->getDisaggregatedData();
    $this->assertIsObject($disaggregated_data);
    $this->assertObjectHasProperty('locations', $disaggregated_data);
    $this->assertObjectHasProperty('metrics', $disaggregated_data);
    $this->assertObjectHasProperty('categories', $disaggregated_data);

    $this->assertCount(2, $disaggregated_data->metrics);
    $this->assertEquals('In need', $disaggregated_data->metrics[3]->getName());
    $this->assertEquals('in_need', $disaggregated_data->metrics[3]->getMachineName());
    // phpcs:disable
    // $this->assertEquals(4648210, $disaggregated_data[0]['metric']->value);
    // phpcs:enable

    $this->assertEquals('Target', $disaggregated_data->metrics[5]->getName());
    $this->assertEquals('target', $disaggregated_data->metrics[5]->getMachineName());

    // Confirm the number of locations.
    $this->assertCount(214, $disaggregated_data->locations);

    $disaggregated_data = $attachment->getDisaggregatedData('latest', $disaggregated_data->metrics[3]);
    $this->assertCount(2, $disaggregated_data->metrics);
    $this->assertCount(214, $disaggregated_data->locations);

    $disaggregated_data = $attachment->getDisaggregatedData('latest', $disaggregated_data->metrics[5]);
    $this->assertCount(2, $disaggregated_data->metrics);
    $this->assertCount(128, $disaggregated_data->locations);
  }

  /**
   * Test parsing of caseload attachments.
   */
  public function testAttachmentCaseload() {
    /** @var \Drupal\ghi_plans\ApiObjects\Attachments\CaseloadAttachment $attachment */
    $attachment = $this->getAttachmentFromFixture('caseload');
    $this->assertInstanceOf(CaseloadAttachment::class, $attachment);

    $this->assertEquals('BP1', $attachment->getTitle());
    $this->assertEquals('BP1', $attachment->getCustomIdWithRefCode());
    $this->assertEquals('BP1', $attachment->getComposedReference());
    $this->assertEquals('HPC 2023', $attachment->getDescription());
    $this->assertEquals('caseload', $attachment->getAttachmentType());
    $this->assertEquals(1112, $attachment->getSourceEntityId());
    $this->assertEquals('plan', $attachment->getSourceEntityType());
    $this->assertEquals('Plan', $attachment->getSourceEntityTypeLabel());
    $this->assertEmpty($attachment->getSourceEntity());

    $base_object = $this->prophesize(BaseObjectInterface::class);
    $base_object->getSourceId()->willReturn(1000);
    $this->assertFalse($attachment->belongsToBaseObject($base_object->reveal()));
    $base_object->getSourceId()->willReturn(1112);
    $this->assertTrue($attachment->belongsToBaseObject($base_object->reveal()));
    $child_base_object = $this->prophesize(BaseObjectChildInterface::class);
    $child_base_object->getParentBaseObject()->willReturn($base_object->reveal());
    $child_base_object->getSourceId()->willReturn(1000);
    $this->assertTrue($attachment->belongsToBaseObject($child_base_object->reveal()));

    $this->assertCount(8, $attachment->getFields());
    $this->assertCount(8, $attachment->getFieldTypes());
    $this->assertEquals(array_keys($attachment->getFields()), $attachment->getFieldTypes());
    $this->assertEquals('Total population', $attachment->getFieldByType('total_population'));
    $this->assertEquals('Total population', $attachment->getFieldByIndex(0));
    $this->assertNull($attachment->getSourceTypeForCalculatedField('latest_reach'));
    $this->assertCount(5, $attachment->getPlanningFields());
    $this->assertCount(3, $attachment->getMeasurementFields());
    $this->assertNull($attachment->getUnitType());
    $this->assertInstanceOf(AttachmentPrototype::class, $attachment->getPrototype());
    $this->assertFalse($attachment->isMeasurementIndex(0));
    $this->assertTrue($attachment->isMeasurementIndex(5));
    $this->assertFalse($attachment->isMeasurementField('affected'));
    $this->assertTrue($attachment->isMeasurementField('cumulative_reach'));
    $this->assertTrue($attachment->isCumulativeReachField('cumulative_reach'));
    $this->assertTrue($attachment->isPendingDataEntry());
    $this->assertEquals(1112, $attachment->getPlanId());
    $this->assertTrue($attachment->hasDisaggregatedData());
    $this->assertTrue($attachment->canBeMapped('latest'));
    $this->assertEquals(4648210, $attachment->getCaseloadValue('in_need'));
    $this->assertEquals(4648210, $attachment->getCaseloadValue('in_need', 'In need'));

    $this->assertEquals(2883267, $attachment->getMeasurementMetricValue('cumulative_reach', 2389));
    $this->assertEquals([
      'in_need' => 4648210,
      'target' => 3124881,
      'total_population' => 22100000,
    ], $attachment->getPlanningValues());
    $this->assertEquals([
      'in_need' => 4648210,
      'target' => 3124881,
      'total_population' => 22100000,
      'cumulative_reach' => 2883267,
      'periodical_reach' => 2883267,
    ], $attachment->getCurrentValues());
    $this->assertEquals([
      2386 => [
        'periodical_reach' => 522701,
        'cumulative_reach' => 522701,
        'in_need' => 4648210,
        'target' => 3124881,
        'total_population' => 22100000,
      ],
      2387 => [
        'cumulative_reach' => 1659672,
        'in_need' => 4648210,
        'target' => 3124881,
        'total_population' => 22100000,
      ],
      2388 => [
        'periodical_reach' => 2314453,
        'cumulative_reach' => 2314453,
        'covered' => 2314453,
        'in_need' => 4648210,
        'target' => 3124881,
        'total_population' => 22100000,
      ],
      2389 => [
        'periodical_reach' => 2883267,
        'cumulative_reach' => 2883267,
        'in_need' => 4648210,
        'target' => 3124881,
        'total_population' => 22100000,
      ],
    ], $attachment->getMeasurementValues());

    $this->assertEquals(22100000, $attachment->getValueByMetricType('total_population'));
    $this->assertEquals(22100000, $attachment->getValueByIndex(0));
  }

  /**
   * Test parsing of indicator attachments.
   */
  public function testAttachmentIndicator() {
    /** @var \Drupal\ghi_plans\ApiObjects\Attachments\IndicatorAttachment $attachment */
    $attachment = $this->getAttachmentFromFixture('indicator');
    $this->assertInstanceOf(IndicatorAttachment::class, $attachment);
    $this->assertEquals('IN1', $attachment->getTitle());
    $this->assertEquals('Nombre de personnes non déplacées en insécurité alimentaire sévère ont reçu une assistance alimentaire', $attachment->getDescription());
    $this->assertEquals('indicator', $attachment->getAttachmentType());
    $this->assertEmpty($attachment->getSourceEntity());
    $this->assertCount(2, $attachment->getFields());
    $this->assertCount(1, $attachment->getPlanningFields());
    $this->assertCount(1, $attachment->getMeasurementFields());
    // $this->assertEquals('amount', $attachment->getUnitType());
    $this->assertInstanceOf(AttachmentPrototype::class, $attachment->getPrototype());
    $this->assertFalse($attachment->isMeasurementIndex(0));
    $this->assertTrue($attachment->isMeasurementIndex(1));
    $this->assertFalse($attachment->isMeasurementField('target'));
    $this->assertTrue($attachment->isMeasurementField('measure'));
    $this->assertTrue($attachment->isPendingDataEntry());
    $this->assertEquals(1112, $attachment->getPlanId());
    $this->assertFalse($attachment->hasDisaggregatedData());
    // phpcs:disable
    // $this->assertEquals(IndicatorAttachment::CALCULATION_METHOD_SUM, $attachment->getCalculationMethod());
    // phpcs:enable

    $monitoring_periods = $this->getPlanReportingPeriodsFromFixture(1112);
    $this->assertEquals(183000, $attachment->getSingleValue('target', $monitoring_periods));
    $this->assertNull($attachment->getSingleValue('measure', $monitoring_periods));

    $data_point_conf = [
      'processing' => 'single',
      'formatting' => 'auto',
      'data_points' => [
        0 => ['metric_type' => 'in_need'],
        1 => ['metric_type' => 'total_population'],
      ],
    ];
    $this->callPrivateMethod($attachment, 'getTooltip', [$data_point_conf]);
    $this->assertTrue($this->callPrivateMethod($attachment, 'isApiCalculated', ['measure', []]));
    $this->assertFalse($this->callPrivateMethod($attachment, 'isApiCalculated', ['target', []]));
    $this->assertFalse($this->callPrivateMethod($attachment, 'isApiCalculated', [
      'measure',
      ['use_calculation_method' => FALSE],
    ]));

    $this->assertTrue($this->callPrivateMethod($attachment, 'isValidCalculatedMethod', [IndicatorAttachment::CALCULATION_METHOD_AVERAGE]));
    $this->assertTrue($this->callPrivateMethod($attachment, 'isValidCalculatedMethod', [IndicatorAttachment::CALCULATION_METHOD_LATEST]));
    $this->assertTrue($this->callPrivateMethod($attachment, 'isValidCalculatedMethod', [IndicatorAttachment::CALCULATION_METHOD_MAXIMUM]));
    $this->assertTrue($this->callPrivateMethod($attachment, 'isValidCalculatedMethod', [IndicatorAttachment::CALCULATION_METHOD_SUM]));
    $this->assertFalse($this->callPrivateMethod($attachment, 'isValidCalculatedMethod', ['something_else']));

    $tooltip = $attachment->formatCalculationTooltip($monitoring_periods[1]);
    $this->assertEquals('hpc_tooltip', $tooltip['#theme']);
    // phpcs:disable
    // $this->assertEquals('This value is the sum of all monitoring periods values, as of date 30 Jun 2023', $tooltip['#tooltip']);
    // $this->assertEquals('functions', $tooltip['#tag_content']['#icon']);
    // phpcs:enable
  }

  /**
   * Test parsing of caseload attachments.
   */
  public function testAttachmentCost() {
    /** @var \Drupal\ghi_plans\ApiObjects\Attachments\CostAttachment $attachment */
    $attachment = $this->getAttachmentFromFixture('financial');
    $this->assertInstanceOf(CostAttachment::class, $attachment);
    $this->assertEmpty($attachment->getDescription());
    $this->assertEquals(344007921, $attachment->getRequirements());
    $this->assertEquals(25, $attachment->getCoverage(86001980.25));
  }

  /**
   * Load an attachment from the fixtures.
   *
   * @param string $type
   *   The type of the attachment.
   *
   * @return \Drupal\ghi_plans\ApiObjects\Attachments\AttachmentInterface
   *   The attachment object.
   */
  private function getAttachmentFromFixture($type) {
    $attachment_data = $this->getApiObjectFixture('Attachments', $type);
    $this->assertNotEmpty($attachment_data);
    $attachment = AttachmentHelper::processAttachment($attachment_data);

    // Set the attachment prototype to prevent exceptions.
    $prototype = $this->getApiObjectFixture('AttachmentPrototype', $attachment_data->AttachmentPrototypeId);
    if (!$prototype) {
      $prototype = $this->getApiObjectFixture('AttachmentPrototype', 'caseload');
    }
    $attachment_prototype = new AttachmentPrototype($prototype);
    (new \ReflectionClass($attachment::class))->getProperty('prototype')->setValue($attachment, $attachment_prototype);

    // Build the disaggregated data based on the facts.
    $facts = array_map(fn ($item) => new AttachmentFact($item), (array) ($attachment->getRawData()->disaggregated ?: []));
    $this->setPrivateProperty($attachment, 'disaggregated', $attachment->buildDisaggregatedData($facts));

    return $attachment;
  }

  /**
   * Mock a plan object loaded by source id.
   *
   * @param int $plan_id
   *   The plan source id.
   * @param int $period_id
   *   The latest published reporting period id.
   * @param \Drupal\ghi_plans\ApiObjects\PlanReportingPeriod[] $reporting_periods
   *   The reporting periods to expose through the mocked plan query.
   *
   * @return \Drupal\ghi_plans\Entity\Plan
   *   The mocked plan object.
   */
  private function mockPlanWithLatestPublishedReportingPeriod(int $plan_id, int $period_id, array $reporting_periods = []): Plan {
    drupal_static_reset('getBaseObjectsFromOriginalIds');

    $plan = $this->createMock(Plan::class);
    $plan->method('bundle')->willReturn('plan');
    $plan->method('getSourceId')->willReturn($plan_id);
    $plan->method('getLastPublishedReportingPeriodId')->willReturn($period_id);

    $entity_storage = $this->createMock('\Drupal\Core\Entity\ContentEntityStorageInterface');
    $entity_storage->expects($this->any())
      ->method('loadByProperties')
      ->with([
        'type' => 'plan',
        'field_original_id' => [$plan_id],
      ])
      ->willReturn([$plan]);

    $entity_type_manager = $this->createMock('\Drupal\Core\Entity\EntityTypeManagerInterface');
    $entity_type_manager->expects($this->any())
      ->method('getStorage')
      ->with('base_object')
      ->willReturn($entity_storage);

    $container = \Drupal::getContainer();
    $container->set('entity_type.manager', $entity_type_manager);
    \Drupal::setContainer($container);

    if ($reporting_periods) {
      drupal_static_reset('getQueryInstance');
      $plan_query = $this->createMock('\Drupal\ghi_plans\Plugin\FabricQuery\PlanQuery');
      $plan_query->method('getPlanReportingPeriods')
        ->with($plan_id)
        ->willReturn($reporting_periods);
      $queries = &drupal_static('getQueryInstance', []);
      $queries['plan'] = $plan_query;
    }

    return $plan;
  }

  /**
   * Create disaggregated data for a single metric and location.
   *
   * @param \Drupal\hpc_api\ApiObjects\Types\MetricType $metric_type
   *   The metric type.
   * @param int $location_id
   *   The location id.
   * @param int $value
   *   The metric value.
   *
   * @return object
   *   A disaggregated data object.
   */
  private function createDisaggregatedMetricData(MetricType $metric_type, int $location_id, int $value): object {
    return (object) [
      'locations' => [
        $location_id => (object) [
          'totals' => [
            $metric_type->id() => $value,
          ],
          'categories' => [],
        ],
      ],
      'metrics' => [
        $metric_type->id() => $metric_type,
      ],
      'categories' => [],
    ];
  }

  /**
   * Load an attachment from the fixtures.
   *
   * @param int $plan_id
   *   The plan id.
   *
   * @return \Drupal\ghi_plans\ApiObjects\PlanReportingPeriod[]
   *   An array of plan reporting periods.
   */
  private function getPlanReportingPeriodsFromFixture($plan_id) {
    $data = $this->getApiObjectFixture('PlanReportingPeriods', $plan_id);
    $this->assertNotEmpty($data);
    $this->assertIsArray($data);
    return array_map(function ($period_data) {
      return new PlanReportingPeriod($period_data);
    }, $data);
  }

  /**
   * Build an array of dummy reporting periods for the caseload fixtures.
   */
  private function mockCaseloadReportingPeriods($ids, int $plan_id = 1188) {
    $reporting_periods = array_map(function ($id, $period_number) use ($plan_id) {
      return new PlanReportingPeriod((object) [
        'Id' => $id,
        'PlanId' => $plan_id,
        'MeasurementsGenerated' => TRUE,
        'PeriodNumber' => $period_number,
        'StartDate' => '2024-0' . $period_number . '-01',
        'EndDate' => '2024-0' . $period_number . '-30',
      ]);
    }, [2386, 2387, 2388, 2389], [1, 2, 3, 4]);
    return array_combine($ids, $reporting_periods);
  }

  /**
   * Mock an attachment fact.
   *
   * @param bool $is_total
   *   Whether this is a total.
   * @param mixed $value
   *   The value.
   * @param string $metric_type
   *   The metric type.
   *
   * @return \Drupal\ghi_plans\ApiObjects\Facts\AttachmentFact
   *   A mocked attachment fact object.
   */
  private function mockAttachmentFact($is_total, $value, $metric_type) {

    $metric = $this->prophesize(MetricType::class);
    $metric->getMachineName()->willReturn($metric_type);

    $fact = $this->prophesize(AttachmentFact::class);
    $fact->isTotal()->willReturn($is_total);
    $fact->getValue()->willReturn($value);
    $fact->getMetric()->willReturn($metric->reveal());
    return $fact->reveal();
  }

}
