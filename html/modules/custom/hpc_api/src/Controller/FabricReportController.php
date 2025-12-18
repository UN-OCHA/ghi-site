<?php

namespace Drupal\hpc_api\Controller;

use Drupal\Component\Render\FormattableMarkup;
use Drupal\Component\Render\MarkupInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Render\Markup;
use Drupal\hpc_common\Helpers\StringHelper;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Controller class for a listing files and a delete callback.
 */
class FabricReportController extends ControllerBase {

  /**
   * The endpoint query to retrieve API data.
   *
   * @var \Drupal\hpc_api\Query\FabricQueryManager
   */
  protected $fabricQueryManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $instance = new static();
    $instance->fabricQueryManager = $container->get('plugin.manager.fabric_query_manager');
    return $instance;
  }

  /**
   * Build the content for the base types report page.
   *
   * @return array
   *   A render array.
   */
  public function buildBaseTypeReport() {
    /** @var \Drupal\hpc_api\Plugin\FabricQuery\BaseTypeQuery $query */
    $query = $this->fabricQueryManager->createInstance('base_type');

    $build = [
      '#type' => 'container',
      'header' => [
        '#type' => 'html_tag',
        '#tag' => 'p',
        '#value' => $this->t('This page lists all base types that are defined in the fabric data backend and that are known to this website.'),
      ],
    ];

    $base_type_definitions = $query->getBaseTypeDefinitions();
    foreach ($base_type_definitions as $query_key => $def) {
      $items = $query->fetchBaseType($query_key) ?: [];
      [, $properties] = $base_type_definitions[$query_key];
      $rows = [];
      /** @var \Drupal\hpc_api\ApiObjects\Types\BaseType[] $items */
      foreach ($items as $item) {
        $raw_data = $item->getRawData();
        $row = [];
        foreach ($properties as $property) {
          $row[] = $raw_data->$property;
        }
        $rows[] = $row;
      }

      $type_label = ucfirst(str_replace('_', ' ', StringHelper::camelCaseToUnderscoreCase($query_key)));
      $build[$query_key] = [
        '#type' => 'details',
        '#title' => $this->t('@label (@count items)', [
          '@label' => $type_label,
          '@count' => count($rows),
        ]),
        'table' => [
          '#type' => 'table',
          '#header' => $properties,
          '#rows' => $rows,
          '#empty' => $this->t('No items found for <em>@type</em>', [
            '@type' => $type_label,
          ]),
        ],
      ];
    }

    return $build;
  }

  /**
   * Title callback.
   *
   * @param int $entity_type_id
   *   The id of the entity type.
   * @param int $entity_id
   *   The entity id.
   *
   * @return Drupal\Component\Render\MarkupInterface
   *   The entity title or NULL.
   */
  public function entityTitle(int $entity_type_id, int $entity_id): MarkupInterface {
    /** @var \Drupal\hpc_api\Plugin\FabricQuery\EntityQuery $query */
    $query = $this->fabricQueryManager->createInstance('entity');
    $entity_type = $query->getEntityTypeById($entity_type_id);
    return new FormattableMarkup('@type <em>@name</em>', [
      '@type' => $entity_type->getLabel(),
      '@name' => $query->lookupEntityLabel($entity_type_id, $entity_id),
    ]);
  }

  /**
   * Page callback for the entity lookup.
   *
   * @param int $entity_type_id
   *   The id of the entity type.
   * @param int $entity_id
   *   The entity id.
   *
   * @return array
   *   A render array.
   */
  public function entityLookupPage(int $entity_type_id, int $entity_id): array {
    /** @var \Drupal\hpc_api\Plugin\FabricQuery\EntityQuery $query */
    $query = $this->fabricQueryManager->createInstance('entity');
    $entity_type = $query->getEntityTypeById($entity_type_id);
    if (!$entity_type) {
      throw new \InvalidArgumentException('Unknown entity type id');
    }
    $entity_query = $query->getEntityQuery($entity_type_id, $entity_id);
    if (!$entity_query) {
      return [
        '#markup' => $this->t('Entity lookup for the entity type <em>@entity_type</em> has not been implemented yet.', [
          '@entity_type' => $entity_type->getName(),
        ]),
      ];
    }

    $data = $query->getEntityData($entity_type_id, $entity_id);
    $rows = [];
    if ($data !== NULL) {
      foreach (get_object_vars($data) as $key => $value) {
        $rows[] = [$key, $value];
      }
    }

    return [
      [
        '#type' => 'details',
        '#title' => $this->t('Query'),
        [
          '#markup' => Markup::create('query { ' . $entity_query . ' }'),
        ],
        '#attributes' => ['class' => ['gin-layer-wrapper']],
      ],
      [
        '#type' => 'table',
        '#header' => [
          $this->t('Property'),
          $this->t('Value'),
        ],
        '#rows' => $rows,
      ],
    ];
  }

}
