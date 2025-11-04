(function ($) {

  /**
   * Style plugin for choropleth maps.
   */

  'use strict';

  const root_styles = getComputedStyle(document.documentElement);
  const MAX_RANGES = 6;

  if (!window.ghi) {
    window.ghi = {};
  }

  /**
   * Constructor for the choropleth style object.
   *
   * @param {String} id
   *   The ID of the map container.
   * @param {Object} state
   *   The state object for the map.
   * @param {Object} options
   *   The options for the map style.
   */
  window.ghi.choroplethMap = class {

    constructor (map, state, options) {
      this.map = map;
      this.state = state;
      this.options = options;
      this.loaded = false;
      this.sourceId = state.getMapId();
      this.areaLabelSourceId = this.sourceId + '-admin-area-labels';
      this.featureLayerId = this.sourceId + '-fill';
      this.labelLayerId = this.sourceId + '-label';
      this.config = {
        feature_style: {
          weight: 1,
          opacity: 0.7,
          color: 'white',
          dashArray: '3',
        },
        feature_style_highlighted: {
          weight: 2,
          opacity: 1,
          color: '#026CB6',
          dashArray: '',
        },
        colors: map.interpolateColors("rgb(255, 255, 255)", map.convertToRGB(root_styles.getPropertyValue('--ghi-widget-color--dark')), MAX_RANGES + 1),
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

        let source = this.buildSource();
        if (!source) {
          return;
        }

        // Initial drawing of the areas.
        map.addSource(self.sourceId, source);
        map.addLayer(this.buildFillLayer(self.sourceId));
        map.addLayer(this.buildOutlineLayer(self.sourceId));
        map.on('click', self.featureLayerId, (e) => self.handleFeatureClick(e, self));

        // Add a layer for the labels, so that we can keep showing them on top
        // of colored admin area or country outlines.
        map.addLayer(state.buildLabelLayer(self.labelLayerId));

        // We also want to show the names of the admin areas as map labels.
        this.addAdminAreaLabels();

        // Add event handling.
        this.addEventListeners(self.sourceId);
      });
    }

    /**
     * Render the locations on the map.
     *
     * @returns {String}
     *   The map id.
     */
    renderLocations = function (duration) {
      if (!this.loaded) {
        return;
      }

      this.state.throbber?.show();

      setTimeout(() => {
        // Update the data.
        let features = this.buildFeatures();
        let data = this.state.buildFeatureCollection(features);
        this.state.getMap().getSource(this.sourceId).setData(data);

        // Also update the data for the point features used for the labels.
        let label_features = this.buildLabelFeatures();
        let label_data = this.state.buildFeatureCollection(label_features);
        this.state.getMap().getSource(this.areaLabelSourceId).setData(label_data);

        this.state.throbber?.hide();
      });
    }

    /**
     * Build the source feature.
     *
     * @returns {Object}
     *   The source feature.
     */
    buildSource = function () {
      let features = this.buildFeatures();
      let data = this.state.buildFeatureCollection(features);
      return this.state.buildGeoJsonSource(data);
    }

    /**
     * Build the features.
     *
     * @returns {Array}
     *   An array of feature objects.
     */
    buildFeatures = function () {
      let state = this.state;
      let geojson_features = state.getLocations(true, false).map(item => state.getMapController().getGeoJSON(item, (feature, location) => {
        feature.properties.object_count = location.object_count;
        feature.properties.admin_level = location.admin_level;
        feature.properties.sort_order = -1 * location.object_count;
        return feature;
      }, false)).filter(d => d);
      return geojson_features;
    }

    /**
     * Build the point features for the labels.
     *
     * @returns {Array}
     *   An array of feature objects.
     */
    buildLabelFeatures = function () {
      let state = this.state;
      return state.getLocations().map(object => object.feature ?? this.buildPointFeatureForObject(object));
    }

    /**
     * Build the fill layer feature.
     *
     * @param {String}
     *   The source id.
     *
     * @returns {Object}
     *   The fill layer feature.
     */
    buildFillLayer = function () {
      let color_steps = this.map.getFillColors(this.getDataRanges(), this.config.colors).flat();
      color_steps.shift();
      return {
        'id': this.featureLayerId,
        'type': 'fill',
        'source': this.sourceId, // reference the data source
        'layout': {},
        'paint': {
          'fill-color': [
            'step',
            ['get', 'object_count'],
          ].concat(color_steps),
          'fill-opacity': [
            'case',
            ['==', ['get', 'object_count'], 0],
            1,  // 1 opacity when no objects.
            ['boolean', ['feature-state', 'hover'], false],
            1,  // 1 opacity when hovering.
            ['boolean', ['feature-state', 'focus'], false],
            1,  // 1 opacity when focused.
            0.6 // Default opacity.
          ],
          'fill-opacity-transition': {
            'duration': 300,
            'delay': 0
          }
        }
      }
    }

    /**
     * Build the outline layer feature.
     *
     * @returns {Object}
     *   The outline layer feature.
     */
    buildOutlineLayer = function () {
      return {
        'id': this.sourceId + '-outline',
        'type': 'line',
        'source': this.sourceId,
        'layout': {},
        'paint': {
          'line-color': [
            'case',
            ['boolean', ['feature-state', 'hover'], false],
            root_styles.getPropertyValue('--ghi-grey--dark'), // 1 opacity when hovering.
            ['boolean', ['feature-state', 'focus'], false],
            root_styles.getPropertyValue('--ghi-grey--dark'),  // 1 opacity when focused.
            root_styles.getPropertyValue('--ghi-grey'), // default color.
          ],
          'line-width': 1,
          'line-offset': [
            'case',
            ['boolean', ['feature-state', 'hover'], false],
            1,  // 1 when hovering.
            ['boolean', ['feature-state', 'focus'], false],
            1,  // 1 when focused.
            0 // Default offset.
          ]
        }
      }
    }

    /**
     * Add admin area labels to the map.
     */
    addAdminAreaLabels = function () {
      let state = this.state;
      let map = state.getMap();

      let label_source_id = this.areaLabelSourceId;
      let backgroundLayer = state.getBackgroundLayer();

      // We need to add a new point data set to which the labels can be
      // attached. Otherwise we would end up with duplicated labels on higher
      // zoom levels due to https://github.com/mapbox/mapbox-gl-js/issues/5583.
      let data = state.buildFeatureCollection(this.buildLabelFeatures());
      map.addSource(label_source_id, state.buildGeoJsonSource(data));

      // Add a layer for the labels.
      map.addLayer({
        'id': label_source_id,
        'type': 'symbol',
        'source': label_source_id,
        'layout': Object.assign({}, state.getCommonTextProperties().layout, {
          'text-field': ['get', 'location_name'],
          'text-anchor': 'center',
        }),
        'paint': state.getCommonTextProperties().paint,
      });
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
    buildPointFeatureForObject = function (object) {
      return {
        'id': object.object_id,
        'type': 'Feature',
        'geometry': {
          'type': 'Point',
          'coordinates': [object.latLng[1], object.latLng[0]],
        },
        'properties': {
          'location_name': object.location_name,
          'sort_order': -1 * object.object_count,
        }
      };
    }

    /**
     * Click handler for the areas.
     *
     * @param {Event} e
     *   The event.
     * @param {ghi.choroplethMap} self
     *   The current style instance.
     */
    handleFeatureClick = function (e, self) {
      let feature = self.state.getFeatureFromEvent(e, self.featureLayerId)
      if (!feature) {
        return;
      }
      let object_id = feature.properties.object_id;
      let object = self.state.getLocationById(object_id);
      self.showSidebarForObject(object);
    }

    /**
     * Add event listeners.
     */
    addEventListeners = function () {
      let state = this.state;
      let map = state.getMap();
      let layer_id = this.featureLayerId;

      map.on('mouseenter', layer_id, (e) => {
        // Enable hover.
        let feature = state.getFeatureFromEvent(e);
        if (!feature) {
          return;
        }
        state.hoverFeature(feature);
        state.showTooltip(this.getTooltipContent(state.getLocationFromFeature(feature)));
      });

      map.on('mousemove', layer_id, (e) => {
        let feature = state.getFeatureFromEvent(e);
        if (!feature && state.getHoveredLocation()) {
          // Disable hover.
          state.resetHover();
          return;
        }
        if (feature && !state.isHovered(feature)) {
          // Update the hover if changed.
          state.hoverFeature(feature);
          state.showTooltip(this.getTooltipContent(state.getLocationFromFeature(feature)));
        }
      });
      map.on('mouseleave', layer_id, () => {
        // Disable hover.
        map.getCanvas().style.cursor = '';
        state.resetHover();
      });
    }

    /**
     * Update the legend items.
     */
    updateLegend = function($legend_container = null) {
      $legend_container = $legend_container ?? this.state.getContainer().find('div.map-legend');
      var $legend = this.state.createRangeLegend(this.getDataRanges(), this.config.colors);
      $legend_container.html($legend);
    }

    /**
     * Show a sidebar for the given object.
     *
     * @param {Object} object
     *   The location object.
     */
    showSidebarForObject = function (object) {
      let state = this.state;
      let modal_content = object.modal_content;
      let build = {
        location_data: modal_content,
        title_heading: modal_content.title_heading,
        title: modal_content.title,
        content: modal_content.content,
        template: [
          '<div class="title-heading">{title_heading}</div>',
          '<div class="title">{title}</div>',
          '<div class="content">{content}</div>',
        ].join(''),
      }

      state.sidebar?.show(object, build);

      let feature = state.getFeatureByObjectId(object.object_id);
      if (feature) {
        state.focusFeature(feature);
      }
    }

    /**
     * Get the data ranges for the current set of locations.
     *
     * @returns {Array}
     *   An array of stop values representing the range in data.
     */
    getDataRanges = function() {
      let state = this.state;
      let object_counts = state.getLocations(false, false).filter(function(d) {
        return d.admin_level == state.getAdminLevel();
      }).map(d => d.object_count);
      return this.map.getDataRanges(object_counts, MAX_RANGES);
    }

    /**
     * Get the tooltip content for the given object.
     *
     * @param {Object} object
     *   The location data object.
     */
    getTooltipContent = function (object) {
      return object.location_name + ' (' + object.object_count + ')';
    }

  }

})(jQuery);