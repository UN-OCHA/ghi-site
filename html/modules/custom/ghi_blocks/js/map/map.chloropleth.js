(function ($, Drupal) {

  const root_styles = getComputedStyle(document.documentElement);

  // Attach behaviors.
  Drupal.behaviors.hpc_plan_map_chloropleth = {
    attach: function(context, settings) {
      if (!settings.plan_operational_presence_map || !Object.keys(settings.plan_operational_presence_map).length) {
        return;
      }
      for (i in settings.plan_operational_presence_map) {
        var map_config = settings.plan_operational_presence_map[i];
        if (!map_config.id || typeof map_config.json == 'undefined' || typeof map_config.json.locations == 'undefined' || map_config.json.locations.length == 0) {
          continue;
        }
        if (!context || !$('#' + map_config.id, context).length) {
          continue;
        }
        var options = {
          admin_level_selector: true,
          popup_style: 'sidebar',
          search_enabled: true,
          map_tiles_url: map_config.map_tiles_url,
        };
        if (typeof map_config.pcodes_enabled != 'undefined') {
          options.pcodes_enabled = map_config.pcodes_enabled;
        }
        if (typeof map_config.disclaimer != 'undefined') {
          options.disclaimer = {
            text: map_config.disclaimer,
            position: 'bottomright',
          };
        }
        Drupal.hpc_map_chloropleth.init(map_config.id, map_config.json, options);
      }
    }
  }

  Drupal.hpc_map_chloropleth = Drupal.hpc_map_chloropleth || {};
  Drupal.hpc_map_chloropleth.states = Drupal.hpc_map_chloropleth.states || {};

  Drupal.hpc_map_chloropleth.convertToRGB = function(color_string_hex) {
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

  // Color interpolation boldly copied and adapted from
  // https://graphicdesign.stackexchange.com/a/83867
  Drupal.hpc_map_chloropleth.interpolateColor = function(color1, color2, factor) {
    if (arguments.length < 3) {
      factor = 0.5;
    }
    var result = color1.slice();
    for (var i = 0; i < 3; i++) {
      result[i] = Math.round(result[i] + factor * (color2[i] - color1[i]));
    }
    return result;
  };

  Drupal.hpc_map_chloropleth.interpolateColors = function(color1, color2, steps) {
    var stepFactor = 1 / (steps - 1),
        interpolatedColorArray = [];

    color1 = color1.match(/\d+/g).map(Number);
    color2 = color2.match(/\d+/g).map(Number);

    for (var i = 0; i < steps; i++) {
      let rgb = Drupal.hpc_map_chloropleth.interpolateColor(color1, color2, stepFactor * i);
      interpolatedColorArray.push("#" + ((1 << 24) + (rgb[0] << 16) + (rgb[1] << 8) + rgb[2]).toString(16).slice(1));
    }

    return interpolatedColorArray;
  }

  Drupal.hpc_map_chloropleth.config = {
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
    colors: Drupal.hpc_map_chloropleth.interpolateColors("rgb(255, 255, 255)", Drupal.hpc_map_chloropleth.convertToRGB(root_styles.getPropertyValue('--ghi-widget-color--dark')), 6),
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

  L.Control.LocationAdminLevelSelectorChloropleth = L.Control.extend({
    onAdd: function(map) {
      return Drupal.hpc_map_chloropleth.addAdminLevelSelector(this, map);
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
      let map_id = Drupal.hpc_map_chloropleth.getMapIdFromContainedElement(button);
      let state = Drupal.hpc_map_chloropleth.getMapState(map_id);
      if (state.admin_level == button.getAttribute('data-admin-level')) {
        return;
      }
      Drupal.hpc_map_chloropleth.changeAdminLevel(state, button.getAttribute('data-admin-level'));
    },
  });

  L.control.location_admin_level_selector_chloropleth = function(opts) {
    return new L.Control.LocationAdminLevelSelectorChloropleth(opts);
  }

  // Initialize the map.
  Drupal.hpc_map_chloropleth.init = function (map_id, data, options) {
    let defaults = {
      admin_level_selector : false,
      map_style: 'circle',
      popup_style: 'modal',
    };
    options = Object.assign({}, defaults, options);

    var state = Drupal.hpc_map_chloropleth.getMapState(map_id);
    state.map_id = map_id;
    state.options = options;
    state.active_area = null;
    Drupal.hpc_map_chloropleth.states[map_id] = state;

    if (!$('#' + state.map_id).length || typeof options.map_tiles_url == 'undefined') {
      return;
    }
    state.map_container_class = '.map-wrapper-' + map_id;
    if (!$(state.map_container_class).length) {
      return;
    }

    state.data = data;

    // Chose the right admin level to start with.
    if (state.data !== null && typeof state.data.locations !== 'undefined' && state.data.locations.length) {
      locations_admin_level = state.data.locations.map(function(item) { return item.admin_level; });
      state.admin_level = Math.min.apply(Math, locations_admin_level);
    }
    else {
      state.admin_level = 1;
    }

    var mapConfig = {
      minZoom: 3,
      maxZoom: 10,
      scrollWheelZoom: false,
      worldCopyJump: false,
      attributionControl: false
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

    // Create the map.
    map = L.map(state.map_id, mapConfig).setView([20, 15], 2);
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
      var text_placeholder = Drupal.t('Filter by location name');
      if (state.options.pcodes_enabled) {
        text_placeholder = Drupal.t('Filter by location name or pcode');
      }
      state.map.addControl( new L.Control.Search({
        sourceData: Drupal.hpc_map_chloropleth.searchSourceData,
        propertyName: 'location_id',
        marker: false,
        collapsed: false,
        initial: false,
        autoCollapseTime: 5 * 60 * 1000, // 5 minutes
        textPlaceholder: text_placeholder,
        getLocationData: function(map_id, location_id) {
          let state = Drupal.hpc_map_chloropleth.getMapState(map_id);
          let location_data = Drupal.hpc_map_chloropleth.getLocationDataById(state, location_id);
          return location_data;
        },
        textErr: function(e) {
          let search_text = e._input.value.replace(/[.*+?^${}()|[\]\\]/g, '');
          var empty_message = Drupal.t('Be sure to enter a location name within the current response plan.');
          if (state.options.pcodes_enabled) {
            empty_message = Drupal.t('Be sure to enter a location name or pcode within the current response plan.');
          }
          return Drupal.t("No results for '<b>!search_string</b>'", {
            '!search_string': search_text,
          }) + '<br /><span class="subline">' + empty_message + '</span>';
        },
        // Callback to build every item in the dropdown.
        buildTip: function(location_id, val) {
          let state = Drupal.hpc_map_chloropleth.getMapState(this._map._container.id);
          let location_data = Drupal.hpc_map_chloropleth.getLocationDataById(state, location_id);
          let search_text = this._input.value.replace(/[.*+?^${}()|[\]\\]/g, '');
          let regex = new RegExp(search_text, "gi");
          let location_name = location_data.location_name.replace(regex, "<b>$&</b>");
          let tip = L.DomUtil.create('div');
          tip.setAttribute('data-location-id', location_id);
          var subline = Drupal.t('Admin Level !level', {
            '!level': location_data.admin_level
          });
          if (state.options.pcodes_enabled && typeof location_data.pcode != 'undefined' && location_data.pcode.length) {
            subline = Drupal.t('Admin Level !level | !pcode', {
              '!level': location_data.admin_level,
              '!pcode': location_data.pcode.replace(regex, "<b>$&</b>"),
            });
          }
          tip.innerHTML = '<span class="location-name">' + location_name + '</span><br />' + '<span class="subline">' + subline + '</span>';
          return tip;
        },
        // Callback for the actual filtering of the data.
        filterData: function(text, records) {
          let state = Drupal.hpc_map_chloropleth.getMapState(this._map._container.id);
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
            let location_data = Drupal.hpc_map_chloropleth.getLocationDataById(state, location_id);
            if (state.options.pcodes_enabled && typeof location_data.pcode != 'undefined') {
              // Search for loccation name annd pcode
              if (regSearch.test(location_data.location_name) || regSearch.test(location_data.pcode)) {
                frecords[location_id] = records[location_id];
                found = true;
              }
            }
            else {
              if (regSearch.test(location_data.location_name)) {
                frecords[location_id] = records[location_id];
                found = true;
              }
            }
          }

          if (found) {
            // Hide the error alert in case it is currently shown.s
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
          let state = Drupal.hpc_map_chloropleth.getMapState(map_id);
          let location_data = Drupal.hpc_map_chloropleth.getLocationDataById(state, location_id);
          this._input.value = location_data.location_name;
          if (state.admin_level == location_data.admin_level) {
            Drupal.hpc_map_chloropleth.showPopup(Drupal.hpc_map_chloropleth.getGeoJSON(location_data), state);
          }
          else {
            // Find the button and click it
            Drupal.hpc_map_chloropleth.changeAdminLevel(state, location_data.admin_level);
            setTimeout(function() {
              Drupal.hpc_map_chloropleth.showPopup(Drupal.hpc_map_chloropleth.getGeoJSON(location_data), state);
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

    // Add admin level selector.
    if (options.admin_level_selector) {
      locations_admin_level = state.data.locations.map(function(item) { return item.admin_level; });
      state.admin_level = Math.min.apply(Math, locations_admin_level);
      L.control.location_admin_level_selector_chloropleth({ position: 'bottomleft', locations: state.data.locations, active_level: state.admin_level}).addTo(map);
    }

    Drupal.hpc_map_chloropleth.buildMap(state);
    state.map.fitBounds(state.geojson.getBounds());

    var layer = L.tileLayer(options.map_tiles_url).addTo(state.map);

    // Add attribution.
    if (typeof options.disclaimer != 'undefined') {
      var map_disclaimer = L.control.attribution({prefix: options.disclaimer.text, position: options.disclaimer.position}).addTo(map);
    }

    tippy($('.leaflet-control-zoom-in').get(0), {
      content: Drupal.t('Zoom in'),
    });
    tippy($('.leaflet-control-zoom-out').get(0), {
      content: Drupal.t('Zoom out'),
    });
  }

  Drupal.hpc_map_chloropleth.searchSourceData = function(text, callResponse) {
    let state = Drupal.hpc_map_chloropleth.getMapState(this._map._container.id);

    callResponse(state.search_index);
    return {
      //called to stop previous requests on map move
      abort: function() {
      }
    };
  }

  /**
   * Get the GeoJSON data for the given location.
   *
   * @param object location
   *   The location object.
   *
   * @returns object
   *   The feature data.
   */
  Drupal.hpc_map_chloropleth.getGeoJSON = function(location) {
    if (typeof this.storage == 'undefined') {
      this.storage = []
    }
    if (typeof this.storage[location.filepath] == 'undefined') {
      let feature = null;
      $.ajax({
        dataType: 'json',
        url: location.filepath,
        success: function (data) {
          feature = data.features[0];
          feature.properties.location_id = location.location_id;
          feature.properties.location_name = location.location_name;
          feature.properties.object_count = location.object_count;
          feature.properties.admin_level = location.admin_level;
        },
        async: false
      });
      this.storage[location.filepath] = feature;
    }
    return this.storage[location.filepath];
  }

  // Build the map, add geojson.
  Drupal.hpc_map_chloropleth.buildMap = function(state) {
    let locations = state.data.locations.filter(function(d) {
      return d.admin_level == state.admin_level;
    });
    if (!locations.length) {
      return false;
    }

    let geojson_features = locations.map(item => Drupal.hpc_map_chloropleth.getGeoJSON(item));
    if (!geojson_features.length) {
      return false;
    }

    if (typeof state.geojson != 'undefined') {
      state.map.removeLayer(state.geojson);
    }

    var geojson = L.geoJson({type: 'FeatureCollection', 'features': geojson_features}, {
      style: function(feature) {
        return Drupal.hpc_map_chloropleth.styleCallback(feature, state);
      },
      onEachFeature: Drupal.hpc_map_chloropleth.onEachFeature,
      filter: function(feature, layer) {
        return feature.properties.admin_level == state.admin_level;
      }
    }).bindTooltip(function (layer) {
      if (typeof layer == 'undefined' || typeof layer.feature == 'undefined' || typeof layer.feature.properties == 'undefined') {
        return;
      }
      return String(layer.feature.properties.location_name) + ' (' + layer.feature.properties.object_count + ')';
    }).addTo(state.map);

    // Add the location id to each location.
    geojson.eachLayer(function (layer) {
      if (typeof layer._path == 'undefined' || !layer._path) {
        return;
      }
      layer._path.setAttribute('location-id', layer.feature.properties.location_id);
      layer._path.setAttribute('layer-id', layer._leaflet_id);
    });

    state.geojson = geojson;

    // If searching is enabled, create our static search index.
    if (state.data.locations.length && state.options.search_enabled) {
      state.search_index = [];
      // Add all locations to our search index.
      for (var i in state.data.locations) {
        let location_data = state.data.locations[i];
        state.search_index.push({
          loc: location_data.latLng,
          location_id: location_data.location_id,
          location_name: location_data.location_name,
          pcode: location_data.pcode,
          admin_level: location_data.admin_level,
        });
      }
    }

    // Add legend.
    Drupal.hpc_map_chloropleth.createLegend(state);

    return state.geojson;
  }

  // Style callback.
  Drupal.hpc_map_chloropleth.styleCallback = function(feature, state) {
    let style = $.extend(Drupal.hpc_map_chloropleth.config.feature_style, {
      fillColor: Drupal.hpc_map_chloropleth.getColor(feature.properties.object_count, state),
      fillOpacity: feature.properties.object_count > 0 ? 0.6 : 0
    });
    return style;
  }

  // Color callback.
  Drupal.hpc_map_chloropleth.getColor = function(object_count, state) {
    let colors = Drupal.hpc_map_chloropleth.config.colors;
    let ranges = Drupal.hpc_map_chloropleth.getDataRanges(state);
    var color = colors[0];
    for (i in ranges) {
      let count = ranges[i];
      if (object_count >= count) {
        color = colors[i];
      }
    }
    return color;
  }

  // Get a dom element based on the given data object.
  Drupal.hpc_map_chloropleth.getElementFromDataFeature = function(feature, state) {
    let element = $('#' + state.map_id).find('[location-id=' + feature.properties.location_id + ']');
    return element.length ? element[0] : null;
  }

  // Assign callbacks to some events for every feature.
  Drupal.hpc_map_chloropleth.onEachFeature = function(feature, layer) {
    layer.on({
      mouseover: function(e) { return Drupal.hpc_map_chloropleth.highlightArea(e.target); },
      mouseout: function(e) { return Drupal.hpc_map_chloropleth.resetHighlightArea(e.target); },
      click: function(e) {
        var layer = e.target;
        let map_id = e.target._map._container.id;
        var state = Drupal.hpc_map_chloropleth.getMapState(map_id);
        Drupal.hpc_map_chloropleth.showPopup(layer.feature, state);
      }
    });
  }

  // Add the admin level selector to the given map.
  Drupal.hpc_map_chloropleth.addAdminLevelSelector = function(self, map) {
    let locations = self.options.locations;
    let active_level = self.options.active_level;
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
      L.DomEvent.addListener(button, 'click', self.changeAdminLevel, self);
      div.appendChild(button);

      // Add a tooltip to the control.
      tippy($(div).get(0), {
        content: Drupal.t('Select Admin Level View'),
      });
    }
    return div;
  }

  // Change the admin level.
  Drupal.hpc_map_chloropleth.changeAdminLevel = function(state, admin_level) {
    let button = $(state.map_container_class + ' .admin-level-select a[data-admin-level=' + admin_level + ']')[0];
    state.admin_level = button.getAttribute('data-admin-level');
    $(state.map_container_class + ' .admin-level-select-button').removeClass('active');
    $(button).addClass('active');
    Drupal.hpc_map_chloropleth.buildMap(state);
    if (state.options.popup_style == 'sidebar') {
      state.sidebar.hide();
    }
  }

  // Open a modal window.
  Drupal.hpc_map_chloropleth.showPopup = function(feature, state) {
    let map_id = state.map_id;
    popup_content = Drupal.hpc_map_chloropleth.areaModal(feature, state);

    if (state.options.popup_style == 'modal') {
      Drupal.attachBehaviors('.leaflet-modal');
      state.map.openModal(popup_content);
      state.map.on('modal.hide', function() {
        state.active_area = null;
      });
      Drupal.attachBehaviors($('#' + map_id).find('.modal').get(0));
    }
    else if (state.options.popup_style == 'sidebar') {
      // Set sidebar content and open it.
      popup_content.pcodes_enabled = state.options.pcodes_enabled;
      state.sidebar.setContent(Drupal.theme('mapPlanCard', popup_content));
      state.sidebar.show();
      state.sidebar.on('hidden', function() {
        let map_id = this._map._container.id;
        let state = Drupal.hpc_map_chloropleth.getMapState(map_id);
        if (state.active_area) {
          let dom_element = Drupal.hpc_map_chloropleth.getElementFromDataFeature(state.active_area, state);
          let layer_id = $(dom_element).attr('layer-id');
          state.active_area = null;
          if (layer_id && typeof state.geojson._layers[layer_id] != 'undefined') {
            Drupal.hpc_map_chloropleth.resetHighlightArea(state.geojson._layers[layer_id]);
          }
        }
      });
      Drupal.attachBehaviors($('#' + map_id).find('.leaflet-sidebar').get(0));

      let dom_element = Drupal.hpc_map_chloropleth.getElementFromDataFeature(feature, state);
      let layer_id = $(dom_element).attr('layer-id');
      if (layer_id && typeof state.geojson._layers[layer_id] != 'undefined') {
        Drupal.hpc_map_chloropleth.highlightArea(state.geojson._layers[layer_id], true, state);
      }

      // Add navigation behavior.
      $(state.sidebar._container).find('.navigation .link').on('click', function() {
        let location_id = $(this).data('location-id');
        var new_active_area = null;
        $.each(state.data.locations, function(i) {
          if (state.data.locations[i].location_id == location_id) {
            new_active_area = Drupal.hpc_map_chloropleth.getGeoJSON(state.data.locations[i]);
          }
        });
        if (new_active_area) {
          if (state.options.search_enabled) {
            // Clear the search input if the navigation is used.
            $(state.map_container_class + ' input.search-input').val('');
          }
          Drupal.hpc_map_chloropleth.showPopup(new_active_area, state);
        }
      });
    }
  }

  // Highlight an area on the map.
  Drupal.hpc_map_chloropleth.highlightArea = function(layer, clear_others, state) {
    clear_others = typeof clear_others != 'undefined' ? clear_others : false;
    if (clear_others) {
      state.geojson.eachLayer(function (layer) {
        state.geojson.resetStyle(layer);
      });
    }
    layer.setStyle(Drupal.hpc_map_chloropleth.config.feature_style_highlighted);

    if (!L.Browser.ie && !L.Browser.opera && !L.Browser.edge) {
        layer.bringToFront();
    }
  }

  // Reset an area to it's original style.
  Drupal.hpc_map_chloropleth.resetHighlightArea = function(layer) {
    let map_id = layer._map._container.id;
    var state = Drupal.hpc_map_chloropleth.getMapState(map_id);
    if (state.active_area && state.active_area.properties.location_id == layer.feature.properties.location_id) {
      // Skip if this is our currently active location.
      return;
    }
    state.geojson.resetStyle(layer);
  }

  // Retrieve the map ID for the given element.
  Drupal.hpc_map_chloropleth.getMapIdFromContainedElement = function(element) {
    return $(element).parents('.leaflet-container').attr('id');
  }

  // Get the map state for the given map id.
  Drupal.hpc_map_chloropleth.getMapState = function(map_id) {
    return Drupal.hpc_map_chloropleth.states.hasOwnProperty(map_id) ? Drupal.hpc_map_chloropleth.states[map_id] : {};
  }

  // Retrieve the map state for the given element.
  Drupal.hpc_map_chloropleth.getMapStateFromContainedElement = function(element) {
    var map_id = $(element).parents('.leaflet-container').attr('id');
    return Drupal.hpc_map_chloropleth.getMapState(map_id);
  }

  // Get a location data object by it's ID.
  Drupal.hpc_map_chloropleth.getLocationDataById = function(state, location_id) {
    let filtered_locations = state.data.locations.filter(function(location) {
      return location.location_id == location_id;
    });
    return filtered_locations.length ? filtered_locations[0] : null;
  }

  Drupal.hpc_map_chloropleth.formatMillions = function(d) {
    var num = parseInt(d) / 1e6;
    return formatCurrency(d3.round(num, 1)) + 'm USD';
  }

  // Create an object containing the contents for an area popup.
  Drupal.hpc_map_chloropleth.areaModal = function(feature, state) {
    var location_id = parseInt(feature.properties.location_id);
    let location_item = Drupal.hpc_map_chloropleth.getLocationDataById(state, location_id);
    let modal_contents = location_item.modal_content;
    state.active_area = feature;

    // Get previous and next items.
    let current_locations = [];
    if (state.options.admin_level_selector) {
      $.each(state.data.locations, function(i) {
        if (state.data.locations[i].admin_level != state.admin_level) {
          return;
        }
        current_locations.push(state.data.locations[i]);
      });
    }
    else {
      current_locations = state.data.locations;
    }
    current_locations.sort(function(a, b) {
      var a_name = a.location_name.toLowerCase();
      var b_name = b.location_name.toLowerCase();
      return ((a_name < b_name) ? -1 : ((a_name > b_name) ? 1 : 0));
    });
    var active_index = null;
    $.each(current_locations, function(i) {
      current_locations[i].location_id = current_locations[i].location_id;
      current_locations[i].location_name = current_locations[i].location_name;
      if (current_locations[i].location_id == location_id) {
        active_index = i;
      }
    });
    let next_index = active_index < current_locations.length - 1 ? active_index + 1 : 0;
    let previous_index = active_index > 0 ? active_index - 1 : current_locations.length - 1;

    return {
      location_data: modal_contents,
      title_heading: modal_contents.title_heading,
      title: modal_contents.title,
      content: modal_contents.content,
      next: next_index !== null ? current_locations[next_index] : null,
      previous: previous_index !== null ? current_locations[previous_index] : null,
      total_count: current_locations.length,
      current_index: active_index + 1,
      template: [
        '<div class="title-heading">{title_heading}</div>',
        '<div class="title">{title}</div>',
        '<div class="content">{content}</div>',
      ].join(''),
      wrapperTemplate: Drupal.hpc_map_chloropleth.config.modal.wrapperTemplate,
    }
  }

  Drupal.hpc_map_chloropleth.getDataRanges = function(state) {
    let object_counts = state.data.locations.filter(function(x) {
      return x.admin_level == state.admin_level;
    }).map(x => x.object_count);
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

  // Create the legend.
  Drupal.hpc_map_chloropleth.createLegend = function(state) {
    let ranges = Drupal.hpc_map_chloropleth.getDataRanges(state);
    var legend = $('<ul>');
    let colors = Drupal.hpc_map_chloropleth.config.colors;
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
      else if (index > 0) {
        let max = (ranges[next_index] - 1);
        text = min != max ? min.toString() + ' - ' + max.toString() : min.toString();
      }
      var legend_item = $('<li>');
      var legend_marker = $('<span>')
        .addClass('legend-marker')
        .css('background-color', colors[index]);
      legend_item.append(legend_marker);
      legend_item.append(text);
      $(legend).append(legend_item);
    }
    $(state.map_container_class + ' .map-legend').html(legend);
  }

})(jQuery, Drupal);
