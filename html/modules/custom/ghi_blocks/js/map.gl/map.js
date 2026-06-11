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
            feature.id = Number(location.id);
            feature.properties = {
              object_id: location.object_id ?? location.id,
              location_id: location.id,
              location_name: location.name,
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

      options = Object.assign(this.config.defaultOptions, options);
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

  }

})(jQuery);
