(function ($) {

  'use strict';

  const root_styles = getComputedStyle(document.documentElement);

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
      this.throbber = null,
      this.options = options;
      this.disabled = false;
      this.variantId = null;
      this.currentIndex = null;
      this.hoveredLocation = null;
      this.focusId = null;
      this.focusedLocation = null;
      this.tooltip = null;
      this.adminLevel = null;
      this.adminLevelControl = null;
      this.searchControl = null;
      // Lazy map request caches store jQuery promises, not raw responses. That
      // lets a click/search retry attach to an in-flight request instead of
      // starting duplicate HTTP calls for the same slice or modal content.
      this.modalContentRequests = {};
      this.dataSliceRequests = {};
      this.ready = false;

      // Chose the right admin level to start with.
      this.setAdminLevel();
    }

    setup = function (options) {
      // Init the legend.
      this.setLegend(new ghi.interactiveLegend(this));

      // Init the legend.
      this.setThrobber(new ghi.throbber(this));

      // Render what we have.
      this.updateMap();

      // Init the tabs.
      this.initTabs();

      // Init the sidebar.
      this.setSidebar(new ghi.sidebar(this));

      // Add admin level control.
      if (this.canSelectAdminLevel()) {
        this.adminLevelControl = new ghi.adminLevelControl(this);
        this.getMap().addControl(this.adminLevelControl, options.admin_level_position);
      }

      // Add search box.
      if (this.canSearch()) {
        this.searchControl = new ghi.searchControl(this, this.getSearchOptions());
        this.getMap().addControl(this.searchControl);
      }

      // Add disclaimer.
      if (typeof options.disclaimer != 'undefined') {
        var mapDisclaimer = document.createElement('div');
        mapDisclaimer.className = 'map-disclaimer';
        mapDisclaimer.textContent = options.disclaimer;
        this.getContainer().append(mapDisclaimer);
      }

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
     * Destroy the map state and any runtime resources it owns.
     */
    destroy = function () {
      this.setIsReady(false);
      this.tooltip?.destroy?.();
      this.style?.destroy?.();
      this.legend?.destroy?.();
      this.sidebar?.destroy?.();
      this.throbber?.destroy?.();
      if (this.adminLevelControl) {
        this.getMap()?.removeControl(this.adminLevelControl);
        this.adminLevelControl = null;
      }
      if (this.searchControl) {
        this.getMap()?.removeControl(this.searchControl);
        this.searchControl = null;
      }
      this.getContainer().off('.mapTabs');
      this.getCanvasContainer().off();
      if (this.map && typeof this.map.remove == 'function') {
        this.map.remove();
      }
      this.map = null;
    }

    /**
     * Get the style class for a style id.
     *
     * @param {String} style
     *   The style id.
     *
     * @returns {Function|null}
     *   The style class, or NULL if none is registered.
     */
    getMapStyleClass = function (style) {
      return this.getMapController().getMapStyleClass(style);
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
        let styleClass = this.getMapStyleClass(options.style);
        if (styleClass) {
          this.style = new styleClass(this.getMapController(), this, options, config);
        }
      }
      if (this.style === null) {
        return null;
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
     * Set the legend handler.
     *
     * @param {Object} legend
     *   The legend handler.
     */
    setThrobber = function (throbber) {
      this.throbber = throbber;
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
    getData = function (tab = null) {
      if (!this.hasMapTabs()) {
        return this.data;
      }
      if (this.currentIndex === null) {
        this.setCurrentIndex();
      }
      return this.getDataForIndex(tab ?? this.currentIndex);
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
     * Get the base data for a map.
     */
    getBaseData = function (tab = null) {
      let data = this.getData(tab);
      if (!data) {
        return null;
      }
      if (data.hasOwnProperty('locations')) {
        return data;
      }
      for (let i in data) {
        if (typeof data[i] != 'object') {
          continue;
        }
        if (!data[i].hasOwnProperty('locations') || !data[i].hasOwnProperty('is_base_data') || !data[i].is_base_data) {
          continue;
        }
        return data[i];
      }
      return null;
    }

    /**
     * Get the base locations for a map.
     */
    getBaseLocations = function (tab = null) {
      let data = this.getBaseData(tab);
      return data?.locations ?? null;
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
      Object.keys(this.data).forEach((tab) => {
        let locations = this.getBaseLocations(tab) ?? [];
        Object.values(locations).forEach((d) => {
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

    /*
     * Lazy map terminology used below:
     *
     * A data slice is the locations/metric payload for one tab or variant. It
     * is enough to render circles, update relative sizing, and power search for
     * the active selection. A modal content request is narrower: it loads one
     * location's sidebar data inside that active slice. Both travel over JSON
     * fragment endpoints, but they are not interchangeable payload shapes.
     */

    /**
     * Check if modal contents can be lazy-loaded for this map.
     *
     * @returns {Boolean}
     *   TRUE if a modal content URL is available, FALSE otherwise.
     */
    hasLazyModalData = function () {
      return typeof this.getOptions().modal_data_url != 'undefined' && this.getOptions().modal_data_url !== null;
    }

    /**
     * Get the modal content endpoint for this map.
     *
     * @returns {String|null}
     *   The modal content URL, if available.
     */
    getLazyModalDataUrl = function () {
      return this.getOptions().modal_data_url ?? null;
    }

    /**
     * Check if data slices can be lazy-loaded for this map.
     *
     * @returns {Boolean}
     *   TRUE if a data slice URL is available, FALSE otherwise.
     */
    hasLazyDataSlices = function () {
      return typeof this.getOptions().slice_data_url != 'undefined' && this.getOptions().slice_data_url !== null;
    }

    /**
     * Get the data slice endpoint for this map.
     *
     * @returns {String|null}
     *   The data slice URL, if available.
     */
    getLazyDataSliceUrl = function () {
      return this.getOptions().slice_data_url ?? null;
    }

    /**
     * Get the target data object for a tab or variant.
     *
     * @param {String|null} index
     *   The current map tab index.
     * @param {String|null} variant_id
     *   The current variant id.
     *
     * @returns {Object|null}
     *   The data target, if available.
     */
    getDataSliceTarget = function (index = null, variant_id = null) {
      index = index ?? this.getCurrentIndex();
      let data = this.hasMapTabs() && index !== null ? this.getDataForIndex(index) : this.getData();
      if (!data) {
        return null;
      }
      if (variant_id) {
        return data.variants?.[variant_id] ?? null;
      }
      return data;
    }

    /**
     * Check if a tab or variant data slice has already been loaded.
     *
     * @param {String|null} index
     *   The current map tab index.
     * @param {String|null} variant_id
     *   The current variant id.
     *
     * @returns {Boolean}
     *   TRUE if the slice is available.
     */
    dataSliceIsLoaded = function (index = null, variant_id = null) {
      let target = this.getDataSliceTarget(index, variant_id);
      return target !== null && (!target.lazy || target.slice_loaded);
    }

    /**
     * Store a loaded data slice.
     *
     * @param {String|null} index
     *   The current map tab index.
     * @param {String|null} variant_id
     *   The current variant id.
     * @param {Object} data_slice
     *   The loaded data slice.
     */
    setDataSlice = function (index = null, variant_id = null, data_slice = {}) {
      index = index ?? this.getCurrentIndex();
      let data = this.hasMapTabs() && index !== null ? this.getDataForIndex(index) : this.getData();
      if (!data) {
        return;
      }
      if (variant_id) {
        // Variant shells are present in the initial payload so menus can be
        // rendered before the values arrive. Merge the fetched slice into that
        // shell to keep labels/tab metadata that were already sent.
        data.variants = data.variants ?? {};
        data.variants[variant_id] = Object.assign({}, data.variants[variant_id] ?? {}, data_slice);
        return;
      }
      Object.assign(data, data_slice);
    }

    /**
     * Load a tab or variant data slice when needed.
     *
     * @param {String|null} index
     *   The current map tab index.
     * @param {String|null} variant_id
     *   The current variant id.
     *
     * @returns {Object}
     *   A jQuery promise.
     */
    loadDataSlice = function (index = null, variant_id = null) {
      if (this.dataSliceIsLoaded(index, variant_id) || !this.hasLazyDataSlices()) {
        return $.Deferred().resolve(this.getDataSliceTarget(index, variant_id)).promise();
      }

      // The key matches the slice identity, not the endpoint URL. Attachment
      // and map identity are already fixed by the map options, while index and
      // variant are the only user-selectable parts of the slice.
      let request_key = [index ?? 'default', variant_id ?? 'base'].join(':');
      if (typeof this.dataSliceRequests[request_key] == 'undefined') {
        let deferred = $.Deferred();
        this.dataSliceRequests[request_key] = deferred.promise();
        this.getMapController().showThrobber(this);
        $.ajax({
          dataType: 'json',
          url: this.getLazyDataSliceUrl(),
          data: {
            data_index: index ?? 'default',
            variant_id: variant_id,
          },
          success: (response) => {
            // Store the slice in the same structure that the initial payload
            // uses. Styles/search controls can then read from getLocations()
            // without caring whether the data was eager or lazy.
            this.setDataSlice(index, variant_id, response);
            deferred.resolve(response);
          },
          error: () => {
            delete this.dataSliceRequests[request_key];
            deferred.reject();
          },
          complete: () => this.getMapController().hideThrobber(this)
        });
      }

      return this.dataSliceRequests[request_key];
    }

    /**
     * Get modal content for the given object from the current map data.
     *
     * @param {Object} object
     *   The location object.
     * @param {String|null} index
     *   The current map tab index.
     * @param {String|null} variant_id
     *   The current variant id.
     *
     * @returns {Object|null}
     *   The modal content if already available, or NULL otherwise.
     */
    getModalContent = function (object, index = null, variant_id = null) {
      index = index ?? object.index ?? this.getCurrentIndex();
      variant_id = variant_id ?? object.variant_id ?? this.getVariantId();

      let data = this.hasMapTabs() && index !== null ? this.getDataForIndex(index) : this.getData();
      if (!data) {
        return object.modal_contents ?? object.modal_content ?? null;
      }

      let object_id = parseInt(object.object_id ?? object.location_id ?? object.id);
      // Modal content may come from three generations of map payloads: the
      // active variant store, the base data store, or older/eager location
      // objects that still embed modal_content directly.
      if (variant_id && data.variants?.[variant_id]?.modal_contents?.[object_id]) {
        return data.variants[variant_id].modal_contents[object_id];
      }
      if (data.modal_contents?.[object_id]) {
        return data.modal_contents[object_id];
      }
      return object.modal_contents ?? object.modal_content ?? null;
    }

    /**
     * Persist modal content into the current map data.
     *
     * @param {String|Number} object_id
     *   The location object id.
     * @param {Object|null} modal_content
     *   The modal content to store.
     * @param {String|null} index
     *   The current map tab index.
     * @param {String|null} variant_id
     *   The current variant id.
     */
    setModalContent = function (object_id, modal_content, index = null, variant_id = null) {
      index = index ?? this.getCurrentIndex();
      let data = this.hasMapTabs() && index !== null ? this.getDataForIndex(index) : this.getData();
      if (!data || modal_content === null) {
        return;
      }

      object_id = String(object_id);
      if (variant_id && this.hasVariant(index, variant_id)) {
        data.variants[variant_id].modal_contents = data.variants[variant_id].modal_contents ?? {};
        data.variants[variant_id].modal_contents[object_id] = modal_content;
        return;
      }

      data.modal_contents = data.modal_contents ?? {};
      data.modal_contents[object_id] = modal_content;
    }

    /**
     * Load modal content lazily for the given object when needed.
     *
     * @param {Object} object
     *   The location object.
     *
     * @returns {Object}
     *   A jQuery promise.
     */
    loadModalContent = function (object) {
      let existing_modal_content = this.getModalContent(object);
      if (existing_modal_content || !this.hasLazyModalData()) {
        return $.Deferred().resolve(existing_modal_content).promise();
      }

      let index = object.index ?? this.getCurrentIndex();
      let variant_id = object.variant_id ?? this.getVariantId();
      let object_id = String(object.object_id ?? object.location_id ?? object.id);
      // Include object id in addition to the active slice identity. A modal
      // fragment is scoped to one location, so sharing by slice alone would
      // return the wrong sidebar content.
      let request_key = [index ?? 'default', variant_id ?? 'base', object_id].join(':');

      if (typeof this.modalContentRequests[request_key] == 'undefined') {
        let deferred = $.Deferred();
        this.modalContentRequests[request_key] = deferred.promise();
        this.getMapController().showThrobber(this);
        $.ajax({
          dataType: 'json',
          url: this.getLazyModalDataUrl(),
          data: {
            data_index: index ?? 'default',
            object_id: object_id,
            variant_id: variant_id,
          },
          success: (response) => {
            this.setModalContent(object_id, response, index, variant_id);
            deferred.resolve(response);
          },
          error: () => deferred.reject(),
          complete: () => this.getMapController().hideThrobber(this)
        });
      }

      return this.modalContentRequests[request_key];
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
      let admin_level_options = this.getAdminLevelOptions();
      let normalized_admin_level = parseInt(admin_level, 10);
      if (admin_level === null || Number.isNaN(normalized_admin_level) || admin_level_options.indexOf(normalized_admin_level) === -1) {
        // Pick the lowest admin level that has data for the current variant.
        // Object filters can remove all locations for the previously active
        // level, so stale control state must be normalized before repainting.
        normalized_admin_level = admin_level_options.length ? Math.min.apply(Math, admin_level_options) : null;
      }
      if (this.adminLevel === normalized_admin_level) {
        return;
      }
      this.adminLevel = normalized_admin_level;
      if (this.isReady() && this.canSelectAdminLevel()) {
        this.updateMap(this.animationDuration, true);
        this.adminLevelControl?.updateControl?.(normalized_admin_level);
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
      // Read from getLocations() rather than raw data so object-filter variants
      // only expose admin levels that still have visible, non-empty locations.
      let locations = this.getLocations(false);
      let locations_admin_level = locations.map(function (item) {
        return parseInt(item.admin_level, 10);
      }).filter(function (admin_level) {
        return !Number.isNaN(admin_level);
      });
      // Create an array with unique values. Sort it, because the order of the
      // locations is not guaranteed to be in the order of their admin level.
      return [...new Set(locations_admin_level)].sort(function (a, b) {
        return a - b;
      });
    }

    /**
     * Refresh the current map after data availability has changed.
     */
    refreshAdminLevelForCurrentData = function () {
      if (!this.canSelectAdminLevel()) {
        this.updateMap(this.animationDuration);
        return;
      }

      let admin_level_options = this.getAdminLevelOptions();
      let current_admin_level = parseInt(this.getAdminLevel(), 10);
      let next_admin_level = admin_level_options.indexOf(current_admin_level) !== -1 ? current_admin_level : null;
      if (next_admin_level === null && admin_level_options.length) {
        next_admin_level = Math.min.apply(Math, admin_level_options);
      }

      if (this.adminLevel !== next_admin_level) {
        this.setAdminLevel(next_admin_level);
        return;
      }

      this.updateMap(this.animationDuration);
      this.adminLevelControl?.updateControl?.(next_admin_level);
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
     * Check if the current map is a choropleth map.
     *
     * @returns {Boolean}
     *   TRUE if it's an choropleth map, FALSE otherwise.
     */
    isChoroplethMap = function () {
      return this.getMapId().indexOf('plan-operational-presence-map') === 0;
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
     * @param {Boolean} filter_empty
     *   Whether to filter empty locations.
     *
     * @return {Array}
     *   An array of location data objects.
     */
    getLocations = function (filter_by_admin_level = true, filter_empty = true) {
      let locations = this.getBaseLocations() ?? [];
      return this.processLocations(locations, filter_by_admin_level, filter_empty);
    }

    /**
     * Process an array of location data objects.
     *
     * @param {Array} locations
     *   The locations to process.
     * @param {Boolean} filter_by_admin_level
     *   Whether to filter by the current admin level.
     * @param {Boolean|Function} filter_empty
     *   Whether or how to filter empty locations.
     * @param {Object|null} properties
     *   Additional properties to assign to each location.
     *
     * @return {Array}
     *   An array of processed location data objects.
     */
    processLocations = function (locations, filter_by_admin_level = true, filter_empty = true, properties = null) {
      let data = this.getData();
      let index = this.getCurrentIndex();
      let variant_id = this.getVariantId();
      if (variant_id && this.hasVariant(this.getCurrentIndex(), variant_id)) {
        let variant = data.variants[variant_id];
        locations = Object.values(variant.locations);
      }
      locations = Array.isArray(locations) ? locations.slice() : Object.values(locations ?? {});

      // Optionally filter by admin level.
      if (filter_by_admin_level && this.canSelectAdminLevel()) {
        let admin_level = this.getAdminLevel();
        locations = locations.filter((d) => d.admin_level == admin_level);
      }

      // Sort alphabetically to get a defined order.
      locations.sort(function(a, b) {
        var a_name = a.hasOwnProperty('sort_key') ? a.sort_key.toLowerCase() : (a.location_name ?? a.name).toLowerCase();
        var b_name = b.hasOwnProperty('sort_key') ? b.sort_key.toLowerCase() : (b.location_name ?? b.name).toLowerCase();
        return a_name.localeCompare(b_name, undefined, {numeric: true, sensitivity: 'base'});
      });

      locations = locations.map(function (d) {
        let location = Object.assign({}, d);
        location.index = index;
        location.variant_id = variant_id;
        if (properties) {
          Object.assign(location, properties);
        }
        return location;
      });

      if (filter_empty !== false) {
        if (typeof filter_empty == 'function') {
          locations = filter_empty(locations);
        }
        else {
          locations = this.filterEmptyLocations(locations);
        }
      }

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
      return this.getMapController().keyArray(this.getLocations(filter_by_admin_level, false), 'object_id');
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
     * Filter out empty locations.
     *
     * @param {Array} locations
     *   An array of location data objects.
     * @return {Array}
     *   An array of location data objects.
     */
    filterEmptyLocations = function (locations) {
      if (this.isChoroplethMap()) {
        return locations.filter((object) => object.object_count > 0);
      }
      else if (!this.isOverviewMap()) {
        return locations.filter((object) => object.total > 0);
      }
      return locations;
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
      if (!variant_id) {
        this.currentIndex = this.hasMapTabs() ? index : null;
        this.variantId = null;
        return true;
      }
      if (!this.hasVariant(index, variant_id)) {
        return false;
      }
      this.currentIndex = this.hasMapTabs() ? index : null;
      this.variantId = variant_id;
      return true;
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
      let data = this.hasMapTabs() ? this.getDataForIndex(index) : this.getData();
      return data && data.hasOwnProperty('variants') && Object.keys(data.variants).length > 0 && data.variants.hasOwnProperty(variant_id);
    }

    /**
     * Build a normal map variant from compact object-filter data.
     *
     * @param {String|Number} variant_id
     *   The selected object id.
     *
     * @returns {Boolean}
     *   TRUE if the variant exists or could be built.
     */
    ensureObjectFilterVariant = function (variant_id) {
      if (!variant_id) {
        return true;
      }

      let data = this.getData();
      if (!data) {
        return false;
      }
      data.variants = data.variants || {};
      if (data.variants[variant_id]) {
        return true;
      }

      let filter_variant = data.object_filter_variants?.[variant_id];
      if (!filter_variant || !Array.isArray(filter_variant.location_ids)) {
        return false;
      }

      // Compact variants only store ids and modal content. Reuse the base
      // location objects so geometry paths, labels, and pcodes stay identical
      // between the unfiltered and filtered map states.
      let locations_by_id = {};
      (data.locations || []).forEach(function (location) {
        locations_by_id[String(location.object_id ?? location.location_id ?? location.id)] = location;
      });
      data.variants[variant_id] = {
        locations: filter_variant.location_ids.map(function (location_id) {
          let location = locations_by_id[String(location_id)] ?? null;
          return location ? Object.assign({}, location, {object_count: 1}) : null;
        }).filter(function (location) {
          return location !== null;
        }),
        modal_contents: filter_variant.modal_contents || {},
      };
      return data.variants[variant_id].locations.length > 0;
    }

    /**
     * Switch the active object-filter variant.
     *
     * @param {String|Number|null} variant_id
     *   The selected object id.
     *
     * @returns {Boolean}
     *   TRUE if the active variant could be set.
     */
    setObjectFilterVariant = function (variant_id) {
      if (!this.ensureObjectFilterVariant(variant_id)) {
        return false;
      }
      return this.setVariantId(null, variant_id || null);
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
      this.resetHover();

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

      this.loadDataSlice(index).done(() => {
        // Update the map.
        this.updateMap(this.animationDuration, true);

        if (this.sidebar?.isVisible()) {
          // If we have an open popup, keep it open and update the content, or
          // close it if there is nothing to show.
          let focused_location = this.focusedLocation ? this.getLocationById(this.focusedLocation.object_id) : null;
          let focused_feature = focused_location ? this.getFeatureByObjectId(focused_location.object_id) : null;
          let location_is_visible = this.isOverviewMap() || (focused_location && focused_location.total > 0);
          if (focused_location && focused_feature && location_is_visible) {
            // The active tab's location object carries the index and variant
            // used by getModalContent().
            this.focusedLocation = focused_location;
            this.showSidebarForObject(this.focusedLocation);
          }
          else {
            this.hideSidebar();
          }
        }
      });
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
      if (!this.hasVariant(index, variant_id)) {
        return;
      }

      this.loadDataSlice(index, variant_id).done(() => {
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
      });
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
      if (!this.getModalContent(object) && this.hasLazyModalData()) {
        this.loadModalContent(object).done(() => {
          if (this.getModalContent(object)) {
            this.showSidebarForObject(object);
          }
        });
        return;
      }

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
     * @param {String} layer_id
     *   The layer id.
     *
     * @returns {Object}|null
     *   A feature object or NULL.
     */
    getFeatureFromEvent = function (e, layer_id = null) {
      let self = this;
      let map = this.getMap();
      layer_id = layer_id ?? this.style.getFeatureLayerId();
      let features = e.features?.length ? e.features : map.queryRenderedFeatures(e.point, {layers: [layer_id]});
      if (!features.length) {
        // No features found.
        return;
      }

      // Filter out all features that are not inside the bounding box for circles.
      features = features.map((d) => {
        d.properties.distance = d.layer.type == 'circle' ? self.calculateDistanceFromFeature(e, d) : null;
        return d;
      }).filter((d) => d.properties.distance === null || d.properties.distance <= d.properties.radius + 2);

      // Sort by ascending distance.
      features.sort(function(a, b) {
        return a.properties.distance - b.properties.distance;
      });

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
     *   An optional filter object. This also supports filtering for a feature
     *   state under the states property.
     *
     * @returns {Array}
     *   An array of feature objects.
     */
    querySourceFeatures = function (layer_id, source_id, filter = null, unique = true) {
      let self = this;
      let options = {
        sourceLayer: layer_id,
      };
      let states = filter?.states ?? null;
      if (filter !== null) {
        delete filter.states;
      }
      if (filter !== null && Object.entries(filter).length > 0) {
        options.filter = filter;
      }
      let features = this.getMap().querySourceFeatures(source_id, options);
      if (states !== null && typeof states == 'object') {
        for (const [key, value] of Object.entries(states)) {
          features = features.filter((feature) => self.getFeatureState(feature.id, key, source_id) === value);
        }
      }
      return unique ? this.getUniqueFeatures(features, typeof unique == 'boolean' ? 'object_id' : unique) : features;
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
    setFeatureState = function (id, property, value, layer_id = null, source_id = null) {
      layer_id = layer_id ?? this.style.getFeatureLayerId();
      source_id = source_id ?? this.getMapId();
      let map = this.getMap();
      let values = {};
      values[property] = value;
      map.setFeatureState(
        { source: source_id, id: id },
        values
      );
      let feature = this.getFeatureById(id, layer_id, source_id);
      if (!feature) {
        return;
      }
      if (this.shouldShowCountryOutlines()) {
        let geojson_source_id = source_id + '-geojson';
        let location = this.getLocationById(feature.properties.object_id);
        let highlight_countries = location?.highlight_countries;
        let filter = highlight_countries ? ['in', ['get', 'location_id'], ['literal', highlight_countries]] : null;
        let geojson_features = this.querySourceFeatures(geojson_source_id, geojson_source_id, filter);
        geojson_features.forEach(item => {
          map.setFeatureState(
            { source: geojson_source_id, id: item.id },
            values
          );
        });
      }
      else {
        let geojson_source_id = this.style.adminAreaSourceId ?? source_id + '-geojson';
        let geojson_layer_id = this.style.adminAreaLayerId ?? source_id + '-geojson';
        let geojson_feature = this.getFeatureById(id, geojson_layer_id, geojson_source_id);
        if (geojson_feature) {
          map.setFeatureState(
            { source: geojson_source_id, id: geojson_feature.id },
            values
          );
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
    getFeatureState = function (id, property, source_id = null) {
      source_id = source_id ?? this.getMapId();
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
      let layer_id = feature.layer.id;
      if (hover_state === true && this.hoveredLocation !== null && !this.isHovered(feature)) {
        // Disable hover on previous feature.
        Object.keys(this.hoveredLocation.layers).forEach((_layer_id) => {
          let source_id = this.getMap().getLayer(_layer_id)?.source ?? feature.source;
          this.setFeatureState(this.hoveredLocation.layers[_layer_id], 'hover', false, _layer_id, source_id);
        });
      }
      // Update the cursor.
      this.getMap().getCanvas().style.cursor = hover_state ? 'pointer' : '';

      if (hover_state === true) {
        // Mark feature as hovered.
        if (this.hoveredLocation === null) {
          this.hoveredLocation = {
            object_id: feature.properties.object_id,
            layers: {},
          };
        }
        this.hoveredLocation.layers[layer_id] = feature.id;
      }
      else if (this.hoveredLocation && this.hoveredLocation.layers.hasOwnProperty(layer_id)) {
        delete this.hoveredLocation.layers[layer_id];
      }

      if (this.hoveredLocation && Object.keys(this.hoveredLocation.layers).length == 0) {
        this.resetHover();
      }

      this.setFeatureState(feature.id, 'hover', hover_state, feature.layer.id, feature.source);
      if (this.hoveredLocation === null) {
        this.getCanvasContainer().trigger('reset-hover', [feature]);
      }
      else if (!this.hoveredLocation.layers.hasOwnProperty(layer_id)) {
        this.getCanvasContainer().trigger('reset-hover', [feature]);
      }
      else {
        this.getCanvasContainer().trigger('hover-feature', [feature]);
      }
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
    isHovered = function (feature) {
      if (this.hoveredLocation === null || !feature) {
        return false;
      }
      if (this.hoveredLocation.object_id != feature.properties.object_id) {
        return false;
      }
      return this.hoveredLocation.layers[feature.layer.id] == feature.id;
    }

    /**
     * Get the hovered location if any.
     *
     * @returns {Object}|null
     *   A location object or null.
     */
    getHoveredLocation = function () {
      if (this.hoveredLocation === null) {
        return null;
      }
      return this.getLocationById(this.hoveredLocation.object_id);
    }

    /**
     * Get the hovered features if any.
     *
     * @returns {Object}|null
     *   A feature object or null.
     */
    getHoverFeature = function (layer_id) {
      if (this.hoveredLocation === null || !this.hoveredLocation.layers.hasOwnProperty(layer_id)) {
        return null;
      }
      return this.getFeatureById(this.hoveredLocation.layers[layer_id], layer_id);
    }

    /**
     * Reset the hover state on features.
     */
    resetHover = function () {
      if (this.hoveredLocation !== null && this.hoveredLocation.layers) {
        Object.keys(this.hoveredLocation.layers).forEach((layer_id) => {
          let source_id = this.getMap().getLayer(layer_id)?.source ?? null;
          this.setFeatureState(this.hoveredLocation.layers[layer_id], 'hover', false, layer_id, source_id);
        });
      }
      for (let layer of this.map.getStyle().layers) {
        if (!layer.hasOwnProperty('source')) {
          continue;
        }
        let features = this.querySourceFeatures(layer.id, layer.source, {'states': {'hover': true}});
        for (let feature of features) {
          this.setFeatureState(feature.id, 'hover', false, layer.id, layer.source);
        }
      }
      this.hoveredLocation = null;
      this.hideTooltip();
      this.getCanvasContainer().trigger('reset-hover');
      this.getMap().getCanvas().style.cursor = '';
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
        // Unfocus previously focused feature.
        this.setFeatureState(this.focusId, 'focus', false);
      }
      if (focus_state && this.hoveredLocation !== null) {
        // Unhover any currently hovered feature.
        this.resetHover();
      }
      this.setFeatureState(feature.id, 'focus', focus_state);
      this.focusId = focus_state ? feature.properties.object_id : null;
      this.focusedLocation = this.focusId ? this.getLocationById(this.focusId) : null;
      let event = this.focusId !== null ? 'focus-feature' : 'reset-focus';
      this.getCanvasContainer().trigger(event, [feature]);
    }

    /**
     * Get the focused feature if any.
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
     * Reset the focus state if a feature is currently focused.
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
      if (!content) {
        this.hideTooltip();
        return;
      }
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
      this.tooltip?.hide();
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
     * Get the background layer that holds the country labels.
     *
     * @returns {Layer}
     *   A mapbox layer object.
     */
    getBackgroundLayer = function () {
      let map = this.getMap();
      let zoom = map.getZoom();
      let layers = map.getStyle().layers;
      for (let layer of layers) {
        if (!layer.id) {
          continue;
        }
        let Layer = map.getLayer(layer.id)
        if (Layer["source-layer"] != "wrl_polbndp_int_ocha_fr_en_ar") {
          continue;
        }
        if (Layer.minzoom > zoom || Layer.maxzoom < zoom) {
          continue;
        }
        return Layer;
      }
    }

    /**
     * Hide a country label from the background layer.
     *
     * @param {String} country_label
     *   The name of the country for which to hide the label.
     */
    hideCountryLabelFromBackgroundLayer = function (country_label) {
      if (!country_label) {
        return;
      }
      let map = this.getMap();
      let backgroundLayer = this.getBackgroundLayer();

      let rule = ['!=', ['get', 'en_short'], country_label];
      let filters = map.getFilter(backgroundLayer.id);
      if (filters.findIndex((value, index) => typeof value == 'object' && value.toString() == rule.toString()) == -1) {
        filters.push(rule);
        map.setFilter(backgroundLayer.id, filters);
      }
    }

    /**
     * Get the common text properties for layout and paint.
     */
    getCommonTextProperties = function () {
      let backgroundLayer = this.getBackgroundLayer();
      return {
        'layout': {
          'symbol-sort-key': ['get', 'sort_order'],
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
        },
        'paint': {
          'text-color': root_styles.getPropertyValue('--ghi-map-admin-area-label-color'),
          'text-halo-color': backgroundLayer.paint['text-halo-color'],
          'text-halo-width': 0.5,
        }
      }
    }

    /**
     * Build the label layer.
     *
     * @returns {Object}
     *   A layer object.
     */
    buildLabelLayer = function (layer_id) {
      let map = this.getMap();
      let source_id = layer_id + '-source';
      map.addSource(source_id, this.buildGeoJsonSource(null));

      map.on('styledata', () => {
        this.updateLabelLayer(layer_id);
      });
      map.on('zoom', () => {
        this.updateLabelLayer(layer_id);
      });

      return {
        'id': layer_id,
        'type': 'symbol',
        'source': source_id,
        'layout': {
          'text-field': ['get', 'en_short'],
          'text-font': [
            'Roboto Regular',
            'Arial Unicode MS Regular'
          ],
          'text-transform': 'uppercase',
        },
        'paint': {
          'text-halo-width': 0
        },
      }
    }

    /**
     * Update the label layer.
     */
    updateLabelLayer = function (label_layer_id) {
      let map = this.getMap();

      let backgroundFeatures = [];
      let backgroundLayer = this.getBackgroundLayer();
      if (backgroundLayer) {
        backgroundFeatures = map.querySourceFeatures(backgroundLayer.source, {
          sourceLayer: backgroundLayer['source-layer'],
        });

        map.setFilter(label_layer_id, map.getFilter(backgroundLayer.id));

        map.setLayoutProperty(label_layer_id, 'text-field', backgroundLayer.layout['text-field']);
        map.setLayoutProperty(label_layer_id, 'text-font', backgroundLayer.layout['text-font']);
        map.setLayoutProperty(label_layer_id, 'text-letter-spacing', backgroundLayer.layout['text-letter-spacing']);
        map.setLayoutProperty(label_layer_id, 'text-size', backgroundLayer.layout['text-size']);
        map.setLayoutProperty(label_layer_id, 'text-transform', backgroundLayer.layout['text-transform']);

        map.setPaintProperty(label_layer_id, 'text-color', backgroundLayer.paint['text-color']);
        map.setPaintProperty(label_layer_id, 'text-halo-color', backgroundLayer.paint['text-halo-color']);
        map.setPaintProperty(label_layer_id, 'text-halo-width', backgroundLayer.paint['text-halo-width']);

        // Hide the label of the currently viewed country from the background
        // layer.
        this.hideCountryLabelFromBackgroundLayer(this.options?.outline_country?.location_name ?? null);
      }
      this.updateMapData(label_layer_id + '-source', backgroundFeatures);
    }

    /**
     * Update the map.
     *
     * @param {Number} duration
     *   Optional: The duration to use for animations.
     * @param {Boolean} full_reload
     *   Whether a full reload should happen.
     */
    updateMap = function (duration = null, full_reload = false) {
      let style = this.getMapStyle();
      if (!style) {
        return;
      }
      // Render what we have.
      style.renderLocations(duration, full_reload);

      // Update the legend.
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
    updateMapData = function (source_id, features, properties = null) {
      this.getMap().getSource(source_id).setData(this.buildFeatureCollection(features, properties));
    }

    /**
     * Create a range based legend.
     *
     * @param {Object} ranges
     *   The ranges to be used.
     * @param {Object} colors
     *   The colors to be used.
     *
     * @returns {Object}
     *   A jQuery node object.
     */
    createRangeLegend = function (ranges, colors) {
      var $legend = $('<ul>');
      for (let i in ranges) {
        let index = parseInt(i, 10);
        if (index == 0 && ranges.length > 1) {
          // Do not show the 0-range in the legend.
          continue;
        }
        let next_index = parseInt(i, 10) + 1;
        let min = ranges[index];
        var text = '';
        if (index == 0 && ranges.length == 1) {
          text = Drupal.theme('number', min);
        }
        else if (index == ranges.length - 1) {
          text = '>= ' + Drupal.theme('number', min);
        }
        else {
          let max = (ranges[next_index] - 1);
          text = min != max ? Drupal.theme('number', min) + ' - ' + Drupal.theme('number', max) : Drupal.theme('number', min);
        }
        var $legend_item = $('<li>');
        var $legend_marker = $('<span>')
          .addClass('legend-marker')
          .css('background-color', colors[index]);
        $legend_item.append($legend_marker);
        $legend_item.append(text);
        $legend.append($legend_item);
      }
      return $legend;
    }

    /**
     * Initialize the map tabs.
     */
    initTabs = function () {
      let self = this;

      // Add tab change behaviour.
      this.getContainer().find('.map-tabs a.map-tab').off('click.mapTabs').on('click.mapTabs', function (e) {
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
      $(this.getContainerClass() + ' .map-tabs div.cd-dropdown a').off('click.mapTabs').on('click.mapTabs', function (e) {
        let parent_index = $(this).parents('li').find('a.map-tab').data('map-index');
        self.switchVariant(parent_index, $(this).data('variant-id'));
        e.preventDefault();
      });
    }

  };

})(jQuery);
