<?php

namespace Drupal\ghi_blocks\Plugin\ConfigurationContainerItem;

use Drupal\Component\Utility\NestedArray;
use Drupal\Component\Utility\Unicode;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element\RenderElementBase;
use Drupal\Core\Render\Element\VerticalTabs;
use Drupal\Core\Render\Markup;
use Drupal\file\Entity\File;
use Drupal\ghi_blocks\Traits\VerticalTabsTrait;
use Drupal\ghi_form_elements\ConfigurationContainerItemPluginBase;
use Drupal\ghi_form_elements\Helpers\FormElementHelper;
use Drupal\ghi_form_elements\Traits\CustomLinkTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a link item for configuration containers.
 *
 * @ConfigurationContainerItem(
 *   id = "link",
 *   label = @Translation("Link"),
 *   description = @Translation("This item displays a link with a title, a description and an optional image."),
 * )
 */
class Link extends ConfigurationContainerItemPluginBase {

  use CustomLinkTrait;
  use VerticalTabsTrait;

  const TITLE_MAX_LENGTH = 150;
  const DESCRIPTION_MAX_LENGTH = 250;
  const THUMBNAIL_DIRECTORY = 'public://content-panes/link-images/';
  // Also allows 'paper_size' but this has been deactivated for the moment,
  // see https://humanitarian.atlassian.net/browse/HPC-9391.
  const CROP_TYPES = ['16x9'];
  const CROP_THUMBNAIL_STYLE = 'crop_thumbnail_2x';
  const RESPONSIVE_IMAGE_STYLE = 'card_hero';

  /**
   * The date formatter service.
   *
   * @var \Drupal\Core\Datetime\DateFormatterInterface
   */
  public $dateFormatter;

  /**
   * The hero image widget crop manager.
   *
   * @var \Drupal\ghi_image\CropManager
   */
  public $cropManager;

