<?php

namespace Drupal\ghi_sections\Menu;

use Drupal\Component\Plugin\Exception\PluginException;
use Drupal\Core\Form\FormStateInterface;
use Drupal\node\Entity\Node;

/**
 * Section menu item class.
 */
class SectionMenuItem implements SectionMenuItemInterface {

  /**
   * The plugin ID.
   *
   * @var string
   */
  protected $pluginId;

  /**
   * The section ID.
   *
   * @var int
   */
  protected $sectionId;

  /**
   * The label.
   *
   * @var string
   */
  protected $label;

  /**
   * An array of plugin configuration.
   *
   * @var mixed[]
   */
  protected $configuration;

  /**
   * Constructs a new section menu item.
   *
   * @param string $plugin_id
   *   The plugin id.
   * @param string $section_id
   *   The section id.
   * @param string $label
   *   The label for the menu item.
   * @param array $configuration
   *   A configuration array for the menu item.
   */
  public function __construct($plugin_id, $section_id, $label, array $configuration = []) {
    $this->pluginId = $plugin_id;
    $this->label = $label;
    $this->sectionId = $section_id;
    $this->configuration = $configuration;
  }

  /**
   * {@inheritdoc}
   */
  public function getPluginId() {
    if (empty($this->pluginId)) {
      throw new PluginException('No plugin ID specified');
    }
    return $this->pluginId;
  }

  /**
   * {@inheritdoc}
   */
  public function getSection() {
    if (empty($this->sectionId)) {
      throw new PluginException('No plugin section specified');
    }
    $section_id = $this->sectionId ?? NULL;
    return $section_id ? Node::load($section_id) : NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getLabel() {
    return (string) $this->label;
  }

  /**
   * {@inheritdoc}
   */
  public function setLabel($label) {
    $this->label = $label;
  }

  /**
   * {@inheritdoc}
   */
  public function getConfiguration() {
    return $this->configuration;
  }

  /**
   * {@inheritdoc}
   */
  public function setConfiguration(array $configuration) {
    $this->configuration = $configuration;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm($form, FormStateInterface $form_state) {

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function getPlugin() {
    /** @var \Drupal\ghi_sections\Menu\SectionMenuPluginManager $plugin_manager */
    $plugin_manager = $this->pluginManager();
    $definitions = $plugin_manager->getDefinitions();
    if (empty($definitions[$this->getPluginId()])) {
      return NULL;
    }
    $plugin = $plugin_manager->createInstance($this->getPluginId(), [
      'section' => $this->sectionId,
    ] + $this->getConfiguration());
    return $plugin;
  }

  /**
   * {@inheritdoc}
   */
  public function toArray() {
    return [
      'plugin_id' => $this->pluginId,
      'section_id' => $this->sectionId,
      'label' => $this->getLabel(),
      'configuration' => $this->configuration,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public static function fromArray($array) {
    $instance = new static($array['plugin_id'], $array['section_id'], $array['label'], $array['configuration']);
    return $instance;
  }

  /**
   * Wraps the section menu plugin manager.
   *
   * @return \Drupal\ghi_sections\Menu\SectionMenuPluginManager
   *   The plugin manager.
   */
  protected function pluginManager() {
    return \Drupal::service('plugin.manager.section_menu');
  }

}
