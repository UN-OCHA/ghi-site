/**
 * @file
 * JavaScript behaviors for map dataset configuration.
 */
(function (Drupal, once) {
  Drupal.behaviors.mapDatasetDisabledOptions = {
    attach(context) {
      once(
        'map-dataset-disabled-options',
        'select[data-map-dataset-disabled-options]',
        context,
      ).forEach((select) => {
        const disabledOptions = JSON.parse(
          select.dataset.mapDatasetDisabledOptions,
        );
        Array.from(select.options).forEach((option) => {
          option.disabled = disabledOptions.includes(option.value);
        });
      });
    },
  };
})(Drupal, once);
