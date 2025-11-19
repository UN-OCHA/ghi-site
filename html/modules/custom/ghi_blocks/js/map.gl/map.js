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
    storage: {},
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
     * @param {Callable} featureCallback
     *   An optional callback for the retrieved features.
     * @param {Boolean} async
     *   Whether to retrieve the feature data blocking or non-blocking.
     *
     * @returns {Object}
     *   The feature data.
     */
    getGeoJSON: function(location, featureCallback = null, async = true) {
      let self = this;
      if (!location.filepath) {
        return null;
      }
      if (typeof this.storage[location.filepath] == 'undefined') {
        this.storage[location.filepath] = null;
        $.ajax({
          dataType: 'json',
          url: location.filepath,
          success: function (data) {
            let type = data.type ?? null;
            let feature = data;
            if (type == 'FeatureCollection') {
              // Merge all feature geometries into a single object, because
              // this is what we need.
              feature = {
                'type': 'Feature',
                'properties': {},
                'geometry': {
                  'type': 'GeometryCollection',
                  'geometries': data.features.map((item) => item.geometry),
                }
              };
            }
            if (!type || !feature) {
              return;
            }
            feature.id = Number(location.location_id);
            feature.properties = {
              object_id: location.object_id ?? location.location_id,
              location_id: location.location_id,
              location_name: location.location_name,
            };
            if (featureCallback) {
              feature = featureCallback(feature, location);
            }
            self.storage[location.filepath] = feature;
          },
          complete: function () {
            self.storage[location.filepath] = self.storage[location.filepath] ?? false;
          },
          async: async
        });
      }
      return this.storage[location.filepath];
    },

    /**
     * Load features for the given locations asynchronously.
     *
     * @param {Array} locations
     *   A locations array.
     * @param {Callable} callback
     *   A callback function.
     * @param {ghi.state} state
     *   The state object.
     */
    loadFeaturesAsync: function (locations, callback, state = null) {
      let self = this;
      if (state !== null) {
        self.showThrobber(state);
      }
      let filepaths = locations.map((d) => d.filepath).filter((d) => d !== null);
      // Trigger loading of the geojson files.
      locations.map(item => this.getGeoJSON(item)).filter(d => d);
      // And wait until all are available before calling the callback.
      let intervall = setInterval(() => {
        // Filter storage down to the requested entries.
        let storage = Object.keys(self.storage)
          .filter(key => filepaths.includes(key))
          .reduce((obj, key) => {
            obj[key] = self.storage[key];
            return obj;
          }, {});
        // Check if all files have finished loading (either a string or false,
        // but not null).
        let storage_filtered = Object.values(storage).filter((d) => d !== null);
        if (storage_filtered.length == 0 || storage_filtered.length == filepaths.length) {
          clearInterval(intervall);
          if (storage_filtered.length > 0) {
            callback(storage_filtered);
          }
          if (state !== null) {
            self.hideThrobber(state);
          }
        }
      }, 500);
    },

    /**
     * Show the throbber.
     *
     * @param {ghi.mapState} state
     *   The map state.
     */
    showThrobber: function (state) {
      state.throbber?.show();
    },

    /**
     * Hide the throbber.
     *
     * @param {ghi.mapState} state
     *   The map state.
     */
    hideThrobber: function (state) {
      state.throbber?.hide();
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
      let locations = state.getLocations();
      let outline_country = typeof options.outline_country != 'undefined' ? this.getGeoJSON(options.outline_country, null, false) : null;
      if (outline_country) {
        map.fitBounds(turf.envelope(outline_country).bbox, { padding: 50 });
      }
      else if (locations.length) {
        locations.forEach(function(d) {
          // Note that mapbox expects lnglat when we use latlng internally. Also
          // see https://github.com/Turfjs/turf/issues/182 for a discussion in an
          // unrelated project that get's some details about latlng vs lnglat.
          bounds.extend([d.latLng[1], d.latLng[0]]);
        });
        map.fitBounds(bounds, { padding: 50 });
      }
      if (locations.length == 1) {
        map.setZoom(6);
      }

      // Setup the state.
      state.setup(options);
    },

    /**
     * Create a map from the given array of objects.
     *
     * @param {Array} array
     *   The array to process. Must be an array of objects.
     * @param {String} property
     *   The object property to use as key.
     *
     * @returns {Object}
     *   A map object with items keyed by the given item property.
     */
    keyArray: function (array, property) {
      let objects = {};
      if (typeof array != 'object' || !array.length) {
        return objects;
      }
      for (let item of array) {
        if (typeof item != 'object' || !item.hasOwnProperty(property)) {
          continue;
        }
        objects[item[property]] = item;
      }
      return objects;
    },

    /**
     * Get the fill colors.
     *
     * @param {Array} ranges
     *   An array with ranges.
     *
     * @returns {Array}
     *  An array relating a stop point in the data with a color to use.
     */
    getFillColors: function(ranges, colors) {
      let fillColors = [];
      for (i in ranges) {
        fillColors.push([ranges[i], colors[i]]);
      }
      return fillColors;
    },

    /**
     * Get the data ranges for the current set of locations.
     *
     * @param {Array} values
     *   The values to get data ranges for.
     * @param {Int} max
     *   The maximum number of items.
     *
     * @returns {Array}
     *   An array of stop values representing the range in data.
     */
    getDataRanges: function (values, max = 6) {
      // The first range is always 0.
      var ranges = [0];
      if (values.length == 0) {
        return ranges;
      }
      let max_count = Math.max.apply(Math, values);
      let range_count = Math.min(max, max_count);

      // We have (max - 1) steps between 0 and the max count.
      let range_step = max_count > (max - 1) ? Math.floor((max_count - 1) / range_count + 1) : 1;

      // Then we have 4 ranges that are build equally distributed based on the
      // range step.
      for (var i = 0; i < range_count - 1; i++) {
        ranges.push(i * range_step + 1);
      }

      // And then add the highest bucket. Adjust the displayed max count so that
      // not only a single area falls into this bucket.
      var max_count_display = max_count;
      let max_steps = [1000, 500, 200, 100, 50, 20, 15, 10, 5, 1];
      for (var i = 0; i < max_steps.length; i++) {
        if (max_count > max_steps[i]) {
          max_count_display = Math.floor(max_count / max_steps[i]) * max_steps[i];
          // Extra check for plausibility. If the max_step-based max_count is
          // lower than the highest value of the last bucket, correct that.
          let last_bucket_max = ranges[ranges.length - 1] + range_step;
          if (max_count_display < last_bucket_max) {
            max_count_display = (max_count > 100 && max_count - last_bucket_max < 10) ? max_count - 10 : last_bucket_max;
          }
          break;
        }
      }
      ranges.push(max_count_display);
      return ranges;
    },

    /**
     * Convert a color string to RGB.
     *
     * @param {String} color_string_hex
     *   The color string in hey notation.
     *
     * @returns {String}
     *   The color in RGB notation.
     */
    convertToRGB: function(color_string_hex) {
      color_string_hex = color_string_hex.trim().replace('#', '');
      if (color_string_hex.length != 6){
        throw "Only six-digit hex colors are allowed.";
      }
      var aRgbHex = color_string_hex.match(/.{1,2}/g);
      var aRgb = [
        parseInt(aRgbHex[0], 16),
        parseInt(aRgbHex[1], 16),
        parseInt(aRgbHex[2], 16)
      ];
      return 'rgb(' + aRgb.join(',') + ')';
    },

    /**
     * Interpolate both given colors.
     *
     * Color interpolation boldly copied and adapted from
     * https://graphicdesign.stackexchange.com/a/83867
     *
     * @param {String} color1
     *   The first color.
     * @param {String} color2
     *   The second color.
     * @param {Number} factor
     *   @todo What is this?
     *
     * @returns {Array}
     *   An interpolated color.
     */
    interpolateColor: function(color1, color2, factor = null) {
      if (factor === null) {
        factor = 0.5;
      }
      var result = color1.slice();
      for (var i = 0; i < 3; i++) {
        result[i] = Math.round(result[i] + factor * (color2[i] - color1[i]));
      }
      return result;
    },

    /**
     * Interpolate both colors in the given amount if steps.
     *
     * @param {String} color1
     *   The first color.
     * @param {String} color2
     *   The second color.
     * @param {Number} steps
     *   The amount of steps.
     *
     * @returns {Array}
     *   An array of the interpolated colors.
     */
    interpolateColors: function(color1, color2, steps) {
      var stepFactor = 1 / (steps - 1),
          interpolatedColorArray = [];

      color1 = color1.match(/\d+/g).map(Number);
      color2 = color2.match(/\d+/g).map(Number);

      for (var i = 0; i < steps; i++) {
        let rgb = this.interpolateColor(color1, color2, stepFactor * i);
        interpolatedColorArray.push("#" + ((1 << 24) + (rgb[0] << 16) + (rgb[1] << 8) + rgb[2]).toString(16).slice(1));
      }

      return interpolatedColorArray;
    },

  }

})(jQuery);
