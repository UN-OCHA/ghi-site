<?php

namespace Drupal\ghi_blocks\Plugin\Block\Generic;

use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\ghi_blocks\Plugin\Block\GHIBlockBase;
use Drupal\ghi_element_sync\SyncableBlockInterface;
use Drupal\ghi_form_elements\Helpers\FormElementHelper;
use Drupal\node\NodeInterface;

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
 *    "node" = @ContextDefinition("entity:node", label = @Translation("Node"), required = FALSE)
 *  }
 * )
 */
class ExternalWidget extends GHIBlockBase implements SyncableBlockInterface {

  const MAX_ITEMS = 2;

  /**
   * {@inheritdoc}
   */
  public static function mapConfig($config, NodeInterface $node, $element_type, $dry_run = FALSE) {
    $mapped_config = [];
    if ($element_type == 'generic_external_widgets') {
      $mapped_config = [
        'select_number' => $config->select_number,
        'widgets' => array_map(function ($item) {
          return (array) $item;
        }, (array) $config->widgets),
      ];
    }
    else {
      // This was a single widget element.
      $mapped_config['select_number'] = 1;
      $mapped_config['widgets'] = [
        [
          'widget_url' => $config->widget_url,
          'widget_url_skip_validation' => $config->widget_url_skip_validation,
          'widget_height' => $config->height,
        ],
      ];
    }
    return [
      'label' => '',
      'label_display' => TRUE,
      'hpc' => $mapped_config,
    ];
  }

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

    $iframes = [];
    foreach ($widgets as $widget) {
      $widget_url = $widget['widget_url'];
      $url = parse_url($widget_url);
      if (strpos($url['host'], 'humdata.org') !== FALSE) {
        // Special handling of HDX quick charts.
        $widget_url_parts = explode(';', $widget_url);
        $base_url = array_shift($widget_url_parts);
        $params = [];
        foreach ($widget_url_parts as $url_part) {
          list($key, $value) = explode('=', $url_part, 2);
          $params[$key] = $value;
        }

        // We add an additional CSS reference.
        $css_url = Url::fromUserInput('/' . drupal_get_path('module', 'ghi_blocks') . '/css/quickcharts.css', [
          'absolute' => TRUE,
        ])->toString();
        if (strpos($css_url, 'docksal') || strpos($css_url, 'ahconu.org')) {
          // On local and dev domains, fallback to the CSS file in current
          // Hum Insight production.
          $css_url = 'https://hum-insight.info/sites/all/modules/custom/hpc_content_panes/css/quickcharts.css';
        }
        $params['externalCss'] = str_replace('/', '%2F', $css_url);

        // And we deactivate some controls.
        $params['chartSettings'] = 'false';
        $params['chartShare'] = 'false';
        $params['allowBiteSwitch'] = 'false';

        $widget_url = $base_url;
        foreach ($params as $key => $value) {
          $widget_url .= ';' . $key . '=' . $value;
        }
      }

      if (empty($widget_url)) {
        continue;
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
            'src' => $widget_url,
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
      '#type' => 'html_tag',
      '#tag' => 'div',
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
      'powerbi.com' => $this->t('PowerBI'),
      'tableau.com' => $this->t('Tableau'),
      'humdata.org' => $this->t('HDX Quick Charts'),
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

    $select_name_selector = FormElementHelper::getStateSelector($form, ['select_number']);

    $default_widgets = $this->getDefaultFormValueFromFormState($form_state, 'widgets');
    for ($i = 1; $i <= self::MAX_ITEMS; $i++) {
      $default = $default_widgets[$i];
      $state_conditions = [];
      for ($j = $i; $j <= self::MAX_ITEMS; $j++) {
        $state_conditions[] = [
          ':input[name="' . $select_name_selector . '"]' => [
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
        '#default_value' => $default['widget_url'] ?? NULL,
        '#maxlength' => 8096,
        '#states' => [
          'required' => $state_conditions,
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
        '#description' => $this->t('Enter the height auf the iframe including the unit if necessary. E.g.: <em>400px</em>, <em>50%</em> or <em>auto</em>'),
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
    $values = $form_state->getValue($form_state->get('current_subform'));
    $subform = $form['container'];

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

      list($base_path) = explode(';', $widget_url, 2);
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

}
