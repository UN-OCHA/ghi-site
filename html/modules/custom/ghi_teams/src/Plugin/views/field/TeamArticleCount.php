<?php

namespace Drupal\ghi_teams\Plugin\views\field;

use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\views\ResultRow;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Field handler to display document counts for a team.
 *
 * @ViewsField("team_article_count")
 */
class TeamArticleCount extends FieldPluginBase {

  /**
   * Array of entities that reference to file.
   *
   * @var array
   */
  protected $loadedReferencers = [];

  /**
   * EntityTypeManager class.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->entityTypeManager = $container->get('entity_type.manager');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function query() {
    // Overridden to prevent additional query.
  }

  /**
   * {@inheritdoc}
   */
  public function render(ResultRow $values) {
    $team = $this->entityTypeManager->getStorage('taxonomy_term')->load($values->_entity->id());
    if (!$team) {
      return 0;
    }
    $content_spaces = $team->get('field_content_spaces')->referencedEntities();
    if (empty($content_spaces)) {
      return 0;
    }
    $content_space_ids = array_map(function ($content_space) {
      return $content_space->id();
    }, $content_spaces);

    $articles = $this->entityTypeManager->getStorage('node')->loadByProperties([
      'type' => 'article',
      'field_content_space' => $content_space_ids,
    ]);
    return count($articles);
  }

}
