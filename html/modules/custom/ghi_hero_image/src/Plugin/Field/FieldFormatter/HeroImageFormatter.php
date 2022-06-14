<?php

namespace Drupal\ghi_hero_image\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Markup;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Url;
use Drupal\ghi_base_objects\Helpers\BaseObjectHelper;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\hpc_common\Helpers\ThemeHelper;

/**
 * Plugin implementation of the 'ghi_hero_image' formatter.
 *
 * @FieldFormatter(
 *   id = "ghi_hero_image",
 *   label = @Translation("Default"),
 *   field_types = {"ghi_hero_image"}
 * )
 */
class HeroImageFormatter extends FormatterBase implements ContainerFactoryPluginInterface {

  /**
   * The responsive image style storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $responsiveImageStyleStorage;

  /**
   * The attachment query.
   *
   * @var \Drupal\ghi_plans\Plugin\EndpointQuery\PlanEntitiesQuery
   */
  public $entitiesQuery;

  /**
   * The SmugMug image service.
   *
   * @var \Drupal\smugmug_api\Service\Image
   */
  public $smugmugImage;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * The link generator.
   *
   * @var \Drupal\Core\Utility\LinkGeneratorInterface
   */
  protected $linkGenerator;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->responsiveImageStyleStorage = $container->get('entity_type.manager')->getStorage('responsive_image_style');
    $instance->entitiesQuery = $container->get('plugin.manager.endpoint_query_manager')->createInstance('plan_entities_query');
    $instance->smugmugImage = $container->get('smugmug_api.image');
    $instance->currentUser = $container->get('current_user');
    $instance->linkGenerator = $container->get('link_generator');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return [
      'responsive_image_style' => '',
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $responsive_image_options = [];
    $responsive_image_styles = $this->responsiveImageStyleStorage->loadMultiple();
    uasort($responsive_image_styles, '\Drupal\responsive_image\Entity\ResponsiveImageStyle::sort');
    if ($responsive_image_styles && !empty($responsive_image_styles)) {
      foreach ($responsive_image_styles as $machine_name => $responsive_image_style) {
        if ($responsive_image_style->hasImageStyleMappings()) {
          $responsive_image_options[$machine_name] = $responsive_image_style->label();
        }
      }
    }

    $elements['responsive_image_style'] = [
      '#title' => $this->t('Responsive image style'),
      '#type' => 'select',
      '#default_value' => $this->getSetting('responsive_image_style') ?: NULL,
      '#required' => TRUE,
      '#options' => $responsive_image_options,
      '#description' => [
        '#markup' => $this->linkGenerator->generate($this->t('Configure Responsive Image Styles'), new Url('entity.responsive_image_style.collection')),
        '#access' => $this->currentUser->hasPermission('administer responsive image styles'),
      ],
    ];
    return $elements;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $summary = [];

    $responsive_image_style = $this->responsiveImageStyleStorage->load($this->getSetting('responsive_image_style'));
    if ($responsive_image_style) {
      $summary[] = $this->t('Responsive image style: @responsive_image_style', [
        '@responsive_image_style' => $responsive_image_style->label(),
      ]);
    }
    else {
      $summary[] = $this->t('Select a responsive image style.');
    }

    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {

    // This all assumes to show web attachments for the moment, which obviously
    // only works for plan sections.
    $element = [];

    $image_url = NULL;
    if ($items->isEmpty() && $attachments = $this->getPlanWebContentAttachments($items)) {
      // If there is noting configured but we managed to get hold of some
      // webcontent attachments, just use the first one.
      $attachment = reset($attachments);
      $image_url = $attachment->url;
    }
    elseif (!$items->isEmpty()) {
      // Otherwise see what's been setup.
      $item = (object) $items->get(0)->getValue();
      if ($item->source == 'hpc_webcontent_file_attachment') {
        // Find the right attachment based on the configuration, or fallback to
        // the first available attachment.
        $attachments = $this->getPlanWebContentAttachments($items);
        $attachment = $attachments ? reset($attachments) : NULL;
        $attachment_id = $item->settings[$item->source]['attachment_id'] ?? array_key_first($attachments);
        if ($attachment_id && !empty($attachments[$attachment_id])) {
          $attachment = $attachments[$attachment_id];
          $image_url = $attachment->url;
        }
      }
      if ($item->source == 'smugmug_api') {
        $image_id = $item->settings[$item->source]['image_id'] ?? NULL;
        $image_urls = $image_id ? $this->smugmugImage->getImageSizes($image_id) : NULL;
        $image_url = $image_urls['X3LargeImageUrl'] ?? NULL;
      }
    }

    if ($image_url) {
      $preview_image = ThemeHelper::theme('imagecache_external_responsive', [
        '#uri' => $image_url,
        '#responsive_image_style_id' => $this->getSetting('responsive_image_style') ?: 'hero',
        '#attributes' => [
          'style' => 'width: 100%',
        ],
      ], TRUE, FALSE);
      $element[0]['source'] = [
        '#type' => 'item',
        '#markup' => Markup::create($preview_image),
      ];
    }

    return $element;
  }

  /**
   * Get the webcontent file attachments of a plan if possible.
   *
   * @param \Drupal\Core\Field\FieldItemListInterface $items
   *   The field values to be rendered.
   *
   * @return \Drupal\ghi_plans\ApiObjects\Attachments\AttachmentInterface[]
   *   An array of attachment objects.
   */
  private function getPlanWebContentAttachments(FieldItemListInterface $items) {
    $entity = $items->getEntity();
    $base_object = BaseObjectHelper::getBaseObjectFromNode($entity);
    $plan_object = $base_object && $base_object->bundle() == 'plan' ? $base_object : NULL;
    if (!$plan_object) {
      return [];
    }
    $this->entitiesQuery->setPlaceholder('plan_id', $plan_object->field_original_id->value);
    return $this->entitiesQuery->getWebContentFileAttachments();
  }

}
