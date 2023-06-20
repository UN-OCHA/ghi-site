<?php

namespace Drupal\ghi_blocks\Plugin\Block\Generic;

use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\ghi_blocks\Plugin\Block\GHIBlockBase;
use Drupal\ghi_form_elements\Helpers\FormElementHelper;

/**
 * Provides an 'External Widget' block.
 *
 * @Block(
 *  id = "generic_external_widget",
 *  admin_label = @Translation("External Widget"),
 *  category = @Translation("Generic elements"),
 *  title = false,
 *  valid_source_elements = {
 *    "generic_external_widgets",
 *    "plan_external_widget"
 *  },
 *  context_definitions = {
 *    "plan" = @ContextDefinition("entity:base_object:plan", label = @Translation("Plan"), required = FALSE),
 *    "year" = @ContextDefinition("integer", label = @Translation("Year"), required = FALSE)
 *  }
 * )
 */
class ExternalWidget extends GHIBlockBase {

  const MAX_ITEMS = 2;
  const MIN_YEAR_HISTORICAL_HPC_DATA = 2011;
  const MAX_YEAR_RANGE = 10;
  const GOOGLE_SHEET = '1MArQSVdbLXLaQ8ixUKo9jIjifTCVDDxTJYbGoRuw3Vw';

  /**
   * {@inheritdoc}
   */
  public function buildContent() {

    // Get the config.
    $conf = $this->getBlockConfig();

    $widgets = $conf['widgets'];
    if (empty($widgets)) {
      return;
    }

    // Limit to what's been setup.
    $widgets = array_slice($widgets, 0, $conf['select_number']);

    $iframes = [];
    foreach ($widgets as $widget) {
      if (empty($widget['widget_url'] ?? NULL)) {
        // Nothing here.
        continue;
      }
      $host = parse_url($widget['widget_url'], PHP_URL_HOST);
      if (strpos($host, 'humdata.org') !== FALSE) {
        // Special handling of HDX quick charts.
        $widget = $this->processWidget($widget);
      }

      $iframe = [
        '#type' => 'html_tag',
        '#tag' => 'div',
        '#attributes' => [
          'class' => [
            'iframe-wrapper',
            'external-widget-iframe-wrapper',
          ],
          'style' => 'height: ' . $widget['widget_height'],
        ],
        [
          '#type' => 'html_tag',
          '#tag' => 'iframe',
          '#attributes' => [
            'src' => $widget['widget_url'],
            'width' => '100%',
            'height' => '100%',
            'style' => 'height: 100%; width: 100%',
            'frameborder' => '0',
            'mozallowfullscreen' => '1',
            'msallowfullscreen' => '1',
            'oallowfullscreen' => '1',
            'webkitallowfullscreen' => '1',
          ],
        ],
      ];
      $iframes[] = $iframe;
    }

    if (empty($iframes)) {
      return;
    }

    return [
      '#attributes' => [
        'class' => [
          'external-widget',
          'up-' . $conf['select_number'],
        ],
      ],
    ] + $iframes;
  }

  /**
   * Returns generic default configuration for block plugins.
   *
   * @return array
   *   An associative array with the default configuration.
   */
  protected function getConfigurationDefaults() {
    return [
      'select_number' => 1,
      'widgets' => [],
    ];
  }

