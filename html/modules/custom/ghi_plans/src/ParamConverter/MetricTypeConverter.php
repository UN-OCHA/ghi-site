<?php

namespace Drupal\ghi_plans\ParamConverter;

use Drupal\Core\ParamConverter\ParamConverterInterface;
use Drupal\hpc_api\Query\FabricQueryManager;
use Symfony\Component\Routing\Route;

/**
 * Converts parameters for upcasting metric type machine names to full objects.
 */
class MetricTypeConverter implements ParamConverterInterface {

  /**
   * The query class for the base types like metric type.
   *
   * @var \Drupal\hpc_api\Plugin\FabricQuery\BaseTypeQuery
   */
  protected $baseTypeQuery;

  /**
   * Constructs a new AttachmentConverter.
   *
   * @param \Drupal\hpc_api\Query\FabricQueryManager $fabric_query_manager
   *   The query manager.
   */
  public function __construct(FabricQueryManager $fabric_query_manager) {
    $this->baseTypeQuery = $fabric_query_manager->hasDefinition('base_type') ? $fabric_query_manager->createInstance('base_type') : NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function convert($value, $definition, $name, array $defaults) {
    if (!empty($value)) {
      return $this->baseTypeQuery?->getMetricTypeByMachineName($value);
    }
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function applies($definition, $name, Route $route) {
    return $name == 'metric_type' || (!empty($definition['type']) && $definition['type'] == 'metric_type');
  }

}
