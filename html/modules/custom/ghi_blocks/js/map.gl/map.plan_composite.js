(function (Drupal) {

  'use strict';

  /**
   * Build map init options for plan composite maps.
   *
   * @param {Object} mapConfig
   *   The map settings.
   *
   * @return {Object}
   *   Map init options.
   */
  function buildOptions(mapConfig) {
    var options = {
      style: 'composite',
      admin_level_selector: true,
      admin_level_label: true,
      admin_level_position: 'top-left',
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
    if (typeof mapConfig.outline_country != 'undefined') {
      options.outline_country = mapConfig.outline_country;
    }
    if (typeof mapConfig.disclaimer != 'undefined') {
      options.disclaimer = mapConfig.disclaimer ?? null;
    }
    if (typeof mapConfig.modal_data_url != 'undefined') {
      options.modal_data_url = mapConfig.modal_data_url;
    }
    return options;
  }

  Drupal.behaviors.planCompositeMap = {
    attach: function(context, settings) {
      if (!window.ghi || !window.ghi.mapLazy) {
        return;
      }
      window.ghi.mapLazy.attach('plan_composite_map', context, settings, {
        wrapperSelector: '.plan-attachment-map-wrapper',
        triggerSelector: '.map-tab',
        buildOptions: buildOptions
      });
    }
  };

})(Drupal);
