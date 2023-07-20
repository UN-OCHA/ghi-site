<?php

namespace Drupal\ghi_sections\Menu;

use Drupal\Component\Plugin\PluginBase;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\ghi_sections\Entity\SectionNodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Base class for section menu item plugins.
 */
abstract class SectionMenuPluginBase extends PluginBase implements SectionMenuPluginInterface {

  use StringTranslationTrait;

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The section menu storage.
   *
   * @var \Drupal\ghi_sections\Menu\SectionMenuStorage
   */
  protected $sectionMenuStorage;

  /**
   * The section for which the item will be used.
   *
   * @var \Drupal\ghi_sections\Entity\SectionNodeInterface
   */
  protected $section;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = new static($configuration, $plugin_id, $plugin_definition);
    $instance->entityTypeManager = $container->get('entity_type.manager');
    $instance->sectionMenuStorage = $container->get('ghi_sections.section_menu.storage');
    $instance->section = $configuration['section'] ?? NULL;
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function getPluginConfiguration() {
    return $this->configuration;
  }

  /**
   * {@inheritdoc}
   */
  public function getPluginLabel() {
    return $this->pluginDefinition['label'];
  }

  /**
   * {@inheritdoc}
   */
  public function getPluginDescription() {
    $plugin_definition = $this->getPluginDefinition();
    if (empty($plugin_definition['description'])) {
      return NULL;
    }
    return $plugin_definition['description'];
  }

  /**
   * {@inheritdoc}
   */
  public function setSection(SectionNodeInterface $section) {
    $this->section = $section;
  }

  /**
   * {@inheritdoc}
   */
  public function getSection() {
    if (!empty($this->section) && is_scalar($this->section)) {
      // Cast numerical or string arguments to node objects.
      $this->section = $this->entityTypeManager->getStorage('node')->load($this->section);
    }
    return $this->section;
  }

  /**
   * {@inheritdoc}
   */
  abstract public function getLabel();

  /**
   * {@inheritdoc}
   */
  abstract public function getItem();

  /**
   * {@inheritdoc}
   */
  abstract public function getWidget();

  /**
   * {@inheritdoc}
   */
  abstract public function isValid();

  /**
   * {@inheritdoc}
   */
  public function getCacheTags() {
    return [];
  }

}
