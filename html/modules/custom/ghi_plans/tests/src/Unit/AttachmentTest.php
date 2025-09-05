<?php

namespace Drupal\Tests\ghi_plans\Unit;

use Drupal\Component\Render\MarkupInterface;
use Drupal\ghi_plans\ApiObjects\AttachmentPrototype\AttachmentPrototype;
use Drupal\ghi_plans\ApiObjects\Attachments\CaseloadAttachment;
use Drupal\ghi_plans\ApiObjects\Attachments\ContactAttachment;
use Drupal\ghi_plans\ApiObjects\Attachments\DataAttachment;
use Drupal\ghi_plans\ApiObjects\Attachments\FileAttachment;
use Drupal\ghi_plans\ApiObjects\Attachments\IndicatorAttachment;
use Drupal\ghi_plans\ApiObjects\Attachments\TextAttachment;
use Drupal\ghi_plans\ApiObjects\PlanReportingPeriod;
use Drupal\ghi_plans\Exceptions\InvalidAttachmentTypeException;
use Drupal\ghi_plans\Helpers\AttachmentHelper;

/**
 * Tests for API attachment objects.
 */
class AttachmentTest extends ApiObjectTestBase {

  /**
   * Test data agnostic parts of DataAttachment.
   */
  public function testAttachmentGenericData() {
    /** @var \Drupal\ghi_plans\ApiObjects\Attachments\DataAttachment $attachment */
    $attachment = $this->getAttachmentFromFixture('caseload');
    $this->assertInstanceOf(DataAttachment::class, $attachment);

    // Null handling.
    $this->assertTrue($attachment->isNullValue(NULL));
    $this->assertTrue($attachment->isNullValue(FALSE));
    $this->assertTrue($attachment->isNullValue(''));
    $this->assertFalse($attachment->isNullValue(0));
    $this->assertFalse($attachment->isNullValue('0'));

    $processing_options = DataAttachment::getProcessingOptions();
    $this->assertCount(2, $processing_options);
    $this->assertArrayHasKey('single', $processing_options);
    $this->assertArrayHasKey('calculated', $processing_options);

    $calculation_options = DataAttachment::getCalculationOptions();
    $this->assertCount(4, $calculation_options);
    $this->assertArrayHasKey('addition', $calculation_options);
    $this->assertArrayHasKey('substraction', $calculation_options);
    $this->assertArrayHasKey('division', $calculation_options);
    $this->assertArrayHasKey('percentage', $calculation_options);

    $formatting_options = DataAttachment::getFormattingOptions();
    $this->assertCount(6, $formatting_options);
    $this->assertArrayHasKey('auto', $formatting_options);
    $this->assertArrayHasKey('currency', $formatting_options);
    $this->assertArrayHasKey('amount', $formatting_options);
    $this->assertArrayHasKey('amount_rounded', $formatting_options);
    $this->assertArrayHasKey('percent', $formatting_options);
    $this->assertArrayHasKey('raw', $formatting_options);

    $widget_options = DataAttachment::getWidgetOptions();
    $this->assertCount(4, $widget_options);
    $this->assertArrayHasKey('none', $widget_options);
    $this->assertArrayHasKey('progressbar', $widget_options);
    $this->assertArrayHasKey('pie_chart', $widget_options);
    $this->assertArrayHasKey('spark_line', $widget_options);
  }

  /**
   * Test data agnostic parts of DataAttachment.
   */
  public function testAttachmentEmptyData() {
    /** @var \Drupal\ghi_plans\ApiObjects\Attachments\DataAttachment $attachment */
    $attachment = AttachmentHelper::processAttachment((object) [
      'id' => 38529,
      'type' => 'caseLoad',
      'attachmentPrototype' => $this->getApiObjectFixture('AttachmentPrototype', 'caseload'),
    ]);
    $this->assertInstanceOf(DataAttachment::class, $attachment);
    $this->assertEmpty($attachment->getSourceEntity());
  }

