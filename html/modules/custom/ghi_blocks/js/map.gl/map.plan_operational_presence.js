(function ($) {

  // Attach behaviors.
  Drupal.behaviors.planOperationalPresenceMap = {
    attach: function(context, settings) {
      if (!ghi || !ghi.mapbox || !ghi.map) {
        return;
      }
      if (!settings.plan_operational_presence_map || !Object.keys(settings.plan_operational_presence_map).length) {
        return;
      }
      let map_keys = Object.keys(settings.plan_operational_presence_map);
      for (i of map_keys) {
        var map_config = settings.plan_operational_presence_map[i];
        if (!map_config.id || typeof map_config.json == 'undefined') {
          continue;
        }
        if (!context || !$('#' + map_config.id, context).length) {
          continue;
        }
        var options = {
          style: 'choropleth',
          admin_level_selector: true,
          search_enabled: true,
          search_options: {
            placeholder: Drupal.t('Filter by location name'),
            empty_message: Drupal.t('Be sure to enter a location name within the current response plan.'),
          },
        };
        if (typeof map_config.pcodes_enabled != 'undefined') {
          options.pcodes_enabled = map_config.pcodes_enabled;
          options.search_options.placeholder = Drupal.t('Filter by location name or pcode');
          options.search_options.search_button_title = Drupal.t('Filter by location name or pcode');
          options.search_options.empty_message = Drupal.t('Be sure to enter a location name or pcode within the current response plan.');
        }
        if (typeof map_config.disclaimer != 'undefined') {
          options.disclaimer = map_config.disclaimer ?? null;
        }
        ghi.map.init(map_config.id, map_config.json, options);
      }
    }
  }

})(jQuery, Drupal);
