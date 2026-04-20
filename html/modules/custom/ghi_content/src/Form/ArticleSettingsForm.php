<?php

namespace Drupal\ghi_content\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Article settings form.
 */
class ArticleSettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'ghi_content_article_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'ghi_content.article_settings',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildForm($form, $form_state);

    $map_config = $this->config('ghi_content.article_settings');
    $form['subarticle_local_render'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Render sub articles locally'),
      '#description' => $this->t('Render sub articles locally in this site instead of using the version from the content backend. This allows to add additional HA specific page elements to an article page and have these displayed also when the article is displayed as a sub article inside another article page.'),
      '#default_value' => $map_config->get('subarticle_local_render'),
    ];
    $form['subarticle_local_render_preview_components'] = [
      '#type' => 'number',
      '#title' => $this->t('Sub article preview components'),
      '#description' => $this->t('The number of local layout builder blocks to render during the initial page request before deferring the remaining sub article content.'),
      '#default_value' => $map_config->get('subarticle_local_render_preview_components') ?? 3,
      '#min' => 0,
      '#states' => [
        'visible' => [
          ':input[name="subarticle_local_render"]' => ['checked' => TRUE],
        ],
      ],
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $map_config = $this->config('ghi_content.article_settings');
    $map_config->set('subarticle_local_render', $form_state->getValue('subarticle_local_render'));
    $map_config->set('subarticle_local_render_preview_components', $form_state->getValue('subarticle_local_render_preview_components'));
    $map_config->save();
    return parent::submitForm($form, $form_state);
  }

}