  /**
   * Test that missing measurements on DataAttachment does not create a loop.
   *
   * This tests against a potential loop in DataAttachment::getMeasurements().
   */
  public function testAttachmentEmptyMeasurementLoop() {
    $measurement_query = $this->getMockBuilder('Drupal\ghi_plans\Plugin\EndpointQuery\MeasurementQuery')
      ->disableOriginalConstructor()
      ->getMock();
    $measurement_query->method('setPlaceholder')->willReturn(NULL);
    $measurement_query->method('getUnprocessedMeasurements')->willReturn([]);
    $measurement_query->expects($this->once())->method('getUnprocessedMeasurements');

    $endpoint_query_manager = $this->getMockBuilder('Drupal\hpc_api\Query\EndpointQueryManager')
      ->disableOriginalConstructor()
      ->getMock();
    $endpoint_query_manager->method('createInstance')->with('measurement_query')->willReturn($measurement_query);

    $container = \Drupal::getContainer();
    $container->set('plugin.manager.endpoint_query_manager', $endpoint_query_manager);
    \Drupal::setContainer($container);

    /** @var \Drupal\ghi_plans\ApiObjects\Attachments\DataAttachment $attachment */
    $attachment = AttachmentHelper::processAttachment((object) [
      'id' => 38529,
      'type' => 'caseLoad',
      'attachmentPrototype' => $this->getApiObjectFixture('AttachmentPrototype', 'caseload'),
    ]);
    $this->assertInstanceOf(DataAttachment::class, $attachment);
    $this->assertEmpty($attachment->getSourceEntity());
  }

