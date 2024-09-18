<?php

namespace Drupal\ghi_blocks\Plugin\Block\Generic;

use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Markup;
use Drupal\ghi_blocks\Plugin\Block\GHIBlockBase;

/**
 * Provides a 'Datawrapper' block.
 *
 * @Block(
 *  id = "generic_datawrapper",
 *  admin_label = @Translation("Datawrapper"),
 *  category = @Translation("Generic elements"),
 *  title = FALSE
 * )
 */
class Datawrapper extends GHIBlockBase {

  /**
   * {@inheritdoc}
   */
  public function buildContent() {

    // Get the config.
    $conf = $this->getBlockConfig();

    if (empty($conf['embed'])) {
      return;
    }

    $height = $this->extractEmbedAttribute($conf['embed'], 'height');
    return [
      '#type' => 'html_tag',
      '#tag' => 'div',
      '#attributes' => [
        'class' => [
          'iframe-wrapper',
          'external-widget-iframe-wrapper',
        ],
        'style' => $height ? 'height: ' . $height : NULL,
      ],
      [
        '#markup' => Markup::create($conf['embed']),
      ],
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
      'embed' => NULL,
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
      'datawrapper.dwcdn.net' => $this->t('Datawrapper'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getConfigForm(array $form, FormStateInterface $form_state) {
    $form['embed'] = [
      '#type' => 'textarea',
      '#title' => $this->t('iframe URL'),
      '#description' => $this->t('Paste the complete <em>Embed code</em> from from the <em>Publish & Embed</em> step in the datawrapper wizard.'),
      '#rows' => 10,
      '#default_value' => $this->getDefaultFormValueFromFormState($form_state, 'embed') ?? NULL,
      '#required' => TRUE,
    ];

    return $form;
  }

  /**
   * Validate handler for portlet configuration form.
   */
  public function blockValidate($form, FormStateInterface $form_state) {
    if ($this->isPreviewSubmit($form_state)) {
      return;
    }

    $allowed_hosts = $this->getAllowedHosts();
    $values = $form_state->getValue($form_state->get('current_subform'));
    $subform = $form['container'];

    $embed = $values['embed'];
    $src = $this->extractEmbedAttribute($embed, 'src');
    $url = parse_url($src);
    if (!UrlHelper::isValid($src, TRUE)) {
      $form_state->setError($subform['embed'], $this->t('Please enter a full URL for the embed source, containing protocol, host and path, e.g. <em>https://datawrapper.dwcdn.net</em>.'));
    }

    $is_valid = FALSE;
    foreach (array_keys($allowed_hosts) as $domain) {
      if (strpos($url['host'], $domain) !== FALSE) {
        $is_valid = TRUE;
        break;
      }
    }

    if (!$is_valid) {
      $form_state->setError($subform['embed'], $this->t('We only support @allowed_hosts as widget provider at the moment. The URL does not seem to match that.', [
        '@allowed_hosts' => count($allowed_hosts) > 1 ? $this->t('@first_items and @last_item', [
          '@first_items' => implode(', ', array_slice(array_values($allowed_hosts), 0, -1)),
          '@last_item' => end($allowed_hosts),
        ]) : reset($allowed_hosts),
      ]));
    }
  }

  /**
   * Extract attributes from the embed code.
   *
   * @param string $embed
   *   The embed code, should actually be iframe markup.
   * @param string $attribute
   *   The attribute name to extract.
   *
   * @return mixed|null
   *   The attributes value if found.
   */
  private function extractEmbedAttribute($embed, $attribute) {
    $dom = Html::load($embed);

    $iframe = $dom->getElementsByTagName('iframe')->item(0);
    if (!isset($iframe)) {
      return NULL;
    }

    // Extract id.
    if (!$iframe->hasAttribute($attribute)) {
      return NULL;
    }
    return $iframe->getAttribute($attribute);
  }

}
