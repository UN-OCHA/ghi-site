<?php

namespace Drupal\ghi_blocks\Form;

use Drupal\Core\Block\BlockPluginInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\ghi_blocks\Plugin\Block\GHIBlockBase;
use Drupal\ghi_blocks\Traits\PageElementsTrait;
use Drupal\hpc_api\Traits\BulkFormTrait;
use Drupal\layout_builder\LayoutEntityHelperTrait;
use Drupal\layout_builder\Plugin\SectionStorage\OverridesSectionStorage;
use Drupal\layout_builder\SectionComponent;
use Drupal\node\NodeInterface;

/**
 * Page elements form.
 */
class PageElementsForm extends FormBase {

  use LayoutEntityHelperTrait;
  use BulkFormTrait;
  use PageElementsTrait;

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'ghi_blocks_page_elements_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?NodeInterface $node = NULL) {
    $form = [];
    if (!$node || !$this->isLayoutCompatibleEntity($node)) {
      return $form;
    }

    $form['#node'] = $node;
    $node_type = $node->type->entity->label();

    $form['description'] = [
      '#prefix' => '<p>',
      '#suffix' => '</p>',
      '#markup' => $this->t('On this page you can see all page elements that are currently configured for this @page_type page.', [
        '@page_type' => str_ends_with($node_type, ' page') ? str_replace(' page', '', $node_type) : $node_type,
      ]),
    ];

    $section_storage = $this->getSectionStorageForEntity($node);
    $sections = $node->get(OverridesSectionStorage::FIELD_NAME)->getValue();
    foreach (array_keys($sections) as $delta) {
      $section = $sections[$delta]['section'];
      $layout_settings = $section->getLayoutSettings();
      $region = $section->getDefaultRegion();

      $rows = [];
      $components = $section->getComponents();
      uasort($components, function (SectionComponent $a, SectionComponent $b) {
        return $a->getWeight() <=> $b->getWeight();
      });

      foreach ($components as $uuid => $component) {
        $plugin = $component->getPlugin();
        if (!$plugin instanceof BlockPluginInterface) {
          continue;
        }
        $route_args = [
          'section_storage_type' => $section_storage->getStorageType(),
          'section_storage' => $section_storage->getStorageId(),
          'delta' => $delta,
          'region' => $region,
          'uuid' => $uuid,
        ];
        $entity_args = [
          'entity_type' => 'node',
          'entity' => $node->id(),
          'uuid' => $uuid,
        ];
        $rows[$uuid] = [
          $plugin->getPluginDefinition()['admin_label'],
          $plugin->label() ?? '',
          $plugin instanceof GHIBlockBase && $plugin->isHidden() ? $this->t('Hidden') : $this->t('Visible'),
          [
            'data' => [
              '#type' => 'dropbutton',
              '#dropbutton_type' => 'small',
              '#links' => array_filter([
                'hide' => $this->getBlockActionLink($this->t('Hide'), $plugin, 'ghi_blocks.hide_entity_block', $entity_args),
                'unhide' => $this->getBlockActionLink($this->t('Unhide'), $plugin, 'ghi_blocks.unhide_entity_block', $entity_args),
                'remove' => $this->getBlockActionLink($this->t('Remove'), $plugin, 'ghi_blocks.remove_entity_block', $entity_args),
                'show_config' => $this->getBlockActionLink($this->t('Show config'), $plugin, 'ghi_blocks.show_block_config', $route_args),
              ]),
            ],
          ],
        ];
      }

      $form[$region] = [
        '#type' => 'container',
      ];
      $form[$region]['label'] = [
        '#type' => 'html_tag',
        '#tag' => 'h2',
        '#value' => $this->t('Region name: @label', [
          '@label' => $layout_settings['label'],
        ]),
        '#access' => count($sections) > 1,
      ];
      $form[$region]['elements'] = [
        '#type' => 'tableselect',
        '#header' => [
          $this->t('Element type'),
          $this->t('Label'),
          $this->t('Status'),
          $this->t('Operations'),
        ],
        '#options' => $rows,
        '#empty' => $this->t('No elements found in this region'),
      ];
    }
    $this->buildBulkForm($form, $this->getBulkFormActions());
    return $form;
  }

  /**
   * Get an action link to be added to the operations dropbutton.
   *
   * @param string|\Drupal\Core\StringTranslation\TranslatableMarkup $label
   *   The label for the link.
   * @param \Drupal\ghi_blocks\Plugin\Block\GHIBlockBase $plugin
   *   The plugin.
   * @param string $route_name
   *   The route name for the link.
   * @param array $route_args
   *   The route arguments.
   *
   * @return array
   *   A link array to be used by dropbutton.
   */
  private function getBlockActionLink($label, BlockPluginInterface $plugin, $route_name, array $route_args = []) {
    if (!$plugin instanceof GHIBlockBase) {
      return NULL;
    }
    if ($route_name == 'ghi_blocks.hide_entity_block' && $plugin->isHidden()) {
      return NULL;
    }
    if ($route_name == 'ghi_blocks.unhide_entity_block' && !$plugin->isHidden()) {
      return NULL;
    }
    return [
      'url' => Url::fromRoute($route_name, $route_args),
      'title' => $label,
      'attributes' => [
        'class' => [
          'dialog-cancel',
          'use-ajax',
        ],
      ],
    ];
  }

  /**
   * Get the bulk form actions.
   *
   * @return array
   *   An array of action key - label pairs.
   */
  private function getBulkFormActions() {
    return [
      'unhide' => $this->t('Unhide'),
      'hide' => $this->t('Hide'),
      'remove' => $this->t('Remove'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    if ($form_state->getTriggeringElement()['#name'] != 'bulk_submit') {
      return;
    }
    $action = $form_state->getValue('action');
    if (!array_key_exists($action, $this->getBulkFormActions())) {
      return;
    }
    $uuids = array_filter($form_state->getValue('elements'));
    $this->actionComponentOnEntity($action, $form['#node'], $uuids);

    // Stay on the page elements form page.
    $form_state->setIgnoreDestination();
  }

}
