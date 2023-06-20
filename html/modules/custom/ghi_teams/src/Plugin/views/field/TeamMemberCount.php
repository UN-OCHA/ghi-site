<?php

namespace Drupal\ghi_teams\Plugin\views\field;

use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\views\ResultRow;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Field handler to display member counts for teams.
 *
 * @ViewsField("team_member_count")
 */
class TeamMemberCount extends FieldPluginBase {

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
    $users = $this->entityTypeManager->getStorage('user')->loadByProperties([
      'field_team' => $values->_entity->id(),
    ]);
    return count($users);
  }

}
