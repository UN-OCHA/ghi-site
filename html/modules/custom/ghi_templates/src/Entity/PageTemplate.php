<?php

namespace Drupal\ghi_templates\Entity;

use Drupal\Component\Render\FormattableMarkup;
use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityChangedTrait;
use Drupal\Core\Entity\EntityPublishedTrait;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Messenger\MessengerTrait;
use Drupal\Core\Plugin\Context\EntityContext;
use Drupal\Core\Render\Markup;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ghi_base_objects\Entity\BaseObjectChildInterface;
use Drupal\layout_builder\LayoutEntityHelperTrait;
use Drupal\layout_builder\Plugin\SectionStorage\OverridesSectionStorage;
use Drupal\layout_builder\Section;
use Drupal\layout_builder\SectionComponent;
use Drupal\user\EntityOwnerTrait;

/**
 * Defines the page template entity class.
 *
 * @ContentEntityType(
 *   id = "page_template",
 *   label = @Translation("Page template"),
 *   label_collection = @Translation("Page templates"),
 *   label_singular = @Translation("page template"),
 *   label_plural = @Translation("page templates"),
 *   label_count = @PluralTranslation(
 *     singular = "@count page template",
 *     plural = "@count page templates",
 *   ),
 *   handlers = {
 *     "view_builder" = "Drupal\ghi_templates\PageTemplateViewBuilder",
 *     "list_builder" = "Drupal\ghi_templates\PageTemplateListBuilder",
 *     "views_data" = "Drupal\ghi_templates\Entity\PageTemplateViewsData",
 *     "form" = {
 *       "default" = "Drupal\ghi_templates\Form\PageTemplateForm",
 *       "add" = "Drupal\ghi_templates\Form\PageTemplateForm",
 *       "edit" = "Drupal\ghi_templates\Form\PageTemplateForm",
 *       "delete" = "Drupal\Core\Entity\ContentEntityDeleteForm",
 *     },
 *     "route_provider" = {
 *       "html" = "Drupal\ghi_templates\PageTemplateHtmlRouteProvider",
 *     },
 *   },
 *   admin_permission = "administer page templates",
 *   base_table = "page_template",
 *   translatable = FALSE,
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "title",
 *     "langcode" = "langcode",
 *     "uuid" = "uuid",
 *     "status" = "status",
 *     "published" = "status",
 *     "uid" = "uid",
 *     "owner" = "uid",
 *   },
 *   links = {
 *     "collection" = "/admin/structure/page-templates",
 *     "canonical" = "/admin/structure/page-templates/{page_template}",
 *     "edit-form" = "/admin/structure/page-templates/{page_template}/edit",
 *     "delete-form" = "/admin/structure/page-templates/{page_template}/delete",
 *     "delete-multiple-form" = "/admin/structure/page-templates/delete",
 *   },
 *   field_ui_base_route = "entity.page_template.collection",
 *   common_reference_target = TRUE
 * )
 */
class PageTemplate extends ContentEntityBase implements PageTemplateInterface {

  use EntityChangedTrait;
  use EntityOwnerTrait;
  use EntityPublishedTrait;
  use LayoutEntityHelperTrait;
  use MessengerTrait;
  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public function setupTemplate() {
    $source_page = $this->getSourceEntity();
    if (!$source_page) {
      return;
    }

    $base_objects = $this->getBaseObjectsfromSource();
    if ($source_page && $base_objects) {
      $this->setBaseObjects($base_objects);
    }

    $source_section_storage = $this->getSectionStorageForEntity($source_page);
    $source_sections = $source_section_storage->getSections();
    foreach ($source_sections as $source_section) {
      $section_config = $source_section->toArray();
      $components = [];
      foreach ($section_config['components'] as $component_config) {
        $component_config['uuid'] = \Drupal::service('uuid')->generate();
        $component = SectionComponent::fromArray($component_config);
        $plugin = $component->getPlugin();
        $definition = $plugin->getPluginDefinition();
        $context_mapping = [
          'context_mapping' => array_intersect_key([
            'node' => 'layout_builder.entity',
          ], $definition['context_definitions']),
        ];
        // And make sure that base objects are mapped too.
        foreach (array_filter($base_objects) as $_base_object) {
          $contexts = [
            EntityContext::fromEntity($_base_object),
          ];
          foreach ($definition['context_definitions'] as $context_key => $context_definition) {
            $matching_contexts = \Drupal::service('context.handler')->getMatchingContexts($contexts, $context_definition);
            if (empty($matching_contexts)) {
              continue;
            }
            $context_mapping['context_mapping'][$context_key] = $_base_object->getUniqueIdentifier();
          }
        }
        $configuration = $context_mapping + $component->get('configuration');
        $component->setConfiguration($configuration);
        $components[$component->getUuid()] = $component->toArray();
      }

      $section_config['components'] = $components;
      $section = Section::fromArray($section_config);
      $sections[] = $section;
    }

    $this->get(OverridesSectionStorage::FIELD_NAME)->setValue($sections);
    $this->save();
  }

