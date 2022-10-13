<?php

namespace Drupal\ghi_plans\ParamConverter;

use Drupal\Core\ParamConverter\ParamConverterInterface;
use Drupal\hpc_api\Query\EndpointQueryManager;
use Symfony\Component\Routing\Route;

/**
 * Converts parameters for upcasting attachment ids to full objects.
 */
class AttachmentConverter implements ParamConverterInterface {

  /**
   * The manager class for endpoint query plugins.
   *
   * @var \Drupal\ghi_plans\Plugin\EndpointQuery\AttachmentQuery
   */
  protected $attachmentQuery;

  /**
   * Constructs a new LanguageConverter.
   *
   * @param \Drupal\hpc_api\Query\EndpointQueryManager $endpoint_query_manager
   *   The language manager.
   */
  public function __construct(EndpointQueryManager $endpoint_query_manager) {
    $this->attachmentQuery = $endpoint_query_manager->createInstance('attachment_query');
  }

  /**
   * {@inheritdoc}
   */
  public function convert($value, $definition, $name, array $defaults) {
    if (!empty($value)) {
      return $this->attachmentQuery->getAttachment($value);
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