  /**
   * Get a list of the allowed hosts for external widgets.
   *
   * @return array
   *   An array with the allowed external hosts, the key is the internal value
   *   and the value is the label to show in the interface.
   */
  private function getAllowedHosts() {
    return [
      'humdata.org' => $this->t('HDX Quick Charts'),
      'powerbi.com' => $this->t('PowerBI'),
      'tableau.com' => $this->t('Tableau'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getConfigForm(array $form, FormStateInterface $form_state) {

    $allowed_hosts = $this->getAllowedHosts();

    $form['select_number'] = [
      '#type' => 'select',
      '#title' => $this->t('Select the number of links that you want to add.'),
      '#options' => array_combine(range(1, self::MAX_ITEMS), range(1, self::MAX_ITEMS)),
      '#default_value' => $this->getDefaultFormValueFromFormState($form_state, 'select_number'),
    ];

    $form['widgets'] = [
      '#tree' => TRUE,
    ];

    $select_number_selector = FormElementHelper::getStateSelector($form, ['select_number']);

    $default_widgets = array_values($this->getDefaultFormValueFromFormState($form_state, 'widgets'));
    for ($i = 1; $i <= self::MAX_ITEMS; $i++) {
      $default = $default_widgets[$i - 1] ?? [];
      $state_conditions = [];
      for ($j = $i; $j <= self::MAX_ITEMS; $j++) {
        $state_conditions[] = [
          ':input[name="' . $select_number_selector . '"]' => [
            'value' => (string) $j,
          ],
        ];
        if ($j != self::MAX_ITEMS) {
          $state_conditions[] = 'or';
        }
      }

      $form['widgets'][$i] = [
        '#type' => 'details',
        '#title' => $this->t('Widget #@number', ['@number' => $i]),
        '#open' => !empty($default['widget_url']),
        '#tree' => TRUE,
        '#states' => [
          'visible' => $state_conditions,
        ],
      ];

      $form['widgets'][$i]['widget_url'] = [
        '#type' => 'textfield',
        '#title' => $this->t('iframe URL'),
        '#description' => $this->t('Supported external widgets are @first_supported and @last_supported. Please note that in the case of HDX Quick Charts, the following parameters will be automatically overwritten: <em>externalCss</em>, <em>chartSettings</em>, <em>chartShare</em> and <em>allowBiteSwitch</em>', [
          '@first_supported' => implode(', ', array_slice($allowed_hosts, 0, -1)),
          '@last_supported' => end($allowed_hosts),
        ]),
        '#default_value' => trim($default['widget_url'] ?? ''),
        '#maxlength' => 8096,
        '#states' => [
          'required' => $state_conditions,
        ],
      ];

      $widget_url_selector = FormElementHelper::getStateSelector($form, [
        'widgets',
        $i,
        'widget_url',
      ]);
      $form['widgets'][$i]['process_widget_url'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Process widget URL'),
        '#description' => $this->t('<em>For HDX quickcharts using the <a href="https://docs.google.com/spreadsheets/d/@google_docs_id">GHO Historical Data Google Spreadsheet</a> only:</em> Process the widget URL to correct some common misconfigurations and to automatically apply year filtering on the data to only show data points up to the year that is relevant for the current page and never more than @max_year_range years in the past. It also overrides the chart titles to assure consistent naming and correct labeling based on the years for which data is shown.', [
          '@google_docs_id' => self::GOOGLE_SHEET,
          '@max_year_range' => self::MAX_YEAR_RANGE,
        ]),
        '#default_value' => array_key_exists('process_widget_url', $default) ? $default['process_widget_url'] : TRUE,
        '#states' => [
          'visible' => [
            ':input[name="' . $widget_url_selector . '"]' => [
              // Matching by regular expression only works due to
              // Drupal.hpc_content_panes_states_extension defined in
              // ghi_form_elements/states_regex.
              // See https://evolvingweb.ca/blog/extending-form-api-states-regular-expressions
              // for details.
              'value' => ['regex' => 'humdata\.org.*' . self::GOOGLE_SHEET],
            ],
          ],
        ],
        '#attached' => [
          'library' => ['ghi_form_elements/states_regex'],
        ],
      ];
      $form['widgets'][$i]['widget_url_skip_validation'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Skip URL validation'),
        '#default_value' => $default['widget_url_skip_validation'] ?? NULL,
      ];
      $form['widgets'][$i]['widget_height'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Height'),
        '#description' => $this->t('Enter the height of the iframe including the unit if necessary. E.g.: <em>400px</em>, <em>50%</em> or <em>auto</em>'),
        '#default_value' => $default['widget_height'] ?? NULL,
        '#states' => [
          'required' => $state_conditions,
        ],
      ];
    }

    return $form;
  }

  /**
   * Validate handler for portlet configuration form.
   */
  public function blockValidate($form, FormStateInterface $form_state) {
    $allowed_hosts = $this->getAllowedHosts();
    $values = $form_state->getValue($form_state->get('current_subform')) ?? [];
    $subform = $form['container'];

    if (!array_key_exists('select_number', $values)) {
      // This is probably a block submit originating from the preview screen.
      return;
    }

    // Normal form submission.
    for ($i = 1; $i <= $values['select_number']; $i++) {
      $widget_url = trim($values['widgets'][$i]['widget_url']);
      $widget_height = trim($values['widgets'][$i]['widget_height']);
      if (empty($widget_url)) {
        $element = $subform['widgets'][$i]['widget_url'];
        $form_state->setError($element, $this->t('The @label field is required', [
          '@label' => $element['#title'],
        ]));
      }
      if (empty($widget_height)) {
        $element = $subform['widgets'][$i]['widget_height'];
        $form_state->setError($element, $this->t('The @label field is required', [
          '@label' => $element['#title'],
        ]));
      }
      elseif ($widget_height != 'auto' && ((string) (int) $widget_height) === $widget_height) {
        $element = $subform['widgets'][$i]['widget_height'];
        $form_state->setError($element, $this->t('The @label field does not seem to contain a unit', [
          '@label' => $element['#title'],
        ]));
      }

      [$base_path] = explode(';', $widget_url, 2);
      $url = parse_url($base_path);
      if (!UrlHelper::isValid($base_path, TRUE)) {
        $form_state->setError($subform['widgets'][$i]['widget_url'], $this->t('Please enter a full URL, containing protocol, host and path, e.g. <em>http://public.tableau.com/my-widget</em>.'));
      }
      elseif (!$values['widgets'][$i]['widget_url_skip_validation']) {
        $is_valid = FALSE;
        foreach (array_keys($allowed_hosts) as $domain) {
          if (strpos($url['host'], $domain) !== FALSE) {
            $is_valid = TRUE;
            break;
          }
        }
        if (!$is_valid) {
          $form_state->setError($subform['widgets'][$i]['widget_url'], $this->t('We only support @allowed_hosts as widget providers at the moment. The URL does not seem to belong to one of them.', [
            '@allowed_hosts' => $this->t('@first_items and @last_item', [
              '@first_items' => implode(', ', array_slice(array_values($allowed_hosts), 0, -1)),
              '@last_item' => end($allowed_hosts),
            ]),
          ]));
        }
      }
    }
  }

  /**
   * Process a quickcharts widget from generic_external_widgets.inc.
   *
   * @param array $widget_conf
   *   The widget configuration, see generic_external_widgets.inc or
   *   plan_external_widget.inc.
   *
   * @return array
   *   The processed widget configuration.
   */
  private function processWidget(array $widget_conf) {

    // Special handling of HDX quick charts.
    $widget_url_parts = explode(';', $widget_conf['widget_url']);
    $base_url = array_shift($widget_url_parts);
    $params = [];
    foreach ($widget_url_parts as $url_part) {
      [$key, $value] = explode('=', $url_part, 2);
      $params[$key] = $value;
    }

    $this->processParams($params, !array_key_exists('process_widget_url', $widget_conf) || $widget_conf['process_widget_url']);

    $widget_url = $base_url;
    foreach ($params as $key => $value) {
      $widget_url .= ';' . $key . '=' . $value;
    }

    $widget_conf['widget_url'] = trim($widget_url);

    return $widget_conf;
  }

  /**
   * Process quickchart params.
   *
   * @param array $params
   *   The parameters for the quickchart iframe url.
   * @param bool $process_url
   *   Whether the url should be processed.
   */
  private function processParams(array &$params, $process_url = TRUE) {
    $base_url = $this->requestStack->getMasterRequest()->getBaseUrl();

    // First we deactivate some controls and set the widget into single mode,
    // which removes the embed title.
    $params['chartSettings'] = 'false';
    $params['chartShare'] = 'false';
    $params['allowBiteSwitch'] = 'false';
    $params['singleWidgetMode'] = 'true';

    // Then we add an additional CSS reference, which will be included by HDX,
    // so that we have some control over the styling.
    $css_url = Url::fromUserInput('/' . drupal_get_path('module', 'ghi_blocks') . '/css/quickcharts.css', [
      'absolute' => TRUE,
    ])->toString();
    if (strpos($css_url, 'docksal') || strpos($css_url, 'ahconu.org')) {
      // On local and dev domains, fallback to the CSS file in current
      // Hum Insight production.
      $css_url = 'https://hum-insight.info/sites/all/modules/custom/hpc_content_panes/css/quickcharts.css';
    }
    $is_dev_environment = strpos($base_url, 'hum-insight-info.ahconu.org') || strpos($base_url, 'hpcviewer.docksal');
    if ($is_dev_environment) {
      $css_url = url('https://hum-insight.info/sites/all/modules/custom/hpc_content_panes/css/quickcharts.css');
    }
    $params['externalCss'] = $css_url ? str_replace('/', '%2F', $css_url) : NULL;

    if (!$process_url) {
      return;
    }

    // Now the complicated stuff. We extract a couple of params and process
    // them seperately. First we look at the url to the HDX data proxy, which
    // takes in a data source url and processes it, based on a "recipe" and a
    // couple of arguments, to be usable by their chart app.
    // We are only interested in the query arguments passed to that proxy url.
    // Those arguments contain the data url (data source) and additional
    // arguments that instruct the proxy how to process the data, e.g. filtering
    // or sorting.
    $proxy_url = urldecode($params['url']);
    $proxy_url_parts = parse_url($proxy_url);
    $query_args = explode('&', $proxy_url_parts['query']);

    // The data url is the url to the data source used by HDX, most likely a
    // google spreadsheet.
    $data_url = '';

    $query_args = array_filter($query_args, function ($query_arg) use (&$data_url) {
      // We use this to extract the data url and remove an obsolete argument.
      if (strpos($query_arg, 'url=') === 0) {
        $data_url = urldecode(str_replace('url=', '', $query_arg));
        return NULL;
      }
      if ($query_arg == 'force=on') {
        return NULL;
      }
      return $query_arg;
    });

    // Make sure we only apply the following to the google spreadsheet we know.
    if (!strpos($data_url, 'docs.google.com') || !strpos($data_url, self::GOOGLE_SHEET)) {
      return;
    }

    // Clean the arguments.
    $filter_numbers_to_remove = [];
    foreach ($query_args as $key => $query_arg) {
      // Remove filters for "year=X".
      if (strpos($query_arg, '%23date%2Byear')) {
        [$filter_key] = explode('=', $query_args[$key - 1]);
        $filter_number = str_replace('filter', '', $filter_key);
        $filter_numbers_to_remove[] = $filter_number;
        unset($query_args[$key]);
        continue;
      }
    }
    foreach ($filter_numbers_to_remove as $filter_number) {
      foreach ($query_args as $key => $query_arg) {
        if (strpos($query_arg, $filter_number . '=')) {
          unset($query_args[$key]);
          continue;
        }
      }
    }

    // There have been some configuration errors for the quickcharts elements,
    // so we try to correct them if possible. First we check if the current
    // page can give us a location code, in which case we use that to basically
    // rebuild the filter processing.
    $location_code = $this->getCountryCode();
    if ($location_code) {
      // If we have a location code, just overwrite the query args completely.
      $query_args = [
        'filter01=select',
        'select-query01-01=%23country%2Bcode=' . $location_code,
      ];
    }

    // Make sure we only apply this if we are sufficiently sure that we know
    // what we have.
    $last_filter_index = $this->getLastHdxQuickchartsFilterIndex($query_args);
    $page_year = $this->hasContext('year') ? $this->getContextValue('year') : NULL;
    if ($last_filter_index != 1 || !$page_year) {
      return;
    }

    $query_args = array_merge($query_args, [
      // Filter for year, minimum should not be older than 10 years before
      // the current page year.
      'filter' . sprintf('%1$02d', $last_filter_index + 1) . '=select',
      'select-query' . sprintf('%1$02d', $last_filter_index + 1) . '-01=%23date%2Byear+%3E+%7B%7B+' . $page_year . '+-+' . self::MAX_YEAR_RANGE . '+%7D%7D',
      // Filter for year, maximum should be the current page year.
      'filter' . sprintf('%1$02d', $last_filter_index + 2) . '=select',
      'select-query' . sprintf('%1$02d', $last_filter_index + 2) . '-01=%23date%2Byear+%3C=+' . $page_year,
      // Sorting by year.
      'filter' . sprintf('%1$02d', $last_filter_index + 2) . '=sort',
      'sort-tags' . sprintf('%1$02d', $last_filter_index + 2) . '=%23date%2Byear',
    ]);

    // Set a title programatically. The titles are part of the embedded config.
    $embed_config = json_decode(urldecode($params['embeddedConfig']));
    if ($embed_config && property_exists($embed_config, 'bites') && !empty($embed_config->bites)) {
      $bite = &$embed_config->bites[0];
      $data_title = $bite->uiProperties->dataTitle ?? $bite->computedProperties->dataTitle;
      switch ($data_title) {
        case 'Response plan funding':
          // This one is shared between the global and plan-specific widgets.
          $bite->uiProperties->title = $location_code == 'G' ? $this->t('Requirements and funding @start_year - @year (USD)', [
            '@start_year' => max($page_year - self::MAX_YEAR_RANGE + 1, self::MIN_YEAR_HISTORICAL_HPC_DATA),
            '@year' => $page_year,
          ]) : $this->t('Requirements and funding until @year (USD)', [
            '@year' => $page_year,
          ]);
          break;

        case 'People targeted':
          $bite->uiProperties->title = $this->t('People in need and people targeted until @year', [
            '@year' => $page_year,
          ]);
          break;

        case 'People targeted for assistance':
          $bite->uiProperties->title = $this->t('People targeted for assistance @start_year - @year', [
            '@start_year' => max($page_year - self::MAX_YEAR_RANGE + 1, self::MIN_YEAR_HISTORICAL_HPC_DATA),
            '@year' => $page_year,
          ]);
          break;
      }

    }

    $params['embeddedConfig'] = str_replace('+', '%20', urlencode(json_encode($embed_config)));
    $new_url = $proxy_url_parts['scheme'] . '://' . $proxy_url_parts['host'] . $proxy_url_parts['path'] . '?' . implode('&', array_merge($query_args, ['url=' . urlencode($data_url)]));
    $params['url'] = urlencode($new_url);
  }

  /**
   * Get location code for the current page if any.
   *
   * This tries to get a location code from the current plan page.
   *
   * @return string|null
   *   Country code or NULL.
   */
  private function getCountryCode() {
    $path = $this->getCurrentUri();
    if (strpos($path, 'overview') === 0) {
      return 'G';
    }
    $plan_object = $this->getCurrentPlanObject();
    if (!$plan_object) {
      return NULL;
    }
    $linked_location_count = $plan_object->hasField('field_country') ? $plan_object->get('field_country')->count() : 0;
    if (!$linked_location_count || $linked_location_count > 1) {
      // None or too many locations.
      return NULL;
    }
    $country = $plan_object->get('field_country')->getEntity();
    if (!$country) {
      return NULL;
    }
    return $country->hasField('field_country_code') ? $country->get('field_country_code') : NULL;
  }

  /**
   * Get the last index of HDX quickcharts filter arguments.
   *
   * @param array $query_args
   *   The query arguments to process.
   *
   * @return int
   *   The integer value of the last existing filter.
   */
  private function getLastHdxQuickchartsFilterIndex(array $query_args) {
    if (empty($query_args)) {
      return 0;
    }
    $last_filter = max(array_map(function ($query_arg) {
      if (strpos($query_arg, 'filter') !== 0) {
        return 0;
      }
      [$key] = explode('=', $query_arg);
      return (int) str_replace('filter', '', $key);
    }, $query_args));
    return (int) $last_filter;
  }

}
