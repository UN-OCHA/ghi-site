(function ($) {

  Drupal.hpc_map_donut = Drupal.hpc_map_donut || {};

  Drupal.hpc_map_donut.config = {
    colors: [
      '#ee7325', // HPC orange for the first segment
      '#f6b891', // Lighter orange
    ],
    empty_color: '#919191',
    donut_attrs: {
      'stroke': '#fff',
      'cursor': 'pointer',
      'opacity': 1,
    },
    chart_attrs: {
      'stroke': '#fff',
      'cursor': 'pointer',
      'opacity': 1,
    },
    circle_attrs: {
      'stroke': 'transparent',
      'cursor': 'pointer',
      'opacity': 0.5,
    },
    text_attrs: {
      'text-anchor': 'middle',
      'alignment-baseline': 'central',
      'fill': '#464646',
    },
    donut_opts: {
      'min_radius': 7,
      'min_radius_text': 16,
      'duration': 400,
    }
  }

  // Callback to check if a given data item for a chart has data.
  Drupal.hpc_map_donut.hasData = function(d, state) {
    let data_indexes = state.active_donut_segments;
    let segment_1 = parseInt(data_indexes[0]);
    let segment_2 = parseInt(data_indexes[1]);
    let has_data = d.object[state.index][segment_1] > 0 || d.object[state.index][segment_2] > 0;
    return has_data;
  }

  // Get the label for a donut.
  Drupal.hpc_map_donut.getLabel = function(d, state) {
    let donut_opts = Drupal.hpc_map_donut.config.donut_opts;
    if (state.options.hasOwnProperty('donut_opts')) {
      donut_opts = Object.assign(donut_opts, state.options.donut_opts);
    }
    let label = d.labels[state.active_donut_display_value];
    let min_radius = donut_opts.hasOwnProperty('min_radius_text') ? donut_opts.min_radius_text : donut_opts.min_radius_text;
    return label && d.r * Math.sqrt(d.scale) >= min_radius ? label : null;
  }

  // Create or update the legend items.
  Drupal.hpc_map_donut.updateLegend = function(state) {
    let map_id = state.map_id;
    let colors = Drupal.hpc_map_donut.config.colors;
    let map_style_config = state.options.map_style_config;

    // Remove old legend items.
    d3.select('#' + map_id + '-legend ul')
      .selectAll('li')
      .data([])
      .exit()
      .remove();

    var legend_items = [];
    for (segment_index of state.active_donut_segments) {
      if (!state.tab_data.legend.hasOwnProperty(segment_index)) {
        continue;
      }
      legend_items.push(state.tab_data.legend[segment_index]);
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
        .style('background-color', colors[colors.length - 1 - i])
        .attr('class', 'legend-icon');
      d3.select(this)
        .append('div')
        .attr('class', 'legend-label')
        .text(d);
    });
  }

  // Redraw the given element in either active or inactive state.
  Drupal.hpc_map_donut.redrawElementWithState = function(element, draw_state) {
    let donut_opts = Drupal.hpc_map_donut.config.donut_opts,
        chart_attrs = Drupal.hpc_map_donut.config.chart_attrs;

    d3.select(element)
      .selectAll('path')
        .transition()
        .duration(donut_opts.duration / 2)
        .attr('d', Drupal.hpc_map_donut.arc)
        // There is something odd with the opacity, or with the order of
        // function calls that lead here and further. For the moment, instead
        // of relying on setting the opacity here, add SASS rule has been added
        // to the theme (see ocha_basic/sass/custom-hpcviewer/_map.scss:446).
        .attr('opacity', d => d.value ? (draw_state ? 1 : chart_attrs.opacity): 0);

    // Also make sure the element is on top of others.
    if (draw_state) {
      Drupal.hpc_map.moveLocationToFront(element);
    }
  }

  // Focus a location.
  Drupal.hpc_map_donut.focusLocation = function(element, state, scale) {
    let map_state = Drupal.hpc_map.getMapStateFromContainedElement(element);
    if (map_state.disabled) {
      return;
    }

    if (state === 1) {
      Drupal.hpc_map_donut.redrawElementWithState(element, true);
    }
    else {

      if (map_state.options.popup_style == 'sidebar' && map_state.active_location) {
        // Check if the sidebar is currently open and showing the element that
        // should be unfocused.
        if (map_state.sidebar.isVisible() && map_state.active_location.location_id == $(element).attr('location-id')) {
          return;
        }
      }

      Drupal.hpc_map_donut.redrawElementWithState(element, false);
    }
  }

  // Calculate the inner radius of a donut.
  Drupal.hpc_map_donut.innerRadius = function(radius, stroke_width, scale) {
    return radius - (stroke_width + scale + radius) / (radius / 3 + scale);
  }

  // Callback for pie layout calculation.
  Drupal.hpc_map_donut.pie = d3.pie()
    .value(function(d, i) { return d ? d : 0; })
    .sort(null);

  // Callback for the arc calculation.
  Drupal.hpc_map_donut.arc = d3.arc()
    .innerRadius(function (d) { return d.innerRadius; })
    .outerRadius(function (d) {
      // Check if we have an active location and make it bigger.
      let state = Drupal.hpc_map.getMapState(d.object.map_id);
      let is_focussed = state.focused_location && state.focused_location.location_id == d.object.location_id;
      let is_active = state.active_location && state.active_location.location_id == d.object.location_id;
      if (is_focussed || is_active) {
        return d.radius + d.strokeWidth;
      }
      return d.radius + d.strokeWidth / 2;
    });

    // Callback for the transition of existing arcs.
    Drupal.hpc_map_donut.arcTween = function(d) {
      if (!this._current) {
        this._current.data = 0;
      }
      var interp = d3.interpolate(this._current, d);
      this._current = interp(0);
      return function(t) {
        var tmp = interp(t);
        return Drupal.hpc_map_donut.arc(tmp);
      }
    }

  /**
   * Create the location objects for the map.
   */
  Drupal.hpc_map_donut.createLocations = function(data, sel, proj) {
    var donut_attrs = Drupal.hpc_map_donut.config.donut_attrs,
        chart_attrs = Drupal.hpc_map_donut.config.chart_attrs,
        circle_attrs = Drupal.hpc_map_donut.config.circle_attrs,
        text_attrs = Drupal.hpc_map_donut.config.text_attrs,
        donut_opts = Drupal.hpc_map_donut.config.donut_opts,
        scale = proj.layer._scale,
        base_radius = 10;

    var color = Drupal.hpc_map_donut.config.colors;
    var map_id = proj.map._container.id;
    var state = Drupal.hpc_map.getMapState(map_id);
    let min_radius = donut_opts.min_radius / scale;

    if (state.options.hasOwnProperty('donut_opts')) {
      donut_opts = Object.assign(donut_opts, state.options.donut_opts);
    }

    // This will hold the segments per donut.
    var donut_data = [];

    // Map the complex nested data array into an array of segments
    for (i = 0; i < data.length; i++) {
      let object = data[i];
      object.map_id = state.map_id;

      let group_keys = Object.keys(object[state.index]);
      let primary_key = group_keys[0];

      let r = object[state.index][primary_key] > 0 ? Drupal.hpc_map.getRadius(object, 1, base_radius, object.radius_factor / scale, min_radius / Math.sqrt(scale)) : min_radius;
      let x = proj.latLngToLayerPoint(object.latLng).x;
      let y = proj.latLngToLayerPoint(object.latLng).y;

      // This will hold the segments for this specific donut.
      var segments = [];

      var donut_group = d3.select('.donut[location-id="' + object.location_id + '"] g');
      var translate_x = r * 4;
      var translate_y = r * 4;
      var scale_x = 1 / scale;
      var scale_y = 1 / scale;
      if (donut_group._groups[0] != null && donut_group._groups[0][0] != null) {
        t = Drupal.hpc_map.transform(donut_group.attr('transform'));
        translate_x = t.translateX;
        translate_y = t.translateY;
        scale_x = t.scaleX;
        scale_y = t.scaleY;
      }

      var segment_indexes = [];
      if (typeof state.options.map_style_config != 'undefined') {
        segment_indexes = state.active_donut_segments;
      }
      else {
        // Legacy, probably not really needed anymore.
        var segment_count = object[state.index].length;
        for (j = segment_count - 1; j >= 0; j--) {
          segment_indexes.push(j);
        }
      }

      var strokeWidth = r / Math.log(base_radius);
      strokeWidth = strokeWidth > 1 ? strokeWidth : 1;
      strokeWidth = strokeWidth < 8 ? strokeWidth : 8;

      // The segments need to be traversed on the reverse order, assuming that
      // the first segment represents the full circle, and following segments
      // represent parts of the circle. Only 2 segments supported at the
      // moment.
      for (segment_index = segment_indexes.length - 1; segment_index >= 0; segment_index--) {
        var segment = {
          object: object,
          r: r,
          x: x - translate_x,
          y: y - translate_y,
          strokeWidth: strokeWidth,
          color: color[segment_indexes.length - 1 - segment_index],
        };

        // Note: This currently only works with a maximum of 2 segments.
        // for (index in data) {
        if (segment_index == 0) {
          let current_index = parseInt(segment_indexes[segment_index]);
          let next_index = parseInt(segment_indexes[segment_index + 1]);
          value = object[state.index][next_index] ? object[state.index][current_index] - object[state.index][next_index] : object[state.index][current_index];
          if (!value || value < 0) {
            // If the second segment is higher than the first one, the value
            // would be negative, resulting in an empty looking or otherwhise
            // wrong donut. Prevent this by setting the value to 0.
            value = 0;
          }
        }
        else {
          // This is the actual second segment, which might be a measurement,
          // in which case we have to take the currently selected monitoring
          // period into account.
          value = object[state.index][parseInt(segment_indexes[segment_index])];
        }
        segment[state.index] = value;
        segments.push(segment);
      }

      var args = segments.map(x => x[state.index]);
      var pie_value = Drupal.hpc_map_donut.pie(args);
      // We need to add radius, strokewidth and color to each path in the arc.
      pie_value.map(function(v, i) {
        v.radius = segments[i].r;
        v.strokeWidth = segments[i].strokeWidth;
        v.scale = scale;
        v.innerRadius = Drupal.hpc_map_donut.innerRadius(v.radius, v.strokeWidth, v.scale);
        v.color = segments[i].color;
        // Also add the object to be able to identify it in subroutines.
        v.object = object;
        return v;
      });

      let values = object[state.index];
      let whole_segment_key = parseInt(state.active_donut_segments[0]);
      let partial_segment_key = parseInt(state.active_donut_segments[1]);

      let labels = {
        'percentage': Drupal.theme('percent', values[partial_segment_key] / values[whole_segment_key]),
        'partial': Drupal.theme('number', values[partial_segment_key], true),
        'full': Drupal.theme('number', values[whole_segment_key], true),
      };

      donut_data.push({
        labels: labels,
        font_size: r / 2 - 2 / Math.sqrt(scale),
        width: r * 8,
        height: r * 8,
        r: r,
        innerRadius: Drupal.hpc_map_donut.innerRadius(r, strokeWidth, scale),
        x: x - translate_x,
        y: y - translate_y,
        transform: 'translate(' + translate_x + ',' + translate_y + ') scale(' + scale_x + ', ' + scale_y + ')',
        strokeWidth: strokeWidth,
        scale: scale,
        object: object,
        segments: segments,
        pie_value: pie_value
      });
    }

    // Now, based on the donut_data array, create a container for each donut in
    // the dataset, add circles, text and donuts.
    sel.selectAll('#' + map_id + ' g')
      .data(donut_data)
      .join(
        function(enter) {
          let group = enter.append("svg")
            .attr('opacity', donut_attrs.opacity)
            .attr('stroke', 'transparent')
            .attr('location-id', d => d.object.location_id)
            .attr('location-name', d => d.object.location_name)
            .attr('x', d => d.x)
            .attr('y', d => d.y)
            .attr('id', d => map_id + '--' + d.object.location_id)
          .append("g")
            .attr("class", d => Drupal.hpc_map_donut.hasData(d, state) ? 'has-data' : 'empty')
            .attr('width', d => d.width)
            .attr('height', d => d.height)
            .attr('location-id', d => d.object.location_id)
            .attr('stroke', donut_attrs.stroke)
            .attr('cursor', donut_attrs.cursor)
            .attr('opacity', donut_attrs.opacity)
            .attr('stroke-width', d => d.strokeWidth / 50)
            .attr('transform', d => d.transform);

          group.append('circle')
            .attr('stroke', circle_attrs.stroke)
            .attr('cursor', circle_attrs.cursor)
            .attr('opacity', circle_attrs.opacity)
            .attr("class", d => Drupal.hpc_map_donut.hasData(d, state) ? 'has-data' : 'empty')
            .attr('location-id', d => d.object.location_id)
            .attr('cx', 0)
            .attr('cy', 0)
            .attr('fill-opacity', donut_opts.hasOwnProperty('circle_opacity') ? donut_opts.circle_opacity : donut_attrs.opacity)
            .attr('opacity', donut_opts.hasOwnProperty('circle_opacity') ? donut_opts.circle_opacity : donut_attrs.opacity)
            .attr('fill', d => Drupal.hpc_map_donut.hasData(d, state) ? '#fff' : Drupal.hpc_map_donut.config.empty_color)
            .attr('r', d => Drupal.hpc_map_donut.hasData(d, state) ? d.innerRadius : base_radius / Math.sqrt(scale))
            .attr('stroke', d => Drupal.hpc_map_donut.hasData(d, state) ? 'transparent' : Drupal.hpc_map_donut.config.empty_color)
            .attr('stroke-width', function(d) {
              if (Drupal.hpc_map_donut.hasData(d, state)) {
                return  '0';
              }
              if (Drupal.hpc_map.isActiveLocation(d.object, state)) {
                return 3 / Math.sqrt(scale);
              }
              return 1 / Math.sqrt(scale);
            });

          group.selectAll('path')
            .data(d => d.pie_value)
            .enter()
            .append("path")
              .attr("d", Drupal.hpc_map_donut.arc)
              .attr('location-id', d => d.object.location_id)
              .style("fill", d => d.color)
              .attr('opacity', d => d.value ? chart_attrs.opacity : 0)
            .each(function(d) {
              this._current = d;
            });

          group.append('text')
            .attr('location-id', d => d.object.location_id)
            .attr('class', 'donut-label')
            .attr('x', '0')
            .attr('y', '0')
            .attr('text-anchor', text_attrs['text-anchor'])
            .attr('alignment-baseline', text_attrs['alignment-baseline'])
            .attr('fill', text_attrs.fill)
            .attr('stroke-width', '0')
            .attr('opacity', 1)
            .attr('font-size', d => d.font_size)
            .text(d => Drupal.hpc_map_donut.getLabel(d, state));
        },
        function(update) {
          t = d3.transition()
            .duration(donut_opts.duration);

          update.select('g')
            .attr("class", d => Drupal.hpc_map_donut.hasData(d, state) ? 'has-data' : 'empty')
            .transition(t)
              .attr('width', d => d.width)
              .attr('height', d => d.height)
              .attr('stroke', donut_attrs.stroke)
              .attr('cursor', donut_attrs.cursor)
              .attr('opacity', donut_attrs.opacity)
              .attr('stroke-width', d => d.strokeWidth / 50)
              .attr("transform", function(d) {
                t = Drupal.hpc_map.transform(d.transform);
                return 'translate(' + t.translateX + ',' + t.translateY + ') scale(' + 1 / d.scale + ', ' + 1 / d.scale + ')';
              });

          update.select('circle')
            .attr("class", d => Drupal.hpc_map_donut.hasData(d, state) ? 'has-data' : 'empty')
            .transition(t)
              .attr('stroke', circle_attrs.stroke)
              .attr('cursor', circle_attrs.cursor)
              .attr('opacity', circle_attrs.opacity)
              .attr('location-id', d => d.object.location_id)
              .attr('fill-opacity', donut_opts.hasOwnProperty('circle_opacity') ? donut_opts.circle_opacity : donut_attrs.opacity)
              .attr('opacity', donut_opts.hasOwnProperty('circle_opacity') ? donut_opts.circle_opacity : donut_attrs.opacity)
              .attr('fill', d => Drupal.hpc_map_donut.hasData(d, state) ? '#fff' : Drupal.hpc_map_donut.config.empty_color)
              .attr('r', d => Drupal.hpc_map_donut.hasData(d, state) ? d.innerRadius : base_radius / Math.sqrt(scale))
              .attr('stroke', d => Drupal.hpc_map_donut.hasData(d, state) ? 'transparent' : Drupal.hpc_map_donut.config.empty_color)
              .attr('stroke-width', function(d) {
                if (Drupal.hpc_map_donut.hasData(d, state)) {
                  return 0;
                }
                if (Drupal.hpc_map.isActiveLocation(d.object, state)) {
                  return 3 / Math.sqrt(scale);
                }
                return 1 / Math.sqrt(scale);
              });

          update.selectAll('path')
            .data(function(d) {
              return d.pie_value;
            })
            .transition(t)
              .attrTween("d", Drupal.hpc_map_donut.arcTween)
            .transition(t)
              .attr('opacity', d => d.value ? chart_attrs.opacity : 0)
              .attr('location-id', d => d.object.location_id)
              .style("fill", d => d.color)

          update.select('text')
            .attr('location-id', d => d.object.location_id)
            .transition()
              .duration(donut_opts.duration / 2)
              .attr('opacity', function(d, i, j) {
                let label = Drupal.hpc_map_donut.getLabel(d, state);
                return j[i].innerHTML != label ? 0 : (label ? 1 : 0);
              })
            .transition(t)
              .attr('opacity', d => Drupal.hpc_map_donut.getLabel(d, state) ? 1 : 0)
              .attr('font-size', d => d.font_size)
              .text(d => Drupal.hpc_map_donut.getLabel(d, state));

        },
        function(exit) {
          exit.transition()
            .duration(donut_opts.duration)
            .attr('opacity', 0)
            .remove();
        }
    );

    if (!sel.selectAll('#' + map_id + ' use').size()) {
      sel.append('use');
    }

    // Add event listeners.
    sel.selectAll('#' + map_id + ' svg').on('click', function(event, d) {
      var state = Drupal.hpc_map.getMapStateFromContainedElement(this);
      Drupal.hpc_map.setActiveLocation(this, d.object, state);
      Drupal.hpc_map.showPopup(d.object, state);
    })
    .on('mouseenter', function() {
      let map_state = Drupal.hpc_map.getMapStateFromContainedElement(this);
      Drupal.hpc_map.focusLocation(this, map_state, 1);
    })
    .on('mouseout', function() {
      let map_state = Drupal.hpc_map.getMapStateFromContainedElement(this);
      Drupal.hpc_map.focusLocation(this, map_state, 0);
    });
  }

})(jQuery, Drupal);
