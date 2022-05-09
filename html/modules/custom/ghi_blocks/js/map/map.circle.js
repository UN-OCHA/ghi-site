(function ($) {

  Drupal.hpc_map_circle = Drupal.hpc_map_circle || {};

  Drupal.hpc_map_circle.config = {
    // Scales used to determine color.
    colors: [
      '#026CB6', // OCHA blue für data points with data
      '#919191', // Grey for data points without data
      '#ee7325', // HPC orange for highlighting
    ],
    attrs: {
      'stroke': '#fff',
      'cursor': 'pointer',
      'opacity': 0.3,
    }
  }

  Drupal.hpc_map_circle.getColor = function(d, state, map_id) {
    let color = Drupal.hpc_map_circle.config.colors;
    if (Drupal.hpc_map.isActiveLocation(d, state)) {
      return color[2];
    }
    if (Drupal.hpc_map.emptyValueForCurrentTab(d, map_id)) {
      return color[1];
    }
    return color[0];
  }

  // Focus a location.
  Drupal.hpc_map_circle.focusLocation = function(element, state) {
    var map_state = Drupal.hpc_map.getMapStateFromContainedElement(element);
    let map_id = map_state.map_id;
    var proj = map_state.locationOverlay.projection,
        scale = proj.layer._scale,
        radius = 10/scale,
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
        .attr('fill', function(d) { return Drupal.hpc_map_circle.getColor(d, map_state, map_id); })
        .attr('r', function(d) { return Drupal.hpc_map.getRadius(d, scale, radius) + 10/scale; })
        .attr('opacity', 0.6);
    }
    else {

      if (map_state.options.popup_style == 'sidebar' && typeof map_state.active_location != 'undefined' && map_state.active_location) {
        // Check if the sidebar is currently open and showing the element that
        // should be unfocused.
        if (map_state.sidebar.isVisible() && map_state.active_location.location_id == $(element).attr('location-id')) {
          return;
        }
      }
      $(element).attr('class', '');
      d3.select(element)
        .transition()
        .duration(250)
        .attr('stroke', attrs.stroke)
        .attr('cursor', attrs.cursor)
        .attr('opacity', attrs.opacity)
        .attr('fill', function(d) { return Drupal.hpc_map_circle.getColor(d, map_state, map_id); })
        .attr('r', function(d) { return Drupal.hpc_map.getRadius(d, scale, radius); });
    }
  }

  Drupal.hpc_map_circle.createLocations = function(data, sel, proj) {

    var attrs = Drupal.hpc_map_circle.config.attrs,
        scale = proj.layer._scale,
        radius = 10/scale,
        strokeWidth = 1/scale;

    var map_id = proj.map._container.id;
    var state = Drupal.hpc_map.getMapState(map_id);

    // Classic circles.
    sel.selectAll('circle')
      .data(data, function (d) {
        return d.location_id;
      })
      .join(
        function (enter) {
          enter.append('circle')
          .attr('cx', function(d){ return proj.latLngToLayerPoint(d.latLng).x; })
          .attr('cy', function(d){ return proj.latLngToLayerPoint(d.latLng).y; })
          .attr('fill', function(d) { return Drupal.hpc_map_circle.getColor(d, state, map_id); })
          .attr('stroke', attrs.stroke)
          .attr('opacity', 0)
          .attr('r', 0)
          .attr('location-id', function(d){ return d.location_id; })
          .transition()
          .duration(500)
          .attr('cursor', attrs.cursor)
          .attr('fill', function(d) { return Drupal.hpc_map_circle.getColor(d, state, map_id); })
          .attr('r', function(d) { return Drupal.hpc_map.getRadius(d, scale, radius); })
          .attr('stroke-width', strokeWidth)
          .attr('opacity', function(d) {
            return Drupal.hpc_map.isActiveLocation(d, state) ? 0.6 : attrs.opacity;
          });
        },
        function(update) {
          update.transition()
          .duration(500)
          .attr('stroke', attrs.stroke)
          .attr('cursor', attrs.cursor)
          .attr('fill', function(d) { return Drupal.hpc_map_circle.getColor(d, state, map_id); })
          .attr('r', function(d) { return Drupal.hpc_map.getRadius(d, scale, radius); })
          .attr('stroke-width', strokeWidth)
          .attr('opacity', function(d) {
            return Drupal.hpc_map.isActiveLocation(d, state) ? 0.6 : attrs.opacity;
          });
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

    // Add event listeners.
    sel.selectAll('circle').on('click', function(event, d) {
      var state = Drupal.hpc_map.getMapStateFromContainedElement(this);
      Drupal.hpc_map.showPopup(d, state);
    })
    .on("mouseover", function() {
      Drupal.hpc_map_circle.focusLocation(this, 1);
      $('#' + map_id + ' .map-circle-tooltip').css("display", "");
    })
    .on('mousemove', function(event, d) {
      var xPosition = event.layerX + 10;
      var yPosition = event.layerY + 10;
      $('#' + map_id + ' .map-circle-tooltip').css("top", yPosition);
      $('#' + map_id + ' .map-circle-tooltip').css("left", xPosition);
      $('#' + map_id + ' .map-circle-tooltip').css("z-index", 100000);
      $('#' + map_id + ' .map-circle-tooltip').html('<b>Location:</b> ' + d.location_name + '<br /><b>Total ' + state.tab_data.metric.name.en.toLowerCase() +':</b> ' + Drupal.theme('number', d.total));
    })
    .on('mouseout', function() {
      Drupal.hpc_map_circle.focusLocation(this, 0);
      $('#' + map_id + ' .map-circle-tooltip').css("display", "none");
    });

  }

})(jQuery, Drupal);
