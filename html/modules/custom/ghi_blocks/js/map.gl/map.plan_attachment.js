(function (Drupal) {

  'use strict';

  /**
   * Build map init options for plan attachment maps.
   *
   * @param {Object} mapConfig
   *   The map settings.
   *
   * @return {Object}
   *   Map init options.
   */
  function buildOptions(mapConfig) {
    var options = {
      admin_level_selector: true,
      search_enabled: true,
      search_options: {
        placeholder: Drupal.t('Filter by location name'),
        empty_message: Drupal.t('Be sure to enter a location name within the current response plan.')
      },
      pcodes_enabled: mapConfig.pcodes_enabled ?? false,
      label_min_zoom: mapConfig.label_min_zoom ?? 0
    };
    if (options.pcodes_enabled) {
      options.search_options.placeholder = Drupal.t('Filter by location name or pcode');
      options.search_options.search_button_title = Drupal.t('Filter by location name or pcode');
      options.search_options.empty_message = Drupal.t('Be sure to enter a location name or pcode within the current response plan.');
    }
    if (typeof mapConfig.style != 'undefined') {
      options.style = mapConfig.style;
      options.style_config = mapConfig.style_config;
    }
    if (typeof mapConfig.outline_country != 'undefined') {
      options.outline_country = mapConfig.outline_country;
    }
    if (typeof mapConfig.disclaimer != 'undefined') {
      options.disclaimer = mapConfig.disclaimer ?? null;
    }
    if (typeof mapConfig.modal_data_url != 'undefined') {
      options.modal_data_url = mapConfig.modal_data_url;
    }
    if (typeof mapConfig.slice_data_url != 'undefined') {
      // slice_data_url hydrates a tab/variant's map data. modal_data_url above
      // hydrates a single sidebar payload for a location inside that slice.
      options.slice_data_url = mapConfig.slice_data_url;
    }
    return options;
  }

  // Attach behaviors.
  Drupal.behaviors.planAttachmentMap = {
    attach: function(context, settings) {
      if (!window.ghi || !window.ghi.mapLazy) {
        return;
      }
      window.ghi.mapLazy.attach('plan_attachment_map', context, settings, {
        wrapperSelector: '.plan-attachment-map-wrapper',
        triggerSelector: '.map-tab',
        buildOptions: buildOptions
      });
    },
    detach: function(context, settings, trigger) {
      if (!window.ghi || !window.ghi.mapLazy) {
        return;
      }
      window.ghi.mapLazy.detach('plan_attachment_map', context, settings, trigger);
    }
  };

})(Drupal);
