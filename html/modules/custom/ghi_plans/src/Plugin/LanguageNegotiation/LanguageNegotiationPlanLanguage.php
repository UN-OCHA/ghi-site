<?php

namespace Drupal\ghi_plans\Plugin\LanguageNegotiation;

use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Routing\Router;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ghi_plans\Entity\Plan;
use Drupal\ghi_sections\Entity\SectionNodeInterface;
use Drupal\ghi_subpages\Entity\SubpageNodeInterface;
use Drupal\language\Attribute\LanguageNegotiation;
use Drupal\language\LanguageNegotiationMethodBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Class for identifying language via URL prefix or domain.
 */
#[LanguageNegotiation(
  id: LanguageNegotiationPlanLanguage::METHOD_ID,
  name: new TranslatableMarkup('Current plan base object'),
  types: [LanguageInterface::TYPE_INTERFACE],
  weight: -8,
  description: new TranslatableMarkup("Language from the current plan base object (if available).")
)]
class LanguageNegotiationPlanLanguage extends LanguageNegotiationMethodBase implements ContainerFactoryPluginInterface {

  /**
   * The language negotiation method id.
   */
  const METHOD_ID = 'language-plan-language';

  /**
   * The router.
   *
   * This is only used when called from an event subscriber, before the request
   * has been populated with the route info.
   *
   * @var \Drupal\Core\Routing\Router
   */
  protected $router;

  /**
   * Constructs a new LanguageNegotiationPlanLanguage instance.
   *
   * @param \Drupal\Core\Routing\Router $router
   *   The router.
   */
  public function __construct(Router $router) {
    $this->router = $router;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $container->get('router.no_access_checks'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getLangcode(?Request $request = NULL) {
    if ($request === NULL) {
      return NULL;
    }
    $parameters = $this->getRequestParameters($request);
    $node = $parameters['node'] ?? NULL;
    if (!$node) {
      return NULL;
    }
    if (!$node instanceof SectionNodeInterface && !$node instanceof SubpageNodeInterface) {
      return NULL;
    }
    if ($node instanceof SectionNodeInterface) {
      $base_object = $node->getBaseObject();
      if ($base_object instanceof Plan) {
        return $base_object->getPlanLanguage();
      }
    }
    elseif ($node instanceof SubpageNodeInterface) {
      $base_object = $node->getParentBaseNode()->getBaseObject();
      if ($base_object instanceof Plan) {
        return $base_object->getPlanLanguage();
      }
    }
    return NULL;
  }

  /**
   * Get the route object from the given request.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request.
   *
   * @return array
   *   The route object.
   */
  private function getRequestParameters(Request $request) {
    return $this->router->matchRequest($request);
  }

}
