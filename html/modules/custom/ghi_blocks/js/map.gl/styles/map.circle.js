(function ($) {

  'use strict';

  const root_styles = getComputedStyle(document.documentElement);

  if (!window.ghi) {
    window.ghi = {};
  }

  /**
   * Constructor for the circle style object.
   *
   * @param {String} id
   *   The ID of the map container.
   * @param {Object} state
   *   The state object for the map.
   * @param {Object} options
   *   The options for the map style.
   */
  window.ghi.circleMap = class {

    constructor (map, state, options) {
      this.map = map;
      this.state = state;
      this.options = options;
      this.loaded = false;
      this.sourceId = state.getMapId();
      this.featureLayerId = this.sourceId + '-circle';
      this.activeFeatureLayerId = this.sourceId + '-circle-active';
      this.labelLayerId = this.sourceId + '-label';
      this.config = {
        // Scales used to determine color.
        colors: [
          root_styles.getPropertyValue('--ghi-default-text-color'), // Data points with data.
          root_styles.getPropertyValue('--ghi-default-border-color'), // Data points without data.
          root_styles.getPropertyValue('--ghi-primary-color'), // Highlights.
        ],
        plan_type_colors: {
          'hrp': root_styles.getPropertyValue('--ghi-plan-type-hrp'),
          'hnrp': root_styles.getPropertyValue('--ghi-plan-type-hnrp'),
          'srp': root_styles.getPropertyValue('--ghi-plan-type-srp'),
          'fa': root_styles.getPropertyValue('--ghi-plan-type-fa'),
          'reg': root_styles.getPropertyValue('--ghi-plan-type-reg'),
          'other': root_styles.getPropertyValue('--ghi-plan-type-other'),
        },
        attrs: {
          'stroke': '#fff',
          'cursor': 'pointer',
          'opacity': 0.3,
          'opacity_overview': 0.8,
          'opacity_hover': 0.6,
          'base_radius': 10,
        }
      };
    }

    /**
     * Get the id of the feature layer.
     *
     * @returns {String}
     *   The id of the feature layer.
     */
    getFeatureLayerId = function () {
      return this.featureLayerId;
    }

    /**
     * Setup the style.
     */
    setup = function () {
      let self = this;
      let state = this.state;
      let map = state.getMap();

      map.on('load', () => {
        if (self.loaded) {
          return;
        }
        self.loaded = true;

        // Add admin area or country outlines.
        if (!state.isOverviewMap()) {
          this.addAdminAreaOutlines();
        }
        else if (state.shouldShowCountryOutlines()) {
          this.addCountryOutlines();
        }

        // Add a layer for the labels, so that we can keep showing them on top
        // of colored admin area or country outlines.
        map.addLayer(this.state.buildLabelLayer(this.labelLayerId));

        // Initial drawing of the circles.
        map.addSource(self.sourceId, this.buildSource());

        if (!state.isOverviewMap()) {
          // On plan attachment maps, we also want to show the names of the
          // admin areas as map labels.
          this.addAdminAreaLabels();
        }

        map.addLayer(this.buildCircleLayer());
        map.on('click', self.featureLayerId, (e) => self.clickHandler(e, self));

        // Build a layer that can hold active features, so that they pop up
        // from behind and appearingly come to the foreground.
        map.addLayer(this.buildActiveFeatureLayer());

        // Add event handling.
        this.addEventListeners(self.sourceId);

        map.on('zoom', () => {
          // Also redraw on zoom. This is mainly important for the offset
          // locations on the plan overview map.
          self.renderLocations();
          this.updateActiveFeatures();
        });
      });
    }

    /**
     * Render the locations on the map.
     *
     * @returns {String}
     *   The map id.
     */
    renderLocations = function (duration = null) {
      if (!this.loaded) {
        return;
      }

      // Update the data.
      let features = this.updateFeatures(duration);
      this.updateMapData(this.sourceId, features);

      // Update the active feature if needed. This updates the position so that
      // the feature on the active layer keeps the same position as the actual
      // feature in the feature layer.
      let focus_feature = this.state.getFocusFeature();
      if (focus_feature) {
        let active_feature = features.filter((d) => d.properties.object_id == focus_feature.properties.object_id)[0] ?? null;
        this.updateActiveFeature(active_feature);
      }

      // Update the active feature if needed. This updates the position so that
      // the feature on the active layer keeps the same position as the actual
      // feature in the feature layer.
      let hover_feature = this.state.getHoverFeature();
      if (hover_feature) {
        let active_feature = features.filter((d) => d.properties.object_id == hover_feature.properties.object_id)[0] ?? null;
        this.updateActiveFeature(active_feature);
      }

      // Needed for changes to the admin level.
      this.addAdminAreaOutlines();
    }

    /**
     * Build the source feature.
     *
     * @returns {Object}
     *   The source feature.
     */
    buildSource = function () {
      let data = this.buildData(this.buildLocationFeatures());
      return this.state.buildGeoJsonSource(data);
    }

    /**
     * Build the data object for a list of features.
     *
     * This is useful whenever we want to call setData() on a source object.
     *
     * @returns {Object}
     *   The geojson data object for the source.
     */
    buildData = function (features) {
      return this.state.buildFeatureCollection(features);
    }

    /**
     * Build the location features.
     *
     * @returns {Array}
     *   An array of feature objects.
     */
    buildLocationFeatures = function () {
      let locations = this.state.getLocations();
      locations = locations.filter((object) => object.total > 0);
      let features = locations.map(object => object.feature ?? this.buildFeatureForObject(object));
      return features;
    }

    /**
     * Update the features in the current map.
     *
     * @returns {Array}
     *   An array of feature objects.
     */
    updateFeatures = function (duration = null) {
      let self = this;
      let state = this.state;
      return state.updateFeatures(this.sourceId, this.featureLayerId, (object) => object.feature ?? self.buildFeatureForObject(object), this.transitionFeature, duration);
    }

    /**
     * Transition a feature from an old state to a new state.
     *
     * @param {Object} state
     *   The state object responsible for the transition.
     * @param {Object} animate
     *   An object with the properties "new", "old" and "object".
     * @param {Number} timestamp
     *   A timestamp relative to the total duration of the animation.
     * @param {Number} duration
     *   The total duration of the animation.
     * @param {Array} transition_features
     *   An array of already transitioned featured for the current animation
     *   cycle.
     *
     * @returns {Object}
     *   A feature object.
     */
    transitionFeature = function (state, animate, timestamp, duration, transition_features) {
      let old_radius = animate.old?.properties?.radius ?? 0;
      let new_radius = animate.new?.properties?.radius ?? 0;
      let object = animate.object;
      let radius_difference = Math.abs(new_radius - old_radius);
      let radius_intervall = radius_difference / duration;
      let transition = structuredClone(animate.new);

      let factor = old_radius < new_radius ? 1 : -1;
      let radius = Math.max(old_radius + (factor * radius_intervall * timestamp), 0);
      radius = old_radius < new_radius ? Math.min(radius, new_radius) : Math.max(radius, new_radius);

      transition.properties.radius = radius;

      let offset_chain = object?.hasOwnProperty('offset_chain') ? object.offset_chain : [];
      if (offset_chain.length > 1) {
        let pixel_offset = transition.properties.radius;
        for (var object_id of offset_chain) {
          let offset_feature = transition_features[object_id] ?? null;
          if (!offset_feature) {
            continue;
          }
          let factor = offset_chain.indexOf(object_id) == 0 || object_id == object.object_id ? 1 : 2;
          pixel_offset += (offset_feature.properties.radius * factor) + (1 * factor);
        }
        transition.geometry.coordinates = state.offsetCoordinates([object.latLng[1], object.latLng[0]], pixel_offset);
      }
      if (transition.properties.object_id === state.focusId) {
        // Update the currently selected active feature too.
        state.style.updateActiveFeature(transition);
      }
      return transition;
    }

    /**
     * Build the geojson feature for the given object.
     *
     * @param {Object} object
     *   The location object.
     *
     * @returns {Object}
     *   The geojson feature object.
     */
    buildFeatureForObject = function (object) {
      let radius = this.getRadius(object);
      return {
        'id': object.object_id,
        'type': 'Feature',
        'geometry': {
          'type': 'Point',
          'coordinates': this.getCenterCoordinates(object),
        },
        'properties': {
          // General properties.
          'object_id': object.object_id,
          'object_name': object.location_name,
          'admin_level': object.admin_level,
          'legend_type': object.plan_type ?? null,
          // Paint properties.
          'radius': radius,
          'color': this.getColor(object),
          'opacity': this.getOpacity(object),
          // Label properties.
          'sort_order': Math.round((1 / radius) * 100),
          'label_offset': Math.sqrt(Math.sqrt(Math.sqrt((radius)))),
          'font_size': Math.sqrt(radius),
          'icon_size': radius / 30,
        }
      };
    }

    /**
     * Build the circle layer feature.
     *
     * @returns {Object}
     *   A layer object.
     */
    buildCircleLayer = function () {
      let opacity_circle = [
        'case',
        ['boolean', ['feature-state', 'hover'], false],
        1,  // 1 opacity when hovering.
        ['boolean', ['feature-state', 'focus'], false],
        1,  // 1 opacity when focused.
        ['boolean', ['feature-state', 'hidden'], false],
        0,  // 0 opacity when hidden.
        ['get', 'opacity'] // Default opacity.
      ];
      return {
        'type': 'circle',
        'id': this.featureLayerId,
        'source': this.sourceId,
        'paint': {
          'circle-radius': ['get', 'radius'],
          'circle-color': ['get', 'color'],
          'circle-opacity': opacity_circle,
          'circle-stroke-width': 1,
          'circle-stroke-color': root_styles.getPropertyValue('--cd-white'),
          'circle-stroke-opacity': opacity_circle,
        },
        'filter': ['==', '$type', 'Point'],
      };
    }

    /**
     * Build the active feature layer.
     *
     * @returns {Object}
     *   A layer object.
     */
    buildActiveFeatureLayer = function () {
      let layer_id = this.activeFeatureLayerId;
      return {
        'id': layer_id,
        'type': 'circle',
        'source': this.state.buildGeoJsonSource(null),
        'layout': {
          'visibility': 'none',
        },
        'paint': {
          'circle-radius': ['+', 5, ['get', 'radius']],
          'circle-color': ['get', 'color'],
          'circle-opacity': 1,
          'circle-stroke-width': 1,
          'circle-stroke-color': root_styles.getPropertyValue('--cd-white'),
          'circle-stroke-opacity': 1,
        },
      }
    }

    /**
     * Add outlines around the countries if available.
     */
    addCountryOutlines = function () {
      let self = this;
      let state = this.state;
      let map = state.getMap();

      let geojson_source_id = this.sourceId + '-geojson';
      if (map.getSource(geojson_source_id)) {
        map.removeLayer(geojson_source_id + '-outline');
        map.removeLayer(geojson_source_id + '-fill');
        map.removeSource(geojson_source_id);
      }
      map.addSource(geojson_source_id, {
        'type': 'geojson',
        'data': null,
        'generateId': false,
      });
      // Fill the polygon.
      map.addLayer({
        'id': geojson_source_id + '-fill',
        'type': 'fill',
        'source': geojson_source_id,
        'layout': {},
        'paint': {
          'fill-color': [
            'case',
            ['boolean', ['feature-state', 'hover'], false],
            '#FFF6D7', // Color when hovered.
            ['boolean', ['feature-state', 'focus'], false],
            '#FFE691', // Color when focused.
            '#FFF6D7', // Default color.
          ],
          'fill-opacity': [
            'case',
            ['boolean', ['feature-state', 'hover'], false],
            1,  // 1 opacity when hovering.
            ['boolean', ['feature-state', 'focus'], false],
            1,  // 1 opacity when focused.
            ['boolean', ['feature-state', 'hidden'], false],
            0,  // 0 opacity when hidden.
            0,
          ],
        }
      });
      // Add an outline around the polygon.
      map.addLayer({
        'id': geojson_source_id + '-outline',
        'type': 'line',
        'source': geojson_source_id,
        'layout': {},
        'paint': {
          'line-color': root_styles.getPropertyValue('--ghi-grey'),
          'line-width': 1,
          'line-opacity': [
            'case',
            ['boolean', ['feature-state', 'hover'], false],
            0.5, // When hovered.
            ['boolean', ['feature-state', 'focus'], false],
            0.5, // When focused.
            0, // Hidden by default.
          ]
        }
      });

      // Build the country features and add them to the source, but do it
      // non-blocking.
      let locations = Object.values(state.getData().geojson ?? {});
      state.getMapController().loadFeaturesAsync(locations, (features) => {
        self.updateMapData(geojson_source_id, features);
      });
    }

    /**
     * Add outlines around the admin areas if available.
     */
    addAdminAreaOutlines = function () {
      let self = this;
      let state = this.state;
      let map = state.getMap();

      let geojson_source_id = this.sourceId + '-geojson';

      if (!map.getSource(geojson_source_id)) {
        map.addSource(geojson_source_id, this.state.buildGeoJsonSource(null));

        // Fill the polygon.
        map.addLayer({
          'id': geojson_source_id + '-fill',
          'type': 'fill',
          'source': geojson_source_id,
          'layout': {},
          'paint': {
            'fill-color': [
              'case',
              ['boolean', ['feature-state', 'hover'], false],
              '#FFF6D7', // Color when hovered.
              ['boolean', ['feature-state', 'focus'], false],
              '#FFE691', // Color when focused.
              '#FFF6D7', // Default color.
            ],
            'fill-opacity': [
              'case',
              ['boolean', ['feature-state', 'hover'], false],
              1,  // 1 opacity when hovering.
              ['boolean', ['feature-state', 'focus'], false],
              1,  // 1 opacity when focused.
              ['boolean', ['feature-state', 'hidden'], false],
              0,  // 0 opacity when hidden.
              0,
            ],
          }
        });
        // Add an outline around the polygon.
        map.addLayer({
          'id': geojson_source_id + '-outline',
          'type': 'line',
          'source': geojson_source_id,
          'layout': {},
          'paint': {
            'line-color': root_styles.getPropertyValue('--ghi-grey'),
            'line-width': 1,
            'line-opacity': 0.5,
          }
        });
      }

      // Build the admin area features and add them to the source, but do it
      // non-blocking.
      state.getMapController().loadFeaturesAsync(state.getLocations(), (features) => {
        self.updateMapData(geojson_source_id, features);
      });
    }

    /**
     * Add admin area labels to the map.
     */
    addAdminAreaLabels = function () {
      let state = this.state;
      let map = state.getMap();

      let label_source_id = this.sourceId + '-labels';
      let backgroundLayer = state.getBackgroundLayer(map);
      let options = state.getOptions();

      map.on('styleimagemissing', (e) => {
        const id = e.id; // id of the missing image

        // Check if this missing icon is
        // one this function can generate.
        if (id != 'hidden-icon') return;

        const width = 60; // The image will be 60 pixels square.
        const bytesPerPixel = 4; // Each pixel is represented by 4 bytes: red, green, blue, and alpha.
        const data = new Uint8Array(width * width * bytesPerPixel);

        let debug = false;
        for (let x = 0; x < width; x++) {
            for (let y = 0; y < width; y++) {
                const offset = (y * width + x) * bytesPerPixel;
                data[offset + 0] = 255; // red
                data[offset + 1] = 0; // green
                data[offset + 2] = 0; // blue
                data[offset + 3] = debug ? 120 : 0; // alpha
            }
        }

        map.addImage(id, { width: width, height: width, data: data });
      });

      // Add a layer for the labels.
      map.addLayer({
        'id': label_source_id,
        'type': 'symbol',
        'source': this.sourceId,
        'minzoom': options.label_min_zoom ?? 0,
        'layout': {
          'symbol-sort-key': ['get', 'sort_order'],
          'text-field': ['get', 'object_name'],
          'text-font': backgroundLayer.layout['text-font'],
          'text-letter-spacing': backgroundLayer.layout['text-letter-spacing'],
          'text-size': ['*', ['get', 'font_size'], 5],
          'text-variable-anchor': [
            'top',
            'bottom',
            'left',
            'right',
          ],
          'text-radial-offset': [
            'interpolate',
            ['linear'],
            ['zoom'],
            2,
            ['*', ['get', 'label_offset'], 1.3],
            5,
            ['get', 'label_offset'],
          ],
          'text-justify': 'auto',
        },
        'paint': {
          'text-color': backgroundLayer.paint['text-color'],
          'text-halo-color': backgroundLayer.paint['text-halo-color'],
          'text-halo-width': backgroundLayer.paint['text-halo-width'],
        }
      });
      // Add a layer with blocking symbols that overlap the circles, so that
      // these can be used by mapbox for collision detection. Otherwhise the
      // labels would appear overlaying the circles.
      map.addLayer({
        'id': label_source_id + '-blocks',
        'type': 'symbol',
        'source': this.sourceId,
        'layout': {
          'icon-image': 'hidden-icon',
          'icon-size': ['get', 'icon_size'],
          'icon-allow-overlap': true,
        },
      });
    }

    /**
     * Click handler for the circles.
     *
     * @param {Event} e
     *   The event.
     * @param {ghi.circleMap} self
     *   The current style instance.
     */
    clickHandler = function (e, self) {
      let feature = self.state.getFeatureFromEvent(e, self.featureLayerId)
      if (!feature) {
        return;
      }
      let object_id = feature.properties.object_id;
      let object = self.state.getLocationById(object_id);
      self.showSidebarForObject(object);
    }

    /**
     * Update the data for the given source id.
     *
     * @param {String} source_id
     *   The source id.
     * @param {Array} features
     *   An array of features to set as the data for the given source.
     */
    updateMapData = function(source_id, features) {
      this.state.updateMapData(source_id, features);
    }

    /**
     * Create or update the legend items.
     */
    updateLegend = function() {
      let state = this.state;
      let colors = this.config.plan_type_colors;
      let options = state.getOptions();
      let $legend_container = state.getContainer().find('.map-legend');
      let $legend = $('<ul>');

      // Remove old legend items.
      $legend_container.find('li').remove();

      var legend_items = [];
      for (var legend_key of Object.keys(options.legend)) {
        legend_items.push({
          'label': options.legend[legend_key],
          'type': legend_key,
        });
      }

      // Set the legend caption.
      if (options.legend_caption) {
        let legend_caption = $legend_container.find('div.legend-caption');
        if (!legend_caption.length) {
          $legend_container.prepend($('<div>').addClass('legend-caption'));
        }
        $legend_container.find('div.legend-caption').text(options.legend_caption);
      }

      for (let item of legend_items) {
        let $legend_icon = $('<div>')
          .css('background-color', colors[item.type])
          .addClass('legend-icon')
          .addClass('legend-icon-' + item.type);
        let $legend_label = $('<div>')
          .text(item.label)
          .addClass('legend-label');
        let $legend_item = $('<li>')
          .addClass('legend-item')
          .attr('data-type', item.type)
          .append($legend_icon)
          .append($legend_label);
          $legend.append($legend_item);
      }
      $legend_container.html($legend);
    }

    /**
     * Update the active features.
     */
    updateActiveFeatures = function () {
      let self = this;
      let state = this.state;
      let map = state.getMap();
      let layer_id = this.activeFeatureLayerId;
      let hover_feature = state.getHoverFeature();
      let focus_feature = state.getFocusFeature();
      let features = [hover_feature, focus_feature].filter(d => d !== null);

      self.updateMapData(layer_id, features);
      map.setLayoutProperty(layer_id, 'visibility', features.length ? 'visible' : 'none');

      if (focus_feature) {
        // There was an issue when selecting a feature, then moving and zooming
        // the map so that the selected feature wouldn't be visible anymore,
        // then selecting another feature in the new viewport, which would then
        // not unset the feature state of the previously active feature. So we
        // need to make sure to unset the feature state of that older feature
        // anytime it becomes visible.
        let source_id = state.getMapId();
        let geojson_source_id = source_id + '-geojson';
        let existing = [];
        if (state.shouldShowCountryOutlines()) {
          let location = state.getLocationById(focus_feature.properties.object_id);
          let highlight_countries = location?.highlight_countries;
          let filter = highlight_countries ? ['in', ['get', 'location_id'], ['literal', highlight_countries]] : null;
          let geojson_features_ids = state.querySourceFeatures(geojson_source_id, geojson_source_id, filter)
            .map((d) => d.id);
          existing = state.querySourceFeatures(geojson_source_id + '-fill', geojson_source_id)
            .filter((d) => !geojson_features_ids.length || geojson_features_ids.indexOf(d.id) == -1)
            .filter((d) => (map.getFeatureState({
              source: geojson_source_id,
              id: d.id
            })['focus'] ?? false) === true);
        }
        else {
          existing = state.querySourceFeatures(geojson_source_id + '-fill', geojson_source_id)
            .filter((d) => !focus_feature || d.id != focus_feature.id)
            .filter((d) => (map.getFeatureState({
              source: geojson_source_id,
              id: d.id
            })['focus'] ?? false) === true);
        }
        if (existing.length) {
          existing.forEach(item => {
            map.setFeatureState(
              { source: geojson_source_id, id: item.id },
              { 'focus': false}
            );
          });
        }
      }
    }

    /**
     * Update the active features.
     */
    updateActiveFeature = function (feature) {
      let self = this;
      let state = this.state;
      let layer_id = this.activeFeatureLayerId;
      let existing_features = state.querySourceFeatures(layer_id, layer_id).map((d) => {
        if (d.properties.object_id != feature.properties.object_id) {
          return d;
        }
        d.properties = feature.properties;
        d.geometry = feature.geometry;
        return d;
      });
      self.updateMapData(layer_id, existing_features);
    }

    /**
     * Show a sidebar for the given object.
     *
     * @param {Object} object
     *   The location object.
     */
    showSidebarForObject = function (object) {
      let state = this.state;
      let data = state.getData();

      let object_id = parseInt(object.object_id);
      let location_data = data.modal_contents[object_id];

      // Check for variant data.
      let variant_id = state.getVariantId();
      if (variant_id && state.hasVariant(state.getCurrentIndex(), variant_id)) {
        location_data = data.variants[variant_id].modal_contents[object_id];
      }
      if (!location_data) {
        // The new tab has no data for the currently active location.
        return;
      }

      let monitoring_period = location_data.monitoring_period ?? null;
      let build = {
        location_data: location_data,
        title: location_data.title,
        tag_line: location_data.tag_line,
        pcodes_enabled: this.options.pcodes_enabled ?? false,
        monitoring_period: monitoring_period ? '<div class="monitoring-period">' + location_data.monitoring_period + '</div>' : '',
        content: this.buildSidebarContent(object),
        template: [
          '<div class="title">{title}</div>',
          '<div class="tag-line">{tag_line}</div>',
          '<div class="content">{content}</div>',
          '<div class="subcontent">{monitoring_period}</div>',
        ].join(''),
      }

      // This looks a bit complicated, but we need to attach the focus changes
      // to the map events, because features that are not currently visible on
      // the zoomed map area can't be focused yet before the panning operation
      // (triggered by sidebar.show()) is finished.
      let focus_feature = state.getFocusFeature();
      this.state.getMap().once('movestart', () => {
        let feature = state.getFeatureByObjectId(object_id);
        if (feature && feature.properties.object_id != focus_feature?.properties?.object_id) {
          state.resetFocus();
        }
      });
      this.state.getMap().once('moveend', () => {
        let feature = state.getFeatureByObjectId(object_id);
        if (feature && feature.properties.object_id != focus_feature?.properties?.object_id) {
          state.focusFeature(feature);
        }
      });

      // Now show the sidebar. This also moves the map to the newly focused
      // feature.
      state.sidebar?.show(object, build);
    }

    /**
     * Build the content of the sidebar.
     *
     * @param {Object} d
     *   The data object for which to build the content.
     *
     * @returns {String}
     *   The HTML of the sidebar content.
     */
    buildSidebarContent = function(object) {
      let state = this.state;
      var data = state.getData();
      var object_id = parseInt(object.object_id);

      var base_data = null;
      let variant_id = state.getVariantId();
      if (variant_id != null && state.hasVariant(state.getCurrentIndex(), variant_id)) {
        base_data = data.variants[variant_id];
      }
      else if (typeof data.modal_contents != 'undefined' && typeof data.modal_contents[object_id] != 'undefined') {
        base_data = data;
      }
      let modal_content = base_data ? base_data.modal_contents[object_id] : (object.modal_content ?? null);
      if (!modal_content) {
        return false;
      }
      if (typeof modal_content.table_data != 'undefined') {
        // Table header and rows are prerendered.
        var table_data = modal_content.table_data;
        return Drupal.theme('table', table_data.header, table_data.rows, {'classes': 'plan-attachment-modal-table'});
      }
      if (typeof modal_content.categories != 'undefined') {
        // Categories need to be rendered as a table here.
        var table_rows = [
          [Drupal.t('Total !metric_name', {'!metric_name': modal_content.metric_label}), Drupal.theme('amount', modal_content.total)],
        ];
        for (let category of Object.values(modal_content.categories)) {
          table_rows.push([
            category.name,
            Drupal.theme('amount', category.value),
          ]);
        }
        return Drupal.theme('table', [], table_rows, {'classes': 'plan-attachment-modal-table'});
      }
      if (typeof modal_content.html != 'undefined') {
        // Full html of the modal is already prepared.
        return modal_content.html;
      }
    }

    /**
     * Add event listeners.
     */
    addEventListeners = function () {
      let self = this;
      let state = this.state;
      let map = state.getMap();
      let layer_id = this.featureLayerId;

      map.on('mouseenter', layer_id, (e) => {
        // Enable hover.
        let feature = state.getFeatureFromEvent(e);
        if (!feature) {
          return;
        }
        if (this.isHidden(feature)) {
          state.resetHover();
          return;
        }

        state.hoverFeature(feature);
        state.showTooltip(this.getTooltipContent(state.getLocationFromFeature(feature)));
      });

      map.on('mousemove', layer_id, (e) => {
        let feature = state.getFeatureFromEvent(e);
        if (!feature) {
          if (state.isHovered()) {
            // Disable hover.
            state.resetHover();
          }
          return;
        }
        if (this.isHidden(feature)) {
          state.resetHover();
          return;
        }
        if (!state.isHovered(feature)) {
          // Update the hover if changed.
          state.hoverFeature(feature);
          state.showTooltip(this.getTooltipContent(state.getLocationFromFeature(feature)));
        }
      });
      map.on('mouseleave', layer_id, () => {
        // Disable hover.
        state.resetHover();
      });

      state.getCanvasContainer().on('focus-feature', function (event, feature) {
        self.updateActiveFeatures();
      });
      state.getCanvasContainer().on('reset-focus', function () {
        self.updateActiveFeatures();
      });
    }

    /**
     * Get the center coordinates for the given object.
     *
     * @param {Object} object
     *   The data object.
     *
     * @returns {Array}
     *   An array with lng and lat coordinates.
     */
    getCenterCoordinates = function (object) {
      return this.state.offsetCoordinates([object.latLng[1], object.latLng[0]], this.getLocationOffset(object));
    }

    /**
     * Get the opacity for the given data point.
     *
     * @param {Object} object
     *   A data object.
     * @param {ghi.mapState} state
     *   The map state object.
     *
     * @returns {Number}
     *   The opacity.
     */
    getOpacity = function (object) {
      let state = this.state;
      let attrs = this.config.attrs;
      if (state.isOverviewMap()) {
        return attrs.opacity_overview;
      }
      return attrs.opacity;
    }

    /**
     * Get the color for the given data point.
     *
     * @param {Object} object
     *   A data object.
     *
     * @returns {Number}
     *   The color.
     */
    getColor = function (object) {
      let state = this.state;
      if (state.isOverviewMap() && object.hasOwnProperty('plan_type')) {
        let color = this.config.plan_type_colors;
        let color_key = object.plan_type.toLowerCase();
        return color.hasOwnProperty(color_key) ? color[color_key] : color['other'];
      }
      let color = this.config.colors;
      if (state.emptyValueForCurrentTab(object)) {
        return color[1];
      }
      return color[0];
    }

    /**
     * Get the radius factor for the given data point.
     *
     * @param {Object} object
     *   A data object.
     *
     * @returns {Number}
     *   The color.
     */
    getRadiusFactor = function (object) {
      let state = this.state;
      if (typeof object == 'undefined') {
        return 1;
      }
      if (typeof object.radius_factor_grouped != 'undefined') {
        return object.radius_factor_grouped;
      }
      if (typeof object.radius_factors != 'undefined' && typeof object.radius_factors[state.currentIndex] != 'undefined') {
        return object.radius_factors[state.currentIndex];
      }
      if (typeof object.radius_factor != 'undefined') {
        return object.radius_factor;
      }
      return 1;
    }

    /**
     * Get the base radius for the map.
     *
     * @returns {Number}
     *   The color.
     */
    getBaseRadius = function () {
      let state = this.state;
      let attrs = this.config.attrs;
      if (typeof state.options.base_radius != 'undefined') {
        return state.options.base_radius;
      }
      return attrs.base_radius;
    }

    /**
     * Calculate the radius for the given data point.
     *
     * @param {Object} object
     *   The data object.
     * @param {Number} base_radius
     *   The base radius.
     * @param {Number} radius_factor
     *   The radius factor.
     * @param {Number} min_radius
     *   The minimum radius.
     *
     * @returns {Number}
     *   The radius for the location.
     */
    getRadius = function (object, base_radius, radius_factor, min_radius) {
      var admin_level = typeof object.admin_level != 'undefined' ? object.admin_level : 1;
      if (typeof base_radius == 'undefined') {
        base_radius = this.getBaseRadius();
      }
      if (typeof radius_factor == 'undefined') {
        radius_factor = this.getRadiusFactor(object);
      }
      let radius = (base_radius + radius_factor) / admin_level;
      radius = (typeof min_radius != 'undefined') ? (radius > min_radius ? radius : min_radius) : radius;
      return radius;
    }

    /**
     * Get the location offset for a data point, depending on the offset chain.
     *
     * @param {Object} object
     *   The data object.
     * @param {ghi.mapState} state
     *   The map state.
     *
     * @returns {Number}
     *   The offset for the location.
     */
    getLocationOffset = function (object) {
      let offset = 0;
      let offset_chain = object.hasOwnProperty('offset_chain') ? object.offset_chain : [];
      if (offset_chain.length <= 1) {
        return 0;
      }
      for (var object_id of offset_chain) {
        let factor = offset_chain.indexOf(object_id) == 0 || object_id == object.object_id ? 1 : 2;
        offset += (this.getRadius(this.state.getLocationById(object_id)) * factor) + (1 * factor);
      }
      return offset;
    }

    /**
     * Get the tooltip content for the given object.
     *
     * @param {Object} object
     *   The location data object.
     */
    getTooltipContent = function (object) {
      let state = this.state;
      let tooltip = object.hasOwnProperty('tooltip') ? object.tooltip : null;
      if (tooltip === null) {
        tooltip = '<b>Location:</b> ' + object.location_name;
        if (typeof state.getData().hasOwnProperty('metric')) {
          tooltip += '<br /><b>Total ' + state.getData().metric.name.en.toLowerCase() + ':</b> ' + Drupal.theme('number', object.total);
        }
      }
      let index = state.getCurrentIndex();
      if (object.hasOwnProperty('tooltip_values') && object.tooltip_values.hasOwnProperty(index) && object.hasOwnProperty(index)) {
        tooltip += '<br />' + object.tooltip_values[index].label + ': ' + object.tooltip_values[index]['value'];
      }
      return tooltip;
    }

    /**
     * Check if the given feature is currently hidden.
     *
     * @param {Object} feature
     *   The feature to check.
     *
     * @returns {Boolean}
     *   TRUE if the feature is marked as hidden, FALSE otherwise.
     */
    isHidden = function (feature) {
      return feature.state.hidden ?? false;
    }

  }

})(jQuery);
