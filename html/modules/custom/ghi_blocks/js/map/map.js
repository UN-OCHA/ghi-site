(function ($, Drupal) {

  Drupal.hpc_map = Drupal.hpc_map || {};
  Drupal.hpc_map.states = Drupal.hpc_map.states || {};

  Drupal.hpc_map.config = {
    modal: {
      wrapperTemplate: [
        '<div class="{OVERLAY_CLS}"></div>',
        '<div class="{MODAL_CLS}">',
        '  <div class="{MODAL_CONTENT_CLS}">',
        '    <div class="{INNER_CONTENT_CLS}"><i class="material-icons close">highlight_off</i>{_content}</div>',
        '  </div>',
        '</div>'
      ].join(''),
    }
  }

  L.Control.LocationAdminLevelSelector = L.Control.extend({
    onAdd: function(map) {
      let locations = this.options.locations;
      let active_level = this.options.active_level;
      locations_admin_level = locations.map(function(item) { return item.admin_level; });

      // Create an array with unique values. Sort it, because the order of the
      // locations is not guaranteed to be in the order of their admin level.
      let admin_level = [...new Set(locations_admin_level)].sort();
      let admin_level_max = Math.max.apply(Math, admin_level);
      let admin_level_min = Math.min.apply(Math, admin_level);
      var div = L.DomUtil.create('div');

      if (admin_level.length == 1 && admin_level_min == 1) {
        return div;
      }
      div.className = 'admin-level-select leaflet-bar leaflet-bar-horizontal';

      // Add one button per admin level.
      for (value = 1; value <= admin_level_max; value++) {
        let button = L.DomUtil.create('a');
        button.innerHTML = value;
        button.setAttribute('href', '#');
        button.setAttribute('role', 'button');
        button.setAttribute('data-admin-level', value);
        button.className = 'admin-level-select-button';
        if (value == active_level) {
          button.className += ' active';
        }
        else if (admin_level.indexOf(value) == -1) {
          button.className += ' disabled';
        }
        L.DomEvent.addListener(button, 'click', this.changeAdminLevel, this);
        div.appendChild(button);
      }
      // Add a tooltip to the control.
      tippy($(div).get(0), {
        content: Drupal.t('Select Admin Level View'),
      });
      return div;
    },

    onRemove: function(map) {
      // Nothing to do here
    },

    changeAdminLevel: function(e) {
      L.DomEvent.preventDefault(e);
      if ($(e.target).hasClass('disabled')) {
        return;
      }
      var button = e.target;
      let map_id = Drupal.hpc_map.getMapIdFromContainedElement(button);
      let state = Drupal.hpc_map.getMapState(map_id);
      if (state.disabled) {
        return;
      }
      if (state.admin_level == button.getAttribute('data-admin-level')) {
        return;
      }
      Drupal.hpc_map.changeAdminLevel(state, button.getAttribute('data-admin-level'));
    }
  });

  L.control.location_admin_level_selector = function(opts) {
    return new L.Control.LocationAdminLevelSelector(opts);
  }

  L.Control.DonutControl = L.Control.extend({
    onAdd: function(map) {
      let map_id = map._container.id;
      var state = Drupal.hpc_map.getMapState(map_id);
      // Create the control sidebar.
      let sidebar_element = L.DomUtil.create('div');
      let sidebar_id = 'leaflet-control-sidebar-' + state.map_id;
      sidebar_element.setAttribute('id', sidebar_id);
      $(sidebar_element).addClass('leaflet-sidebar-container');
      sidebar_element.innerHTML = '';
      $('#' + map_id).append(sidebar_element);
      state.control_sidebar = L.control.sidebar(sidebar_id, {
        position: 'right'
      });
      state.map.addControl(state.control_sidebar);
      state.control_sidebar.on('hidden', function () {
        Drupal.hpc_map.enableMap(state);
      });

      $('#' + map_id).parents('.map-container').addClass('has-donut-control');

      // Create the control button.
      var button = L.DomUtil.create('div', 'donut-control leaflet-bar leaflet-bar-horizontal');
      button.innerHTML = '<i class="material-icons donut-control-icon" title="Change displayed metrics">settings</i>'
      L.DomEvent.addListener(button, 'click', this.openDonutControl, this);
      return button;
    },

    openDonutControl: function(e) {
      var state = Drupal.hpc_map.getMapStateFromContainedElement(e.target);
      Drupal.hpc_map.disableMap(state);

      state.control_sidebar.setContent(Drupal.theme('mapDonutControlForm', state));
      let control_container = state.control_sidebar._contentContainer;
      Drupal.attachBehaviors(control_container);
      state.control_sidebar.show();

      // Add form submit logic.
      $('button', control_container).click(function(e) {
        e.preventDefault();
        var state = Drupal.hpc_map.getMapStateFromContainedElement(e.target);
        $('.form-error', control_container).remove();
        // Update the donut element configuration.
        let full_segment = parseInt($('.donut-whole-segment select', control_container).val());
        let partial_segment = parseInt($('.donut-partial-segment select', control_container).val());
        if (full_segment == partial_segment) {
          var error = $('<div></div>').addClass('form-error').html(Drupal.t('Please chose different metrics for the donut segments.'));
          $('.donut-control-form', control_container).append(error);
        }
        else {
          state.active_donut_segments = [
            full_segment,
            partial_segment
          ];

          state.active_donut_display_value = $('.donut-display-value select', control_container).val();

          if (Drupal.hpc_map.metricIsMeasurement(state, partial_segment)) {
            let selected_monitoring_period = parseInt($('.monitoring-period select', control_container).val());
            if (selected_monitoring_period) {
              state.active_monitoring_period = selected_monitoring_period;
              state.tab_data.locations = state.data[state.index].location_variants[state.active_monitoring_period].locations;
              state.tab_data.modal_contents = state.data[state.index].location_variants[state.active_monitoring_period].modal_contents;
            }
            else {
              state.active_monitoring_period = Drupal.hpc_map.getDefaultMonitoringPeriod(state);
            }
          }
          else {
            state.active_monitoring_period = null;
            state.tab_data = state.data[state.index];
          }

          // Hide the sidebar.
          state.control_sidebar.hide();
          // Rebuild the locations so that the changes can take effect.
          Drupal.hpc_map.buildLocationMap(state.locationOverlay.selection, state.locationOverlay.projection);
        }
      });
    }
  });

  L.control.donut_control = function(opts) {
    return new L.Control.DonutControl(opts);
  }

  Drupal.hpc_map.metricIsMeasurement = function(state, metric_index) {
    if (!state.data[state.index].hasOwnProperty('measurement_metrics')) {
      return false;
    }
    let measurement_metrics = state.data[state.index].measurement_metrics;
    return measurement_metrics.indexOf(metric_index) != -1;
  }

  Drupal.hpc_map.getDefaultMonitoringPeriod = function(state) {
    let config = state.options.map_style_config;
    default_monitoring_period = null;
    if (config.hasOwnProperty('donut_monitoring_periods') && Object.getOwnPropertyNames(config.donut_monitoring_periods).length) {
      // config.donut_monitoring_periods will be empty if the map has been
      // configured to show a single monitoring period.
      if (config.donut_monitoring_periods.length) {
        default_monitoring_period = parseInt(config.donut_monitoring_periods[0]);
      }
      else if (typeof state.data[state.index].measurements != 'undefined' && Object.keys(state.data[state.index].measurements).length) {
        default_monitoring_period = parseInt(Object.keys(state.data[state.index].measurements)[0]);
      }
    }
    return default_monitoring_period;
  }

  // Initialize the map.
  Drupal.hpc_map.init = function (map_id, data, options) {
    let defaults = {
      admin_level_selector : false,
      mapbox_url: 'https://api.mapbox.com/styles/v1/reliefweb/clbfjni1x003m15nu67uwtbly/tiles/256/{z}/{x}/{y}?title=view&access_token=pk.eyJ1IjoicmVsaWVmd2ViIiwiYSI6IldYR2ZuV3cifQ.eSPZMZWE6UyLtO0OH_-qrw',
      map_style: 'circle',
      popup_style: 'modal',
      search_enabled: false,
      search_options: {
        empty_message: Drupal.t('Be sure to enter a location name within the current response plan.'),
        placeholder: Drupal.t('Filter by location name'),
      },
      pcodes_enabled: true,
    };
    options = Object.assign({}, defaults, options);

    var state = Drupal.hpc_map.getMapState(map_id);
    state.map_id = map_id;
    state.options = options;
    state.disabled = false;

    Drupal.hpc_map.states[map_id] = state;

    if (!$('#' + state.map_id).length) {
      return;
    }
    state.map_container_class = '.map-wrapper-' + map_id;
    if (!$(state.map_container_class).length) {
      return;
    }

    if ($(state.map_container_class + ' .map-tabs a').length) {
      // The first tab is the active one by default.
      state.index = $(state.map_container_class + ' .map-tabs a').first().data('map-index');
    }
    else {
      // There are no tabs. Just grab the first item in data and set that as
      // active.
      state.index = data ? Object.getOwnPropertyNames(data)[0] : 0;
    }
    state.variant_id = null;
    state.active_location = null;
    state.focused_location = null;
    state.data = data;
    state.tab_data = state.data != null && typeof state.data[state.index] != 'undefined' ? state.data[state.index] : null;
    $(state.map_container_class + ' .map-tabs li').first().addClass('active');

    // Chose the right admin level to start with.
    if (state.tab_data !== null && typeof state.tab_data.locations !== 'undefined' && state.tab_data.locations.length) {
      locations_admin_level = state.tab_data.locations.map(function(item) { return item.admin_level; });
      state.admin_level = Math.min.apply(Math, locations_admin_level);
    }
    else {
      state.admin_level = 1;
    }

    var mapConfig = {
      minZoom: 2,
      maxZoom: 10,
      scrollWheelZoom: false,
      worldCopyJump: false,
      attributionControl: false,
      zoomSnap: 0.1
    };
    var map;

    if (typeof state.map != 'undefined') {
      // If we already have a map, destroy it.
      state.map.remove();
    }

    // This is mainly important for printing, see this issue for details:
    // https://github.com/Leaflet/Leaflet/issues/3575
    var originalInitTile = L.GridLayer.prototype._initTile
    L.GridLayer.include({
      _initTile: function (tile) {
        originalInitTile.call(this, tile);

        var tileSize = this.getTileSize();

        tile.style.width = tileSize.x + 1 + 'px';
        tile.style.height = tileSize.y + 1 + 'px';
      }
    });

    // If we have map locations, use these to zoom into the map.
    if (state.tab_data !== null && typeof state.tab_data.locations !== 'undefined' && state.tab_data.locations.length) {
      // Get the bound coordinates and set up the map to fit.
      var bounding_box = state.tab_data.locations.map(x => x.latLng);
      map = L.map(state.map_id, mapConfig)
        // Set view so that all markers fit.
        .fitBounds(bounding_box, { padding: [0, 80] })
        // Restrict movements to the world.
        .setMaxBounds([[-90.0, -210.0], [90.0, 210.0]]);
      state.map = map;
      if (state.tab_data.locations.length == 1) {
        map.setZoom(6);
      }

      if (options.map_style == 'donut' && typeof options.map_style_config != 'undefined') {
        // Which metrics to use for the donut segments.
        let config = options.map_style_config;
        state.active_donut_segments = [];
        state.active_donut_segments.push(config.hasOwnProperty('donut_whole_segment_default') ? config.donut_whole_segment_default : config.donut_whole_segments[0]);
        state.active_donut_segments.push(config.hasOwnProperty('donut_partial_segment_default') ? config.donut_partial_segment_default : config.donut_partial_segments[0]);
        // What to display in the donut center.
        state.active_donut_display_value = config.hasOwnProperty('donut_display_value') ? config.donut_display_value : 'percentage';

        // If there are monitoring periods, use the latest one by default.
        state.active_monitoring_period = Drupal.hpc_map.getDefaultMonitoringPeriod(state);
      }

      // Creates D3 location overlays.
      var locationOverlay = L.d3SvgOverlay(Drupal.hpc_map.buildLocationMap, {zoomDraw: true});
      locationOverlay.addTo(map);
      state.locationOverlay = locationOverlay;

      // Add admin level selector.
      if (options.admin_level_selector) {
        L.control.location_admin_level_selector({ position: 'bottomleft', locations: state.tab_data.locations, active_level: state.admin_level }).addTo(map);
      }
      // Add donut control.
      if (options.map_style == 'donut' && typeof options.map_style_config != 'undefined' && (options.map_style_config.donut_partial_segments.length > 1 || options.map_style_config.donut_whole_segments.length > 1)) {
        L.control.donut_control({ position: 'topright' }).addTo(map);
      }
    }
    else {
      // Otherwhise create an empty map with the whole world visible.
      map = L.map(state.map_id, mapConfig).setView([20, 15], 2);
      state.map = map;
    }

    var layer = L.tileLayer(options.mapbox_url).addTo(map);

    // Add attribution.
    if (typeof options.disclaimer != 'undefined') {
      var map_disclaimer = L.control.attribution({prefix: options.disclaimer.text, position: options.disclaimer.position}).addTo(map);
    }

    state.map = map;

    // Add support for a dynamic sidebar.
    if (state.options.popup_style == 'sidebar') {
      let sidebar_element = L.DomUtil.create('div');
      let sidebar_id = 'leaflet-sidebar-' + state.map_id;
      sidebar_element.setAttribute('id', sidebar_id);
      $(sidebar_element).addClass('leaflet-sidebar-container');
      sidebar_element.innerHTML = '';
      $('#' + map_id).append(sidebar_element);
      state.sidebar = L.control.sidebar(sidebar_id, {
        position: 'right'
      });
      state.map.addControl(state.sidebar);
    }

    if (state.options.search_enabled) {
      // Now add the search control to the map.
      search_options = state.options.search_options;
      state.map.addControl( new L.Control.Search({
        sourceData: Drupal.hpc_map.searchSourceData,
        propertyName: 'location_id',
        marker: false,
        collapsed: false,
        initial: false,
        autoCollapseTime: 5 * 60 * 1000, // 5 minutes
        textPlaceholder: search_options.placeholder,
        getLocationData: function(map_id, location_id) {
          let state = Drupal.hpc_map.getMapState(map_id);
          let location_data = Drupal.hpc_map.getLocationDataById(state, location_id);
          return location_data;
        },
        textErr: function(e) {
          let search_text = e._input.value.replace(/[.*+?^${}()|[\]\\]/g, '');
          return Drupal.t("No results for '<b>!search_string</b>'", {
            '!search_string': search_text,
          }) + (search_options.empty_message ? '<br /><span class="subline">' + search_options.empty_message + '</span>' : '');
        },
        // Callback to build every item in the dropdown.
        buildTip: function(location_id, val) {
          let state = Drupal.hpc_map.getMapState(this._map._container.id);
          let location_data = Drupal.hpc_map.getLocationDataById(state, location_id);
          let search_text = this._input.value.replace(/[.*+?^${}()|[\]\\]/g, '');
          let regex = new RegExp(search_text, "gi");
          let location_name = location_data.location_name.replace(regex, "<b>$&</b>");
          let tip = L.DomUtil.create('div');
          tip.setAttribute('data-location-id', location_id);
          var subline = null;
          if (location_data.admin_level) {
            var subline = Drupal.t('Admin Level !level', {
              '!level': location_data.admin_level
            });
            if (state.options.pcodes_enabled && typeof location_data.pcode != 'undefined' && location_data.pcode && location_data.pcode.length) {
              subline = Drupal.t('Admin Level !level | !pcode', {
                '!level': location_data.admin_level,
                '!pcode': location_data.pcode.replace(regex, "<b>$&</b>"),
              });
            }
          }

          if (subline) {
            tip.innerHTML = '<span class="location-name">' + location_name + '</span><br />' + '<span class="subline">' + subline + '</span>';
          }
          else {
            tip.innerHTML = '<span class="location-name">' + location_name + '</span>';
          }
          return tip;
        },
        // Callback for the actual filtering of the data.
        filterData: function(text, records) {
          let state = Drupal.hpc_map.getMapState(this._map._container.id);
          var I, icase, regSearch, frecords = {};
          var found = false;

          text = text.replace(/[.*+?^${}()|[\]\\]/g, '');  // Sanitize remove all special characters.
          if (text === '') {
            return [];
          }

          I = this.options.initial ? '^' : '';  // Search only initial text.
          icase = !this.options.casesensitive ? 'i' : undefined;
          regSearch = new RegExp(I + text, icase);

          for (var location_id in records) {
            let location_data = Drupal.hpc_map.getLocationDataById(state, location_id);
            if (state.options.pcodes_enabled && typeof location_data.pcode != 'undefined') {
              // Search for loccation name annd pcode
              if (regSearch.test(location_data.location_name) || regSearch.test(location_data.pcode)) {
                frecords[location_id] = records[location_id];
                found = true;
              }
            }
            else {
              // Search only for location name.
              if (regSearch.test(location_data.location_name)) {
                frecords[location_id] = records[location_id];
                found = true;
              }
            }
          }

          if (found) {
            // Hide the error alert in case it is currently shown.
            this.hideAlert();
          }
          else {
            // Show the error alert if no results have been found.
            this.showAlert();
          }
          return frecords;
        },
        // Callback for format data to indexed data.
        formatData: function(json) {
          var self = this,
          propName = this.options.propertyName,
          propLoc = this.options.propertyLoc,
          i, jsonret = {};
          for (i in json) {
            let location_id = self._getPath(json[i], propName);
            jsonret[location_id] = L.latLng(self._getPath(json[i], propLoc));
          }
          return jsonret;
        },
        moveToLocation: function(latlng, location_id, map) {
          let map_id = this._map._container.id;
          let state = Drupal.hpc_map.getMapState(map_id);
          let location_data = Drupal.hpc_map.getLocationDataById(state, location_id);
          this._input.value = location_data.location_name;
          if (!location_data.admin_level || state.admin_level == location_data.admin_level) {
            Drupal.hpc_map.moveToLocation(latlng, state);
            Drupal.hpc_map.showPopup(location_data, state);
          }
          else {
            // Find the button and click it
            Drupal.hpc_map.changeAdminLevel(state, location_data.admin_level);
            setTimeout(function() {
              Drupal.hpc_map.moveToLocation(latlng, state);
              Drupal.hpc_map.showPopup(location_data, state);
            }, 500);
          }
        }
      }));

      $(state.map_container_class).on({
        mouseenter: function () {
          //stuff to do on mouse enter
          $(state.map_container_class + ' .search-tip').removeClass('hover');
          $(this).addClass('hover');
        },
        mouseleave: function () {
          //stuff to do on mouse leave
          $(this).removeClass('hover');
        }
      }, '.search-tip');
    }

    $(state.map_container_class + ' .map-tabs a.map-tab').click(function(e) {
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
        Drupal.hpc_map.switchTab(map_id, $(this).data('map-index'));
      }
      e.preventDefault();
    });

    $(state.map_container_class + ' .map-tabs div.ghi-dropdown--content a').click(function(e) {
      let parent_index = $(this).parents('li').find('a.map-tab').data('map-index');
      Drupal.hpc_map.switchVariant(map_id, parent_index, $(this).data('variant-id'));
      e.preventDefault();
    });

    tippy($('.leaflet-control-zoom-in').get(0), {
      content: Drupal.t('Zoom in'),
    });
    tippy($('.leaflet-control-zoom-out').get(0), {
      content: Drupal.t('Zoom out'),
    });

    // This is necessary to make the map work in PDF downloads, where the paper
    // size of A2 that we use is apparently causing issues during the map
    // resizing, resulting in a grey area to the right of the map.
    setInterval(function() {
      state.map.invalidateSize();
    }, 100);

  }

  Drupal.hpc_map.searchSourceData = function(text, callResponse) {
    let state = Drupal.hpc_map.getMapState(this._map._container.id);

    callResponse(state.search_index);
    return {
      //called to stop previous requests on map move
      abort: function() {
      }
    };
  }

  // Build the location map based on the currently active map tab.
  Drupal.hpc_map.buildLocationMap = function (sel, proj) {
    var map_id = proj.map._container.id;
    if (typeof Drupal.hpc_map.states[map_id] == 'undefined') {
      return;
    }
    var state = Drupal.hpc_map.getMapState(map_id);
    var locations = state.tab_data.locations.length > 0 ? state.tab_data.locations : {};

    $('a[data-map-index="' + state.index + '"] .period-number').remove();
    if (state.variant_id && Drupal.hpc_map.dataHasVariant(state.tab_data, state.variant_id)) {
      locations = Object.values(state.tab_data.variants[state.variant_id].locations);
      $('a[data-map-index="' + state.index + '"] + .variant-toggle').find('button .ghi-dropdown__btn-label').html('#' + state.tab_data.variants[state.variant_id].tab_label);
    }

    // If we have donut segments, update the totals per location and the radius
    // used for drawing the maps.
    if (state.active_donut_segments) {
      locations = locations.map(function(d) {
        let main_total = d[state.index][state.active_donut_segments[0]];
        d.total = main_total ? main_total : 0;
        return d;
      });
      var locations_max = Math.max.apply(Math, locations.map(function(d) { return d.total; }));
      locations = locations.map(function(d) {
        var radius_factor = locations_max > 0 ? 30 / locations_max * d.total : 1;
        radius_factor = radius_factor > 1 ? radius_factor : 1;
        d.radius_factor = radius_factor;
        d.radius_factors['attachment'] = radius_factor;
        return d;
      });
      state.tab_data.locations = locations;
    }

    // Make sure we have integer values.
    locations = locations.map(function(d) {
      let total = typeof d.total == 'string' ? parseInt(d.total.replace(/,/g, '')) : d.total;
      if (Object.is(NaN, total)) {
        total = 0;
      }
      d.total = total;
      return d;
    });

    if (locations.length && state.options.admin_level_selector) {
      // Calculate a radius factor for data points grouped by admin level, but
      // make sure that the data is relative across the different metrics.
      var locations_all_metrics = [];
      for (var property in state.data) {
        if (!state.data.hasOwnProperty(property)) {
          continue;
        }
        let metric_data = state.data[property];
        let metric_locations = metric_data.locations;
        // Make sure we have integer values.
        metric_locations = metric_locations.map(function(d) {
          let total = typeof d.total == 'string' ? parseInt(d.total.replace(/,/g, '')) : d.total;
          if (Object.is(NaN, total)) {
            total = 0;
          }
          d.total = total;
          return d;
        });
        if (state.variant_id != null && Object.keys(metric_data.variants).length > 0 && metric_data.variants[state.variant_id]) {
          metric_locations = Object.values(metric_data.variants[state.variant_id].locations);
        }
        locations_all_metrics = locations_all_metrics.concat(metric_locations.filter(function(d) {
          return d.admin_level == state.admin_level;
        }));
      }

      // Get the total sum in the current set of locations.
      var totals_sum = 0;
      for (i = 0; i < locations_all_metrics.length; i++) {
        totals_sum += locations_all_metrics[i].total;
      }
      var totals_max = 1;
      if (locations_all_metrics.length > 0) {
        totals_max = Math.max.apply(Math, locations_all_metrics.map(function(d) { return d.total; }));
      }

      // Then filter the locations for display.
      locations = locations.filter(function(d) {
        return d.admin_level == state.admin_level;
      });

      var radius_factor_max = 40;
      var radius_factor_min = 1;

      // And update the grouped radius factor.
      locations = locations.map(function(d) {
        d.radius_factor_grouped = d.total > 0 ? Math.ceil(radius_factor_max / totals_max * d.total) : radius_factor_min;
        return d;
      });
    }
    // Sort the locations to create a defined order.
    locations.sort((a, b) => a.location_id - b.location_id);

    // And finally trigger the creation on the map.
    Drupal.hpc_map.createLocations(locations, sel, proj);

    // If searching is enabled, create our static search index.
    if (state.tab_data.locations.length && state.options.search_enabled) {
      state.search_index = [];
      // Add all locations to our search index.
      for (var i in state.tab_data.locations) {
        let location_data = state.tab_data.locations[i];
        state.search_index.push({
          loc: location_data.latLng,
          location_id: location_data.location_id,
          location_name: location_data.location_name,
          pcode: location_data.pcode,
          admin_level: location_data.admin_level,
        });
      }
    }
  }

  // Check if the given data has a given variant.
  Drupal.hpc_map.dataHasVariant = function(data, variant_id) {
    return data && data.hasOwnProperty('variants') && Object.keys(data.variants).length > 0 && data.variants.hasOwnProperty(variant_id);
  }

  // Retrieve the map ID for the given element.
  Drupal.hpc_map.getMapIdFromContainedElement = function(element) {
    return $(element).parents('.leaflet-container').attr('id');
  }

  // Retrieve the map state for the given element.
  Drupal.hpc_map.getMapStateFromContainedElement = function(element) {
    var contained_element = element.nodeName == 'use' ? Drupal.hpc_map.getElementFromUseElement(element) : $(element);
    var map_id = Drupal.hpc_map.getMapIdFromContainedElement(contained_element);
    return map_id ? Drupal.hpc_map.getMapState(map_id) : null;
  }

  // Get a dom element based on the given data object.
  Drupal.hpc_map.getElementFromDataObject = function(object, state) {
    let elements = $('#' + state.map_id).find('svg[location-id=' + object.location_id + '],circle[location-id=' + object.location_id + ']');
    return elements.length ? elements[0] : null;
  }

  // Get a location data object by it's ID.
  Drupal.hpc_map.getLocationDataById = function(state, location_id) {
    let filtered_locations = state.tab_data.locations.filter(function(location) {
      return location.location_id == location_id;
    });
    return filtered_locations.length ? filtered_locations[0] : null;
  }

  // Get a location data object from the HTML element or on of its childs.
  Drupal.hpc_map.getLocationObjectFromContainedElement = function(element) {
    var contained_element = element.nodeName == 'use' ? Drupal.hpc_map.getElementFromUseElement(element) : $(element);
    if (location_id = $(contained_element).attr('location-id')) {
      var state = Drupal.hpc_map.getMapStateFromContainedElement(contained_element);
      return Drupal.hpc_map.getLocationDataById(state, location_id);
    }
    let parents = $(contained_element).parents('svg[location-id]');
    return parents.length ? d3.select(parents[0]).data()[0].object : null;
  }

  // Get a dom element for the given use element.
  Drupal.hpc_map.getElementFromUseElement = function(element) {
    if (element.nodeName != 'use') {
      return;
    }
    let element_id = $(element).attr('href');
    return $(element_id).length ? $(element_id)[0] : null;
  }

  // Get a location data object from an HTML <use> element.
  Drupal.hpc_map.getLocationObjectFromUseElement = function(element) {
    let svg_element = Drupal.hpc_map.getElementFromUseElement(element);
    if (!svg_element) {
      return null;
    }
    var state = Drupal.hpc_map.getMapStateFromContainedElement(element);
    let location_id = $(svg_element).attr('location-id');
    return Drupal.hpc_map.getLocationDataById(state, location_id);
  }

  // See if the given data point is empty for the current map state.
  Drupal.hpc_map.emptyValueForCurrentTab = function(d, map_id) {
    if (typeof d == 'undefined') {
      return false;
    }
    if (typeof d.empty_tab_values == 'undefined') {
      return false;
    }
    var state = Drupal.hpc_map.getMapState(map_id);
    return d.empty_tab_values[state.index];
  }

  // Move the map to show the given latLng coordinates.
  Drupal.hpc_map.moveToLocation = function(latlng, state) {
    if (state.options.popup_style == 'modal') {
      let scale = state.locationOverlay.projection.scale;
      let factor = 1 / (scale >= 1 ? scale : 1);
      latlng.lng -= 50 * factor;
      // Note: panTo doesn't work for some reason, failing to update
      // the locations, kind of dragging them with the pan so that
      // location markers end up on wrrong parts of the map. Using
      // flyTo this doesn't happen.
      state.map.flyTo(latlng, state.map._zoom);
    }
    else {
      state.map.flyTo(latlng);
    }
  }

  // Focus a location. Wrapper function that hands over to the actual map
  // implementation class.
  Drupal.hpc_map.focusLocation = function(element, state, focus_state) {
    if (!element) {
      return;
    }
    focus_state = typeof focus_state == 'undefined' ? 1 : focus_state;
    let contained_element = element.nodeName == 'use' ? Drupal.hpc_map.getElementFromUseElement(element) : element;
    let object = Drupal.hpc_map.getLocationObjectFromContainedElement(contained_element);
    if (!object || (focus_state && state.focused_location && object.location_id == state.focused_location.location_id)) {
      return;
    }

    if (focus_state) {
      // If we want to focus a location, make sure there is no other currently
      // focused location on the map.
      $('#' + state.map_id).find('[data-map-focus="1"]').removeAttr('data-map-focus').each(function(i, el) {
        Drupal.hpc_map.focusLocation(el, state, 0);
      });
      state.focused_location = object;
      $(contained_element).attr('data-map-focus', '1');
    }
    else {
      state.focused_location = null;
      $(contained_element).removeAttr('data-map-focus');
    }

    // Hand off to the appropriate handler.
    if (state.options.map_style == 'circle') {
      Drupal.hpc_map_circle.focusLocation(contained_element, focus_state);
    }
    if (state.options.map_style == 'donut') {
      Drupal.hpc_map_donut.focusLocation(contained_element, focus_state, state.locationOverlay.projection.scale);
    }

    if (focus_state) {
      Drupal.hpc_map.moveLocationToFront(contained_element);
    }
    else if (state.active_location) {
      let active_element = Drupal.hpc_map.getElementFromDataObject(state.active_location, state);
      Drupal.hpc_map.moveLocationToFront(active_element);
    }

  }

  // Change the admin level.
  Drupal.hpc_map.changeAdminLevel = function(state, admin_level) {
    let button = $(state.map_container_class + ' .admin-level-select a[data-admin-level=' + admin_level + ']')[0];
    state.admin_level = admin_level;
    $(state.map_container_class + ' .admin-level-select-button').removeClass('active');
    $(button).addClass('active');
    Drupal.hpc_map.buildLocationMap(state.locationOverlay.selection, state.locationOverlay.projection);
    if (state.options.popup_style == 'sidebar') {
      state.sidebar.hide();
    }
  }

  // Move the given element to the front.
  Drupal.hpc_map.moveLocationToFront = function(element) {
    let object = Drupal.hpc_map.getLocationObjectFromContainedElement(element);
    if (!object) {
      return;
    }
    // Move the element to the front.
    // See https://developer.mozilla.org/en-US/docs/Web/SVG/Element/use
    let map_id = Drupal.hpc_map.getMapIdFromContainedElement(element);
    $(element).parent().find('use').attr('href', '#' + map_id + '--' + object.location_id);
  }

  // Set an element as the currently active one.
  Drupal.hpc_map.setActiveLocation = function(element, object, state) {
    if (object == null) {
      state.active_location = null;
      $(element).removeAttr('data-map-status');
      Drupal.hpc_map.focusLocation(element, state, 0);
      return;
    }
    if ($(element).attr('data-map-status') == 'active-location' && state.active_location && state.active_location.location_id == object.location_id) {
      // Just focus the location item on the map.
      Drupal.hpc_map.focusLocation(element, state);
      return;
    }

    // First reset the active state and unfocus any location currently marked
    // as active.
    state.active_location = null;
    Drupal.hpc_map.resetActiveLocation(state);

    // Then set this one as the active.
    state.active_location = object;
    $(element).attr('data-map-status', 'active-location');

    // And focus the location item on the map.
    Drupal.hpc_map.focusLocation(element, state);

    // Move the element to the front.
    Drupal.hpc_map.moveLocationToFront(element);

    let latLng = new L.latLng(object.latLng);
    if (!state.map.getBounds().contains(latLng)) {
      Drupal.hpc_map.moveToLocation(latLng, state);
    }
  }

  // Unfocus the current active location if there is one.
  Drupal.hpc_map.resetActiveLocation = function(state) {
    $('#' + state.map_id).find('[data-map-status="active-location"]').removeAttr('data-map-status').each(function(i, el) {
      Drupal.hpc_map.setActiveLocation(el, null, state);
    });
  }

  // Check if a data object is currently set active as the active one.
  Drupal.hpc_map.isActiveLocation = function(object, state) {
    if (!state.active_location) {
      return false;
    }
    let element = Drupal.hpc_map.getElementFromDataObject(object, state);
    if (!element) {
      return false;
    }
    return state.active_location.location_id == object.location_id && $(element).attr('data-map-status') == 'active-location';
  }

  // Get the factor for the radius.
  Drupal.hpc_map.getRadiusFactor = function(d) {
    if (typeof d == 'undefined') {
      return 1;
    }
    if (typeof d.radius_factor_grouped != 'undefined') {
      return d.radius_factor_grouped;
    }
    if (typeof d.radius_factor != 'undefined') {
      return d.radius_factor;
    }
    return 1;
  }

  // Calculate the radius for the given data point.
  Drupal.hpc_map.getRadius = function(d, scale, base_radius, radius_factor, min_radius) {
    var admin_level = typeof d.admin_level != 'undefined' ? d.admin_level : 1;
    if (typeof radius_factor == 'undefined') {
      radius_factor = Drupal.hpc_map.getRadiusFactor(d);
    }
    let radius = (base_radius + (radius_factor / scale)) / admin_level;
    return (typeof min_radius != 'undefined') ? (radius > min_radius ? radius : min_radius) : radius;
  }

  // Get the defined colors for the given map style.
  Drupal.hpc_map.getColors = function(map_style) {
    if (map_style == 'donut') {
      return Drupal.hpc_map_donut.config.colors;
    }
    else {
      return Drupal.hpc_map_circle.config.colors;
    }
  }

  // Get the map state for the given map id.
  Drupal.hpc_map.getMapState = function(map_id) {
    return Drupal.hpc_map.states.hasOwnProperty(map_id) ? Drupal.hpc_map.states[map_id] : {};
  }

  // Switch to a different map tab.
  Drupal.hpc_map.switchTab = function (map_id, index) {
    var state = Drupal.hpc_map.getMapState(map_id);
    $(state.map_container_class + ' .map-tabs ul > li').removeClass('active');
    $(state.map_container_class + ' .map-tabs a[data-map-index="' + index + '"]').parents('li').addClass('active');
    state.index = index;
    state.tab_data = state.hasOwnProperty('data') && state.data.hasOwnProperty(index) ? state.data[index] : null;

    // Check if the variant needs to be changed too.
    let variant_dropdown = $(state.map_container_class + ' .map-tabs a[data-map-index="' + index + '"]').parent('li').find('.cd-dropdown');
    let variant_id = variant_dropdown ? $(variant_dropdown).find('a:first-child').data('variant-id') : null;
    if (variant_id != null && state.tab_data && state.variant_id == null && variant_dropdown) {
      Drupal.hpc_map.switchVariant(map_id, index, variant_id);
      return;
    }

    if (state.tab_data !== null && typeof state.locationOverlay != 'undefined') {
      Drupal.hpc_map.buildLocationMap(state.locationOverlay.selection, state.locationOverlay.projection);
      if (state.active_location) {
        // If we have an open popup, keep it open and update the content.
        var active_location = state.active_location;
        var plan_modal = Drupal.hpc_map.planModal(active_location, state);
        if (state.options.popup_style == 'modal') {
          // Modal popup.
          if (plan_modal) {
            $('.leaflet-modal .modal-content .modal-inner .content').html(plan_modal.content);
            $('.leaflet-modal .modal-content .modal-inner .subcontent').html(plan_modal.monitoring_period);
            state.map.modal.update();
          }
          else {
            state.map.closeModal();
          }
        }
        else {
          // Sidebar popup.
          if (plan_modal) {
            Drupal.hpc_map.showPopup(active_location, state);
          }
          else {
            state.sidebar.hide();
          }
        }
      }
    }
    if (state.options.map_style == 'donut') {
      Drupal.hpc_map_donut.updateLegend(state);
    }
  }

  // Switch to a different variant of a map tab.
  Drupal.hpc_map.switchVariant = function (map_id, index, variant_id) {
    var state = Drupal.hpc_map.getMapState(map_id);

    // Mark the variant as active.
    $(state.map_container_class + ' .map-tabs li .cd-dropdown a').removeClass('active');
    $(state.map_container_class + ' .map-tabs a[data-map-index="' + index + '"]').parent('li').find('.cd-dropdown').find('a[data-variant-id="' + variant_id + '"]').addClass('active');

    // And set the variant for the state.
    state.variant_id = variant_id;

    // And hand over to the general tab switching which updates the data in the map.
    Drupal.hpc_map.switchTab(map_id, index);
  }

  // Disable the map.
  Drupal.hpc_map.disableMap = function(state) {
    state.disabled = true;
    $(state.map._container).addClass('leaflet-map-disabled');
    state.map.dragging.disable();
    state.map.zoomControl.disable();
    state.map.touchZoom.disable();
    state.map.doubleClickZoom.disable();
    state.map.scrollWheelZoom.disable();
    state.map.boxZoom.disable();
    state.map.keyboard.disable();
    if (state.map.tap) {
      state.map.tap.disable();
    }
    document.getElementById(state.map_id).style.cursor = 'default';
    $(state.map._container).find('.search-input').attr('disabled', 'disabled');
    $(state.map._container).find('.admin-level-select-button').addClass('leaflet-disabled');
  }

  // Enable the map.
  Drupal.hpc_map.enableMap = function(state) {
    state.disabled = false;
    $(state.map._container).removeClass('leaflet-map-disabled');
    state.map.dragging.enable();
    state.map.zoomControl.enable();
    state.map.touchZoom.enable();
    state.map.doubleClickZoom.enable();
    state.map.scrollWheelZoom.enable();
    state.map.boxZoom.enable();
    state.map.keyboard.enable();
    if (state.map.tap) {
      state.map.tap.enable();
    }
    document.getElementById(state.map_id).style.cursor = 'grab';
    $(state.map._container).find('.search-input').removeAttr('disabled');
    $(state.map._container).find('.admin-level-select-button').removeClass('leaflet-disabled');
  }

  // Show a popup. Depending on the way that this map was setup, it can be a
  // modal or a sidebar drawer.
  Drupal.hpc_map.showPopup = function(object, state) {

    if (state.disabled) {
      return;
    }

    let element = Drupal.hpc_map.getElementFromDataObject(object, state);
    if (!element) {
      return;
    }

    var popup_content = Drupal.hpc_map.planModal(object, state);
    if (!popup_content) {
      return;
    }

    Drupal.hpc_map.setActiveLocation(element, object, state);

    if (state.options.popup_style == 'modal') {
      state.map.openModal(popup_content);
      Drupal.attachBehaviors($('.leaflet-modal').get(0));
      state.map.on('modal.hide', function() {
        state.active_location = null;
        Drupal.hpc_map.resetActiveLocation(state);
      });
    }
    else if (state.options.popup_style == 'sidebar') {
      // Set sidebar content and open it.
      popup_content.pcodes_enabled = state.options.pcodes_enabled;
      state.sidebar.setContent(Drupal.theme('mapPlanCard', popup_content));
      if ($(state.sidebar._contentContainer).find('.map-card-metric-wrapper').length > 0) {
        // This is a map card with multiple metric items on it. Make sure that
        // we show only those metrics here that are also displayed on the map
        // right now.
        let metric_wrappers = $(state.sidebar._contentContainer).find('.map-card-metric-wrapper');
        $(metric_wrappers).hide();
        let colors = Drupal.hpc_map.getColors(state.options.map_style);
        for (i in state.active_donut_segments) {
          let segment_index = state.active_donut_segments[i];
          $(metric_wrappers).filter('[data-metric-index="' + segment_index + '"]').show();
          $(metric_wrappers).filter('[data-metric-index="' + segment_index + '"]').find('.metric-color-code').css('background-color', colors[colors.length - 1 - i]);
        }
      }
      // Quick access to the card container.
      let card_container = $(state.sidebar._contentContainer).find('.map-plan-card-container .content');
      let colors = Drupal.hpc_map.getColors(state.options.map_style);

      // Add ratio.
      let values = $(card_container).find('.map-card-metric-wrapper:visible .metric-value > span').map((k, v) => { return $(v).data('value'); });
      if (values.length == 2) {
        let ratio_item = $('<div></div>')
          .addClass('ratio-visible')
          .html('<div class="metric-label"><div class="metric-color-code"></div>' + Drupal.t('Ratio') + '</div><div class="metric-value">' + Drupal.theme('percent', 1 / values[0] * values[1]) + '</div>');
        $(ratio_item).find('.metric-color-code').css('background', 'linear-gradient(-45deg, ' + colors[0] + ',' + colors[0] + ' 49%,' + colors[1] + ' 51%)');
        $(card_container).append(ratio_item);
      }

      let chart_monitoring_period_wrapper = $('<div></div>')
          .addClass('monitoring-period-wrapper');

      // See if we should add a bar chart with the measurement values progress.
      if (Drupal.hpc_map.shouldShowPlanCardMeasurementProgress(state)) {
        // Add a heading to the chart.
        let chart_header_content = Drupal.t('Published measurements');
        let chart_header = $('<div></div>')
          .addClass('chart-header')
          .html(chart_header_content);
        $(chart_monitoring_period_wrapper).append(chart_header);
        // And add the actual measurement progresss bar chart.
        Drupal.hpc_map.createMetricMeasurementBarChart(state, colors, chart_monitoring_period_wrapper);
      }

      // Also for donut style maps, if the second segment contains a
      // measurement, we want to show the monitoring period for that.
      if (state.options.map_style == 'donut' && typeof state.data[state.index].measurement_metrics != 'undefined' && state.data[state.index].measurement_metrics.indexOf(state.active_donut_segments[1]) != -1) {
        let measurements = state.data[state.index].measurements;
        if (measurements && measurements.hasOwnProperty(state.active_monitoring_period)) {
          let period_item = $('<div></div>')
              .addClass('monitoring-period')
              .html(Drupal.t('Monitoring period: <br />!monitoring_period', {
                '!monitoring_period': measurements[state.active_monitoring_period].reporting_period,
              }));
          $(chart_monitoring_period_wrapper).append(period_item);
        }

        $(card_container).append(chart_monitoring_period_wrapper);

      }

      // Now show the sidebar.
      state.sidebar.show();
      // And also add an event handler for when it's closed again.
      state.sidebar.on('hidden', function() {
        let map_id = this._map._container.id;
        let state = Drupal.hpc_map.getMapState(map_id);
        if (!state.active_location) {
          return;
        }
        let element = Drupal.hpc_map.getElementFromDataObject(state.active_location, state);
        Drupal.hpc_map.setActiveLocation(element, null, state);
      });
      // Add navigation behavior.
      $(state.sidebar._container).find('.navigation .link').on('click', function() {
        let location_id = $(this).data('location-id');
        var new_active_location = null;
        $.each(state.tab_data.locations, function(i) {
          if (state.tab_data.locations[i].location_id == location_id) {
            new_active_location = state.tab_data.locations[i];
          }
        });
        if (new_active_location) {
          if (state.options.search_enabled) {
            // Clear the search input if the navigation is used.
            $(state.map_container_class + ' input.search-input').val('');
          }
          Drupal.hpc_map.showPopup(new_active_location, state);
          let element = Drupal.hpc_map.getElementFromDataObject(new_active_location, state);
          Drupal.hpc_map.focusLocation(element, state, 1);
        }
      });
    }
    Drupal.attachBehaviors(state.sidebar._container);
  }

  // Show a modal window with data about the location.
  Drupal.hpc_map.planModal = function(d, state) {
    var content = Drupal.hpc_map.planModalContent(d, state);
    var tab_data = state.tab_data;
    var location_id = parseInt(d.location_id);

    var location_data = tab_data.modal_contents[location_id];
    if (state.variant_id && Drupal.hpc_map.dataHasVariant(tab_data, state.variant_id)) {
      location_data = tab_data.variants[state.variant_id].modal_contents[location_id];
    }
    if (!location_data) {
      // The new tab has no data for the currently active location.
      return;
    }
    var monitoring_period = tab_data.monitoring_period ? ('<div class="monitoring-period">' + tab_data.monitoring_period + '</div>') : '';
    monitoring_period = location_data.monitoring_period ? ('<div class="monitoring-period">' + location_data.monitoring_period + '</div>') : '';

    // Get previous and next items.
    // First clone the locations list.
    let current_locations = tab_data.locations.map(location => location);
    if (state.options.admin_level_selector) {
      // Optionally filter by admin level.
      current_locations = current_locations.filter((d) => d.admin_level == state.admin_level);
    }

    // Sort alphabetically.
    current_locations.sort(function(a, b) {
      var a_name = a.location_name.toLowerCase();
      var b_name = b.location_name.toLowerCase();
      return ((a_name < b_name) ? -1 : ((a_name > b_name) ? 1 : 0));
    });
    // Get the current index in the sorted list.
    let current_location = current_locations.filter((l) => l.location_id == d.location_id)[0];
    var current_index = current_locations.indexOf(current_location);

    let next_index = current_index < current_locations.length - 1 ? current_index + 1 : 0;
    let previous_index = current_index > 0 ? current_index - 1 : current_locations.length - 1;

    return {
      location_data: location_data,
      title: location_data.title,
      tag_line: location_data.tag_line,
      monitoring_period: monitoring_period,
      content: content,
      next: next_index !== null ? current_locations[next_index] : null,
      previous: previous_index !== null ? current_locations[previous_index] : null,
      total_count: current_locations.length,
      current_index: current_index + 1,
      template: [
        '<div class="title">{title}</div>',
        '<div class="tag-line">{tag_line}</div>',
        '<div class="content">{content}</div>',
        '<div class="subcontent">{monitoring_period}</div>',
      ].join(''),
      wrapperTemplate: Drupal.hpc_map.config.modal.wrapperTemplate,
    }
  }

  // Create the content for the plan modal.
  Drupal.hpc_map.planModalContent = function(d, state) {
    var tab_data = state.tab_data;
    var location_id = parseInt(d.location_id);

    var base_data = null;
    if (state.variant_id != null && Drupal.hpc_map.dataHasVariant(tab_data, state.variant_id)) {
      base_data = tab_data.variants[state.variant_id];
    }
    else if (typeof tab_data.modal_contents[location_id] != 'undefined') {
      base_data = tab_data;
    }
    if (!base_data) {
      return false;
    }
    let modal_content = base_data.modal_contents[location_id];
    if (typeof modal_content == 'undefined') {
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
      for (cat in modal_content.categories) {
        category = modal_content.categories[cat];
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

  // Create a measurement progress bar chart.
  Drupal.hpc_map.createMetricMeasurementBarChart = function(state, colors, card_container) {
    let measurements = state.data[state.index].measurements;
    let location = state.active_location;
    let metric_indexes = state.active_donut_segments;
    let active_monitoring_period = state.active_monitoring_period;
    let legend = state.data[state.index].legend;

    var container = $('<div></div>')
      .addClass('measurement-bar-chart-container')
      .css("position", "relative");

    $(card_container).append(container);
    var data = [];
    var groups = ['partial', 'full'];
    let enabled_periods = state.options.map_style_config.donut_monitoring_periods;
    for (i of Object.keys(measurements)) {
      if (enabled_periods.indexOf(i) == -1) {
        continue;
      }
      let measurement = measurements[i];
      let full_segment = measurement.locations[location.location_id][metric_indexes[0]];
      let partial_segment = measurement.locations[location.location_id][metric_indexes[1]];
      let bar_values = {
        'period': measurement.id,
        'measurement': measurement,
        'full': full_segment > partial_segment ? full_segment - partial_segment : 0,
        'partial': partial_segment <= full_segment ? partial_segment : full_segment,
      };
      data.push(bar_values);
    }

    var width = data.length * 15,
        height = 30;

    var svg = d3.select(container[0]).append("svg")
      .style('width', width + 'px')
      .style('height', (height + 5) + 'px')
      .style('overflow', 'visible')
      .append("g");

    var stack = d3.stack()
      .keys(groups)
      .order(d3.stackOrderNone)
      .offset(d3.stackOffsetNone);
    var dataStackLayout = stack(data);

    // As this is a stacked bar chart, we need to first create the sum of each
    // bar to create the y-domain for the chart.
    var sums_y = [];
    for (i = 0; i < dataStackLayout.length; i++) {
      for (j = 0; j < dataStackLayout[i].length; j++) {
        if (typeof sums_y[j] == 'undefined') {
          sums_y[j] = 0;
        }
        sums_y[j] = d3.max([sums_y[j], dataStackLayout[i][j][1]]);
      }
    }

    // Define the scales.
    var x = d3.scaleBand()
      .domain(dataStackLayout[0].map(function (d) {
        return d.data.period;
      }))
      .range([0, width], .65, 0)
      .paddingInner(0.5);
    var y = d3.scaleLinear()
      .domain([0, d3.max(sums_y)])
      .rangeRound([height, 0]);

    var layer = svg.selectAll(".stack")
      .data(dataStackLayout)
      .enter().append("g")
      .attr("class", "stack")
      .style('pointer-events', 'all')
      .style("fill", function (d, i) {
        return colors[i];
      });

    layer.selectAll("rect")
      .data(function (d) { return d; })
      .join('rect')
      .style("cursor", function(d) {
        period_id = d.data.measurement.id;
        if (Drupal.hpc_map.canDisplayMonitoringPeriodData(state, period_id, state.active_donut_segments[1])) {
          return "pointer";
        }
        return "default";
      })
      .style("pointer-events", "all")
      .attr("x", (d) => x(d.data.period))
      .attr("y", (d) => y(d[1]))
      .attr("height", (d) => y(d[0]) - y(d[1]))
      .attr("width", x.bandwidth())
      .style('opacity', function(d) {
        return active_monitoring_period == d.data.measurement.id ? 1 : 0.3;
      })
      .attr('stroke-width', 1)
      .attr('stroke', colors[0])
      .on('click', function(event, d) {
        // Check if we have data for this measurement period.
        period_id = d.data.measurement.id;
        if (Drupal.hpc_map.canDisplayMonitoringPeriodData(state, period_id, state.active_donut_segments[1])) {
          // Yes, so switch over to display the selected one on the map, don't
          // close the sidebar, but update it's content.
          var tooltip = $(event.srcElement).parents('.measurement-bar-chart-container').find('.measurement-bar-chart-tooltip');
          $(tooltip).css("display", "none");

          state.active_monitoring_period = period_id;
          state.tab_data.locations = state.data[state.index].location_variants[state.active_monitoring_period].locations;
          state.tab_data.modal_contents = state.data[state.index].location_variants[state.active_monitoring_period].modal_contents;
          // The order here is important. First call showPopup(), which will
          // set the active location too. Then call buildLocationMap() to
          // redraw the map for the updated period. Then make the current
          // location active again.
          Drupal.hpc_map.showPopup(state.active_location, state);
          Drupal.hpc_map.buildLocationMap(state.locationOverlay.selection, state.locationOverlay.projection);
        }
      })
      .on("mouseover", function(event, d) {
        var tooltip = $(event.srcElement).parents('.measurement-bar-chart-container').find('.measurement-bar-chart-tooltip');
        var tooltip_lines = [];
        tooltip_lines.push(d.data.measurement.reporting_period);
        tooltip_lines.push('<span>' + legend[metric_indexes[0]] + ':</span><span>' + Drupal.theme('number', d.data.measurement.locations[location.location_id][metric_indexes[0]]) + '</span>');
        tooltip_lines.push('<span>' + legend[metric_indexes[1]] + ':</span><span>' + Drupal.theme('number', d.data.measurement.locations[location.location_id][metric_indexes[1]]) + '</span>');
        $(tooltip).html('<div>' + tooltip_lines.join('</div><div>') + '</div>');

        $(tooltip).css("display", "block");
      })
      .on("mouseout", function() { tooltip.style("display", "none"); })
      .on("mousemove", function(event, d) {
        var tooltip = $(event.srcElement).parents('.measurement-bar-chart-container').find('.measurement-bar-chart-tooltip');
        $(tooltip).css("top", '42px');
        $(tooltip).css("left", 0);
      });

    // Prep the tooltip bits, initial display is hidden
    var tooltip = d3.select(container[0]).append("div")
    .style("display", "none")
    .style("position", "absolute")
    .attr("class", "measurement-bar-chart-tooltip");
  }

  // Check if the measurement progress should be displayed in map cards.
  Drupal.hpc_map.shouldShowPlanCardMeasurementProgress = function(state) {
    // Only for donuts.
    if (state.options.map_style != 'donut') {
      return false;
    }
    if (!state.active_donut_segments) {
      // Not sure how this can happen, but we have no active donut segments.
      return false;
    }
    if (!state.data[state.index].hasOwnProperty('location_variants')) {
      return false;
    }
    if (typeof state.data[state.index].measurements == 'undefined' || !Object.keys(state.data[state.index].measurements).length) {
      // No measurements found.
      return false;
    }
    let segments = state.active_donut_segments;
    if (segments[0] == segments[1]) {
      // Both segments show the same.
      return false;
    }
    if (state.data[state.index].measurement_metrics.indexOf(segments[1]) == -1) {
      // The second segment needs to be a measurement.
      return false;
    }
    return true;
  }

  // Build options for the available monitoring period options for the given metric items.
  Drupal.hpc_map.buildMonitoringPeriodOptions = function(state, metric_index) {
    if (!state.data[state.index].hasOwnProperty('location_variants')) {
      return [];
    }
    let enabled_periods = state.options.map_style_config.donut_monitoring_periods;
    let location_variants = Object.keys(state.data[state.index].location_variants);
    let measurements = state.data[state.index].measurements;
    var options = [];
    for (var monitoring_period_id of state.options.map_style_config.donut_monitoring_periods) {
      if (!measurements.hasOwnProperty(monitoring_period_id) || enabled_periods.indexOf(monitoring_period_id) == -1 || location_variants.indexOf(monitoring_period_id) == -1) {
        continue;
      }
      if (!Drupal.hpc_map.canDisplayMonitoringPeriodData(state, monitoring_period_id, metric_index)) {
        continue;
      }
      options.push({value: monitoring_period_id, label: measurements[monitoring_period_id].reporting_period});
    }
    return options;
  }

  // Check if we can display data about the given monitoring period.
  Drupal.hpc_map.canDisplayMonitoringPeriodData = function(state, period_id, metric_index) {
    // Check if we have data about this period id at all.
    if (!state.data[state.index].hasOwnProperty('location_variants') || !state.data[state.index].location_variants.hasOwnProperty(period_id)) {
      return false;
    }

    // Check if at least one value has a measurment to show on the map.
    // We want to prevent maps with all gray circles.
    let totals = Math.max.apply(Math, state.data[state.index].location_variants[period_id].locations.map(function(d) {
      let value = d[state.index][metric_index] !== null ? d[state.index][metric_index] : 0;
      return value;
    }));
    return totals > 0;
  }

  Drupal.hpc_map.formatMillions = function(d) {
    var num = parseInt(d) / 1e6;
    return formatCurrency(d3.round(num, 1)) + 'm USD';
  },

  // Create the location items for the map.
  Drupal.hpc_map.createLocations = function(data, sel, proj) {
    var map_id = proj.map._container.id;
    var state = Drupal.hpc_map.getMapState(map_id);

    // Adds new locations.
    if (state.options.map_style == 'donut') {
      Drupal.hpc_map_donut.createLocations(data, sel, proj);
      Drupal.hpc_map_donut.updateLegend(state);
    }
    else {
      Drupal.hpc_map_circle.createLocations(data, sel, proj);
    }
  };

  Drupal.hpc_map.transform = function(transform) {
    // Create a dummy g for calculation purposes only. This will never
    // be appended to the DOM and will be discarded once this function
    // returns.
    var g = document.createElementNS("http://www.w3.org/2000/svg", "g");

    // Set the transform attribute to the provided string value.
    g.setAttributeNS(null, "transform", transform);

    // consolidate the SVGTransformList containing all transformations
    // to a single SVGTransform of type SVG_TRANSFORM_MATRIX and get
    // its SVGMatrix.
    var matrix = g.transform.baseVal.consolidate().matrix;

    // Below calculations are taken and adapted from the private function
    // transform/decompose.js of D3's module d3-interpolate.
    var {a, b, c, d, e, f} = matrix;   // ES6, if this doesn't work, use below assignment
    // var a=matrix.a, b=matrix.b, c=matrix.c, d=matrix.d, e=matrix.e, f=matrix.f; // ES5
    var scaleX, scaleY, skewX;
    if (scaleX = Math.sqrt(a * a + b * b)) a /= scaleX, b /= scaleX;
    if (skewX = a * c + b * d) c -= a * skewX, d -= b * skewX;
    if (scaleY = Math.sqrt(c * c + d * d)) c /= scaleY, d /= scaleY, skewX /= scaleY;
    if (a * d < b * c) a = -a, b = -b, skewX = -skewX, scaleX = -scaleX;
    return {
      translateX: e,
      translateY: f,
      rotate: Math.atan2(b, a) * 180 / Math.PI,
      skewX: Math.atan(skewX) * 180 / Math.PI,
      scaleX: scaleX,
      scaleY: scaleY
    };
  }

})(jQuery, Drupal);
