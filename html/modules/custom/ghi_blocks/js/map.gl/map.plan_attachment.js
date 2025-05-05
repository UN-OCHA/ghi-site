(function ($) {

  // Attach behaviors.
  Drupal.behaviors.planAttachmentMap = {
    attach: function(context, settings) {
      if (!ghi || !ghi.mapbox || !ghi.map) {
        return;
      }
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
          search_enabled: true,
          search_options: {
            placeholder: Drupal.t('Filter by location name'),
            empty_message: Drupal.t('Be sure to enter a location name within the current response plan.'),
          },
          pcodes_enabled: map_config.pcodes_enabled ?? false,
        };
        if (options.pcodes_enabled) {
          options.search_options.placeholder = Drupal.t('Filter by location name or pcode');
          options.search_options.search_button_title = Drupal.t('Filter by location name or pcode');
          options.search_options.empty_message = Drupal.t('Be sure to enter a location name or pcode within the current response plan.');
        }
        if (typeof map_config.style != 'undefined') {
          options.style = map_config.style;
          options.style_config = map_config.style_config;
        }
        if (typeof map_config.outlineCountry != 'undefined') {
          options.outlineCountry = map_config.outlineCountry;
        }
        if (typeof map_config.disclaimer != 'undefined') {
          options.disclaimer = map_config.disclaimer ?? null;
        }
        ghi.map.init(map_config.id, map_config.json, options);
      }
    }
  }

})(jQuery, Drupal);
