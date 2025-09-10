(function ($) {

  'use strict';

  const root_styles = getComputedStyle(document.documentElement);

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
        colors: this.interpolateColors("rgb(255, 255, 255)", this.convertToRGB(root_styles.getPropertyValue('--ghi-widget-color--dark')), 6),
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
      let geojson_features = state.getLocations().map(item => state.getMapController().getGeoJSON(item, (feature, location) => {
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
      return {
        'id': this.featureLayerId,
        'type': 'fill',
        'source': this.sourceId, // reference the data source
        'layout': {},
        'paint': {
          'fill-color': {
            'property': 'object_count',
            'stops': this.getFillColors(),
          },
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
      let backgroundLayer = state.getBackgroundLayer(map);

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
        'layout': {
          'symbol-sort-key': ['get', 'sort_order'],
          'text-field': ['get', 'location_name'],
          'text-font': backgroundLayer.layout['text-font'],
          'text-letter-spacing': backgroundLayer.layout['text-letter-spacing'],
          'text-size': [
            'interpolate',
            ['linear'],
            ['zoom'],
            3,
            8,
            7,
            20
          ],
          'text-anchor': 'center',
        },
        'paint': {
          'text-color': 'black',
          'text-halo-color': backgroundLayer.paint['text-halo-color'],
          'text-halo-width': 0.5,
        },
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
        map.getCanvas().style.cursor = 'pointer';
        state.hoverFeature(feature);
        state.showTooltip(this.getTooltipContent(state.getLocationFromFeature(feature)));
      });

      map.on('mousemove', layer_id, (e) => {
        let feature = state.getFeatureFromEvent(e);
        if (!feature) {
          return;
        }
        if (!state.isHovered(feature)) {
          // Update the hover if changed.
          map.getCanvas().style.cursor = 'pointer';
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
     * Create or update the legend items.
     */
    updateLegend = function() {
      let state = this.state;
      let ranges = this.getDataRanges();
      let $legend_container = state.getContainer().find('.map-legend');
      var $legend = $('<ul>');
      let colors = this.config.colors;
      for (i in ranges) {
        let index = parseInt(i, 10);
        if (index == 0) {
          // Do not show the 0-range in the legend.
          continue;
        }
        let next_index = parseInt(i, 10) + 1;
        let min = ranges[index];
        var text = '';
        if (index == ranges.length - 1) {
          text = '≥ ' + min.toString();
        }
        else {
          let max = (ranges[next_index] - 1);
          text = min != max ? min.toString() + ' - ' + max.toString() : min.toString();
        }
        var $legend_item = $('<li>');
        var $legend_marker = $('<span>')
          .addClass('legend-marker')
          .css('background-color', colors[index]);
        $legend_item.append($legend_marker);
        $legend_item.append(text);
        $legend.append($legend_item);
      }
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
     * Get the fill colors.
     *
     * @returns {Array}
     *  An array relating a stop point in the data with a color to use.
     */
    getFillColors = function() {
      let ranges = this.getDataRanges();
      let colors = [];
      for (i in ranges) {
        colors.push([ranges[i], this.config.colors[i]]);
      }
      return colors;
    }

    /**
     * Get the data ranges for the current set of locations.
     *
     * @returns {Array}
     *   An array of stop values representing the range in data.
     */
    getDataRanges = function() {
      let state = this.state;
      let object_counts = state.getLocations().filter(function(d) {
        return d.admin_level == state.getAdminLevel();
      }).map(d => d.object_count);
      let max_count = Math.max.apply(Math, object_counts);
      let range_count = Math.min(5, max_count);

      // We have 4 steps between 0 and the max count.
      let range_step = max_count > 4 ? Math.floor((max_count - 1) / range_count + 1) : 1;

      // The first range is always 0.
      var ranges = [0];

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

    /**
     * Convert a color string to RGB.
     *
     * @param {String} color_string_hex
     *   The color string in hey notation.
     *
     * @returns {String}
     *   The color in RGB notation.
     */
    convertToRGB = function(color_string_hex) {
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
  }

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
    interpolateColor = function(color1, color2, factor = null) {
      if (factor === null) {
        factor = 0.5;
      }
      var result = color1.slice();
      for (var i = 0; i < 3; i++) {
        result[i] = Math.round(result[i] + factor * (color2[i] - color1[i]));
      }
      return result;
    };

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
    interpolateColors = function(color1, color2, steps) {
      var stepFactor = 1 / (steps - 1),
          interpolatedColorArray = [];

      color1 = color1.match(/\d+/g).map(Number);
      color2 = color2.match(/\d+/g).map(Number);

      for (var i = 0; i < steps; i++) {
        let rgb = this.interpolateColor(color1, color2, stepFactor * i);
        interpolatedColorArray.push("#" + ((1 << 24) + (rgb[0] << 16) + (rgb[1] << 8) + rgb[2]).toString(16).slice(1));
      }

      return interpolatedColorArray;
    }

  }

})(jQuery);