<?php

namespace Drupal\ghi_geojson\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\ghi_form_elements\Form\WizardBase;
use InvalidArgumentException;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a form for adding new geojson versions.
 */
class GeoJsonUploadForm extends WizardBase {

  /**
   * GeoJSON service.
   *
   * @var \Drupal\ghi_geojson\GeoJson
   */
  public $geojson;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    /** @var \Drupal\ghi_geojson\Form\GeoJsonUploadForm $instance */
    $instance = new static();
    $instance->geojson = $container->get('geojson');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'ghi_geojson_upload_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?string $iso3 = NULL) {
    $form = parent::buildForm($form, $form_state);
    $iso_codes = $this->geojson->getIsoCodes();

    // Define our steps.
    $steps = [
      'country',
      'action',
      'upload',
    ];
    // Find out in which step we currently are.
    $initial_step = array_key_first($steps);
    if ($iso3 !== NULL) {
      if (!in_array($iso3, $iso_codes)) {
        throw new InvalidArgumentException('Invalid argument ' . $iso3 . ' geojson upload form.');
      }
      $initial_step++;
      $form_state->setValue('iso3', $iso3);
    }
    $step = $form_state->get('step') ?: $initial_step;
    $action = self::getActionFromFormState($form_state);

    // Do the step navigation.
    if ($action === 'back' && $step > 0) {
      $step--;
    }
    elseif ($action == 'next' && $step < count($steps)) {
      $step++;
    }
    $form_state->set('step', $step);

    $iso3 = $iso3 ?: $form_state->getValue('iso3');
    $versions = $iso3 ? $this->geojson->getVersionsForIsoCode($iso3) : [];
    $replace_version = $form_state->getValue(['replace_options', 'version']) ?: reset($versions);
    $archive_version_options = $iso3 ? $this->getVersionOptions($iso3, $replace_version) : [];
    $new_version_options = $iso3 ? $this->getVersionOptions($iso3) : [];

    $form['iso3'] = [
      '#type' => 'select',
      '#title' => $this->t('Country (ISO3)'),
      '#description' => !empty($versions) ? $this->t('Existing versions for @iso3: <em>@versions</em>', [
        '@iso3' => $iso3,
        '@versions' => implode(', ', $versions),
      ]) : NULL,
      '#options' => array_combine($iso_codes, $iso_codes),
      '#ajax' => [
        'event' => 'change',
        'callback' => [static::class, 'updateAjax'],
        'wrapper' => $this->ajaxWrapperId,
      ],
      '#default_value' => $form_state->getValue('iso3'),
      '#disabled' => $step > 0,
    ];
    $form['replace'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Replace an existing version'),
      '#default_value' => $form_state->getValue('replace'),
      '#disabled' => $step > array_flip($steps)['action'],
      '#access' => $step >= array_flip($steps)['action'],
    ];
    if (empty($versions)) {
      $form['replace']['#default_value'] = FALSE;
      $form['replace']['#disabled'] = TRUE;
    }
    $form['replace_options'] = [
      '#type' => 'fieldset',
      '#tree' => TRUE,
      '#states' => [
        'visible' => [
          'input[name="replace"]' => ['checked' => TRUE],
        ],
      ],
      '#disabled' => $step > array_flip($steps)['action'],
      '#access' => $step >= array_flip($steps)['action'],
    ];

