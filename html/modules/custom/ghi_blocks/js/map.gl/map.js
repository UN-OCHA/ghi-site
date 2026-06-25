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
    featureRequests: {},
    // Built-in styles are loaded before this controller by the map.gl library.
    styleClasses: {
      circle: window.ghi.circleMap,
      composite: window.ghi.compositeMap,
      choropleth: window.ghi.choroplethMap,
    },
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
     * Destroy a map state and its Mapbox instance.
     *
     * @param {String} map_id
     *   The ID of the map container.
     */
    destroy: function (map_id) {
      let state = this.getMapState(map_id);
      if (!state) {
        return;
      }
      state.destroy();
      delete this.states[map_id];
    },

    /**
     * Get the map style class for a style id.
     *
     * @param {String} style
     *   The style id.
     *
     * @returns {Function|null}
     *   The style class, or NULL if none is available.
     */
    getMapStyleClass: function (style) {
      return this.styleClasses[style] ?? null;
    },

    /**
     * Normalize loaded GeoJSON data into the feature shape used by maps.
     *
     * @param {Object} data
     *   Loaded GeoJSON data.
     *
     * @returns {Object|null}
     *   A feature object, or NULL if the data is not usable.
     */
    normalizeGeoJSONData: function (data) {
      let type = data?.type ?? null;
      let feature = data;
      if (type == 'FeatureCollection') {
        // Merge all feature geometries into a single object, because
        // this is what we need.
        let features = Array.isArray(data.features) ? data.features : [];
        feature = {
          'type': 'Feature',
          'properties': {},
          'geometry': {
            'type': 'GeometryCollection',
            'geometries': features.map((item) => item.geometry),
          }
        };
      }
      return type && feature ? feature : null;
    },

    /**
     * Prepare cached GeoJSON data for a specific location.
     *
     * @param {Object} feature
     *   The cached feature data.
     * @param {Object} location
     *   The location object.
     * @param {Callable} featureCallback
     *   An optional callback for the prepared feature.
     *
     * @returns {Object}
     *   The prepared feature data.
     */
    prepareGeoJSONFeature: function(feature, location, featureCallback = null) {
      if (!feature) {
        return feature;
      }

      // Treat cache entries as shared geometry. Properties such as object_count
      // are view-specific and must be applied freshly for each map render.
      let location_id = location.id ?? location.location_id;
      let location_name = location.name ?? location.location_name;
      feature = Object.assign({}, feature, {
        id: Number(location_id),
        properties: {
          object_id: location.object_id ?? location_id,
          location_id: location_id,
          location_name: location_name,
        },
      });
      if (featureCallback) {
        feature = featureCallback(feature, location);
      }
      return feature;
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
      if (!location || !location.filepath) {
        return null;
      }
      if (async !== false) {
        this.loadGeoJSON(location);
      }
      else if (typeof this.storage[location.filepath] == 'undefined' || this.storage[location.filepath] === null) {
        this.storage[location.filepath] = null;
        $.ajax({
          dataType: 'json',
          url: location.filepath,
          success: function (data) {
            self.storage[location.filepath] = self.normalizeGeoJSONData(data);
          },
          complete: function () {
            self.storage[location.filepath] = self.storage[location.filepath] ?? false;
          },
          async: false
        });
      }
      return this.prepareGeoJSONFeature(this.storage[location.filepath], location, featureCallback);
    },

    /**
     * Load GeoJSON data for a location.
     *
     * @param {Object} location
     *   The location object.
     *
     * @returns {Object}
     *   A jQuery promise that resolves when the GeoJSON cache entry is ready.
     */
    loadGeoJSON: function (location) {
      if (!location || !location.filepath) {
        return $.Deferred().resolve(false).promise();
      }
      let filepath = location.filepath;
      if (typeof this.storage[filepath] != 'undefined' && this.storage[filepath] !== null) {
        return $.Deferred().resolve(this.storage[filepath]).promise();
      }
      if (typeof this.featureRequests[filepath] != 'undefined') {
        return this.featureRequests[filepath];
      }

      let deferred = $.Deferred();
      this.storage[filepath] = null;
      $.ajax({
        dataType: 'json',
        url: filepath,
        success: (data) => {
          this.storage[filepath] = this.normalizeGeoJSONData(data);
        },
        error: () => {
          this.storage[filepath] = false;
        },
        complete: () => {
          this.storage[filepath] = this.storage[filepath] ?? false;
          deferred.resolve(this.storage[filepath]);
        }
      });
      this.featureRequests[filepath] = deferred.promise();
      return this.featureRequests[filepath];
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
      locations = locations ?? [];
      let requests = locations
        .filter((location) => location && location.filepath)
        .map((location) => this.loadGeoJSON(location));
      if (!requests.length) {
        callback([]);
        if (state !== null) {
          self.hideThrobber(state);
        }
        return;
      }
      $.when.apply($, requests).always(() => {
        let features = locations.map((location) => self.getGeoJSON(location)).filter((feature) => feature);
        callback(features);
        if (state !== null) {
          self.hideThrobber(state);
        }
      });
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
     * Prepare a bitmap snapshot for Snap PNG captures.
     *
     * Snap's element screenshot can miss WebGL canvas contents even after the
     * map is ready. In PNG capture mode we copy the rendered canvas into an
     * overlaid image so the export captures the same visual map.
     *
     * @param {ghi.mapState} state
     *   The map state.
     */
    preparePngCaptureSnapshot: function (state) {
      let map = state.getMap();
      let element = document.getElementById(state.getMapId());
      if (!map || !element) {
        return;
      }
      let blockElement = element.closest('.block');

      element.scrollIntoView({
        block: 'center',
        inline: 'nearest',
      });
      map.resize();

      let attempts = 0;
      let failSnapshot = () => {
        element.setAttribute('data-map-snapshot-error', '');
        map.off('idle', syncSnapshot);
      };
      let syncSnapshot = () => {
        attempts++;
        let isReady = false;
        try {
          isReady = map.isStyleLoaded() && map.areTilesLoaded();
        }
        catch (e) {
          isReady = false;
        }
        let style = state.style ?? null;
        let layerId = style && typeof style.getFeatureLayerId === 'function' ? style.getFeatureLayerId() : null;
        let locations = typeof state.getLocations === 'function' ? state.getLocations() : [];
        if (isReady && style && style.loaded !== true) {
          isReady = false;
        }
        if (isReady && layerId) {
          let renderedFeatures = [];
          if (!map.getLayer(layerId)) {
            isReady = false;
          }
          else if (locations.length) {
            try {
              renderedFeatures = map.queryRenderedFeatures({ layers: [layerId] });
            }
            catch (e) {
              try {
                renderedFeatures = map.queryRenderedFeatures(undefined, { layers: [layerId] });
              }
              catch (e) {
                renderedFeatures = [];
              }
            }
            isReady = renderedFeatures.length > 0;
          }
        }
        if (!isReady) {
          if (attempts < 40) {
            setTimeout(syncSnapshot, 500);
          }
          else {
            failSnapshot();
          }
          return;
        }

        let canvas = element.querySelector('.mapboxgl-canvas');
        let mapContainer = element.querySelector('.mapboxgl-map');
        if (!canvas || !mapContainer) {
          failSnapshot();
          return;
        }

        let snapshot = element.querySelector(':scope > .mapboxgl-canvas-snapshot');
        if (!snapshot) {
          snapshot = document.createElement('img');
          snapshot.className = 'mapboxgl-canvas-snapshot';
          snapshot.alt = '';
          snapshot.setAttribute('aria-hidden', 'true');
          snapshot.style.display = 'block';
          snapshot.style.width = '100%';
          snapshot.style.height = mapContainer.offsetHeight + 'px';
          snapshot.style.objectFit = 'cover';
          element.appendChild(snapshot);
        }

        requestAnimationFrame(() => {
          requestAnimationFrame(() => {
            try {
              let snapshotUrl = canvas.toDataURL('image/png');
              snapshot.src = snapshotUrl;
              mapContainer.style.display = 'none';
              element.setAttribute('data-map-snapshot-ready', '');
              blockElement?.classList.add('map-image-loaded');
              map.off('idle', syncSnapshot);
            }
            catch (e) {
              failSnapshot();
            }
          });
        });
      };

      map.on('idle', syncSnapshot);
      setTimeout(syncSnapshot, 500);
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

      options = $.extend(true, {}, this.config.defaultOptions, options);
      let mapbox = new ghi.mapbox();
      let map = mapbox.addMap(element, options);
      if (!map) {
        if (element && options.png_capture_mode === true) {
          element.setAttribute('data-map-snapshot-error', '');
        }
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
      if (options.png_capture_mode === true) {
        this.preparePngCaptureSnapshot(state);
      }
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
     * Get the fill color stops for the given ranges.
     *
     * @param {Array} ranges
     *   The range stops.
     * @param {Object} colors
     *   The colors keyed by range index.
     *
     * @returns {Array}
     *   An array relating a stop point in the data with a color to use.
     */
    getFillColors: function (ranges, colors) {
      let fillColors = [];
      for (let i in ranges) {
        fillColors.push([ranges[i], colors[i]]);
      }
      return fillColors;
    },

    /**
     * Get the fill color for a specific value.
     *
     * @param {Array} ranges
     *   The range stops.
     * @param {Object} colors
     *   The colors keyed by range index.
     * @param {Number} value
     *   The value to map to a color.
     *
     * @returns {String}
     *   A color code as a string.
     */
    getFillColor: function (ranges, colors, value) {
      for (let i in ranges) {
        if (value <= ranges[i]) {
          return i > 0 ? colors[i - 1] : colors[i];
        }
      }
      return colors[Object.keys(colors).length - 1];
    },

    /**
     * Get the data ranges for the given values.
     *
     * @param {Array} values
     *   The values to get data ranges for.
     * @param {Number} max
     *   The maximum number of range buckets.
     *
     * @returns {Array}
     *   An array of stop values representing the range in data.
     */
    getDataRanges: function (values, max = 6) {
      var ranges = [0];
      if (values.length == 0) {
        return ranges;
      }
      let max_count = Math.max.apply(Math, values);
      let range_count = Math.min(max, max_count);
      let range_step = max_count > (max - 1) ? Math.floor((max_count - 1) / range_count + 1) : 1;

      for (var i = 0; i < range_count - 1; i++) {
        ranges.push(i * range_step + 1);
      }

      var max_count_display = max_count;
      let max_steps = [1000, 500, 200, 100, 50, 20, 15, 10, 5, 1];
      for (var j = 0; j < max_steps.length; j++) {
        if (max_count > max_steps[j]) {
          max_count_display = Math.floor(max_count / max_steps[j]) * max_steps[j];
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

  }

})(jQuery);
