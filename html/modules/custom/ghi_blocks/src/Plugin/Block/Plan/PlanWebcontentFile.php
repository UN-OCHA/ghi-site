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
        'resource' => 'fabric_query:resource',
      ]
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildContent() {
    // Retrieve the resource.
    $conf = $this->getBlockConfig();
    $conf['resource_id'] = $conf['resource_id'] ?? ($conf['attachment_id'] ?? NULL);
    if (empty($conf['resource_id'])) {
      return;
    }

    /** @var \Drupal\hpc_api\Plugin\FabricQuery\ResourceQuery $query */
    $query = $this->getQueryHandler('resource');
    $resource = $query?->getResource($conf['resource_id']) ?? NULL;
    if (!$resource) {
      return NULL;
    }
    return [
      '#theme' => 'ghi_image',
      '#url' => $resource->getUrl()->toString(),
      '#credit' => $resource->getCredit(),
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
      'resource_id' => NULL,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getConfigForm(array $form, FormStateInterface $form_state) {
    $options = [];

    // Retrieve the resources.
    $plan = $this->getCurrentPlanObject();
    /** @var \Drupal\hpc_api\Plugin\FabricQuery\ResourceQuery $query */
    $query = $this->getQueryHandler('resource');
    $resources = $plan ? $query->getResourcesByObject($plan->bundle(), $plan->getSourceId()) : [];

    if (!empty($resources)) {
      foreach ($resources as $resource) {
        $url = $resource->getUrl();
        $url->setOptions([
          'external' => TRUE,
          'attributes' => [
            'target' => '_blank',
          ],
        ]);
        $options[$resource->id()] = [
          'id' => $resource->id(),
          'title' => $resource->getName(),
          'file_name' => $resource->getName(),
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
      'id' => $this->t('Resource ID'),
      'title' => $this->t('Title'),
      'file_name' => $this->t('File name'),
      'file_url' => $this->t('File URL'),
      'preview' => $this->t('Preview'),
    ];

    $form['resource_id'] = [
      '#type' => 'tableselect',
      '#tree' => TRUE,
      '#header' => $table_header,
      '#validated' => TRUE,
      '#options' => $options,
      '#default_value' => $this->getDefaultFormValueFromFormState($form_state, 'resource_id') ?? array_key_first($options),
      '#multiple' => FALSE,
      '#empty' => $this->t('There are no file resources available in the current plan context.'),
      '#required' => TRUE,
    ];
    return $form;
  }

}