    $form['replace_options']['version'] = [
      '#type' => 'select',
      '#title' => $this->t('Version to replace'),
      '#options' => array_combine($versions, $versions),
      '#default_value' => $replace_version,
      '#ajax' => [
        'event' => 'change',
        'callback' => [static::class, 'updateAjax'],
        'wrapper' => $this->ajaxWrapperId,
      ],
    ];
    $form['replace_options']['archive_existing'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Archive existing version'),
      '#description' => $this->t('You can only archive the existing version, if there are still versions available between the version to be replaced (<em>@replace_version</em>) and the next version behind that, or if there is only a single version.', [
        '@replace_version' => $replace_version,
      ]),
      '#default_value' => $form_state->getValue(['replace_options', 'archive_existing'], TRUE),
    ];
    if (empty($archive_version_options)) {
      $last_version_before_replace = $versions[array_search($replace_version, $versions) + 1] ?? NULL;
      $form['replace_options']['archive_existing']['#description'] .= '<br />' . $this->t('<span class="form-item__error-message">There are no versions available between <em>@replace_version</em> and <em>@last_version_before_replace</em>.</span>', [
        '@replace_version' => $replace_version,
        '@last_version_before_replace' => $last_version_before_replace,
      ]);
      $form['replace_options']['archive_existing']['#default_value'] = FALSE;
      $form['replace_options']['archive_existing']['#disabled'] = TRUE;
    }
    $form['replace_options']['archive_version'] = [
      '#type' => 'select',
      '#title' => $this->t('Archived version is valid until (and including)'),
      '#options' => $archive_version_options,
      '#default_value' => $form_state->getValue(['replace_options', 'archive_version'], NULL),
      '#states' => [
        'visible' => [
          'input[name="replace_options[archive_existing]"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['new_versions_options'] = [
      '#type' => 'fieldset',
      // '#title' => $this->t('Replace options'),
      '#tree' => TRUE,
      '#states' => [
        'visible' => [
          'input[name="replace"]' => ['checked' => FALSE],
        ],
      ],
      '#disabled' => $step > array_flip($steps)['action'],
      '#access' => $step >= array_flip($steps)['action'],
    ];
    $form['new_versions_options']['version'] = [
      '#type' => 'select',
      '#title' => $this->t('New version is valid until (and including)'),
      '#options' => $new_version_options,
      '#default_value' => $form_state->getValue(['new_versions_options', 'version'], NULL),
    ];

    $form['upload'] = [
      '#type' => 'file',
      '#title' => $this->t('Archive'),
      '#description' => $this->t('The archive must be a zip file following a specific structure. If you are unsure, download an existing GeoJSON version and use that as a reference.'),
      '#disabled' => $step > array_flip($steps)['upload'],
      '#access' => $step >= array_flip($steps)['upload'],
      '#required' => TRUE,
    ];

    $form['actions'] = [
      '#type' => 'actions',
    ];

    if ($step > $initial_step) {
      $form['actions']['back'] = [
        '#type' => 'button',
        '#value' => $this->t('Back'),
        '#limit_validation_errors' => array_filter([
          $step > 0 ? ['iso3'] : NULL,
          $step > array_flip($steps)['action'] ? ['replace_options'] : NULL,
          $step > array_flip($steps)['action'] ? ['replace'] : NULL,
        ]),
        '#ajax' => [
          'event' => 'click',
          'callback' => [static::class, 'updateAjax'],
          'wrapper' => $this->ajaxWrapperId,
        ],
      ];
    }

    if ($step < count($steps) - 1) {
      $form['actions']['next'] = [
        '#type' => 'button',
        '#button_type' => 'primary',
        '#value' => $this->t('Next'),
        '#ajax' => [
          'event' => 'click',
          'callback' => [static::class, 'updateAjax'],
          'wrapper' => $this->ajaxWrapperId,
        ],
      ];
    }
    else {
      $form['actions']['submit'] = [
        '#type' => 'submit',
        '#button_type' => 'primary',
        '#value' => $this->t('Process upload'),
      ];
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $iso3 = $form_state->getValue('iso3');
    $files = $this->getRequest()->files->get('files', []);
    if (!empty($files['upload'])) {
      $file_upload = $files['upload'];
      if (!$file_upload->isValid()) {
        $form_state->setErrorByName('upload', $this->t('The file could not be uploaded.'));
      }
      elseif ($errors = $this->geojson->validateArchiveFile($file_upload->getRealPath(), $iso3)) {
        // Validate the content of the archive.
        $form_state->setErrorByName('upload', $this->t('The uploaded file did not pass validation. The following issues have been found: @errors', [
          '@errors' => implode(', ', $errors),
        ]));
      }
      else {
        $form_state->setValue('upload', $file_upload->getRealPath());
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $iso3 = $form_state->getValue('iso3');
    $replace = $form_state->getValue('replace');
    $replace_options = $form_state->getValue('replace_options');
    $new_versions_options = $form_state->getValue('new_versions_options');
    $upload_file = $form_state->getValue('upload');
    $status = FALSE;
    if ($replace) {
      // We want to replace a version.
      $version = $replace_options['version'];
      if ($replace_options['archive_existing']) {
        // But first we want to archive the version that is to be replaced.
        // Renaming is sufficient.
        $archive_version = $replace_options['archive_version'];
        $status = $this->geojson->renameVersion($iso3, $version, $archive_version);
        if ($status) {
          $this->messenger()->addStatus($this->t('The GeoJSON version <em>@version</em> for <em>@iso3</em> has been archived as version <em>@archive_version</em>.', [
            '@iso3' => $iso3,
            '@version' => $version,
            '@archive_version' => $archive_version,
          ]));
        }
        else {
          $this->messenger()->addError($this->t('There was an error archiving GeoJSON version <em>@version</em> for <em>@iso3</em> as archive version <em>@archive_version</em>.', [
            '@iso3' => $iso3,
            '@version' => $version,
            '@archive_version' => $archive_version,
          ]));
          $status = FALSE;
        }
      }
      if ($status) {
        $status = $this->geojson->saveUploadArchive($iso3, $version, $upload_file);
        $this->messenger()->addStatus($this->t('The GeoJSON archive has been successfully uploaded as version <em>@version</em>.', [
          '@version' => $version,
        ]));
      }
    }
    else {
      // We want to add a new version without replacing one.
      $version = $new_versions_options['version'];
      $status = $this->geojson->saveUploadArchive($iso3, $version, $upload_file);
      if ($status) {
        $this->messenger()->addStatus($this->t('The GeoJSON archive has been successfully uploaded as version <em>@version</em>.', [
          '@version' => $version,
        ]));
      }
    }
    if ($status) {
      $form_state->setRedirectUrl(Url::fromRoute('ghi_geojson.geojson_sources.directory_listing', [
        'iso3' => $iso3,
        'version' => $version,
      ]));
    }
    else {
      $this->messenger()->addError($this->t('There was an error uploading GeoJSON version <em>@version</em> for <em>@iso3</em>.', [
        '@iso3' => $iso3,
        '@version' => $version,
      ]));
    }
  }

  /**
   * Get version options.
   *
   * @param string $iso3
   *   The iso3 code of the country.
   * @param int|string $replace_version
   *   Either 'current' or a 4 digit number representing the version year.
   *
   * @return int[]
   *   An array of integers, representing the versions.
   */
  private function getVersionOptions(string $iso3, mixed $replace_version = NULL): array {
    if (empty($iso3)) {
      return [];
    }
    $options = [];
    $versions = $this->geojson->getVersionsForIsoCode($iso3);
    $min = 2010;
    $max = date('Y') - 1;
    $range = range($min, $max);
    if ($replace_version === NULL) {
      $options = array_diff($range, $versions);
    }
    else if ($replace_version == 'current' && count($versions) == 1) {
      $options = $range;
    }
    else if ($replace_version == 'current' && count($versions) > 1) {
      $previous_version = $versions[1];
      $options = array_filter($range, function ($year) use ($previous_version) {
        return $year > $previous_version;
      });
    }
    else if ($replace_version != 'current' && count($versions) > 1) {
      $index = array_search($replace_version, $versions);
      $previous_version = $versions[$index + 1] ?? NULL;
      $options = array_filter($range, function ($year) use ($replace_version, $previous_version) {
        return $year < $replace_version && ($previous_version === NULL || $year > $previous_version);
      });
    }
    return array_reverse(array_combine($options, $options), TRUE);
  }

}
