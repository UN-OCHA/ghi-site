(function ($) {

  const root_styles = getComputedStyle(document.documentElement);

  Drupal.hpc_map_circle = Drupal.hpc_map_circle || {};

  Drupal.hpc_map_circle.config = {
    // Scales used to determine color.
    colors: [
      root_styles.getPropertyValue('--cd-default-text-color'), // Data points with data.
      root_styles.getPropertyValue('--cd-default-border-color'), // Data points without data.
      root_styles.getPropertyValue('--cd-primary-color'), // Highlights.
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

  Drupal.hpc_map_circle.showLocationTooltip = function(map_id, state, d, x, y) {
    $('#' + map_id + ' .map-circle-tooltip').css("top", y);
    $('#' + map_id + ' .map-circle-tooltip').css("left", x);
    $('#' + map_id + ' .map-circle-tooltip').css("z-index", 100000);
    $('#' + map_id + ' .map-circle-tooltip').html('<b>Location:</b> ' + d.location_name + '<br /><b>Total ' + state.tab_data.metric.name.en.toLowerCase() +':</b> ' + Drupal.theme('number', d.total));
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
          .attr('id', d => map_id + '--' + d.location_id)
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
      var state = Drupal.hpc_map.getMapStateFromContainedElement(element);
      let d = Drupal.hpc_map.getLocationObjectFromContainedElement(element);
      Drupal.hpc_map.showPopup(d, state);
      Drupal.hpc_map_circle.focusLocation(element, 1);
    })
    .on("mouseover", function(event) {
      let element = Drupal.hpc_map.getElementFromUseElement($(event.target.href.baseVal));
      Drupal.hpc_map_circle.focusLocation(element, 1);
      $('#' + map_id + ' .map-circle-tooltip').css("display", "");
    })
    .on('mousemove', function(event) {
      let element = Drupal.hpc_map.getElementFromUseElement($(event.target.href.baseVal));
      var state = Drupal.hpc_map.getMapStateFromContainedElement(element);
      let d = Drupal.hpc_map.getLocationObjectFromContainedElement(element);
      var xPosition = event.layerX + 10;
      var yPosition = event.layerY + 10;
      Drupal.hpc_map_circle.showLocationTooltip(map_id, state, d, xPosition, yPosition);
    })
    .on('mouseout', function(event) {
      let element = Drupal.hpc_map.getElementFromUseElement($(event.target.href.baseVal));
      Drupal.hpc_map_circle.focusLocation(element, 0);
      $('#' + map_id + ' .map-circle-tooltip').css("display", "none");
    });

  }

})(jQuery, Drupal);
