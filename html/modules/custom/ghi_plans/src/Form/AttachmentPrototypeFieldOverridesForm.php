<?php

namespace Drupal\ghi_plans\Form;

use Drupal\Core\Cache\CacheTagsInvalidatorInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\KeyValueStore\KeyValueFactoryInterface;
use Drupal\ghi_plans\ApiObjects\Prototypes\AttachmentPrototype;
use Drupal\ghi_plans\Plugin\FabricQuery\AttachmentPrototypeQuery;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Configure attachment prototype field overrides.
 */
class AttachmentPrototypeFieldOverridesForm extends FormBase {

  /**
   * The key-value factory.
   *
   * @var \Drupal\Core\KeyValueStore\KeyValueFactoryInterface
   */
  protected KeyValueFactoryInterface $keyValueFactory;

  /**
   * The cache tags invalidator.
   *
   * @var \Drupal\Core\Cache\CacheTagsInvalidatorInterface
   */
  protected CacheTagsInvalidatorInterface $cacheTagsInvalidator;

  /**
   * Constructs a new attachment prototype field overrides form.
   *
   * @param \Drupal\Core\KeyValueStore\KeyValueFactoryInterface $key_value_factory
   *   The key-value factory.
   * @param \Drupal\Core\Cache\CacheTagsInvalidatorInterface $cache_tags_invalidator
   *   The cache tags invalidator.
   */
  public function __construct(KeyValueFactoryInterface $key_value_factory, CacheTagsInvalidatorInterface $cache_tags_invalidator) {
    $this->keyValueFactory = $key_value_factory;
    $this->cacheTagsInvalidator = $cache_tags_invalidator;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('keyvalue'),
      $container->get('cache_tags.invalidator'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'ghi_plans_attachment_prototype_field_overrides_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $stored_overrides = $this->getStoredOverrides();

    $form['intro'] = [
      '#markup' => '<p>' . $this->t('Configure targeted attachment prototype field additions and replacements for stale Fabric prototype definitions.') . '</p>',
    ];

    $form['additions_section'] = [
      '#type' => 'details',
      '#title' => $this->t('Field additions'),
      '#open' => TRUE,
    ];
    $form['additions_section']['field_additions'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Additions JSON'),
      '#title_display' => 'invisible',
      '#description' => $this->t('Enter a JSON object keyed by attachment prototype id. Each value can be one addition object or a list of addition objects.'),
      '#default_value' => $this->formatJson($stored_overrides['additions'] ?? []),
      '#rows' => 12,
      '#required' => TRUE,
    ];
    $form['additions_section']['example'] = [
      '#type' => 'details',
      '#title' => $this->t('Example'),
      'content' => [
        '#type' => 'html_tag',
        '#tag' => 'pre',
        '#value' => $this->formatJson([
          '1234' => [
            'metric_type' => 'cumulative_reach',
            'field_group' => 'measurement',
            'label' => 'People reached (cumulative)',
          ],
        ]),
      ],
    ];

    $form['replacements_section'] = [
      '#type' => 'details',
      '#title' => $this->t('Field replacements'),
      '#open' => TRUE,
    ];
    $form['replacements_section']['field_replacements'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Replacements JSON'),
      '#title_display' => 'invisible',
      '#description' => $this->t('Enter a JSON object keyed by attachment prototype id. Each prototype value must be an object keyed by original field index.'),
      '#default_value' => $this->formatJson($stored_overrides['replacements'] ?? []),
      '#rows' => 12,
      '#required' => TRUE,
    ];
    $form['replacements_section']['example'] = [
      '#type' => 'details',
      '#title' => $this->t('Example'),
      'content' => [
        '#type' => 'html_tag',
        '#tag' => 'pre',
        '#value' => $this->formatJson([
          '5647' => [
            '6' => [
              'metric_type' => 'cumulative_reach',
              'field_group' => 'measurement',
            ],
          ],
        ]),
      ],
    ];

    $form['actions'] = [
      '#type' => 'actions',
      'submit' => [
        '#type' => 'submit',
        '#value' => $this->t('Save configuration'),
        '#button_type' => 'primary',
      ],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    parent::validateForm($form, $form_state);

    $additions = $this->decodeJsonField('field_additions', $form_state);
    $replacements = $this->decodeJsonField('field_replacements', $form_state);
    if ($additions === FALSE || $replacements === FALSE) {
      return;
    }

    $addition_errors = [];
    $replacement_errors = [];
    $this->validateAdditions($additions, $addition_errors);
    $this->validateReplacements($replacements, $replacement_errors);
    foreach ($addition_errors as $message) {
      $form_state->setErrorByName('field_additions', $message);
    }
    foreach ($replacement_errors as $message) {
      $form_state->setErrorByName('field_replacements', $message);
    }
    if ($addition_errors || $replacement_errors) {
      return;
    }

    $form_state->setValue('field_overrides_decoded', [
      'additions' => $additions,
      'replacements' => $replacements,
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $this->keyValueFactory
      ->get(AttachmentPrototypeQuery::FIELD_OVERRIDES_KEY_VALUE_COLLECTION)
      ->set(AttachmentPrototypeQuery::FIELD_OVERRIDES_KEY, $form_state->getValue('field_overrides_decoded'));

    $this->cacheTagsInvalidator->invalidateTags([AttachmentPrototype::FIELD_OVERRIDES_CACHE_TAG]);
    $this->messenger()->addStatus($this->t('Saved attachment prototype field overrides.'));
  }

  /**
   * Get the stored overrides.
   *
   * @return array
   *   The stored field overrides.
   */
  private function getStoredOverrides(): array {
    return $this->keyValueFactory
      ->get(AttachmentPrototypeQuery::FIELD_OVERRIDES_KEY_VALUE_COLLECTION)
      ->get(AttachmentPrototypeQuery::FIELD_OVERRIDES_KEY, []);
  }

  /**
   * Format data as pretty JSON.
   *
   * @param array $data
   *   The data to format.
   *
   * @return string
   *   The formatted JSON.
   */
  private function formatJson(array $data): string {
    if ($data === []) {
      return '{}';
    }
    return json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '{}';
  }

  /**
   * Decode and validate one JSON input field.
   *
   * @param string $field_name
   *   The form field name.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current form state.
   *
   * @return array|false
   *   The decoded JSON object, or FALSE when invalid.
   */
  private function decodeJsonField(string $field_name, FormStateInterface $form_state): array|false {
    $raw_value = trim((string) $form_state->getValue($field_name));
    try {
      $value = json_decode($raw_value, TRUE, 512, JSON_THROW_ON_ERROR);
    }
    catch (\JsonException $exception) {
      $form_state->setErrorByName($field_name, $this->t('The JSON is invalid: @message', [
        '@message' => $exception->getMessage(),
      ]));
      return FALSE;
    }
    if (!is_array($value)) {
      $form_state->setErrorByName($field_name, $this->t('The value must be a JSON object.'));
      return FALSE;
    }
    return $value;
  }

  /**
   * Validate field additions.
   *
   * @param mixed $additions
   *   The additions section.
   * @param array $errors
   *   The validation errors.
   */
  private function validateAdditions(mixed $additions, array &$errors): void {
    if ($additions === []) {
      return;
    }
    if (!is_array($additions)) {
      $errors[] = $this->t('The additions value must be an object keyed by attachment prototype id.');
      return;
    }
    foreach ($additions as $prototype_id => $prototype_additions) {
      if (!is_numeric($prototype_id)) {
        $errors[] = $this->t('The additions key "@key" must be an attachment prototype id.', ['@key' => $prototype_id]);
        continue;
      }
      if (!is_array($prototype_additions)) {
        $prototype_additions = [$prototype_additions];
      }
      elseif (array_key_exists('metric_type', $prototype_additions)) {
        $prototype_additions = [$prototype_additions];
      }
      elseif (!array_is_list($prototype_additions)) {
        $errors[] = $this->t('The additions value for prototype @prototype_id must be one addition object or a list of addition objects.', [
          '@prototype_id' => $prototype_id,
        ]);
        continue;
      }
      foreach ($prototype_additions as $addition) {
        $this->validateFieldOverride($addition, $errors);
      }
    }
  }

  /**
   * Validate field replacements.
   *
   * @param mixed $replacements
   *   The replacements section.
   * @param array $errors
   *   The validation errors.
   */
  private function validateReplacements(mixed $replacements, array &$errors): void {
    if ($replacements === []) {
      return;
    }
    if (!is_array($replacements)) {
      $errors[] = $this->t('The replacements value must be an object keyed by attachment prototype id.');
      return;
    }
    foreach ($replacements as $prototype_id => $prototype_replacements) {
      if (!is_numeric($prototype_id)) {
        $errors[] = $this->t('The replacements key "@key" must be an attachment prototype id.', ['@key' => $prototype_id]);
        continue;
      }
      if (!is_array($prototype_replacements)) {
        $errors[] = $this->t('The replacements value for prototype @prototype_id must be an object keyed by original field index.', [
          '@prototype_id' => $prototype_id,
        ]);
        continue;
      }
      foreach ($prototype_replacements as $index => $replacement) {
        if (!is_numeric($index)) {
          $errors[] = $this->t('The replacement index "@index" for prototype @prototype_id must be numeric.', [
            '@index' => $index,
            '@prototype_id' => $prototype_id,
          ]);
          continue;
        }
        $this->validateFieldOverride($replacement, $errors);
      }
    }
  }

  /**
   * Validate a single field override.
   *
   * @param mixed $override
   *   The override definition.
   * @param array $errors
   *   The validation errors.
   */
  private function validateFieldOverride(mixed $override, array &$errors): void {
    if (is_string($override)) {
      $metric_type = $override;
      $field_group = NULL;
    }
    elseif (is_array($override)) {
      $metric_type = $override['metric_type'] ?? NULL;
      $field_group = $override['field_group'] ?? NULL;
    }
    else {
      $errors[] = $this->t('Each override must be either a metric type string or an object.');
      return;
    }

    if (!is_string($metric_type) || !preg_match('/^[a-z0-9_]+$/', $metric_type)) {
      $errors[] = $this->t('Each override must define a valid metric_type machine name.');
    }
    if ($field_group !== NULL && (!is_string($field_group) || !AttachmentPrototypeQuery::normalizeFieldGroup($field_group))) {
      $errors[] = $this->t('The field_group value must be one of: @groups.', [
        '@groups' => implode(', ', AttachmentPrototypeQuery::getSupportedFieldGroups()),
      ]);
    }
  }

}