  /**
   * The embargoed access manager service.
   *
   * @var \Drupal\ghi_embargoed_access\EmbargoedAccessManager
   */
  protected $embargoedAccessManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->dateFormatter = $container->get('date.formatter');
    $instance->cropManager = $container->get('ghi_image.crop_manager');
    $instance->embargoedAccessManager = $container->has('ghi_embargoed_access.manager') ? $container->get('ghi_embargoed_access.manager') : NULL;
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm($element, FormStateInterface $form_state) {
    $element['#attached']['library'] = [
      'ghi_blocks/block_config.links',
      'maxlength/maxlength',
    ];

    $element = parent::buildForm($element, $form_state);

    $element['tabs'] = [
      '#type' => 'vertical_tabs',
      '#parents' => array_merge($element['#parents'], ['tabs']),
      '#default_tab' => 'link',
    ];

    $element['label'] = [
      '#title' => $this->t('Title'),
      '#required' => TRUE,
      '#maxlength' => self::TITLE_MAX_LENGTH,
      '#attributes' => [
        'data-maxlength' => self::TITLE_MAX_LENGTH,
        '#maxlength_js_enforce' => TRUE,
        'class' => ['maxlength'],
      ],
    ] + $element['label'];

    $element['link'] = [
      '#type' => 'details',
      '#title' => $this->t('Link'),
      '#tree' => TRUE,
      '#group' => 'tabs',
    ];

    $element['link']['link'] = [
      '#type' => 'custom_link',
      '#title' => $this->t('Add a link to this element'),
      '#default_value' => $this->config['link']['link'] ?? [],
      '#element_context' => $this->getContext(),
      '#no_label' => TRUE,
      '#required' => TRUE,
    ];

    $element['content'] = [
      '#type' => 'details',
      '#title' => $this->t('Content and description'),
      '#tree' => TRUE,
      '#group' => 'tabs',
    ];
    $element['content']['date'] = [
      '#type' => 'date',
      '#title' => $this->t('Date'),
      '#default_value' => $this->config['content']['date'] ?? date('Y-m-d'),
      '#required' => TRUE,
    ];

    $element['content']['description_toggle'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Use free-text description instead of the date.'),
      '#default_value' => $this->config['content']['description_toggle'] ?? NULL,
    ];
    $toggle_selector = FormElementHelper::getStateSelector($element, ['content', 'description_toggle']);
    $element['content']['description'] = [
      '#type' => 'text_format',
      '#title' => $this->t('Description'),
      '#format' => 'wysiwyg_simple',
      '#allowed_formats' => ['wysiwyg_simple'],
      '#default_value' => $this->config['content']['description']['value'] ?? NULL,
      '#states' => [
        'visible' => [
          ':input[name="' . $toggle_selector . '"]' => ['checked' => TRUE],
        ],
      ],
      '#attributes' => [
        'data-maxlength' => self::DESCRIPTION_MAX_LENGTH,
        '#maxlength_js_enforce' => TRUE,
        'class' => ['maxlength'],
      ],
    ];

    $element['image'] = [
      '#type' => 'details',
      '#title' => $this->t('Image'),
      '#tree' => TRUE,
      '#group' => 'tabs',
    ];
    $element['image']['image'] = [
      '#type' => 'managed_file',
      '#upload_location' => self::THUMBNAIL_DIRECTORY,
      '#upload_validators' => [
        'file_validate_extensions' => ['jpg jpeg png gif'],
      ],
      '#default_value' => $this->config['image']['image'] ?? NULL,
      // Add properties needed by value() and process() methods.
      '#field_name' => $this->t('Image'),
    ];
    $image_selector = FormElementHelper::getStateSelector($element, ['image', 'image', 'fids']);

    // Also allows to define the crop type but this has been deactivated for
    // the moment, see https://humanitarian.atlassian.net/browse/HPC-9391.
    // @codingStandardsIgnoreStart
    // $crop_type_options = [];
    // foreach (self::CROP_TYPES as $crop_type) {
    //   $crop_type_options[$crop_type] = $this->entityTypeManager->getStorage('crop_type')->load($crop_type)->label();
    // }
    // $element['image']['image']['crop_type'] = [
    //   '#type' => 'select',
    //   '#title' => $this->t('Display'),
    //   '#options' => $crop_type_options,
    //   '#default_value' => $this->config['image']['crop_type'] ?? NULL,
    //   '#weight' => 5,
    //   '#wrapper_attributes' => [
    //     'style' => 'width: 100%;',
    //   ],
    //   '#states' => [
    //     'visible' => [
    //       ':input[name="' . $image_selector . '"]' => ['empty' => FALSE],
    //     ],
    //   ],
    // ];
    // @codingStandardsIgnoreEnd
    $element['image']['image']['crop_type'] = [
      '#type' => 'hidden',
      '#value' => self::CROP_TYPES[0],
    ];

    // Note: We nest the image crop inside the image widget so that it get's
    // updated together with the image widget when file operations are done
    // (upload, remove), even though this leads to problems. Specifically, the
    // image widget sets the fid as the only item inside the submitted values
    // of the widget, so all the crop information is lost. It's still available
    // in the user input and we can retrieve it from there in
    // self::validateElement().
    $user_input = $form_state->getUserInput();
    $file_id = NULL;
    if (!empty(NestedArray::keyExists($user_input, $element['#parents']))) {
      $file_id = $form_state->get('image_fid');
    }
    $element['image']['image']['image_crop'] = [
      '#type' => 'ghi_image_crop',
      '#file' => $this->loadFile($file_id),
      '#crop_type_list' => self::CROP_TYPES,
      '#crop_preview_image_style' => self::CROP_THUMBNAIL_STYLE,
      '#show_default_crop' => TRUE,
      '#show_crop_area' => TRUE,
      '#warn_mupltiple_usages' => TRUE,
      '#default_value' => $this->config['image']['image_crop'] ?? NULL,
      '#states' => [
        'visible' => [
          ':input[name="' . $image_selector . '"]' => ['empty' => FALSE],
        ],
      ],
      '#weight' => 100,
    ];

    $element['#element_validate'] = [
      [static::class, 'validateElement'],
    ];

    // Let the tab element set itself up.
    VerticalTabs::processVerticalTabs($element['tabs'], $form_state, $form_state->getCompleteForm());
    RenderElementBase::processGroup($element['tabs']['group'], $form_state, $form_state->getCompleteForm());

    $this->processVerticalTabs($element, $form_state);

    return $element;
  }

