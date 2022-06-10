<?php

namespace Drupal\ghi_content\Plugin\Block;

use Drupal\Core\Form\FormStateInterface;
use Drupal\ghi_base_objects\Helpers\BaseObjectHelper;
use Drupal\ghi_blocks\Interfaces\OptionalTitleBlockInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides an 'RelatedArticles' block.
 *
 * @Block(
 *  id = "related_sections",
 *  admin_label = @Translation("Related sections"),
 *  category = @Translation("Narrative Content"),
 *  context_definitions = {
 *    "node" = @ContextDefinition("entity:node", label = @Translation("Node")),
 *   }
 * )
 */
class RelatedSections extends ContentBlockBase implements OptionalTitleBlockInterface {

  /**
   * The remote source service.
   *
   * @var \Drupal\ghi_sections\SectionManager
   */
  protected $sectionManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->sectionManager = $container->get('ghi_sections.manager');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function buildContent() {
    $entity = $this->getCurrentBaseEntity();
    if (!$entity) {
      return NULL;
    }
    $base_objects = BaseObjectHelper::getBaseObjectsFromNode($entity);
    if (empty($base_objects)) {
      return NULL;
    }
    $sections = array_filter(array_map(function ($base_object) {
      $section = $this->sectionManager->loadSectionForBaseObject($base_object);
      return $section && $section->isPublished() ? $section : NULL;
    }, $base_objects));

    if (empty($sections)) {
      return NULL;
    }

    $build = [
      '#theme' => 'related_sections',
      '#title' => $this->label(),
      '#sections' => $sections,
    ];

    return $build;
  }

  /**
   * Returns generic default configuration for block plugins.
   *
   * @return array
   *   An associative array with the default configuration.
   */
  protected function getConfigurationDefaults() {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function getConfigForm(array $form, FormStateInterface $form_state) {
    return $form;
  }

}
