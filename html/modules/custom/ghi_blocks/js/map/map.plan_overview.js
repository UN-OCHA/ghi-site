(function ($) {

  // Attach behaviors.
  Drupal.behaviors.hpc_plan_overview_map = {
    attach: function(context, settings) {
      if (!settings.plan_overview_map || !Object.keys(settings.plan_overview_map).length) {
        return;
      }
      let map_keys = Object.keys(settings.plan_overview_map);
      for (i of map_keys) {
        var map_config = settings.plan_overview_map[i];
        if (!map_config.id || typeof map_config.json == 'undefined') {
          continue;
        }
        if (!context || !$('#' + map_config.id, context).length) {
          continue;
        }
        var options = {
          base_radius: 7,
          popup_style: 'sidebar',
          map_tiles_url: map_config.map_tiles_url,
          legend: typeof map_config.legend != 'undefined' ? map_config.legend : false,
        };
        if (typeof map_config.map_style != 'undefined') {
          options.map_style = map_config.map_style;
          options.map_style_config = map_config.map_style_config;
        }
        if (typeof map_config.search_enabled != 'undefined' && map_config.search_enabled) {
          options.search_enabled = true;
          options.search_options = {
            placeholder: Drupal.t('Filter by country name'),
            empty_message: Drupal.t('Be sure to enter a valid country name.'),
          };
        }
        if (typeof map_config.map_disclaimer != 'undefined') {
          options.disclaimer = {
            text: map_config.map_disclaimer,
            position: 'bottomright',
          };
        }
        Drupal.hpc_map.init(map_config.id, map_config.json, options);
      }
    }
  }

})(jQuery, Drupal);
