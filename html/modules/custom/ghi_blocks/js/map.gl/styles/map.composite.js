(function ($) {

  /**
   * Style plugin for composite maps.
   *
   * The drawing of the donuts has been build based on examples from
   * https://docs.mapbox.com/mapbox-gl-js/example/cluster-html/.
   */

  'use strict';

  const root_styles = getComputedStyle(document.documentElement);
  const MAX_RANGES = 5;

  if (!window.ghi) {
    window.ghi = {};
  }

  /**
   * Constructor for the composite style object.
   *
   * @param {String} id
   *   The ID of the map container.
   * @param {Object} state
   *   The state object for the map.
   * @param {Object} options
   *   The options for the map style.
   */
  window.ghi.compositeMap = class {

    constructor (map, state, options) {
      this.map = map;
      this.state = state;
      this.options = options;
      this.loaded = false;
      this.sourceId = state.getMapId();
      this.featureLayerId = this.sourceId + '-composite';
      this.labelLayerId = this.sourceId + '-label';
      this.adminAreaSourceId = this.sourceId + '-geojson-source';
      this.adminAreaLayerId = this.sourceId + '-geojson';
      this.markers = {};
      this.markersOnScreen = {};
      this.config = {
        // Scales used to determine color.
        colors: {
          'full_pie': root_styles.getPropertyValue('--ghi-map-donut-full'),
          'slices': {
            0: root_styles.getPropertyValue('--ghi-map-donut-slice-1'),
            1: root_styles.getPropertyValue('--ghi-map-donut-slice-2'),
            2: root_styles.getPropertyValue('--ghi-map-donut-slice-3'),
          },
          'border': root_styles.getPropertyValue('--ghi-map-donut-border'),
          'legend_border': root_styles.getPropertyValue('--ghi-map-legend-outline'),
        },
        // patterns: {
        //   0: `<pattern id="slice-1" patternUnits="userSpaceOnUse" width="4" height="4">
        //     <path d="M-1,1 l2,-2
        //       M0,4 l4,-4
        //       M3,5 l2,-2"
        //     style="stroke:grey; stroke-width:1" />
        //   </pattern>`,
        // },
        polygon_colors: {
          0: "#FFFFFF",
          1: "#C5DFEF",
          2: "#64BDEA",
          3: "#009EDB",
          4: "#0074B7",
          5: "#002E6E",
        },
        attrs: {
          'stroke': '#fff',
          'cursor': 'pointer',
          'opacity': 0.3,
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

        // Add source and layer for the admin area outlines.
        map.addSource(this.adminAreaSourceId, this.state.buildGeoJsonSource(null));
        this.addAdminAreaLayers();
        this.buildAdminAreaFeatures();

        // Add a layer for the labels, so that we can keep showing them on top
        // of colored admin area or country outlines.
        map.addLayer(this.state.buildLabelLayer(this.labelLayerId));

        // Initial drawing of the donuts.
        map.addSource(self.sourceId, this.buildSource());

        // Show the names of the admin areas as map labels.
        this.addAdminAreaLabels();

        map.on('click', (e) => self.handleFeatureClick(e));

        // Add event handling.
        this.addEventListeners(self.sourceId);

        // Preload all geojson files asynchronously.
        state.getMapController().loadFeaturesAsync(this.getFullPieLocations(false, false), () => {}, state);
      });

    }

    /**
     * Render the locations on the map.
     *
     * @returns {String}
     *   The map id.
     */
    renderLocations = function (duration = null, full_reload = false) {
      if (!this.loaded) {
        return;
      }

      // Update the data.
      let features = this.updateFeatures(duration);
      this.updateMapData(this.sourceId, features);
      this.updateMarkers(features, true);

      if (full_reload) {
        this.addAdminAreaLayers();
        this.updateMapData(this.adminAreaSourceId, this.buildAdminAreaFeatures());
      }
    }

    /**
     * Build the source feature for the full pie.
     *
     * @returns {Object}
     *   The source feature.
     */
    buildSource = function () {
      let data = this.state.buildFeatureCollection(this.buildFullPieFeatures());
      return this.state.buildGeoJsonSource(data);
    }

    /**
     * Build the location features for the full pies.
     *
     * @returns {Array}
     *   An array of feature objects.
     */
    buildFullPieFeatures = function () {
      let full_pie_metric = this.state.getData().full_pie.metric_index;
      let locations = this.getFullPieLocations();
      let features = locations.map(object => object.feature ?? this.buildFeatureForObject(object, full_pie_metric));
      this.updateMarkers(features);
      return features;
    }

    /**
     * Get the location data for the full pie.
     *
     * @returns {Array}
     *   An array of processed location objects.
     */
    getFullPieLocations = function (filter_by_admin_level = true, filter_empty = true) {
      let locations = this.state.getBaseLocations();
      return this.state.processLocations(locations, filter_by_admin_level, filter_empty ? (locations) => this.filterEmptyLocations(locations) : false);
    }

    /**
     * Get the data for the slices.
     *
     * @returns {Array}
     *   An data object.
     */
    getSliceData = function () {
      return this.state.getData().slices ?? [];
    }

    /**
     * Get the location data for the slices.
     *
     * @returns {Array}
     *   An array of processed location objects.
     */
    getSliceLocations = function (filter_by_admin_level = true, filter_empty = true) {
      let state = this.state;
      let slices = this.getSliceData();
      let locations = state.getBaseLocations();
      return slices.map((slice, i) => this.state.processLocations(locations, filter_by_admin_level, filter_empty ? (locations) => this.filterEmptyLocations(locations) : false, {
        slice: i,
      }));
    }

    /**
     * Get the data for the polygons.
     *
     * @returns {Array}
     *   An data object.
     */
    getPolygonData = function () {
      return this.state.getData().polygon ?? null;
    }

    /**
     * Get the location data for the polygons.
     *
     * @returns {Array}
     *   An array of processed location objects.
     */
    getPolygonLocations = function (filter_by_admin_level = true, filter_empty = true) {
      let state = this.state;
      if (!this.showPolygons()) {
        return [];
      }
      let locations = state.getBaseLocations();
      return this.state.processLocations(locations, filter_by_admin_level, filter_empty ? (locations) => this.filterEmptyLocations(locations) : false);
    }

    filterEmptyLocations = function (locations) {
      let full_pie_metric = this.state.getData().full_pie.metric_index;
      return locations.filter((object) => object.metrics[full_pie_metric] > 0);
    }

    /**
     * Build the features for the admin area layer.
     *
     * @returns {Array}
     *   Returns an empty array as the features will be loaded
     *   asynchronously.
     */
    buildAdminAreaFeatures = function () {
      let self = this;
      let state = this.state;
      let polgon_data = this.getPolygonData();
      let locations = this.getFullPieLocations(true, false);
      let locations_keyed = {};
      for (let location of locations) {
        locations_keyed[location.object_id] = location;
      }

      // Build the admin area features and add them to the source, but do it
      // non-blocking.
      state.getMapController().loadFeaturesAsync(locations, (features) => {
        if (polgon_data) {
          for (let feature of features) {
            let feature_location_id = feature.properties.location_id;
            feature.properties.value = locations_keyed[feature_location_id].metrics[polgon_data.metric_index];
          }
        }
        self.updateMapData(self.adminAreaSourceId, features);
      }, state);
      return state.querySourceFeatures(self.adminAreaLayerId, self.adminAreaSourceId);
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
      let full_pie_metric = this.state.getData().full_pie.metric_index;
      let features = state.updateFeatures(this.sourceId, this.featureLayerId, (object) => object.feature ?? self.buildFeatureForObject(object, full_pie_metric));
      return features;
    }

    /**
     * Update the markers for the given set of features.
     *
     * @param {Array} features
     */
    updateMarkers = function (features, force_update = false) {
      const newMarkers = {};
      let self = this;
      let state = this.state;
      let slices = this.getSliceData();

      // Sort features by descending totals.
      features.sort(((a, b) => a.properties.total > b.properties.total ? -1 : 1));

      // for every cluster on the screen, create an HTML marker for it (if we didn't yet),
      // and add it to the map if it's not there already
      for (const feature of features) {
        let coordinates = feature.geometry.coordinates;
        const id = feature.id;

        // Not sure why these offsets work, but without these, the position of
        // the marker is slightly off compared to the circle layer using the same coordinates.
        let offset = [0, 3];

        let marker = this.markers[id];
        if (marker && force_update) {
          marker.remove();
          this.markersOnScreen[id] = false;
        }
        if (!marker || force_update) {
          marker = this.markers[id] = new mapboxgl.Marker({
            element: this.createDonutChart(feature, slices),
            offset: offset,
          }).setLngLat(coordinates);
        }
        newMarkers[id] = marker;

        feature.layer = {
          id: self.featureLayerId,
        };
        marker.getElement().addEventListener('mouseenter', function () {
          state.hoverFeature(feature);
          state.showTooltip(self.getTooltipContent(state.getLocationFromFeature(feature)));
        });
        marker.getElement().addEventListener('mousemove', function () {
          state.hoverFeature(feature);
          state.showTooltip(self.getTooltipContent(state.getLocationFromFeature(feature)));
        });
        marker.getElement().addEventListener('mouseleave', function () {
          state.resetHover();
        });
        marker.getElement().addEventListener('click', function () {
          self.handleFeatureClick(null, feature);
        });

        if (!this.markersOnScreen[id]) {
          marker.addTo(this.state.getMap());
        }
      }
      // For every marker we've added previously, remove those that are no
      // longer visible.
      for (const id in this.markersOnScreen) {
        if (!newMarkers[id]) {
          this.markersOnScreen[id].remove();
        }
      }
      this.markersOnScreen = newMarkers;
    }

    /**
     * Create a donut chart.
     *
     * @param {Object} feature
     *   The feature object for which to create the donut chart.
     *
     * @returns {Node}
     *  A DOM node object.
     */
    createDonutChart = function (feature, slices) {
      const object_id = feature.properties.object_id;
      const colors = this.config.colors.slices;
      const r = feature.properties.radius;
      const r0 = 0;
      const w = (r * 2) + 4;

      // Define the patterns.
      let patterns = {};
      for (const [i, pattern] of Object.entries(this.config.patterns ?? {})) {
        html += pattern;
        patterns[i] = 'url(#slice-1)';
      }

      // Create the donut container.
      let html = `<div class="donut donut-${object_id}"><svg width="${w}" height="${w}" viewbox="-2 -2 ${w} ${w}">`;

      // Add the full segement.
      html += `<circle cx="${r}" cy="${r}" r="${r}" fill="${feature.properties.color}" />`;

      // Create one segment per slice, each segments start at 0 because the
      // data should be overlayed.
      let i = 0;
      let max = 0;
      for (const slice of slices) {
        let slice_total = feature.properties.metrics[slice.metric_index];
        if (slice_total > 0) {
          let slice_size = slice_total < feature.properties.total ? slice_total / feature.properties.total : 1;
          html += this.createDonutSegment(0, slice_size, r, r0, 'slice-' + i, colors[i], patterns[i] ?? null);
          max = Math.max(max, slice_size);
        }
        i++;
      }

      // Create a circle to use as a border.
      let border_color = this.config.colors['border'];
      html += `<circle cx="${r}" cy="${r}" r="${r}" fill="transparent" stroke="${border_color}" stroke-width="1" />`;
      html += `</svg></div>`;

      const el = document.createElement('div');
      el.innerHTML = html;
      return el.firstChild;
    }

    /**
     * Create a legend icon.
     *
     * @param {Number} size
     *   The size of the donut segment between 0 and 1.
     * @param {String} color
     *   The color string to use as a fill color.
     * @param {String|Int|null} pattern_id
     *   The pattern id as defined in the config.
     *
     * @returns {Node}
     *   A DOM node object.
     */
    createLegendIcon = function (size, color, pattern_id = null) {
      const r = 12;
      const r0 = 0;
      const w = (r * 2) + 10;
      const h = (r * 2) + 4;
      let legend_border = this.config.colors['legend_border'];
      let donut_border = this.config.colors['border'];
      let full_pie = this.config.colors['full_pie'];
      let html = `<svg width="${w}" height="${h}" viewbox="-2 -2 ${w} ${h}">`;

      let pattern_url = null;
      if (pattern_id !== null && typeof this.config.patterns == 'object' && typeof this.config.patterns[pattern_id] != 'undefined') {
        html += this.config.patterns[pattern_id];
        pattern_url = 'url(#slice-' + (parseInt(pattern_id) + 1) + ')';
      }
      html += `<circle cx="${r}" cy="${r}" r="${r + 0.5}" fill="${full_pie}" />`;
      html += `<circle cx="${r}" cy="${r}" r="${r}" fill="transparent" stroke="${donut_border}" stroke-width="0.5" />`;
      html += this.createDonutSegment(0, size, r, r0, null, color, pattern_url, legend_border);
      html += `</svg>`;
      const el = document.createElement('div');
      el.innerHTML = html;
      return el.firstChild;
    }

    /**
     * Create a segment for a donut chart.
     *
     * @param {Number} start
     *   The start of the segment on a range from 0 to 1.
     * @param {Number} end
     *   The end of the segment on a range from 0 to 1.
     * @param {Number} r
     *   The outer radius in pixels.
     * @param {Number} r0
     *   The inner radius in pixels.
     * @param {String} fill
     *   The value for the fill attribute.
     * @param {String} pattern
     *   The value for the fill attribute for a pattern.
     * @param {String} border
     *   The value for the fill attribute.
     *
     * @returns {String}
     *   A string containing an SVG path.
    */
    createDonutSegment = function (start, end, r, r0, class_name = null, fill, pattern = null, border = null) {
      // end = 0.66;
      let full_pie = end - start === 1;
      if (full_pie) {
        end -= 0.00001;
      }
      const a0 = 2 * Math.PI * (start - 0.25);
      const a1 = 2 * Math.PI * (end - 0.25);
      const x0 = Math.cos(a0), y0 = Math.sin(a0);
      const x1 = Math.cos(a1), y1 = Math.sin(a1);
      const largeArc = end - start > 0.5 ? 1 : 0;

      // Draw an SVG path.
      let segment = `<path d="M ${r + r0 * x0} ${r + r0 * y0} L ${r + r * x0} ${r + r * y0}
        A ${r} ${r} 0 ${largeArc} 1 ${r + r * x1} ${r + r * y1} L ${r + r0 * x1} ${r + r0 * y1}
        A ${r0} ${r0} 0 ${largeArc} 0 ${r + r0 * x0} ${r + r0 * y0} Z" fill="${fill}" class="${class_name}" />`;
      if (pattern !== null) {
        segment += this.createDonutSegment(start, end, r, r0, class_name, pattern);
      }
      if (border !== null) {
        segment += this.createDonutSegment(start, end, r, r + 1, class_name, border);
        if (!full_pie) {
          segment += `<path d="M ${r} -1 L ${r} ${r}" fill="transparent" stroke-width="1" stroke="${border}" class="${class_name}" />`;
          segment += `<path d="M ${r} ${r} L ${r + r * x1} ${r + r * y1}" fill="transparent" stroke-width="1" stroke="${border}" class="${class_name}" />`;
        }
        segment += `<path d="M ${2 * r} ${r} L ${2 * r + 10} ${r}" fill="transparent" stroke-width="1" stroke="${border}" class="${class_name}" />`;
      }

      return segment;
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
    buildFeatureForObject = function (object, metric_index) {
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
          'total': object.metrics[metric_index],
          'metrics': object.metrics,
          // Paint properties.
          'radius': radius,
          'color': this.config.colors.full_pie,
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
     * Add layers for the outlines around the admin areas if available.
     */
    addAdminAreaLayers = function () {
      let state = this.state;
      let map = state.getMap();

      let geojson_source_id = this.adminAreaSourceId;
      let geojson_layer_id = this.adminAreaLayerId;

      // Fill the polygon.
      if (!map.getLayer(geojson_layer_id)) {
        map.addLayer({
          'id': geojson_layer_id,
          'type': 'fill',
          'source': geojson_source_id,
          'paint': {},
          'layout': {},
        });
      }
      // Add an outline around the polygon.
      if (!map.getLayer(geojson_layer_id + '-outline')) {
        map.addLayer({
          'id': geojson_layer_id + '-outline',
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

      let paint = {
        'fill-outline-color': root_styles.getPropertyValue('--cd-white')
      };
      if (this.showPolygons()) {
        let color_steps = this.map.getFillColors(this.getDataRanges(), this.config.polygon_colors).flat();
        color_steps.shift();
        Object.assign(paint, {
          'fill-color': [
            'step',
            ['get', 'value'],
          ].concat(color_steps),
          'fill-opacity': 1,
        });
      }
      else {
        Object.assign(paint, {
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
        });
      }
      for (const [property, value] of Object.entries(paint)) {
        map.setPaintProperty(geojson_layer_id, property, value);
      }
    }

    /**
     * Add admin area labels to the map.
     */
    addAdminAreaLabels = function () {
      let state = this.state;
      let map = state.getMap();

      let label_source_id = this.sourceId + '-labels';
      let backgroundLayer = state.getBackgroundLayer();
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
        'layout': Object.assign({}, state.getCommonTextProperties().layout, {
          'text-field': ['get', 'object_name'],
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
        }),
        'paint': state.getCommonTextProperties().paint,
      });
      // Add a layer with blocking symbols that overlap the donuts, so that
      // these can be used by mapbox for collision detection. Otherwhise the
      // labels would appear overlaying the donuts.
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
     * Update the data for the given source id.
     *
     * @param {String} source_id
     *   The source id.
     * @param {Array} features
     *   An array of features to set as the data for the given source.
     */
    updateMapData = function(source_id, features) {
      this.state.updateMapData(source_id, features);
      if (source_id == this.sourceId) {
        this.buildAdminAreaFeatures();
      }
    }

    /**
     * Update the legend items.
     */
    updateLegend = function($legend_container = null) {
      let self = this;
      let data = this.state.getData().full_pie;
      let options = this.state.getOptions();
      $legend_container = $legend_container ?? this.state.getContainer().find('div.map-legend');
      $legend_container.html('');

      if (this.showPolygons()) {
        let polygon_data = this.getPolygonData();
        let $polygon_legend = $('<div class="map-legend--inner polygon">');
        let $label = $('<div>')
          .text(polygon_data.metric.name.en)
          .addClass('label');
        $polygon_legend.append($label);
        $polygon_legend.append(this.state.createRangeLegend(this.getDataRanges(), this.config.polygon_colors));
        $legend_container.append($polygon_legend);
      }

      let $legend = $('<div class="map-legend--inner donut"><ul></ul></div>');

      var legend_items = [];
      legend_items.push({
        'label': data.metric.name.en,
        'type': 'full',
        'toggle_class': false,
        'icon': this.createLegendIcon(1, this.config.colors['full_pie']),
      });

      let sliceData = this.getSliceData();
      for (const [i, slice] of Object.entries(sliceData)) {
        legend_items.push({
          'label': slice.metric.name.en,
          'type': 'slice-' + (parseInt(i) + 1),
          'toggle_class': 'slice-' + i,
          'icon': this.createLegendIcon((0.25 * sliceData.length) - (0.25 * i), this.config.colors['slices'][i]),
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
        let $legend_icon = item.icon;
        let $legend_label = $('<div>')
          .text(item.label)
          .addClass('legend-label');
        let $legend_item = $('<li>')
          .addClass('legend-item')
          .attr('data-type', item.type)
          .append($legend_icon)
          .append($legend_label);

        if (item.toggle_class) {
          // Attach togglable behaviour so that slices can be hidden and shown.
          $legend_item.addClass('toggle');
          $legend_item.on('click', function () {
            $(this).toggleClass('inactive');
            $(this).parents('.mapboxgl-map').find('.donut .' + item.toggle_class).toggle();
          });
        }

        $legend.children('ul').append($legend_item);
      }
      $legend_container.append($legend);
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


      let monitoring_period = data.full_pie.monitoring_period ?? null;
      let build = {
        // location_data: object,
        title: object.title ?? object.location_name,
        // tag_line: location_data.tag_line ?? null,
        pcodes_enabled: this.options.pcodes_enabled ?? false,
        monitoring_period: monitoring_period ? '<div class="monitoring-period">' + monitoring_period + '</div>' : '',
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
      this.state.getMap().once('moveend', () => {
        let feature = state.getFeatureByObjectId(object_id);
        state.resetHover();
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
     * Callback for handling mouseenter events
     *
     * @param {Event} e
     *   The event.
     * @param {String|null} layer_id
     *   The layer id if different than the default one for donuts.
     */
    handleMouseEnter = function (e, layer_id = null) {
      let state = this.state;
      // Enable hover.
      let feature = state.getFeatureFromEvent(e, layer_id);
      if (!feature) {
        return;
      }
      if (this.isHidden(feature)) {
        state.resetHover();
        return;
      }

      state.hoverFeature(feature);
      state.showTooltip(this.getTooltipContent(state.getLocationFromFeature(feature)));
    }

    /**
     * Callback for handling mousemove events
     *
     * @param {Event} e
     *   The event.
     * @param {String|null} layer_id
     *   The layer id if different than the default one for donuts.
     */
    handleMouseMove = function (e, layer_id = null) {
      let state = this.state;
      let feature = state.getFeatureFromEvent(e, layer_id);
      if (!feature && state.getHoveredLocation()) {
        // Disable hover.
        state.resetHover();
        return;
      }
      if (feature && this.isHidden(feature)) {
        state.resetHover();
        return;
      }
      if (feature && !state.isHovered(feature)) {
        // Update the hover if changed.
        state.hoverFeature(feature);
        state.showTooltip(this.getTooltipContent(state.getLocationFromFeature(feature)));
      }
    }

    /**
     * Callback for handling mouseleave events
     *
     * @param {Event} e
     *   The event.
     * @param {String|null} layer_id
     *   The layer id if different than the default one for donuts.
     */
    handleMouseLeave = function (e, layer_id = null) {
      let state = this.state;
      let feature = state.getFeatureFromEvent(e, layer_id);
      if (feature) {
        state.hoverFeature(feature, false);
      }
      else {
        state.resetHover();
      }
    }

    /**
     * Click handler for the donuts or the polygons.
     *
     * @param {Event} e
     *   The event.
     * @param {Object} feature
     *   The event.
     */
    handleFeatureClick = function (e = null, feature = null) {
      if (e !== null) {
        let feature = null;
        // We only look at the geojson layer, as the markers are not on any
        // actual mapbox layer but drawn directly on the canvas with their own
        // event handlers attached.
        feature = this.state.getFeatureFromEvent(e, this.adminAreaLayerId);
      }
      if (!feature || this.isHidden(feature)) {
        return;
      }
      let object = this.state.getLocationFromFeature(feature);
      if (!object) {
        return;
      }
      this.showSidebarForObject(object);
    }

    /**
     * Add event listeners.
     */
    addEventListeners = function () {
      let self = this;
      let state = this.state;
      let map = state.getMap();

      map.on('mouseenter', self.adminAreaLayerId, (e) => {
        self.handleMouseEnter(e, self.adminAreaLayerId);
      });

      map.on('mousemove', self.adminAreaLayerId, (e) => {
        self.handleMouseMove(e, self.adminAreaLayerId);
      });

      map.on('mouseleave', self.adminAreaLayerId, (e) => {
        this.handleMouseLeave(e, self.adminAreaLayerId);
      });

      // React to focus changes. The focus changes themselves are done in
      // map.state.js and trigger events that we attach to.
      state.getCanvasContainer().on('hover-feature', function (event, feature) {
        $(state.getCanvasContainer()).find('.donut').removeClass('hover');
        $(state.getCanvasContainer()).find('.donut-' + feature.properties.object_id).addClass('hover');
      });
      state.getCanvasContainer().on('reset-hover', function () {
        $(state.getCanvasContainer()).find('.donut').removeClass('hover');
      });
      state.getCanvasContainer().on('focus-feature', function (event, feature) {
        $(state.getCanvasContainer()).find('.donut').removeClass('focus');
        $(state.getCanvasContainer()).find('.donut-' + feature.properties.object_id).addClass('focus');
      });
      state.getCanvasContainer().on('reset-focus', function () {
        $(state.getCanvasContainer()).find('.donut').removeClass('focus');
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
      return this.config.attrs.opacity;
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
     * Get the data ranges for the current set of locations.
     *
     * @returns {Array}
     *   An array of stop values representing the range in data.
     */
    getDataRanges = function() {
      let polygon_metric_index = this.getPolygonData().metric_index;
      let total = this.getPolygonLocations(true, false).map(d => d.metrics[polygon_metric_index]);
      return this.map.getDataRanges(total, MAX_RANGES);
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

        // Add values for the polygon.
        let polygonData = this.getPolygonData();
        if (polygonData) {
          let polygon_metric_index = polygonData.metric_index;
          tooltip += '<br /><b>' + polygonData.metric.name.en + ':</b> ' + Drupal.theme('number', object.metrics[polygon_metric_index]);
        }

        // Add values for the full pie.
        if (typeof state.getData().hasOwnProperty('full_pie')) {
          tooltip += '<br /><b>' + state.getData().full_pie.metric.name.en + ':</b> ' + Drupal.theme('number', object.total);
        }

        // Add values for the slices.
        let slices = this.getSliceData();
        for (const slice of slices) {
          let sliceValue = null;
          if (object.metrics[slice.metric_index] !== null) {
            sliceValue = Drupal.theme('number', object.metrics[slice.metric_index]);
          }
          tooltip += '<br /><b>' + slice.metric.name.en + ':</b> ' + (sliceValue ?? Drupal.t('No data'));
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
      let state = this.state;
      let location = state.getLocationFromFeature(feature);
      return location === null || location.total == 0;
    }

    /**
     * Check whether polygons are shown or not.
     */
    showPolygons = function () {
      return this.getPolygonData() !== null;
    }

  }

})(jQuery);
