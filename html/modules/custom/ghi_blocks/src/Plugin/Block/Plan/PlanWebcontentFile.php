<?php

namespace Drupal\ghi_blocks\Plugin\Block\Plan;

use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\Plugin\Context\EntityContextDefinition;
use Drupal\ghi_blocks\Plugin\Block\GHIBlockBase;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\hpc_common\Plugin\HPCBlockMetadata;

/**
 * Provides a 'PlanWebcontentFile' block.
 */
#[Block(
  id: 'plan_webcontent_file',
  admin_label: new TranslatableMarkup('Web Content File'),
  category: new TranslatableMarkup('Plan elements'),
  context_definitions: [
    'node' => new EntityContextDefinition('entity:node', new TranslatableMarkup('Node')),
    'plan' => new EntityContextDefinition('entity:base_object', new TranslatableMarkup('Plan'), constraints: ['Bundle' => 'plan']),
    'plan_cluster' => new EntityContextDefinition('entity:base_object', new TranslatableMarkup('Cluster'), required: FALSE, constraints: ['Bundle' => 'governing_entity']),
  ]
)]
class PlanWebcontentFile extends GHIBlockBase {

  /**
   * {@inheritdoc}
   */
  public static function metadata(): ?HPCBlockMetadata {
    return new HPCBlockMetadata(
      usesTitle: FALSE,
      dataSources: [
        'file_asset' => 'fabric_query:file_asset',
      ]
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildContent() {
    // Retrieve the file asset.
    $conf = $this->getBlockConfig();
    $conf['file_asset_id'] = $conf['file_asset_id'] ?? ($conf['attachment_id'] ?? NULL);
    if (empty($conf['file_asset_id'])) {
      return;
    }

    /** @var \Drupal\hpc_api\Plugin\FabricQuery\FileAssetQuery $query */
    $query = $this->getQueryHandler('file_asset');
    $file_asset = $query?->getFileAsset($conf['file_asset_id']) ?? NULL;
    if (!$file_asset) {
      return NULL;
    }
    return [
      '#theme' => 'ghi_image',
      '#url' => $file_asset->getUrl()->toString(),
      '#credit' => $file_asset->getCredit(),
      '#style' => 'wide',
    ];
  }

  /**
   * Returns generic default configuration for block plugins.
   *
   * @return array
   *   An associative array with the default configuration.
   */
  protected function getConfigurationDefaults() {
    return [
      'file_asset_id' => NULL,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getConfigForm(array $form, FormStateInterface $form_state) {
    $options = [];

    // Retrieve the file assets.
    $plan = $this->getCurrentPlanObject();
    /** @var \Drupal\hpc_api\Plugin\FabricQuery\FileAssetQuery $query */
    $query = $this->getQueryHandler('file_asset');
    $file_assets = $plan ? $query->getFileAssetsByObject($plan->bundle(), $plan->getSourceId()) : [];

    if (!empty($file_assets)) {
      foreach ($file_assets as $file_asset) {
        $url = $file_asset->getUrl();
        $url->setOptions([
          'external' => TRUE,
          'attributes' => [
            'target' => '_blank',
          ],
        ]);
        $options[$file_asset->id()] = [
          'id' => $file_asset->id(),
          'title' => $file_asset->getName(),
          'file_name' => $file_asset->getName(),
          'file_url' => Link::fromTextAndUrl($url->toString(), $url),
          'preview' => [
            'data' => [
              '#theme' => 'imagecache_external',
              '#style_name' => 'thumbnail',
              '#uri' => $url->toString(),
            ],
          ],
        ];
      }
    }

    $table_header = [
      'id' => $this->t('File asset ID'),
      'title' => $this->t('Title'),
      'file_name' => $this->t('File name'),
      'file_url' => $this->t('File URL'),
      'preview' => $this->t('Preview'),
    ];

    $form['file_asset_id'] = [
      '#type' => 'tableselect',
      '#tree' => TRUE,
      '#header' => $table_header,
      '#validated' => TRUE,
      '#options' => $options,
      '#default_value' => $this->getDefaultFormValueFromFormState($form_state, 'file_asset_id') ?? array_key_first($options),
      '#multiple' => FALSE,
      '#empty' => $this->t('There are no file assets available in the current plan context.'),
      '#required' => TRUE,
    ];
    return $form;
  }

}
