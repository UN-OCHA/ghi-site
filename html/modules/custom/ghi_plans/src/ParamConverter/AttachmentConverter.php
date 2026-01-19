<?php

namespace Drupal\ghi_plans\ParamConverter;

use Drupal\Core\ParamConverter\ParamConverterInterface;
use Drupal\hpc_api\Query\FabricQueryManager;
use Symfony\Component\Routing\Route;

/**
 * Converts parameters for upcasting attachment ids to full objects.
 */
class AttachmentConverter implements ParamConverterInterface {

  /**
   * The manager class for endpoint query plugins.
   *
   * @var \Drupal\ghi_plans\Plugin\FabricQuery\AttachmentQuery
   */
  protected $attachmentQuery;

  /**
   * Constructs a new AttachmentConverter.
   *
   * @param \Drupal\hpc_api\Query\FabricQueryManager $fabric_query_manager
   *   The query manager.
   */
  public function __construct(FabricQueryManager $fabric_query_manager) {

    $this->attachmentQuery = $fabric_query_manager->hasDefinition('attachment') ? $fabric_query_manager->createInstance('attachment') : NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function convert($value, $definition, $name, array $defaults) {
    if (!empty($value)) {
      return $this->attachmentQuery?->getAttachment($value);
    }
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function applies($definition, $name, Route $route) {
    return $name == 'attachment' || (!empty($definition['type']) && $definition['type'] == 'attachment');
  }

}
