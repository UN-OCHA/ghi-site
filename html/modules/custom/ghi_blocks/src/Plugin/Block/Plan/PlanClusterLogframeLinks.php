<?php

namespace Drupal\ghi_blocks\Plugin\Block\Plan;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Markup;
use Drupal\ghi_blocks\Interfaces\OverrideDefaultTitleBlockInterface;
use Drupal\ghi_blocks\Plugin\Block\GHIBlockBase;
use Drupal\ghi_form_elements\Traits\OptionalLinkTrait;
use Drupal\ghi_plan_clusters\Entity\PlanCluster;
use Drupal\ghi_plans\Entity\GoverningEntity;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a 'PlanClusterLogframeLinks' block.
 *
 * @Block(
 *  id = "plan_cluster_logframe_links",
 *  admin_label = @Translation("Cluster logframe links"),
 *  category = @Translation("Plan elements"),
 *  default_title = @Translation("Cluster Framework"),
 *  context_definitions = {
 *    "node" = @ContextDefinition("entity:node", label = @Translation("Node"), constraints = { "Bundle": "logframe" }),
 *    "plan" = @ContextDefinition("entity:base_object", label = @Translation("Plan"), constraints = { "Bundle": "plan" })
 *  }
 * )
 */
class PlanClusterLogframeLinks extends GHIBlockBase implements OverrideDefaultTitleBlockInterface {

  use OptionalLinkTrait;

  /**
   * The block plugin manager.
   *
   * @var \Drupal\Core\Block\BlockManagerInterface
   */
  protected $blockPluginManager;

  /**
   * The logframe manager.
   *
   * @var \Drupal\ghi_subpages\LogframeManager
   */
  public $logframeManager;

  /**
   * The plan cluster manager.
   *
   * @var \Drupal\ghi_plan_clusters\PlanClusterManager
   */
  protected $planClusterManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    /** @var \Drupal\ghi_blocks\Plugin\Block\Plan\PlanClusterLogframeLinks $instance */
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->blockPluginManager = $container->get('plugin.manager.block');
    $instance->logframeManager = $container->get('ghi_subpages.logframe_manager');
    $instance->planClusterManager = $container->get('ghi_plan_clusters.manager');
    return $instance;
  }

  /**
   * Retrieve the renderable entities for this instance.
   *
   * @return \Drupal\ghi_subpages\Entity\LogframeSubpage[]|null
   *   An array of cluster logframe nodes.
   */
  private function getRenderableEntities() {
    $section = $this->getCurrentBaseEntity();
    if (!$section) {
      return NULL;
    }

    $cluster_logframes = $this->planClusterManager->loadPlanClusterLogframeSubpageNodesForSection($section);
    if (empty($cluster_logframes)) {
      // Nothing to render.
      return NULL;
    }
    return $cluster_logframes;
  }

  /**
   * {@inheritdoc}
   */
  public function buildContent() {

    // Get the entities to render.
    $cluster_logframes = $this->getRenderableEntities();
    if (empty($cluster_logframes)) {
      return;
    }

    $rendered_items = [];
    foreach ($cluster_logframes as $cluster_logframe) {
      if (!$cluster_logframe->isPublished()) {
        continue;
      }
      $cluster = $cluster_logframe->getParentNode();
      if (!$cluster instanceof PlanCluster) {
        continue;
      }
      $cluster_base_object = $cluster->getBaseObject();
      if (!$cluster_base_object instanceof GoverningEntity) {
        continue;
      }
      $icon = $cluster_base_object->getIconEmbedCode();
      $link = $this->getLinkFromUri($cluster_logframe->toUrl()->toUriString());
      $rendered_items[] = [
        '#theme' => 'link_box',
        '#image' => Markup::create($icon),
        '#title' => $this->t('@cluster_name cluster logical framework', [
          '@cluster_name' => $cluster->label(),
        ]),
        '#description' => $this->t('See cluster framework'),
        '#link' => $link->toRenderable(),
      ];
    }

    $build = [
      '#theme' => 'item_list',
      '#items' => array_filter($rendered_items),
      '#attributes' => [
        'class' => ['links'],
      ],
      // This is important to make the template suggestions logic work in
      // common_design_subtheme.theme.
      '#context' => [
        'plugin_type' => 'links',
        'plugin_id' => $this->getPluginId(),
      ],
      '#gin_lb_theme_suggestions' => FALSE,
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
   * Form builder for the config form.
   *
   * @param array $form
   *   An associative array containing the initial structure of the subform.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @return array
   *   The full form array for this subform.
   */
  public function getConfigForm(array $form, FormStateInterface $form_state) {
    return $form;
  }

}
