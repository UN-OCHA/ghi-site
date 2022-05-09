(function ($) {

  // Attach behaviors.
  Drupal.behaviors.hpc_plan_map = {
    attach: function(context, settings) {
      if (!settings.plan_attachment_map || !Object.keys(settings.plan_attachment_map).length) {
        return;
      }
      let map_keys = Object.keys(settings.plan_attachment_map);
      for (i of map_keys) {
        var map_config = settings.plan_attachment_map[i];
        if (!map_config.id || typeof map_config.json == 'undefined') {
          continue;
        }
        if (!context || !$('#' + map_config.id, context).length) {
          continue;
        }
        var options = {
          admin_level_selector: true,
          mapbox_url: 'https://api.mapbox.com/styles/v1/berliner/cjtekox5u4wng1foawbxty5iq/tiles/256/{z}/{x}/{y}?access_token=pk.eyJ1IjoiYmVybGluZXIiLCJhIjoiY2p0ZWtheXZ4MWl3ejQ0b2FxenV3NGNlbSJ9.BHeHnEiQ_uY33hRMmW-HJA',
          popup_style: 'sidebar',
          search_enabled: true,
          search_options: {
            placeholder: Drupal.t('Filter by location name'),
            empty_message: Drupal.t('Be sure to enter a location name within the current response plan.'),
          }
        };
        if (typeof map_config.pcodes_enabled != 'undefined') {
          options.pcodes_enabled = map_config.pcodes_enabled;
          options.search_options.placeholder = Drupal.t('Filter by location name or pcode');
          options.search_options.empty_message = Drupal.t('Be sure to enter a location name or pcode within the current response plan.');
        }
        if (typeof map_config.map_style != 'undefined') {
          options.map_style = map_config.map_style;
          options.map_style_config = map_config.map_style_config;
        }
        if (typeof map_config.disclaimer != 'undefined') {
          options.disclaimer = {
            text: map_config.disclaimer,
            position: 'bottomright',
          };
        }
        Drupal.hpc_map.init(map_config.id, map_config.json, options);
      }
    }
  }

})(jQuery, Drupal);
