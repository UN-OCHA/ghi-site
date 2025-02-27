(function ($) {

  'use strict';

  if (!window.ghi) {
    window.ghi = {};
  }

  /**
   * Define the main map object.
   */
  window.ghi.map = {

    states: {},
    config: {
      map: {
        padding: 50,
      },
      defaultOptions: {
        admin_level_selector : false,
        style: 'circle',
        search_enabled: false,
        search_options: {
          empty_message: Drupal.t('Be sure to enter a location name within the current response plan.'),
          placeholder: Drupal.t('Filter by location name'),
        },
        disclaimer: Drupal.t('The boundaries and names shown and the designations used on this map do not imply official endorsement or acceptance by the United Nations.'),
        pcodes_enabled: true,
        legend: false,
        interactive_legend: false,
        zoom: 4,
        zoom_min: 4,
        zoom_max: 10,
      }
    },

    /**
     * Get the map state for the given id.
     *
     * @param {String} map_id
     *   The ID of the map container.
     * @param {Map} map
     *   A mapbox map object.
     * @param {Object} data
     *   The data for the map.
     * @param {Object} options
     *   The options for the map.
     *
     * @returns {ghi.mapState}
     *   A map state object.
     */
    getMapState: function (map_id, map, data, options) {
      if (typeof data != 'undefined') {
        this.states[map_id] = new ghi.mapState(map_id, map, this, data, options);
      }
      return this.states.hasOwnProperty(map_id) ? this.states[map_id] : null;
    },

    /**
     * Get the GeoJSON data for the given location.
     *
     * @param {Object} location
     *   The location object.
     *
     * @returns {Object}
     *   The feature data.
     */
    getGeoJSON: function(location, featureCallback = null) {
      if (typeof this.storage == 'undefined') {
        this.storage = []
      }
      if (location.filepath && typeof this.storage[location.filepath] == 'undefined') {
        let feature = null;
        $.ajax({
          dataType: 'json',
          url: location.filepath,
          success: function (data) {
            let type = data.type ?? null;
            feature = type == 'FeatureCollection' ? (data.features[0] ?? null) : data;
            if (!type || !feature) {
              return;
            }
            feature.properties.object_id = location.location_id;
            feature.properties.location_id = location.location_id;
            feature.properties.location_name = location.location_name;
            if (featureCallback) {
              feature = featureCallback(feature, location);
            }
          },
          async: false
        });
        this.storage[location.filepath] = feature;
      }
      return this.storage[location.filepath];
    },

    /**
     * Initialize the map
     *
     * @param {String} map_id
     *   The map id.
     * @param {Object} data
     *   The data for the map.
     * @param {Object} options
     *   The options for the map.
     */
    init: function (map_id, data, options) {
      let element = document.getElementById(map_id);
      $(element).addClass('mapbox-map-wrapper');

      options = Object.assign(this.config.defaultOptions, options);
      let mapbox = new ghi.mapbox();
      let map = mapbox.addMap(element, options);
      if (!map) {
        return;
      }

      // Get the map state.
      var state = this.getMapState(map_id, map, data, options);
      state.setCurrentIndex();

      // Get the map style.
      let style = state.getMapStyle(options);
      if (!style) {
        element.removeAttribute('data-map-enabled');
        return;
      }

      // Set the bounds.
      var bounds = new mapboxgl.LngLatBounds();
      state.getLocations().forEach(function(d) {
        // Note that mapbox expects lnglat when we use latlng internally. Also
        // see https://github.com/Turfjs/turf/issues/182 for a discussion in an
        // unrelated project that get's some details about latlng vs lnglat.
        bounds.extend([d.latLng[1], d.latLng[0]]);
      });
      if (state.getLocations().length) {
        map.fitBounds(bounds, { padding: 50 });
      }
      if (state.getLocations().length == 1) {
        map.setZoom(6);
      }

      // Setup the state.
      state.setup(options);
    },

  }

})(jQuery);