  /**
   * {@inheritdoc}
   */
  public function getSourceEntity() {
    return $this->field_entity_reference?->entity ?? NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getBaseObjects() {
    return $this->field_base_objects?->referencedEntities() ?? [];
  }

  /**
   * {@inheritdoc}
   */
  public function setBaseObjects(array $base_objects) {
    foreach ($base_objects as $base_object) {
      $this->field_base_objects[] = [
        'target_id' => $base_object->id(),
      ];
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getBaseObjectsfromSource($source_page = NULL) {
    $source_page = $source_page ?? $this->getSourceEntity();
    $base_objects = [];
    if ($source_page && $source_page->hasField('field_base_objects')) {
      $base_objects = $source_page->field_base_objects?->referencedEntities();
    }
    elseif ($source_page && $source_page->hasField('field_base_object')) {
      $base_objects = [$source_page->field_base_object?->entity];
    }
    elseif ($source_page && $source_page->hasField('field_entity_reference')) {
      $base_objects = $this->getBaseObjectsfromSource($source_page->field_entity_reference?->entity);
    }
    foreach ($base_objects as $base_object) {
      if (!$base_object instanceof BaseObjectChildInterface) {
        continue;
      }
      $parent = $base_object->getParentBaseObject();
      if (!$parent) {
        continue;
      }
      $base_objects[] = $parent;
    }
    return $base_objects;
  }

  /**
   * {@inheritdoc}
   */
  public function getSourceSummary($source_template = NULL) {
    if ($source_template === NULL) {
      $source_template = 'Template based on <em>@source</em> @entity_type_lowercase page';
    }

    $base_objects = $this->getBaseObjects();

    $base_object_summmaries = [];
    if (!empty($base_objects)) {
      $base_object_summmaries = array_map(function ($base_object) {
        return new FormattableMarkup('@base_object_type_lowercase <em>@base_object</em>', [
          '@base_object_type_lowercase' => strtolower($base_object->type->entity->label() ?? ''),
          '@base_object_type' => $base_object->type->entity->label(),
          '@base_object' => $base_object->label(),
        ]);
      }, array_filter($base_objects));
    }

    $source_summary = new FormattableMarkup($source_template, [
      '@source' => $this->getSourceEntity()?->label() ?? $this->t('deleted'),
      '@entity_type_lowercase' => strtolower($this->getSourceEntity()?->type?->entity->label() ?? ''),
      '@entity_type' => $this->getSourceEntity()?->type?->entity->label(),
    ]);

    if (count($base_object_summmaries) > 1) {
      $base_object_summary = new TranslatableMarkup('@first_items and @last_item', [
        '@first_items' => Markup::create(implode(', ', array_slice($base_object_summmaries, 0, count($base_object_summmaries) - 1))),
        '@last_item' => end($base_object_summmaries),
      ]);
    }
    else {
      $base_object_summary = new FormattableMarkup('@base_object_summaries', [
        '@base_object_summaries' => Markup::create(implode(', ', $base_object_summmaries)),
      ]);
    }

    $base_object_summary = new TranslatableMarkup('using @base_objects for data context', [
      '@base_objects' => Markup::create($base_object_summary),
    ]);

    return Markup::create(implode(', ', array_filter(array_merge([
      $source_summary,
    ], [$base_object_summary]))));
  }

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    /** @var \Drupal\Core\Field\BaseFieldDefinition[] $fields */
    $fields = parent::baseFieldDefinitions($entity_type);
    $fields += static::ownerBaseFieldDefinitions($entity_type);

    // Add the published field.
    $fields += static::publishedBaseFieldDefinitions($entity_type);

    $fields['id']->setLabel(t('Page template ID'))
      ->setDescription(t('The page template ID.'));

    $fields['title'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Title'))
      ->setDescription(t('An internal title for the template that makes it easy to find.'))
      ->setRequired(TRUE)
      ->setTranslatable(TRUE)
      ->addConstraint('UniqueLabel')
      ->setSetting('max_length', 255)
      ->setDisplayOptions('view', [
        'label' => 'hidden',
        'type' => 'string',
        'weight' => -5,
      ])
      ->setDisplayOptions('form', [
        'type' => 'string_textfield',
        'weight' => -5,
      ])
      ->setDisplayConfigurable('form', TRUE);

    $fields['status']
      ->setDisplayOptions('form', [
        'type' => 'boolean_checkbox',
        'settings' => [
          'display_label' => TRUE,
        ],
        'weight' => 120,
      ])
      ->setDisplayConfigurable('form', TRUE);

    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Authored on'))
      ->setDescription(t('The date and time that the content was created.'))
      ->setTranslatable(TRUE)
      ->setDisplayOptions('form', [
        'type' => 'datetime_timestamp',
        'weight' => 10,
      ])
      ->setDisplayConfigurable('form', TRUE);

    $fields['changed'] = BaseFieldDefinition::create('changed')
      ->setLabel(t('Changed'))
      ->setDescription(t('The time that the node was last edited.'))
      ->setTranslatable(TRUE);

    return $fields;
  }

}
