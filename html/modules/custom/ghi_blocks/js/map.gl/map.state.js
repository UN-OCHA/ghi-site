(function ($) {

  'use strict';

  if (!window.ghi) {
    window.ghi = {};
  }

  /**
   * Define the map state class.
   */
  window.ghi.mapState = class {

    /**
     * Constructor for the map state object.
     *
     * @param {String} id
     *   The ID of the map container.
     * @param {Map} map
     *   A mapbox map object.
     * @param {ghi.map} mapController
     *   The map controller object.
     * @param {Object} data
     *   The data object for the map.
     * @param {Object} options
     *   The options object for the map state.
     */
    constructor (id, map, mapController, data, options) {
      this.id = id;
      this.mapController = mapController;
      this.data = typeof data != 'undefined' ? data : {};
      this.map = map;
      this.style = null;
      this.animationDuration = 500;
      this.legend = null;
      this.sidebar = null;
      this.options = options;
      this.disabled = false;
      this.variantId = null;
      this.currentIndex = null;
      this.hoverId = null;
      this.focusId = null;
      this.focusedLocation = null;
      this.tooltip = null;
      this.adminLevel = null;
      this.adminLevelControl = null;
      this.ready = false;

      // Chose the right admin level to start with.
      this.setAdminLevel();
    }

    setup = function (options) {
      // Init the legend.
      this.setLegend(new ghi.interactiveLegend(this));

      // Render what we have.
      this.updateMap();

      // Init the tabs.
      this.initTabs();

      // Init the sidebar.
      this.setSidebar(new ghi.sidebar(this));

      // Add admin level control.
      if (this.canSelectAdminLevel()) {
        this.adminLevelControl = new ghi.adminLevelControl(this);
        this.getMap().addControl(this.adminLevelControl);
      }

      // Add search box.
      if (this.canSearch()) {
        this.getMap().addControl(new ghi.searchControl(this, this.getSearchOptions()));
      }

      // Add disclaimer.
      if (typeof options.disclaimer != 'undefined') {
        var mapDisclaimer = document.createElement('div');
        mapDisclaimer.className = 'map-disclaimer';
        mapDisclaimer.textContent = options.disclaimer;
        this.getContainer().append(mapDisclaimer);
      }
      // map.addControl(new ghi.disclaimerControl(this, options.disclaimer));

      this.setIsReady();
    }

    /**
     * Get the mapbox map object.
     *
     * @returns {Object}
     *   The mapbox map object.
     */
    getMap = function () {
      return this.map;
    }

    /**
     * Get the map style for the given id.
     *
     * @param {Object} options
     *   The options object for the map style.
     * @param {Object} config
     *   A config object for the map style.
     *
     * @returns {*}
     *   A map style object.
     */
    getMapStyle = function (options, config) {
      if (this.style === null && typeof options != 'undefined') {
        if (options.style === 'circle') {
          this.style = new ghi.circleMap(this.getMapController(), this, options, config);
        }
        if (options.style === 'chloropleth') {
          this.style = new ghi.chloroplethMap(this.getMapController(), this, options, config);
        }
      }
      if ((typeof this.style['renderLocations']) != "function") {
        return null;
      }
      if ((typeof this.style['setup']) == "function") {
        // Let the style set itself up.
        this.style.setup();
      }
      return this.style;
    }

    /**
     * Get the map id.
     *
     * @returns {String}
     *   The map id.
     */
    getMapId = function () {
      return this.id;
    }

    /**
     * Get the main map instance.
     *
     * @returns {Object}
     *   The instance of the map handler that this state is attached to.
     */
    getMapController = function () {
      return this.mapController;
    }

    /**
     * Set the legend handler.
     *
     * @param {Object} legend
     *   The legend handler.
     */
    setLegend = function (legend) {
      this.legend = legend;
    }

    /**
     * Set the sidebar handler.
     *
     * @param {Object} sidebar
     *   The sidebar handler.
     */
    setSidebar = function (sidebar) {
      this.sidebar = sidebar;
    }

    /**
     * Set the ready property.
     *
     * @param {Boolean} value
     *   The value for the ready property.
     */
    setIsReady = function (value = true) {
      this.ready = value;
    }

    /**
     * Check if the map state is marked as ready.
     *
     * @returns {Boolean}
     *   TRUE if the state is marked as ready, FALSE otherwise.
     */
    isReady = function () {
      return this.ready === true;;
    }

    /**
     * Get the current data.
     *
     * @return {Object}
     *   A data object.
     */
    getData = function () {
      if (!this.hasMapTabs()) {
        return this.data;
      }
      if (this.currentIndex === null) {
        this.setCurrentIndex();
      }
      return this.getDataForIndex(this.currentIndex);
    }

    /**
     * Get the data for the given index.
     *
     * @param {String} index
     *   The index for which to retrieve the data.
     *
     * @return {Object}
     *   A data object.
     */
    getDataForIndex = function (index) {
      return this.data != null && typeof this.data[index] != 'undefined' ? this.data[index] : null;
    }

    /**
     * Get all location data, across all map tabs.
     *
     * @returns {Object}
     *   A map object with location data, keyed by object id.
     */
    getAllData = function () {
      if (!this.hasMapTabs()) {
        return this.data;
      }
      let data = {};
      Object.values(this.data).forEach((tab) => {
        Object.values(tab.locations).forEach((d) => {
          data[d.object_id] = d;
        });
      });
      return data;
    }

    /**
     * Get the options.
     *
     * @return {Object}
     *   An options object.
     */
    getOptions = function () {
      return this.options;
    }

    /**
     * Check if the map allows searching.
     *
     * @return {Boolean}
     *   TRUE if search is enabled, FALSE otherwise.
     */
    canSearch = function () {
      let options = this.getOptions();
      return typeof options.search_enabled != 'undefined' && options.search_enabled == true;
    }

    /**
     * Get the search options.
     *
     * @returns {Object}
     *   An object with search options.
     */
    getSearchOptions = function () {
      let options = this.getOptions();
      return typeof options.search_options != 'undefined' ? options.search_options : {};
    }

    /**
     * Check if the map allows selection of different admin levels.
     *
     * @return {Boolean}
     *   TRUE if the admin level can be selected, FALSE otherwise.
     */
    canSelectAdminLevel = function () {
      let options = this.getOptions();
      return typeof options.admin_level_selector != 'undefined' && options.admin_level_selector == true;
    }

    /**
     * Set the current admin level.
     *
     * @param {Number} admin_level
     *   The new admin level.
     * @returns
     */
    setAdminLevel = function (admin_level = null) {
      if (!admin_level) {
        admin_level = Math.min(...this.getAdminLevelOptions());
      }
      if (this.adminLevel == admin_level) {
        return;
      }
      this.adminLevel = admin_level;
      if (this.isReady() && this.canSelectAdminLevel()) {
        this.updateMap(this.animationDuration, true);
        this.adminLevelControl.updateControl(admin_level);
        if (this.sidebar?.isVisible()) {
          this.sidebar.hide();
        }
      }
    }

    /**
     * Get the admin level.
     *
     * @returns int
     *   The admin level.
     */
    getAdminLevel = function () {
      return this.adminLevel;
    }

    /**
     * Get the admin level options.
     *
     * @returns {Array}
     *   An array of sequential numbers for the admin level.
     */
    getAdminLevelOptions = function () {
      let data = this.getData();
      let locations = typeof data.locations != 'undefined' ? data.locations : [];
      let locations_admin_level = locations.map(function (item) { return item.admin_level; });
      // Create an array with unique values. Sort it, because the order of the
      // locations is not guaranteed to be in the order of their admin level.
      return [...new Set(locations_admin_level)].sort();
    }

    /**
     * Check if the current map is an overview map.
     *
     * @returns {Boolean}
     *   TRUE if it's an overview map, FALSE otherwise.
     */
    isOverviewMap = function () {
      return this.getMapId().indexOf('plan-overview-map') === 0;
    }

    /**
     * Check if the maps should show country outlines when available.
     *
     * @returns {Boolean}
     *   TRUE if country outlines should be shown, FALSE otherwise.
     */
    shouldShowCountryOutlines = function () {
      return this.isOverviewMap() && (this.getOptions().global_config?.country_outlines ?? false);
    }

    /**
     * Get the active data.
     *
     * @param {Boolean} filter_by_admin_level
     *   Whether to filter by the current admin level.
     *
     * @return {Array}
     *   An array of location data objects.
     */
    getLocations = function (filter_by_admin_level = true) {
      let data = this.getData();
      let locations = typeof data.locations != 'undefined' ? data.locations : [];

      let index = this.getCurrentIndex();
      let variant_id = this.getVariantId();
      if (variant_id && this.hasVariant(this.getCurrentIndex(), variant_id)) {
        let variant = data.variants[variant_id];
        locations = Object.values(variant.locations);
      }

      // Optionally filter by admin level.
      if (filter_by_admin_level && this.canSelectAdminLevel()) {
        let admin_level = this.getAdminLevel();
        locations = locations.filter((d) => d.admin_level == admin_level);
      }

      // Sort alphabetically to get a defined order.
      locations.sort(function(a, b) {
        var a_name = a.hasOwnProperty('sort_key') ? a.sort_key.toLowerCase() : a.location_name.toLowerCase();
        var b_name = b.hasOwnProperty('sort_key') ? b.sort_key.toLowerCase() : b.location_name.toLowerCase();
        return a_name.localeCompare(b_name, undefined, {numeric: true, sensitivity: 'base'});
      });

      locations = locations.map(function (d) {
        d.index = index;
        d.variant_id = variant_id;
        return d;
      });
      return locations;
    }

    /**
     * Get the locations keyed by the object id.
     *
     * @param {Boolean} filter_by_admin_level
     *   Whether to filter by the current admin level.
     *
     * @returns {Object}
     *   A map object with locations keyed by their object id.
     */
    getLocationsKeyed = function (filter_by_admin_level = true) {
      let locations = {};
      for (let location of this.getLocations(filter_by_admin_level)) {
        locations[location.object_id] = location;
      }
      return locations;
    }

    /**
     * Get a location object by the given object id.
     *
     * @param {Number} object_id
     *   The id of the data object.
     * @param {Boolean} filter_by_admin_level
     *   Whether to filter by the current admin level.
     *
     * @returns {Object}|null
     *   The data object or NULL.
     */
    getLocationById = function (object_id, filter_by_admin_level = true) {
      return this.getLocationsKeyed(filter_by_admin_level)[object_id] ?? null;
    }

    /**
     * Get a location object from the given feature.
     *
     * @param {Object} feature
     *   The feature.
     *
     * @returns {Object}|null
     *   The data object or NULL.
     */
    getLocationFromFeature = function (feature) {
      let object_id = feature.properties.object_id ?? null;
      return object_id ? this.getLocationById(object_id) : null;
    }

    /**
     * Check if there are map tabs.
     *
     * @returns {Boolean}
     *   TRUE if the map has tabs, FALSE otherwise.
     */
    hasMapTabs = function () {
      let $map_tabs = this.getContainer().find('.map-tabs');
      return $map_tabs.length > 0;
    }

    /**
     * Set the current index.
     *
     * @param {String} index
     *   The index of the currently active map tab.
     */
    setCurrentIndex = function (index) {
      if (!this.hasMapTabs()) {
        this.currentIndex = null;
        return;
      }
      let $map_tabs = this.getContainer().find('.map-tabs');
      if (typeof index == 'undefined') {
        if ($map_tabs.find('a').length) {
          // The first tab is the active one by default.
          index = $map_tabs.find('a').first().data('map-index');
        }
        else {
          // There are no tabs. Just grab the first item in data and set that as
          // active.
          index = this.data ? Object.getOwnPropertyNames(this.data)[0] : 0;
        }
      }
      this.currentIndex = index ?? 0;
      $map_tabs.find('ul > li').removeClass('active');
      $map_tabs.find('li a[data-map-index="' + index + '"]').parent('li').addClass('active');
    }

    /**
     * Get the current index.
     *
     * @returns {String}
     *   The index of the currently active map tab.
     */
    getCurrentIndex = function () {
      if (this.currentIndex === null && this.hasMapTabs()) {
        this.setCurrentIndex();
      }
      return this.currentIndex;
    }

    /**
     * Set the variant id.
     *
     * @param {Number} variant_id
     */
    setVariantId = function (index, variant_id) {
      if (!this.hasVariant(index, variant_id)) {
        return false;
      }
      this.currentIndex = index;
      this.variantId = variant_id;
    }

    /**
     * Get the variant id.
     *
     * @returns {Number}
     *   The variant id.
     */
    getVariantId = function () {
      return this.variantId;
    }

    /**
     * Check if the given data has a given variant.
     *
     * @param {String} variant_id
     *   A variant id.
     *
     * @returns {Boolean}
     *   TRUE if the current data has the given variant id, FALSE otherwise.
     */
    hasVariant = function (index, variant_id) {
      let data = this.getDataForIndex(index);
      return data && data.hasOwnProperty('variants') && Object.keys(data.variants).length > 0 && data.variants.hasOwnProperty(variant_id);
    }

    /**
     * Switch to a different map tab.
     *
     * @param {Number} index
     *   The index to switch to.
     */
    switchTab = function (index) {
      // See if we are actually switching tabs. It might also be the same tab
      // with a different variant.
      let new_tab = index != this.getCurrentIndex();

      // Set the new index.
      this.setCurrentIndex(index);

      // Check if the variant needs to be changed too.
      if (new_tab) {
        let $item = this.getContainer().find('.map-tabs a[data-map-index="' + index + '"]').parent('li');
        let $toggle = $item.find('.variant-toggle');
        let variant_dropdown = $item.find('.cd-dropdown');
        let default_variant_id = $toggle.data('variant-id') ?? ($(variant_dropdown).find('a:first-child').data('variant-id') ?? null);
        if (default_variant_id && this.hasVariant(index, default_variant_id)) {
          this.switchVariant(index, default_variant_id);
          return;
        }
      }

      // Update the map.
      this.updateMap(this.animationDuration, true);

      if (this.sidebar?.isVisible()) {
        // If we have an open popup, keep it open and update the content, or
        // close it if there is nothing to show.
        if (this.focusedLocation && this.getFeatureByObjectId(this.focusedLocation.object_id) && this.getLocationById(this.focusedLocation.object_id)) {
          this.style.showSidebarForObject(this.focusedLocation);
        }
        else {
          this.hideSidebar();
        }
      }
    }

    /**
     * Switch to a different variant of a map tab.
     *
     * @param {String} index
     *   The tab index.
     * @param {Number} variant_id
     *   The variant id.
     */
    switchVariant = function (index, variant_id) {
      if (this.setVariantId(index, variant_id) === false) {
        // The variant can't be set, so we abort.
        return;
      }

      let $item = this.getContainer().find('.map-tabs a[data-map-index="' + index + '"]').parent('li');
      let $toggle = $item.find('.variant-toggle');
      let variant = this.getDataForIndex(index).variants[variant_id];

      // Mark the variant tab as active.
      this.getContainer().find('.map-tabs div.cd-dropdown a').removeClass('active');
      $item.find('.cd-dropdown').find('a[data-variant-id="' + variant_id + '"]').addClass('active');

      // Update the dropdown label.
      $toggle.find('button .ghi-dropdown__btn-label').html('#' + variant.tab_label);

      // Store the currently used variant id.
      $toggle.data('variant-id', variant_id);

      // And hand over to the general tab switching which updates the data in the map.
      this.switchTab(index);
    }

    /**
     * Show the given object in the sidebar.
     *
     * This is mainly used to access this from the mapbox controls, e.g. the
     * search control.
     *
     * @param {Object} object
     *   The location object to show in the sidebar.
     */
    showSidebarForObject = function (object) {
      let map = this.getMap();
      let style = this.style;

      // See if the admin level needs to be switched.
      if (this.getAdminLevel() == object.admin_level) {
        // No, so we can go straight to showing the sidebar.
        style.showSidebarForObject(object);
        return;
      }

      // Yes, so update the admin level.
      this.setAdminLevel(object.admin_level);

      // Already show the sidebar to prevent jumping around.
      style.showSidebarForObject(object);

      // And queue for refresh. Without waiting for the data to fully load,
      // the building of the sidebar navigation will fail because the
      // features are not yet on the map.
      let callback = function (e) {
        if (!e || e.isSourceLoaded) { style.showSidebarForObject(object); return }
        // Queue again if the source wasn't fully loaded yet.
        map.once('data', callback);
      }
      map.once('data', callback);
    }

    /**
     * Hide the sidebar.
     *
     * Also reset the focus.
     */
    hideSidebar = function () {
      this.sidebar.hide();
      this.resetFocus();
    }

    /**
     * Check if the given object is currently visible on the map.
     */
    objectIsVisible = function (object) {
      if (!object.hasOwnProperty('plan_type')) {
        return true;
      }
      return this.legend?.isHiddenType(object.plan_type) ? false : true;
    }

    /**
     * Get a feature from the given event.
     *
     * @param {Event} e
     *   The event.
     * @param {String} source_id
     *   The source id.
     *
     * @returns {Object}|null
     *   A feature object or NULL.
     */
    getFeatureFromEvent = function (e, source_id) {
      let self = this;
      let map = this.getMap();
      let is_circle_style = this.options.style == 'circle';

      let features = e.features.length ? e.features : map.queryRenderedFeatures(e.point, {layers: [source_id]});
      if (!features.length) {
        // No features found.
        return;
      }

      // Filter out all features that are not inside the bounding box for circles.
      if (is_circle_style) {
        // Process features and calculate the distance to the event point.
        features = features.map((d) => {
          d.properties.distance = self.calculateDistanceFromFeature(e, d);
          return d;
        }).filter((d) => d.properties.distance !== null && d.properties.distance <= d.properties.radius + 2);

        // Sort by ascending distance.
        features.sort(function(a, b) {
          return a.properties.distance - b.properties.distance;
        });
      }

      return features.length ? features[0] : null;
    }

    /**
     * Calculate the distance between the event position and the given feature.
     *
     * @param {Event} e
     *   The event, e.g. a mousemove.
     * @param {Object} feature
     *   A feature object.
     *
     * @returns {Number}
     *   The distance as a number.
     */
    calculateDistanceFromFeature = function (e, feature) {
      if (feature.geometry.type != 'Point' || feature.geometry.coordinates.length != 2) {
        return null;
      }
      let center = this.getMap().project(feature.geometry.coordinates);
      return Math.sqrt((e.point.x - center.x) ** 2 + (e.point.y - center.y) ** 2);
    }

    /**
     * Get a feature by its id.
     *
     * @param {Number} id
     *   The feature id to look up.
     * @param {String} layer_id
     *   Optional: Specify the layer id in which to look for the feature.
     * @param {String} source_id
     *   Optional: Specify the source id in which to look for the feature.
     *
     * @returns {Object}|null
     *   A feature object or NULL.
     */
    getFeatureById = function (id, layer_id = null, source_id = null) {
      layer_id = layer_id ?? this.style.getFeatureLayerId();
      source_id = source_id ?? this.getMapId();
      let features = this.querySourceFeatures(layer_id, source_id, ["==", ["id"], id]);
      return features.length ? features[0] : null;
    }

    /**
     * Get the feature for the object.
     *
     * @param {Object} object
     *   The object to look up.
     * @param {String} layer_id
     *   Optional: Specify the layer id in which to look for the feature.
     *
     * @returns {Object}|null
     *   A feature object or NULL.
     */
    getFeatureByObject = function (object, layer_id = null) {
      let object_id = object.object_id ?? null;
      return object_id ? this.getFeatureByObjectId(object_id) : null;
    }

    /**
     * Get a feature by the object id.
     *
     * @param {Number} object_id
     *   The object id to look up.
     * @param {String} layer_id
     *   Optional: Specify the layer id in which to look for the feature.
     *
     * @returns {Object}|null
     *   A feature object or NULL.
     */
    getFeatureByObjectId = function (object_id, layer_id = null) {
      layer_id = layer_id ?? this.style.getFeatureLayerId();
      let source_id = this.getMapId();
      let features = this.querySourceFeatures(layer_id, source_id, ["==", "object_id", object_id]);
      return features.length ? features[0] : null;
    }

    /**
     * Get all features from the source.
     *
     * @param {String} layer_id
     *   The id of the layer.
     * @param {String} source_id
     *   The id of the source.
     * @param {Object} filter
     *   An optional filter object.
     *
     * @returns {Array}
     *   An array of feature objects.
     */
    querySourceFeatures = function (layer_id, source_id, filter = null, unique = true) {
      let options = {
        sourceLayer: layer_id,
      };
      if (filter !== null) {
        options.filter = filter;
      }
      let features = this.getMap().querySourceFeatures(source_id, options);
      return unique ? this.getUniqueFeatures(features, 'object_id') : features;
    }

    /**
     * Get the unique features from the given set.
     *
     * Because features come from tiled vector data, feature geometries may be
     * split or duplicated across tile boundaries. As a result, features may
     * appear multiple times in query results.
     *
     * Taken from https://docs.mapbox.com/mapbox-gl-js/example/query-similar-features/
     *
     * @param {Array} features
     *   An array of features.
     * @param {*} comparatorProperty
     *   The property to compare against.
     *
     * @returns {Array}
     *   The unique set of features.
     */
    getUniqueFeatures = function (features, comparatorProperty) {
      const uniqueIds = new Set();
      const uniqueFeatures = [];
      features.forEach(feature => {
        const id = feature.properties[comparatorProperty];
        if (!uniqueIds.has(id)) {
          uniqueIds.add(id);
          uniqueFeatures.push(feature);
        }
      });
      return uniqueFeatures;
    }

    /**
     * Set a value on a feature state property.
     *
     * @param {Number} id
     *   The id of the feature.
     * @param {String} property
     *   The name of the property to set.
     * @param {*} value
     *   The value to set.
     */
    setFeatureState = function (id, property, value) {
      let source_id = this.getMapId();
      let map = this.getMap();
      let values = {};
      values[property] = value;
      map.setFeatureState(
        { source: source_id, id: id },
        values
      );
      let feature = this.getFeatureById(id, source_id);
      if (feature) {
        if (this.shouldShowCountryOutlines()) {
          let geojson_source_id = source_id + '-geojson';
          let location = this.getLocationById(feature.properties.object_id);
          let highlight_countries = location?.highlight_countries;
          let filter = highlight_countries ? ['in', ['get', 'location_id'], ['literal', location.highlight_countries]] : null;
          let geojson_features = this.querySourceFeatures(geojson_source_id, geojson_source_id, filter);
          geojson_features.forEach(item => {
            map.setFeatureState(
              { source: geojson_source_id, id: item.id },
              values
            );
          });
        }
        else {
          let geojson_source_id = source_id + '-geojson';
          let geojson_feature = this.getFeatureById(id, geojson_source_id + '-fill', geojson_source_id);
          if (geojson_feature) {
            map.setFeatureState(
              { source: geojson_source_id, id: geojson_feature.id },
              values
            );
          }
        }
      }
    }

    /**
     * Get the value on a feature state property.
     *
     * @param {Number} id
     *   The id of the feature.
     * @param {String} property
     *   The name of the property to get.
     */
    getFeatureState = function (id, property) {
      let source_id = this.getMapId();
      let map = this.getMap();
      let values = map.getFeatureState(
        { source: source_id, id: id },
      );
      return values && values.hasOwnProperty(property) ? values[property] : null;
    }

    /**
     * Set the hover property on a feature.
     *
     * @param {Object} feature
     *   A feature object.
     * @param {*} hover_state
     *   The hover state to set.
     */
    hoverFeature = function (feature, hover_state = true) {
      if (hover_state === true && this.hoverId !== null) {
        // Disable hover on previous feature.
        this.setFeatureState(this.hoverId, 'hover', false);
      }
      // Update the cursor.
      this.getMap().getCanvas().style.cursor = hover_state ? 'pointer' : '';

      this.hoverId = hover_state ? feature.id : null;
      this.setFeatureState(feature.id, 'hover', hover_state);
      let event = this.hoverId !== null ? 'focus-feature' : 'reset-focus';
      this.getCanvasContainer().trigger(event, [feature]);
    }

    /**
     * Check if the given feature is currently hovered over.
     *
     * @param {Object} feature
     *   The feature to check.
     *
     * @returns {Boolean}
     *   TRUE if currently hovered, FALSE otherwise.
     */
    isHovered = function (feature = null) {
      return this.hoverId !== null && (!feature || feature.id == this.hoverId);
    }

    /**
     * Get the hovered feature if any.
     *
     * @returns {Object}|null
     *   A feature object or null.
     */
    getHoverFeature = function () {
      if (this.hoverId === null) {
        return null;
      }
      return this.getFeatureById(this.hoverId);
    }

    /**
     * Reset the hover state on features.
     */
    resetHover = function () {
      if (this.hoverId === null) {
        return;
      }
      this.getMap().getCanvas().style.cursor = '';
      this.setFeatureState(this.hoverId, 'hover', false);
      this.hoverId = null;
      this.hideTooltip();
      this.getCanvasContainer().trigger('reset-focus');
    }

    /**
     * Focus a feature.
     *
     * @param {Object} feature
     *   The feature to focus.
     * @param {Boolean} focus_state
     *   The focus state to set.
     */
    focusFeature = function(feature, focus_state = true) {
      if (focus_state && this.focusId !== null) {
        // Unfocus previously focussed feature.
        this.setFeatureState(this.focusId, 'focus', false);
      }
      if (focus_state && this.hoverId !== null) {
        // Unhover any currently hovered feature.
        this.hoverFeature(feature, false);
      }
      this.setFeatureState(feature.id, 'focus', focus_state);
      this.focusId = focus_state ? feature.properties.object_id : null;
      this.focusedLocation = this.focusId ? this.getLocationById(this.focusId) : null;
      let event = this.focusId !== null ? 'focus-feature' : 'reset-focus';
      this.getCanvasContainer().trigger(event, [feature]);
    }

    /**
     * Get the focussed feature if any.
     *
     * @returns {Object}|null
     *   A feature object or null.
     */
    getFocusFeature = function () {
      if (this.focusedLocation === null) {
        return null;
      }
      return this.getFeatureByObject(this.focusedLocation);
    }

    /**
     * Reset the focus state if a feature is currently focussed.
     */
    resetFocus = function () {
      if (this.focusId === null) {
        return;
      }
      this.setFeatureState(this.focusId, 'focus', false);
      this.focusId = null;
      this.focusedLocation = null;
      this.getCanvasContainer().trigger('reset-focus');
    }

    /**
     * Update the features in the current map.
     *
     * @returns {Array}
     *   An array of feature objects.
     */
    updateFeatures = function (source_id, layer_id, build_callback, transition_callback, duration = null) {
      let locations = this.getLocations();
      let features = [];
      for (let object of locations) {
        features.push(build_callback(object));
      }
      let should_animate = duration && transition_callback;
      if (!should_animate) {
        return features;
      }
      return this.transitionFeatures(features, source_id, layer_id, transition_callback, duration);
    }

    /**
     * Handle the transition of features from an old state to a new state.
     *
     * @param {Array} new_features
     *   An array of featore objects.
     * @param {String} source_id
     *   The source id.
     * @param {String} layer_id
     *   The layer id.
     * @param {CallableFunction} transition_callback
     *   The transition callback.
     * @param {Number} duration
     *   The duration of the tranisition animation.
     * @returns
     */
    transitionFeatures = function (new_features, source_id, layer_id, transition_callback, duration) {
      let self = this;
      let existing_features = {};
      for (let feature of this.querySourceFeatures(layer_id, source_id)) {
        existing_features[feature.properties.object_id] = feature;
      }

      let animate_objects = {};
      let features = {};
      let locations = this.getLocationsKeyed();

      // First look at the new features and create animations as requested.
      for (let new_feature of new_features) {
        let object = locations[new_feature.properties.object_id];
        let old_feature = existing_features[object.object_id] ?? null;
        if (!old_feature) {
          old_feature = structuredClone(new_feature);
          old_feature.properties.radius = 0;
        }
        let object_id = object.object_id;
        animate_objects[object_id] = {
          'object_id': object_id,
          'old': old_feature,
          'new': new_feature,
          'object': object,
        };
        features[object_id] = old_feature;
      }

      // Animate features that will be removed.
      // @todo This is not currently working. Transitioning-in new features
      // works without issues, but transitioning out runs against a wall, the
      // features simply disappear. No idea why.
      if (Object.values(existing_features).length) {
        for (let old_feature of Object.values(existing_features)) {
          let object_id = old_feature.properties.object_id;
          if (features.hasOwnProperty(object_id)) {
            continue;
          }
          let new_feature = structuredClone(old_feature);
          new_feature.properties.radius = 0;
          animate_objects[object_id] = {
            'object_id': object_id,
            'old': old_feature,
            'new': new_feature,
            'object': this.getAllData()[object_id] ?? null,
          };
          features[object_id] = old_feature;
        }
      }

      if (Object.keys(animate_objects).length) {
        self.updateMapData(source_id, Object.values(features));

        // Create an animation.
        let end_time = null;
        function animateMarker(timestamp) {
          if (end_time === null) {
            end_time = timestamp + duration;
          }
          let transition_features = {};
          for (let d of Object.values(animate_objects)) {
            transition_features[d.object_id] = transition_callback(self, d, duration - (end_time - timestamp), duration, transition_features);
          }
          // Get the data and mark it as in a transition state.
          self.updateMapData(source_id, Object.values(transition_features), {'transition': true});

          // Request the next frame of the animation.
          if (timestamp < end_time) {
            requestAnimationFrame(animateMarker);
          }
          else {
            // Remove disappeared features.
            self.updateMapData(source_id, new_features);
          }
        }

        // Start the animation.
        requestAnimationFrame(animateMarker)
      }
      return Object.values(features);
    }

    /**
     * Offset the given coordinates by the given pixel offset.
     *
     * @param {Array} coordinates
     *   An array of coordinates in [lng, lat] format.
     * @param {Number} pixel_offset
     *   The offset in pixels
     *
     * @returns {Array}
     *   An array of coordinates in [lng, lat] format.
     */
    offsetCoordinates = function (coordinates, pixel_offset) {
      let map = this.getMap();
      let point = map.project(coordinates);
      point.x += pixel_offset;
      coordinates = map.unproject(point);
      return [coordinates.lng, coordinates.lat];
    }

    /**
     * Build the data object for a list of features.
     *
     * This is useful whenever we want to call setData() on a source object.
     *
     * @returns {Object}
     *   The geojson data object for the source.
     */
    buildFeatureCollection = function (features, properties = null) {
      return {
        'type': 'FeatureCollection',
        'features': features,
        'properties': properties,
        'generateId': false,
      };
    }

    /**
     * Build the source feature.
     *
     * @returns {Object}
     *   The source feature.
     */
    buildGeoJsonSource = function (data) {
      return {
        'type': 'geojson',
        'data': data,
        'generateId': false,
      };
    }

    /**
     * Show a tooltip
     *
     * @param {String} content
     *   The HTML string of the content to show.
     */
    showTooltip = function (content) {
      if (!this.getContainer().parent().find('.tooltip').length) {
        let tooltip = document.createElement('div');
        tooltip.className = 'tooltip';
        this.getContainer().parent().append(tooltip);
      }
      if (this.tooltip === null) {
        this.tooltip = tippy(this.getContainer().parent().find('.tooltip').get(0), {
          followCursor: true,
          allowHTML: true,
          arrow: false,
          offset: [15, 5],
          placement: 'bottom-start',
        });
      }
      this.tooltip.setContent(content);
      this.tooltip.show();
    }

    /**
     * Hide the tooltip.
     */
    hideTooltip = function () {
      this.tooltip.hide();
    }

    /**
     * See if the given data point is empty for this map state.
     *
     * @param {Object} object
     *   The location data object.
     *
     * @returns {Boolean}
     *   TRUE or FALSE.
     */
    emptyValueForCurrentTab = function (object) {
      if (typeof object == 'undefined') {
        return false;
      }
      if (typeof object.empty_tab_values == 'undefined') {
        return false;
      }
      return object.empty_tab_values[this.currentIndex];
    }

    /**
     * Get the map container.
     *
     * @returns {Object}
     */
    getContainer = function () {
      return $(this.getContainerClass());
    }

    /**
     * Get the map canvas container.
     *
     * @returns {Object}
     */
    getCanvasContainer = function () {
      return $(this.getContainerClass() + ' .mapboxgl-canvas-container');
    }

    /**
     * Get the container class.
     *
     * @return {String}
     *   A string to be used as a class for the container.
     */
    getContainerClass = function () {
      return '.map-wrapper-' + this.getMapId();
    }

    /**
     * Update the map.
     *
     * @param {Number} duration
     *   Optional: The duration to use for animations.
     */
    updateMap = function (duration = null) {
      let style = this.getMapStyle();
      if (!style) {
        return;
      }
      // Render what we have.
      style.renderLocations(duration);

      // Add the legend.
      style.updateLegend();
      this.legend?.setup();
    }

    /**
     * Update the data for the given source id.
     *
     * @param {String} source_id
     *   The source id.
     * @param {Array} features
     *   An array of features to set as the data for the given source.
     */
    updateMapData = function(source_id, features, properties = null) {
      this.getMap().getSource(source_id).setData(this.buildFeatureCollection(features, properties));
    }

    /**
     * Initialize the map tabs.
     */
    initTabs = function () {
      let self = this;

      // Add tab change behaviour.
      this.getContainer().find('.map-tabs a.map-tab').click(function (e) {
        if ($(this).parents('li').hasClass('active') && $(this).parent('li').find('button.ghi-dropdown__btn').length > 0) {
          // If a map tab is already active and there is a dropdown for variants,
          // open that instead.
          let dropdown_toggle = $(this).parent('li').find('button.ghi-dropdown__btn');
          if (dropdown_toggle) {
            e.stopPropagation();
            $(dropdown_toggle).click();
          }
        }
        else {
          self.switchTab($(this).data('map-index'));
        }
        e.preventDefault();
      });

      // Add variant change behaviour.
      $(this.getContainerClass() + ' .map-tabs div.cd-dropdown a').click(function (e) {
        let parent_index = $(this).parents('li').find('a.map-tab').data('map-index');
        self.switchVariant(parent_index, $(this).data('variant-id'));
        e.preventDefault();
      });
    }

  };

})(jQuery);
