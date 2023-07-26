<?php

namespace Drupal\ghi_hero_image\Plugin\Field\FieldWidget;

use Drupal\Core\Entity\EntityFormInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\ghi_base_objects\Helpers\BaseObjectHelper;
use Drupal\ghi_form_elements\Helpers\FormElementHelper;
use Drupal\ghi_plans\Entity\GoverningEntity;
use Drupal\ghi_plans\Entity\Plan;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines the 'ghi_hero_image' field widget.
 *
 * @FieldWidget(
 *   id = "ghi_hero_image",
 *   label = @Translation("Hero image"),
 *   field_types = {"ghi_hero_image"},
 * )
 */
class HeroImageWidget extends WidgetBase {

  /**
   * The SmugMug user service.
   *
   * @var \Drupal\smugmug_api\Service\User
   */
  public $smugmugUser;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->smugmugUser = $container->get('smugmug_api.user');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return [
      'source' => NULL,
      'settings' => [
        'hpc_webcontent_file_attachment' => NULL,
        'smugmug_api' => NULL,
        'crop_image' => TRUE,
      ],
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {

    $form_object = $form_state->getFormObject();
    $entity = $form_object instanceof EntityFormInterface ? $form_object->getEntity() : NULL;

    // See if we have a plan context.
    $plan_object = NULL;
    $base_object = NULL;
    if ($entity instanceof NodeInterface) {
      $base_object = BaseObjectHelper::getBaseObjectFromNode($entity);
      if ($base_object instanceof GoverningEntity) {
        $plan_object = $base_object->getPlan();
      }
      if ($base_object instanceof Plan) {
        $plan_object = $base_object;
      }
    }

    // See if we can use SmugMug.
    $smugmug_ocha = $this->smugmugUser->getUser('ocha');

    $source_options = array_filter([
      'none' => $this->t('No image'),
      'inherit' => $this->t('Inherit from referenced content (not always possible)'),
      'hpc_webcontent_file_attachment' => $plan_object ? $this->t('HPC Webcontent File Attachment') : NULL,
      'smugmug_api' => $smugmug_ocha ? $this->t('Smugmug: @user', ['@user' => $smugmug_ocha['Name']]) : NULL,
    ]);

    $element['source'] = [
      '#type' => 'select',
      '#title' => $element['#title'],
      '#options' => $source_options,
      '#default_value' => $items[$delta]->source ?? array_key_first($source_options),
    ];

    $element['settings'] = [
      '#type' => 'container',
    ];

    $parents = $element['#field_parents'];
    $parents[] = $this->fieldDefinition->getName();
    $parents[] = $delta;

    $source_selector = FormElementHelper::getStateSelectorFromParents($parents, ['source']);

    $element['settings']['hpc_webcontent_file_attachment'] = [
      '#type' => 'webcontent_file_select',
      '#default_value' => $items[$delta]->settings['hpc_webcontent_file_attachment'] ?? NULL,
      '#plan_object' => $plan_object,
      '#base_object' => $base_object,
      '#states' => [
        'visible' => [
          ':input[name="' . $source_selector . '"]' => ['value' => 'hpc_webcontent_file_attachment'],
        ],
      ],
    ];
    $element['settings']['smugmug_api'] = [
      '#type' => 'smugmug_image',
      '#default_value' => $items[$delta]->settings['smugmug_api'] ?? NULL,
      '#states' => [
        'visible' => [
          ':input[name="' . $source_selector . '"]' => ['value' => 'smugmug_api'],
        ],
      ],
      '#smugmug_user_scope' => $smugmug_ocha,
    ];

    $element['settings']['crop_image'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Crop image'),
      '#default_value' => $items[$delta]->settings['crop_image'] ?? TRUE,
    ];

    return $element;
  }

}