  /**
   * Test value retrieval from DataAttachments.
   */
  public function testAttachmentGetDataValues() {
    /** @var \Drupal\ghi_plans\ApiObjects\Attachments\CaseloadAttachment $attachment */
    $attachment = $this->getAttachmentFromFixture('caseload');
    $this->assertInstanceOf(CaseloadAttachment::class, $attachment);
    $conf = [
      'processing' => 'single',
      'data_points' => [['index' => 2]],
    ];
    $this->assertEquals(4648210, $attachment->getValue($conf));

    $conf = [
      'processing' => 'single',
      'data_points' => [['index' => 3]],
    ];
    $this->assertEquals(3124881, $attachment->getValue($conf));

    $conf = [
      'processing' => 'calculated',
      'calculation' => 'addition',
      'data_points' => [
        0 => ['index' => 2],
        1 => ['index' => 3],
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
      'data_points' => [['index' => 2]],
    ];
    $this->expectException(InvalidAttachmentTypeException::class);
    $attachment->getValue($conf);
  }

  /**
   * Test the getDataForAllReportingPeriods method.
   */
  public function testGetDataForAllReportingPeriods() {
    /** @var \Drupal\ghi_plans\ApiObjects\Attachments\CaseloadAttachment $attachment */
    $attachment = $this->getAttachmentFromFixture('caseload_empty_values');
    $this->assertInstanceOf(CaseloadAttachment::class, $attachment);
    $reporting_periods = $this->mockCaseloadReportingPeriods([2386, 2387, 2388, 2389]);
    $values = $attachment->getValuesForAllReportingPeriods(2, FALSE, FALSE, $reporting_periods);
    $this->assertIsArray($values);
    $this->assertArrayHasKey(2386, $values);
    $this->assertArrayHasKey(2387, $values);
    $this->assertArrayHasKey(2388, $values);
    $this->assertArrayHasKey(2389, $values);
    $expected = [
      2386 => 4648210,
      2387 => 4648210,
      2388 => 4648210,
      2389 => 4648210,
    ];
    $this->assertEquals($expected, $values);

    $values = $attachment->getValuesForAllReportingPeriods(1, TRUE, FALSE, $reporting_periods);
    $this->assertEquals([], $values);

    $values = $attachment->getValuesForAllReportingPeriods(1, FALSE, TRUE, $reporting_periods);
    $this->assertEquals([], $values);

    $values = $attachment->getValuesForAllReportingPeriods(1, TRUE, TRUE, $reporting_periods);
    $this->assertEquals([], $values);

    $values = $attachment->getValuesForAllReportingPeriods(3, FALSE, TRUE, $reporting_periods);
    $expected = [
      2386 => 0,
      2387 => 3124881,
      2388 => 3124881,
      2389 => 3124881,
    ];
    $this->assertEquals($expected, $values);

    $values = $attachment->getValuesForAllReportingPeriods(3, TRUE, FALSE, $reporting_periods);
    $expected = [
      2387 => 3124881,
      2388 => 3124881,
      2389 => 3124881,
    ];
    $this->assertEquals($expected, $values);
  }

  /**
   * Test the getLastNonEmptyReportingPeriod method.
   */
  public function testGetLastNonEmptyReportingPeriod() {
    /** @var \Drupal\ghi_plans\ApiObjects\Attachments\CaseloadAttachment $attachment */
    $attachment = $this->getAttachmentFromFixture('caseload_empty_values');
    $this->assertInstanceOf(CaseloadAttachment::class, $attachment);
    $reporting_periods = $this->mockCaseloadReportingPeriods([2386, 2387, 2388, 2389]);
    $this->assertNull($attachment->getLastNonEmptyReportingPeriod(1, $reporting_periods));
    $this->assertEquals($reporting_periods[2389], $attachment->getLastNonEmptyReportingPeriod(2, $reporting_periods));
    $this->assertEquals($reporting_periods[2389], $attachment->getLastNonEmptyReportingPeriod(3, $reporting_periods));
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
          0 => ['index' => 2],
          1 => ['index' => 0],
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
          0 => ['index' => 2],
          1 => ['index' => 0],
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
          0 => ['index' => 2],
          1 => ['index' => 0],
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
          0 => ['index' => 2],
          1 => ['index' => 0],
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
          0 => ['index' => 2],
          1 => ['index' => 0],
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
          0 => ['index' => 2],
          1 => ['index' => 3],
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
          0 => ['index' => 2],
          1 => ['index' => 3],
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
          0 => ['index' => 5],
          1 => ['index' => 0],
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
          0 => ['index' => 2],
          1 => ['index' => 3],
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
          0 => ['index' => 2],
          1 => ['index' => 3],
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
   * Test value formatting from DataAttachments.
   *
   * @dataProvider dataProviderAttachmentFormatDataValues
   */
  public function testAttachmentFormatDataValues($conf, $expected) {
    /** @var \Drupal\ghi_plans\ApiObjects\Attachments\DataAttachment $attachment */
    $attachment = $this->getAttachmentFromFixture('caseload');
    $this->assertInstanceOf(DataAttachment::class, $attachment);
    $build = $attachment->formatValue($conf);
    $this->assertEquals('container', $build['#type']);
    $this->assertArrayHasKey(0, $build);
    $this->assertEquals($expected, $build[0]);
    $this->assertArrayHasKey('tooltips', $build);
  }

  /**
   * Test disaggregated data of DataAttachment.
   *
   * This test is not complete because it would require a more complex data
   * setup and mocking of database queries and/or API requests.
   */
  public function testAttachmentDisaggregatedData() {
    /** @var \Drupal\ghi_plans\ApiObjects\Attachments\DataAttachment $attachment */
    $attachment = $this->getAttachmentFromFixture('caseload');
    $this->assertInstanceOf(DataAttachment::class, $attachment);

    $disaggregated_data = $attachment->getDisaggregatedDataMultiple();
    $this->assertEmpty($disaggregated_data);

    $disaggregated_data = $attachment->getDisaggregatedDataMultiple([2387]);
    $this->assertEmpty($disaggregated_data);

    $disaggregated_data = $attachment->getDisaggregatedData();
    $this->assertEquals('Population', $disaggregated_data[0]['metric']->name->en);
    $this->assertEquals('totalPopulation', $disaggregated_data[0]['metric']->type);
    $this->assertEquals(22100000, $disaggregated_data[0]['metric']->value);

    $this->assertEquals('Affectés', $disaggregated_data[1]['metric']->name->en);
    $this->assertEquals('affected', $disaggregated_data[1]['metric']->type);
    $this->assertNull($disaggregated_data[1]['metric']->value);

    $this->assertEquals('Dans le besoin', $disaggregated_data[2]['metric']->name->en);
    $this->assertEquals('inNeed', $disaggregated_data[2]['metric']->type);
    $this->assertEquals(4648210, $disaggregated_data[2]['metric']->value);

    $this->assertEquals('Ciblés', $disaggregated_data[3]['metric']->name->en);
    $this->assertEquals('target', $disaggregated_data[3]['metric']->type);
    $this->assertEquals(3124881, $disaggregated_data[3]['metric']->value);

    $this->assertEquals('Atteints attendus', $disaggregated_data[4]['metric']->name->en);
    $this->assertEquals('expectedReach', $disaggregated_data[4]['metric']->type);
    $this->assertEquals(2300000, $disaggregated_data[4]['metric']->value);

    $disaggregated_data = $attachment->getDisaggregatedData('latest', TRUE, FALSE);
    $this->assertCount(5, $disaggregated_data);

    $disaggregated_data = $attachment->getDisaggregatedData('latest', FALSE, TRUE);
    $this->assertCount(5, $disaggregated_data);

    $disaggregated_data = $attachment->getDisaggregatedData('latest', TRUE, TRUE);
    $this->assertCount(5, $disaggregated_data);

    $disaggregated_data = $attachment->getDisaggregatedData('latest', TRUE, FALSE, TRUE);
    $this->assertCount(5, $disaggregated_data);

    $disaggregated_data = $attachment->getDisaggregatedData('latest', FALSE, TRUE, TRUE);
    $this->assertCount(5, $disaggregated_data);

    $disaggregated_data = $attachment->getDisaggregatedData('latest', TRUE, TRUE, TRUE);
    $this->assertCount(5, $disaggregated_data);
  }

  /**
   * Test parsing of caseload attachments.
   */
  public function testAttachmentCaseload() {
    /** @var \Drupal\ghi_plans\ApiObjects\Attachments\CaseloadAttachment $attachment */
    $attachment = $this->getAttachmentFromFixture('caseload');
    $this->assertInstanceOf(CaseloadAttachment::class, $attachment);
    $this->assertEquals('BP1', $attachment->getTitle());
    $this->assertEquals('HPC 2023', $attachment->getDescription());
    $this->assertEquals('caseload', $attachment->getType());
    $this->assertEmpty($attachment->getSourceEntity());
    $this->assertCount(8, $attachment->getMetricFields());
    $this->assertCount(5, $attachment->getGoalMetricFields());
    $this->assertCount(3, $attachment->getMeasurementMetricFields());
    $this->assertNull($attachment->getUnitType());
    $this->assertInstanceOf(AttachmentPrototype::class, $attachment->getPrototype());
    $this->assertFalse($attachment->isMeasurementIndex(0));
    $this->assertTrue($attachment->isMeasurementIndex(5));
    $this->assertFalse($attachment->isMeasurementField('Population'));
    $this->assertTrue($attachment->isMeasurementField('Cumul atteints'));
    $this->assertTrue($attachment->isPendingDataEntry());
    $this->assertEquals(1112, $attachment->getPlanId());
    $this->assertTrue($attachment->hasDisaggregatedData());
    $this->assertFalse($attachment->canBeMapped('latest'));
  }

  /**
   * Test parsing of caseload attachments.
   */
  public function testAttachmentIndicator() {
    /** @var \Drupal\ghi_plans\ApiObjects\Attachments\IndicatorAttachment $attachment */
    $attachment = $this->getAttachmentFromFixture('indicator');
    $this->assertInstanceOf(IndicatorAttachment::class, $attachment);
    $this->assertEquals('SP1/IN1', $attachment->getTitle());
    $this->assertEquals('Nombre de personnes non déplacées en insécurité alimentaire sévère ont reçu une assistance alimentaire', $attachment->getDescription());
    $this->assertEquals('indicator', $attachment->getType());
    $this->assertEmpty($attachment->getSourceEntity());
    $this->assertCount(2, $attachment->getMetricFields());
    $this->assertCount(1, $attachment->getGoalMetricFields());
    $this->assertCount(1, $attachment->getMeasurementMetricFields());
    $this->assertEquals('amount', $attachment->getUnitType());
    $this->assertInstanceOf(AttachmentPrototype::class, $attachment->getPrototype());
    $this->assertFalse($attachment->isMeasurementIndex(0));
    $this->assertTrue($attachment->isMeasurementIndex(1));
    $this->assertFalse($attachment->isMeasurementField('Ciblés'));
    $this->assertTrue($attachment->isMeasurementField('Mesure'));
    $this->assertTrue($attachment->isPendingDataEntry());
    $this->assertEquals(1112, $attachment->getPlanId());
    $this->assertFalse($attachment->hasDisaggregatedData());
    $this->assertEquals(IndicatorAttachment::CALCULATION_METHOD_SUM, $attachment->getCalculationMethod());

    $monitoring_periods = $this->getPlanReportingPeriodsFromFixture(1112);
    $this->assertEquals(183000, $attachment->getSingleValue(0, $monitoring_periods));
    $this->assertNull($attachment->getSingleValue(1, $monitoring_periods));

    $data_point_conf = [
      'processing' => 'single',
      'formatting' => 'auto',
      'data_points' => [
        0 => ['index' => 2],
        1 => ['index' => 0],
      ],
    ];
    $this->callPrivateMethod($attachment, 'getTooltip', [$data_point_conf]);
    $data_point_conf['use_calculation_method'] = FALSE;
    $this->assertFalse($this->callPrivateMethod($attachment, 'isApiCalculated', [1, $data_point_conf]));

    $this->assertTrue($this->callPrivateMethod($attachment, 'isValidCalculatedMethod', [IndicatorAttachment::CALCULATION_METHOD_AVERAGE]));
    $this->assertTrue($this->callPrivateMethod($attachment, 'isValidCalculatedMethod', [IndicatorAttachment::CALCULATION_METHOD_LATEST]));
    $this->assertTrue($this->callPrivateMethod($attachment, 'isValidCalculatedMethod', [IndicatorAttachment::CALCULATION_METHOD_MAXIMUM]));
    $this->assertTrue($this->callPrivateMethod($attachment, 'isValidCalculatedMethod', [IndicatorAttachment::CALCULATION_METHOD_SUM]));
    $this->assertFalse($this->callPrivateMethod($attachment, 'isValidCalculatedMethod', ['something_else']));

    $tooltip = $attachment->formatCalculationTooltip($monitoring_periods[1]);
    $this->assertEquals('hpc_tooltip', $tooltip['#theme']);
    $this->assertEquals('This value is the sum of all monitoring periods values, as of date 30 Jun 2023', $tooltip['#tooltip']);
    $this->assertEquals('functions', $tooltip['#tag_content']['#icon']);
  }

  /**
   * Test parsing of fileWebContent attachments.
   */
  public function testAttachmentFileWebContent() {
    /** @var \Drupal\ghi_plans\ApiObjects\Attachments\FileAttachment $attachment */
    $attachment = $this->getAttachmentFromFixture('filewebcontent');
    $this->assertInstanceOf(FileAttachment::class, $attachment);
    $this->assertEquals('262835-burkinafaso_ocha_Michele-Cattani_hero.jpg', $attachment->getTitle());
    $this->assertEquals('https://api.hpc.tools/public/files/rpm/262835-burkinafaso_ocha_Michele-Cattani_hero.jpg', $attachment->getUrl());
    $this->assertEquals('OCHA/Michele Cattani', $attachment->getCredit());
    $this->assertNull($attachment->getDescription());
  }

  /**
   * Test parsing of textWebContent attachments.
   */
  public function testAttachmentTextWebContent() {
    /** @var \Drupal\ghi_plans\ApiObjects\Attachments\TextAttachment $attachment */
    $attachment = $this->getAttachmentFromFixture('textwebcontent');
    $this->assertInstanceOf(TextAttachment::class, $attachment);
    $this->assertEquals('MSA detail', $attachment->getTitle());
    $this->assertStringStartsWith('<h3>Refugee Response</h3>', $attachment->getContent());
    $this->assertInstanceOf(MarkupInterface::class, $attachment->getMarkup());
  }

  /**
   * Test parsing of contact attachments.
   */
  public function testAttachmentContact() {
    /** @var \Drupal\ghi_plans\ApiObjects\Attachments\ContactAttachment $attachment */
    $attachment = $this->getAttachmentFromFixture('contact');
    $this->assertInstanceOf(ContactAttachment::class, $attachment);
    $this->assertEquals('Craig Hampton', $attachment->getTitle());
    $this->assertEquals([
      'id' => 4617,
      'type' => 'contact',
      'name' => 'Craig Hampton',
      'mail' => 'hamptonc@who.int',
      'agency' => 'WHO',
    ], $attachment->toArray());
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
    return AttachmentHelper::processAttachment($attachment_data);
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
  private function mockCaseloadReportingPeriods($ids) {
    $reporting_periods = array_map(function ($id, $period_number) {
      return new PlanReportingPeriod((object) [
        'id' => $id,
        'planId' => 1188,
        'measurementsGenerated' => TRUE,
        'periodNumber' => $period_number,
        'startDate' => '2024-0' . $period_number . '-01',
        'endDate' => '2024-0' . $period_number . '-30',
      ]);
    }, [2386, 2387, 2388, 2389], [1, 2, 3, 4]);
    return array_combine($ids, $reporting_periods);
  }

}
