<?php

namespace Drupal\ghi_geojson\Form;

use Drupal\Core\Access\AccessResultAllowed;
use Drupal\Core\Access\AccessResultForbidden;
use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a form for deleting geojson versions.
 */
class DeleteConfirmForm extends ConfirmFormBase {

  /**
   * GeoJSON service.
   *
   * @var \Drupal\ghi_geojson\GeoJson
   */
  public $geojson;

  /**
   * The iso3 code of the country.
   *
   * @var string
   */
  protected string $iso3;

  /**
   * The version.
   *
   * @var string
   */
  protected string $version;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $instance = parent::create($container);
    $instance->geojson = $container->get('geojson');
    return $instance;
  }

  /**
   * Custom access callback for the delete form.
   *
   * @param string $iso3
   *   The iso3 code of the country.
   * @param string $version
   *   The version to delete.
   *
   * @return void
   *   @todo
   */
  public function access(string $iso3, string $version) {
    if ($version == 'current') {
      return new AccessResultForbidden();
    }
    $versions = $this->geojson->getVersionsForIsoCode($iso3);
    if (!in_array($version, $versions)) {
      return new AccessResultForbidden();
    }
    return new AccessResultAllowed();
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'ghi_geojson_delete_confirm_form';
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return $this->t('Are you sure you want to delete version <em>@version</em> for @iso3', [
      '@iso3' => $this->iso3,
      '@version' => $this->version,
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription() {
    return $this->t('This action cannot be undone.');
  }

  /**
   * {@inheritdoc}
   */
  public function getConfirmText() {
    return $this->t('Delete version');
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelText() {
    return $this->t('Cancel');
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl() {
    return Url::fromRoute('ghi_geojson.geojson_sources');
  }

  /**
   * {@inheritdoc}
   */
  public function getFormName() {
    return $this->getFormId();
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?string $iso3 = NULL, ?string $version = NULL) {
    $this->iso3 = $iso3;
    $this->version = $version;
    $form = parent::buildForm($form, $form_state);
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    if ($this->geojson->deleteVersion($this->iso3, $this->version)) {
      $this->messenger()->addStatus($this->t('The GeoJSON version <em>@version</em> for <em>@iso3</em> has been deleted.', [
        '@iso3' => $this->iso3,
        '@version' => $this->version,
      ]));
    }
    $form_state->setRedirectUrl($this->getCancelUrl());
  }

}
