(function ($) {

  // Attach behaviors.
  Drupal.behaviors.planOverviewMap = {
    attach: function(context, settings) {
      if (!ghi || !ghi.mapbox || !ghi.map) {
        return;
      }

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
          global_config: settings.map_config,
          legend: typeof map_config.legend != 'undefined' ? map_config.legend : false,
          interactive_legend: true,
          zoom: 1.5,
          zoom_min: 1.5,
          zoom_max: 5,
        };
        if (typeof map_config.style != 'undefined') {
          options.style = map_config.style;
          options.style_config = typeof map_config.style_config != 'undefined' ? map_config.style_config : {};
        }
        if (typeof map_config.search_enabled != 'undefined' && map_config.search_enabled) {
          options.search_enabled = true;
          options.search_options = {
            placeholder: Drupal.t('Search for country or plan'),
            search_button_title: Drupal.t('Search for country or plan'),
            empty_message: Drupal.t('Try with a different search term.'),
          };
        }
        if (typeof map_config.map_disclaimer != 'undefined') {
          options.disclaimer = map_config.map_disclaimer ?? null;
        }
        ghi.map.init(map_config.id, map_config.json, options);
      }
    }
  }

})(jQuery, Drupal);