  /**
   * Validate the link form element.
   *
   * @param array $element
   *   The element build in ::buildForm().
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state object.
   * @param array $complete_form
   *   The complete form that contains the element.
   */
  public static function validateElement(&$element, FormStateInterface $form_state, &$complete_form) {
    // Get the image fid and store it in the form state.
    $file_id = $form_state->getValue($element['#parents'])['image']['image'][0] ?? NULL;
    $form_state->set('image_fid', $file_id);

    // Extract the cropping information from the user input.
    $user_input = $form_state->getUserInput();
    $crop_type = NestedArray::getValue($user_input, array_merge($element['#parents'], ['image', 'image', 'crop_type']));
    $form_state->setValue(array_merge($element['#parents'], ['image', 'crop_type']), $crop_type);

    $image_crop = NestedArray::getValue($user_input, array_merge($element['#parents'], ['image', 'image', 'image_crop']));
    if ($file_id && $file = File::load($file_id)) {
      $image_crop['file-uri'] = $file->getFileUri();
    }
    $form_state->setValue(array_merge($element['#parents'], ['image', 'image_crop']), $image_crop);

    // @todo This repeats logic from
    // \Drupal\ghi_form_elements\Element\CustomLink::elementValidate().
    $link_config = NestedArray::getValue($form_state->getValue($element['#parents']), ['link', 'link']) ?? [];
    if ($link_config['link_type'] == 'custom') {
      $url = NestedArray::getValue($link_config, ['link_custom', 'url']);
      $transformed_url = self::transformUrl($url);
      if (!$transformed_url) {
        $form_state->setError($element['link']['link']['link_custom']['url'], t('The link URL must be valid and accessible.'));
      }
      if (!$form_state->hasAnyErrors() && $transformed_url !== $url) {
        $form_state->setValue(array_merge($element['#parents'], ['link', 'link', 'link_custom', 'url']), $transformed_url);
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array $values, $mode) {
    if (!in_array($mode, ['add_item', 'edit_item'])) {
      return;
    }
    $file_uri = $values['image']['image_crop']['file-uri'] ?? NULL;
    if (!$file_uri) {
      return;
    }
    foreach (self::CROP_TYPES as $crop_type) {
      $this->cropManager->processCropSubmit($file_uri, $crop_type, $values['image']['image_crop']);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getRenderArray() {
    $link_config = NestedArray::getValue($this->config, ['link', 'link']) ?? [];
    $link = $this->getLinkFromConfiguration($link_config, $this->getContext());
    if (!$link) {
      return [];
    }
    $build = [
      '#theme' => 'link_box',
      '#title' => Unicode::truncate($this->config['label'], self::TITLE_MAX_LENGTH),
      '#description' => $this->getDescription(),
      '#link' => $link->toRenderable(),
    ];
    $file = $this->loadFile();
    if ($file && file_exists($file->getFileUri())) {
      $image_style = 'card_hero';
      if ($this->config['image']['crop_type'] == 'paper_size') {
        $image_style = 'paper_size';
      }
      $build['#image'] = [
        '#theme' => 'ghi_image',
        '#responsive_image_style' => $this->entityTypeManager->getStorage('responsive_image_style')->load($image_style),
        '#url' => $file->getFileUri(),
      ];
    }
    return $build;
  }

  /**
   * Get the URL preview string for the link.
   *
   * @return string
   *   The string representation of the url.
   */
  public function getUrlString() {
    $link = $this->getLinkFromConfiguration($this->config['link']['link'], $this->getContext());
    $url = $link?->getUrl() ?? NULL;
    $url_string = $url?->toString();
    $classes = [];
    if ($url && $this->embargoedAccessManager && $this->embargoedAccessManager->isProtectedUrl($url)) {
      $classes[] = 'protected';
    }
    return [
      '#type' => 'html_tag',
      '#tag' => 'span',
      '#value' => $url_string,
      '#attributes' => ['class' => $classes],
    ];
  }

  /**
   * Get the date for a link.
   *
   * @return string|null
   *   The formatted date of the link.
   */
  public function getFormattedDate() {
    $date = $this->config['content']['date'];
    $timestamp = strtotime($date);
    return $timestamp ? $this->dateFormatter->format($timestamp, 'custom', 'd M Y') : NULL;
  }

  /**
   * Get the image file if any has been uploaded.
   *
   * @return \Drupal\file\FileInterface|null
   *   The file entity object or NULL.
   */
  public function getImageFile() {
    return $this->loadFile();
  }

  /**
   * Get the image preview.
   *
   * @return array
   *   A render array.
   */
  public function getImage() {
    $file = $this->loadFile();
    if (!$file) {
      return NULL;
    }
    $image_style = '16x9_480';
    if ($this->config['image']['crop_type'] == 'paper_size') {
      $image_style = 'paper_size_480';
    }
    return [
      '#theme' => 'image_style',
      '#style_name' => $image_style,
      '#uri' => $file->getFileUri(),
      '#attached' => [
        'library' => [
          'ghi_blocks/block_config.links',
        ],
      ],
    ];
  }

  /**
   * Get the description for the link.
   *
   * @return string|\Drupal\Component\Render\MarkupInterface
   *   The description markup.
   */
  public function getDescription() {
    if ($this->shouldDisplayDescription()) {
      $description = $this->config['content']['description']['value'];
      return Markup::create($description);
    }
    else {
      return $this->getFormattedDate();
    }
  }

  /**
   * Whether the link should display the description or not.
   *
   * @return bool
   *   TRUE if the description should be shown, FALSE otherwise which will show
   *   the date.
   */
  protected function shouldDisplayDescription() {
    return !empty($this->config['content']['description_toggle']);
  }

  /**
   * Get the file id of the uploaded image if any.
   *
   * @return int|null
   *   The file id or NULL.
   */
  private function getImageFileId() {
    if (empty($this->config['image']['image']) || !is_array($this->config['image']['image'])) {
      return NULL;
    }
    return reset($this->config['image']['image']);
  }

  /**
   * Load the associated file if any has been uploaded.
   *
   * @param int $file_id
   *   The file id to load or NULL to load the configured file.
   *
   * @return \Drupal\file\FileInterface|null
   *   The file entity object or NULL.
   */
  private function loadFile($file_id = NULL) {
    $file_id = $file_id ?? $this->getImageFileId();
    return $file_id ? $this->entityTypeManager->getStorage('file')->load($file_id) : NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getConfigurationErrors() {
    $errors = [];
    if (!$this->getRenderArray()) {
      $errors[] = $this->t('The link cannot be rendered. Verify that the target page is publicly available.');
    }
    return $errors;
  }

}
