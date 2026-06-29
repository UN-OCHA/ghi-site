(function (Drupal, drupalSettings) {

  'use strict';

  /**
   * Build the existing map init options.
   *
   * @param {Object} mapConfig
   *   The map settings.
   *
   * @return {Object}
   *   Map init options.
   */
  function buildOptions(mapConfig) {
    var options = {
      base_radius: 7,
      global_config: drupalSettings.map_config,
      legend: typeof mapConfig.legend != 'undefined' ? mapConfig.legend : false,
      interactive_legend: true,
      zoom: 1.5,
      zoom_min: 1.5,
      zoom_max: 5
    };
    if (typeof mapConfig.style != 'undefined') {
      options.style = mapConfig.style;
      options.style_config = typeof mapConfig.style_config != 'undefined' ? mapConfig.style_config : {};
    }
    if (typeof mapConfig.search_enabled != 'undefined' && mapConfig.search_enabled) {
      options.search_enabled = true;
      options.search_options = {
        placeholder: Drupal.t('Search for country or plan'),
        search_button_title: Drupal.t('Search for country or plan'),
        empty_message: Drupal.t('Try with a different search term.')
      };
    }
    if (typeof mapConfig.disclaimer != 'undefined') {
      options.disclaimer = mapConfig.disclaimer ?? null;
    }
    if (typeof mapConfig.modal_data_url != 'undefined') {
      options.modal_data_url = mapConfig.modal_data_url;
    }
    return options;
  }

  // Attach behaviors.
  Drupal.behaviors.planOverviewMap = {
    attach: function(context, settings) {
      if (!window.ghi || !window.ghi.mapLazy) {
        return;
      }
      window.ghi.mapLazy.attach('plan_overview_map', context, settings, {
        wrapperSelector: '.plan-overview-map-wrapper',
        triggerSelector: '.map-tab',
        buildOptions: buildOptions
      });
    }
  };

})(Drupal, drupalSettings);
