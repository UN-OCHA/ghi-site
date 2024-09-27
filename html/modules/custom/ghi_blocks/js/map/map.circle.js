(function ($) {

  const root_styles = getComputedStyle(document.documentElement);

  Drupal.hpc_map_circle = Drupal.hpc_map_circle || {};

  Drupal.hpc_map_circle.config = {
    // Scales used to determine color.
    colors: [
      root_styles.getPropertyValue('--ghi-default-text-color'), // Data points with data.
      root_styles.getPropertyValue('--ghi-default-border-color'), // Data points without data.
      root_styles.getPropertyValue('--ghi-primary-color'), // Highlights.
    ],
    plan_type_colors: {
      'hrp': root_styles.getPropertyValue('--ghi-plan-type-hrp'),
      'fa': root_styles.getPropertyValue('--ghi-plan-type-fa'),
      'rrp': root_styles.getPropertyValue('--ghi-plan-type-rrp'),
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
  }

  // Get the color for the given data point.
  Drupal.hpc_map_circle.getColor = function(d, state, map_id) {
    if (map_id.indexOf('plan-overview-map') === 0 && d.hasOwnProperty('plan_type')) {
      let color = Drupal.hpc_map_circle.config.plan_type_colors;
      let color_key = d.plan_type.toLowerCase();
      return color.hasOwnProperty(color_key) ? color[color_key] : color['other'];
    }
    let color = Drupal.hpc_map_circle.config.colors;
    if (Drupal.hpc_map.isActiveLocation(d, state)) {
      return color[2];
    }
    if (Drupal.hpc_map.emptyValueForCurrentTab(d, map_id)) {
      return color[1];
    }
    return color[0];
  }

  // Get the color for the given data point.
  Drupal.hpc_map_circle.getOpacity = function(d, state, map_id) {
    let attrs = Drupal.hpc_map_circle.config.attrs;
    if (Drupal.hpc_map.isActiveLocation(d, state)) {
      return attrs.opacity_hover;
    }
    if (map_id.indexOf('plan-overview-map') === 0) {
      return attrs.opacity_overview;
    }
    return attrs.opacity;
  }

  // Get the base radius to be used.
  Drupal.hpc_map_circle.getBaseRadius = function(state) {
    let attrs = Drupal.hpc_map_circle.config.attrs;
    if (typeof state.options.base_radius != 'undefined') {
      return state.options.base_radius;
    }
    return attrs.base_radius;
  }

  // Get the location offset for a data point, depending on the offset chain.
  Drupal.hpc_map_circle.getLocationOffset = function(d, state, scale) {
    let offset = 0;
    let offset_chain = d.hasOwnProperty('offset_chain') ? d.offset_chain : [];
    if (offset_chain.length == 0) {
      return 0;
    }
    let base_radius = Drupal.hpc_map_circle.getBaseRadius(state);
    for (object_id of offset_chain) {
      if (object_id == d.object_id) {
        continue;
      }
      let location_data = Drupal.hpc_map.getLocationDataById(state, object_id);
      let radius = Drupal.hpc_map.getRadius(location_data, scale, base_radius/scale);
      offset += radius + base_radius/scale * 2.5;
    }
    if (d.location_id == 206) {
      console.log(offset, d);
    }
    return offset;
  }

  // Create or update the legend items.
  Drupal.hpc_map_circle.updateLegend = function(state) {
    let map_id = state.map_id;
    let colors = Drupal.hpc_map_circle.config.plan_type_colors;

    // Remove old legend items.
    d3.select('#' + map_id + '-legend ul')
      .selectAll('li')
      .data([])
      .exit()
      .remove();

    let dedicated_plan_types = ['hrp', 'fa'];
    let plan_types = $('#' + map_id + ' .leaflet-overlay-pane svg[plan-type]').map(function() {
      let plan_type = $(this).attr('plan-type');
      return dedicated_plan_types.indexOf(plan_type) != -1 ? plan_type.toUpperCase() : Drupal.t('Other');
    }).toArray().filter(function (value, index, self) {
      return self.indexOf(value) === index;
    });
    sorted_plan_types = ['HRP', 'FA', 'Other'];
    plan_types = sorted_plan_types.filter(v => plan_types.includes(v));

    var legend_items = [];
    for (legend_key of Object.keys(state.options.legend)) {
      legend_items.push({
        'label': state.options.legend[legend_key],
        'type': legend_key,
      });
    }

    // Set the legend caption.
    if (state.options.legend_caption) {
      let legend_caption = $('#' + map_id + '-legend div.legend-caption');
      if (!legend_caption.length) {
        $('#' + map_id + '-legend').prepend($('<div>').addClass('legend-caption'));
      }
      $('#' + map_id + '-legend div.legend-caption').text(state.options.legend_caption);
    }

    // Add new legend items.
    var items = d3.select('#' + map_id + '-legend ul')
      .selectAll('li')
      .data(legend_items)
      .enter()
      .append('li')
      .attr('class', 'legend-item');

    // Build new legend items.
    items.each(function(d, i) {
      d3.select(this)
        .append('div')
        .style('background-color', colors[d.type])
        .attr('class', function() {
          let classes = [
            'legend-icon',
            d.type ? 'legend-icon-' + d.type : null,
          ].filter((el) => el !== null);
          return classes.join(' ');
        });
      d3.select(this)
        .append('div')
        .attr('class', 'legend-label')
        .text(d.label);
    });
  }

  // Focus a location.
  Drupal.hpc_map_circle.focusLocation = function(element, state) {
    if (!element) {
      return;
    }
    var map_state = Drupal.hpc_map.getMapStateFromContainedElement(element);
    let map_id = map_state.map_id;

    let base_radius = Drupal.hpc_map_circle.getBaseRadius(map_state);

    var proj = map_state.locationOverlay.projection,
        scale = proj.layer._scale,
        radius = base_radius/scale,
        attrs = Drupal.hpc_map_circle.config.attrs;

    if (state == 1) {
      // Unfocus all currently focused elements.
      $('#' + map_state.map_id).find('circle.active').each(function(i, el) {
        Drupal.hpc_map_circle.focusLocation(el, 0);
      });

      $(element).attr('class', 'active');

      d3.select(element)
        .transition()
        .duration(250)
        .attr('fill', d => Drupal.hpc_map_circle.getColor(d, map_state, map_id))
        .attr('r', d => Drupal.hpc_map.getRadius(d, scale, radius) + base_radius/scale)
        .attr('opacity', d => Drupal.hpc_map_circle.getOpacity(d, map_state, map_id));
    }
    else {

      if (map_state.options.popup_style == 'sidebar' && typeof map_state.active_location != 'undefined' && map_state.active_location) {
        // Check if the sidebar is currently open and showing the element that
        // should be unfocused.
        if (map_state.sidebar.isVisible() && map_state.active_location.object_id == $(element).attr('object-id')) {
          return;
        }
      }
      $(element).attr('class', '');
      d3.select(element)
        .transition()
        .duration(250)
        .attr('stroke', attrs.stroke)
        .attr('cursor', attrs.cursor)
        .attr('opacity', d => Drupal.hpc_map_circle.getOpacity(d, map_state, map_id))
        .attr('fill', d => Drupal.hpc_map_circle.getColor(d, map_state, map_id))
        .attr('r', d => Drupal.hpc_map.getRadius(d, scale, radius));
    }
  }

  Drupal.hpc_map_circle.showLocationTooltip = function(map_id, state, d, x, y) {
    $('#' + map_id + ' .map-circle-tooltip').css("top", y);
    $('#' + map_id + ' .map-circle-tooltip').css("left", x);
    $('#' + map_id + ' .map-circle-tooltip').css("z-index", 100000);
    let tooltip = '<b>Location:</b> ' + d.location_name + '<br />';
    if (d.hasOwnProperty('offset_chain') && d.offset_chain.length && d.hasOwnProperty('plan_type')) {
      tooltip += '<b>Plan type :</b> ' + state.options.legend[d.plan_type];
    }
    if (typeof state.tab_data.metric != 'undefined') {
      tooltip += '<b>Total ' + state.tab_data.metric.name.en.toLowerCase() +':</b> ' + Drupal.theme('number', d.total);
    }
    $('#' + map_id + ' .map-circle-tooltip').html(tooltip);
  }

  Drupal.hpc_map_circle.createLocations = function(data, sel, proj) {

    var map_id = proj.map._container.id;
    var state = Drupal.hpc_map.getMapState(map_id);

    let base_radius = Drupal.hpc_map_circle.getBaseRadius(state);

    var attrs = Drupal.hpc_map_circle.config.attrs,
        scale = proj.layer._scale,
        radius = base_radius/scale,
        strokeWidth = 1/scale;

    // Classic circles.
    sel.selectAll('circle')
      .data(data, function (d) {
        return d.object_id;
      })
      .join(
        function (enter) {
          enter.append('circle')
          .attr('cx', d => proj.latLngToLayerPoint(d.latLng).x + Drupal.hpc_map_circle.getLocationOffset(d, state, scale))
          .attr('cy', d => proj.latLngToLayerPoint(d.latLng).y)
          .attr('fill', d => Drupal.hpc_map_circle.getColor(d, state, map_id))
          .attr('stroke', attrs.stroke)
          .attr('opacity', 0)
          .attr('r', 0)
          .attr('object-id', d => d.object_id)
          .attr('id', d => map_id + '--' + d.object_id)
          .transition()
          .duration(500)
          .attr('cursor', attrs.cursor)
          .attr('fill', d => Drupal.hpc_map_circle.getColor(d, state, map_id))
          .attr('r', d => Drupal.hpc_map.getRadius(d, scale, radius))
          .attr('stroke-width', strokeWidth)
          .attr('opacity', d => Drupal.hpc_map_circle.getOpacity(d, state, map_id));
        },
        function(update) {
          update.transition()
          .duration(500)
          .attr('cx', d => proj.latLngToLayerPoint(d.latLng).x + Drupal.hpc_map_circle.getLocationOffset(d, state, scale))
          .attr('stroke', attrs.stroke)
          .attr('cursor', attrs.cursor)
          .attr('fill', d => Drupal.hpc_map_circle.getColor(d, state, map_id))
          .attr('r', d => Drupal.hpc_map.getRadius(d, scale, radius))
          .attr('stroke-width', strokeWidth)
          .attr('opacity', d => Drupal.hpc_map_circle.getOpacity(d, state, map_id));
        },
        function(exit) {
          exit.transition()
            .duration(500)
            .attr('opacity', 0)
            .remove();
        }
      );

    $('.map-container #' + map_id).append("<div class='map-circle-tooltip' style='position: absolute;'></div>");
    $('#' + map_id + ' .map-circle-tooltip').css("display", "none");

    if (!sel.selectAll('#' + map_id + ' use').size()) {
      sel.append('use');
    }

    // Add event listeners.
    sel.selectAll('circle').on('click', function(event, d) {
      if (!d) {
        return;
      }
      var state = Drupal.hpc_map.getMapStateFromContainedElement(this);
      Drupal.hpc_map.showPopup(d, state);
      Drupal.hpc_map_circle.focusLocation(this, 1);
    })
    .on("mouseover", function(event, d) {
      if (!d) {
        return;
      }
      Drupal.hpc_map_circle.focusLocation(this, 1);
      $('#' + map_id + ' .map-circle-tooltip').css("display", "");
    })
    .on('mousemove', function(event, d) {
      if (!d) {
        return;
      }
      var xPosition = event.layerX + 10;
      var yPosition = event.layerY + 10;
      Drupal.hpc_map_circle.showLocationTooltip(map_id, state, d, xPosition, yPosition);
    })
    .on('mouseout', function(event, d) {
      if (!d) {
        return;
      }
      Drupal.hpc_map_circle.focusLocation(this, 0);
      $('#' + map_id + ' .map-circle-tooltip').css("display", "none");
    });

    // Special handling for the use element.
    sel.selectAll('use').on('click', function(event) {
      // let element = $(event.target.href.baseVal);
      let element = Drupal.hpc_map.getElementFromUseElement($(event.target.href.baseVal));
      if (!element) {
        return;
      }
      var state = Drupal.hpc_map.getMapStateFromContainedElement(element);
      let d = Drupal.hpc_map.getLocationObjectFromContainedElement(element);
      Drupal.hpc_map.showPopup(d, state);
      Drupal.hpc_map_circle.focusLocation(element, 1);
    })
    .on("mouseover", function(event) {
      let element = Drupal.hpc_map.getElementFromUseElement($(event.target.href.baseVal));
      if (!element) {
        return;
      }
      Drupal.hpc_map_circle.focusLocation(element, 1);
      $('#' + map_id + ' .map-circle-tooltip').css("display", "");
    })
    .on('mousemove', function(event) {
      let element = Drupal.hpc_map.getElementFromUseElement($(event.target.href.baseVal));
      if (!element) {
        return;
      }
      var state = Drupal.hpc_map.getMapStateFromContainedElement(element);
      let d = Drupal.hpc_map.getLocationObjectFromContainedElement(element);
      var xPosition = event.layerX + 10;
      var yPosition = event.layerY + 10;
      Drupal.hpc_map_circle.showLocationTooltip(map_id, state, d, xPosition, yPosition);
    })
    .on('mouseout', function(event) {
      let element = Drupal.hpc_map.getElementFromUseElement($(event.target.href.baseVal));
      if (!element) {
        return;
      }
      Drupal.hpc_map_circle.focusLocation(element, 0);
      $('#' + map_id + ' .map-circle-tooltip').css("display", "none");
    });

  }

})(jQuery, Drupal);
