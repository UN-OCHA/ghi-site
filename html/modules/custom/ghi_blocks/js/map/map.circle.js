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
      offset += radius + (base_radius + 3)/scale;
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
        .attr('data-type', d.type)
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
      Drupal.hpc_map_circle.hideLocationTooltip(map_id);
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

  Drupal.hpc_map_circle.showLocationTooltip = function(map_id, state, d, event) {
    $('#' + map_id + ' .map-circle-tooltip').css('position', 'fixed');
    $('#' + map_id + ' .map-circle-tooltip').css('left', event.clientX + 10);
    $('#' + map_id + ' .map-circle-tooltip').css('top', event.clientY + 10);
    $('#' + map_id + ' .map-circle-tooltip').css('z-index', 100000);
    let tooltip = d.hasOwnProperty('tooltip') ? d.tooltip : null;
    if (tooltip === null) {
      tooltip = '<b>Location:</b> ' + d.location_name;
      if (typeof state.tab_data.metric != 'undefined') {
        tooltip += '<br /><b>Total ' + state.tab_data.metric.name.en.toLowerCase() +':</b> ' + Drupal.theme('number', d.total);
      }
    }
    let index = state.index;
    if (d.hasOwnProperty('tooltip_values') && d.tooltip_values.hasOwnProperty(index) && d.hasOwnProperty(index)) {
      tooltip += '<br />' + d.tooltip_values[index].label + ': ' + d.tooltip_values[index]['value'];
    }
    $('#' + map_id + ' .map-circle-tooltip').html(tooltip);
    $('#' + map_id + ' .map-circle-tooltip').css('display', 'block');
  }

  Drupal.hpc_map_circle.hideLocationTooltip = function(map_id) {
    $('#' + map_id + ' .map-circle-tooltip').css('display', 'none');
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
          .attr('legend-type', d => state.options.legend ? d.plan_type : null)
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

    let $tooltip = $('.map-circle-tooltip');
    if (!$tooltip.length) {
      $('.map-container #' + map_id).append("<div class='map-circle-tooltip' style='position: absolute; top: 0; left: 0;'></div>");
      $tooltip = $('.map-circle-tooltip');
    }
    $tooltip.css('display', 'none');

    if (!sel.selectAll('#' + map_id + ' use').size()) {
      sel.append('use');
    }

    // Add event listeners.
    sel.selectAll('#' + map_id + ' circle[object-id], #' + map_id + ' use').on('click', function(event, d) {
      var state = Drupal.hpc_map.getMapStateFromContainedElement(this);
      if (!d && event.target) {
        location_object = Drupal.hpc_map.getLocationObjectFromUseElement(event.target);
      }
      else {
        location_object = d;
      }
      Drupal.hpc_map.setActiveLocation(this, location_object, state);
      Drupal.hpc_map.showPopup(location_object, state);
    })
    .on('mousemove', function(event, d) {
      if (!d && event.target) {
        location_object = Drupal.hpc_map.getLocationObjectFromUseElement(event.target);
      }
      else {
        location_object = d;
      }
      if (!location_object) {
        return;
      }
      Drupal.hpc_map_circle.showLocationTooltip(map_id, state, location_object, event);
    })
    .on('mouseenter', function(event, d) {
      var state = Drupal.hpc_map.getMapStateFromContainedElement(this);
      Drupal.hpc_map.focusLocation(this, state, 1);

    })
    .on('mouseout', function(event, d) {
      var state = Drupal.hpc_map.getMapStateFromContainedElement(this);
      Drupal.hpc_map.focusLocation(this, state, 0);
    });

    // Make sure that tooltips are hidden and spots are unfocused when not
    // hovering over any circle.
    $('#' + map_id).on('mousemove', function(event) {
      let state = Drupal.hpc_map.getMapState(map_id);
      let location_object = Drupal.hpc_map.getLocationObjectFromContainedElement(event.target);
      let focused_element = state.focused_location ? Drupal.hpc_map.getElementFromDataObject(state.focused_location, state) : null;
      if (!location_object && focused_element) {
        Drupal.hpc_map.focusLocation(focused_element, state, 0);
        return;
      }
    })
  }

})(jQuery, Drupal);
