<?php

namespace Drupal\ghi_base_objects\Migrate;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\migrate\Plugin\MigrateSourcePluginManager;
use Drupal\migrate\Plugin\MigrationPluginManagerInterface;
use Drupal\migrate_plus\Entity\MigrationGroup;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Service class for migrated entities.
 */
class MigratedEntityManager implements ContainerInjectionInterface {

  use StringTranslationTrait;

  /**
   * The migration plugin manager.
   *
   * @var \Drupal\migrate\Plugin\MigrationPluginManagerInterface
   */
  protected $migrationPluginManager;

  /**
   * The migration source plugin manager.
   *
   * @var \Drupal\migrate\Plugin\MigrateSourcePluginManager
   */
  protected $migrateSourcePluginManager;

  /**
   * The messenger service.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * Public constructor.
   */
  public function __construct(MigrationPluginManagerInterface $migration_plugin_manager, MigrateSourcePluginManager $migrate_source_plugin_manager, MessengerInterface $messenger) {
    $this->migrationPluginManager = $migration_plugin_manager;
    $this->migrateSourcePluginManager = $migrate_source_plugin_manager;
    $this->messenger = $messenger;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('plugin.manager.migration'),
      $container->get('plugin.manager.migrate.source'),
      $container->get('messenger'),
    );
  }

  /**
   * Alter entity forms.
   *
   * Disable fields that are subject to migration. Any manual changes would be
   * overwritten during future migration runs anyways.
   *
   * @param array $form
   *   The form array to alter.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state object.
   */
  public function entityFormAlter(array &$form, FormStateInterface $form_state) {
    $form_object = $form_state->getFormObject();
    if (!$form_object instanceof ContentEntityForm) {
      return;
    }
    $entity = $form_object->getEntity();
    if (!$entity) {
      return;
    }
    $migration_definition = $this->getMigrationDefinition($entity);
    if (!$migration_definition) {
      return;
    }

    $migration_group = MigrationGroup::load($migration_definition['migration_group']);
    $t_args = [
      '@label' => $migration_group->label(),
    ];
    $disabled_field_text = $this->t('This field is disabled because it is automatically populated from @label.', $t_args);

    $overwrite_properties = $migration_definition['destination']['overwrite_properties'] ?? [];
    $process_fields = array_keys($migration_definition['process'] ?? []);
    $fields = array_unique(array_merge($overwrite_properties, $process_fields));
    foreach ($fields as $field_key) {
      if (!array_key_exists($field_key, $form)) {
        continue;
      }

      if (($form[$field_key]['widget'][0]['#type'] ?? NULL) == 'text_format') {
        $form[$field_key]['widget_copy'] = $form[$field_key]['widget'];
        $form[$field_key]['widget_copy'][0]['#type'] = 'textarea';
        $form[$field_key]['widget_copy'][0]['#format'] = 'plan_text';
        $form[$field_key]['widget_copy'][0]['#disabled'] = TRUE;
        $form[$field_key]['widget']['#access'] = FALSE;
      }
      // Add a condition to manage disabled relationship of terms.
      elseif (isset($form['relations'][$field_key])) {
        $form['relations'][$field_key]['#disabled'] = TRUE;
        // Add a tooltip for disabled relationship of terms.
        $form['relations'][$field_key]['#attributes']['title'] = $disabled_field_text;
      }
      else {
        $form[$field_key]['#disabled'] = TRUE;
        // Add a tooltip for each individual disabled field.
        $form[$field_key]['#attributes']['title'] = $disabled_field_text;
      }
    }

    if ($migration_group) {
      $this->messenger->addWarning($this->t('Some of the fields in this form have been disabled because their content is automatically synced from @label.', $t_args));
    }
    else {
      $this->messenger->addWarning($this->t('Some of the fields in this form have been disabled because their content is automatically synced from an external source.'));
    }
  }

  /**
   * Get the migration object that is responsible for migrating the entity.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity for which to load the migration.
   *
   * @return array
   *   The migration definition as an array or NULL
   */
  private function getMigrationDefinition(ContentEntityInterface $entity) {
    $definitions = $this->migrationPluginManager->getDefinitions();
    $entity_type_id = $entity->getEntityTypeId();
    $entity_bundle = $entity->bundle();
    $definitions = array_filter($definitions, function (array $definition) use ($entity_type_id, $entity_bundle) {
      $plugin_id = $definition['destination']['plugin'] ?? NULL;
      $bundle = $definition['destination']['default_bundle'] ?? NULL;
      if (!$bundle) {
        $bundle = $definition['process']['type']['default_value'] ?? NULL;
      }
      return $plugin_id === 'entity:' . $entity_type_id && $bundle == $entity_bundle;
    });
    return count($definitions) == 1 ? reset($definitions) : NULL;
  }

}
