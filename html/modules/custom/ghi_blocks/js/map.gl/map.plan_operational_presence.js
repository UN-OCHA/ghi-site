(function (Drupal) {

  'use strict';

  /**
   * Build map init options for operational presence maps.
   *
   * @param {Object} mapConfig
   *   The map settings.
   *
   * @return {Object}
   *   Map init options.
   */
  function buildOptions(mapConfig) {
    var options = {
      style: 'choropleth',
      admin_level_selector: true,
      search_enabled: true,
      search_options: {
        placeholder: Drupal.t('Filter by location name'),
        empty_message: Drupal.t('Be sure to enter a location name within the current response plan.')
      }
    };
    if (typeof mapConfig.pcodes_enabled != 'undefined') {
      options.pcodes_enabled = mapConfig.pcodes_enabled;
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
    return options;
  }

  // Attach behaviors.
  Drupal.behaviors.planOperationalPresenceMap = {
    attach: function(context, settings) {
      if (!window.ghi || !window.ghi.mapLazy) {
        return;
      }
      window.ghi.mapLazy.attach('plan_operational_presence_map', context, settings, {
        wrapperSelector: '.plan-operational-presence-map-wrapper',
        buildOptions: buildOptions
      });
    }
  };

})(Drupal);
