(function ($, drupalSettings) {

  'use strict';

  if (!window.ghi) {
    window.ghi = {};
  }

  window.ghi.mapbox = class {

    /**
     * Constructor for the map state object.
     *
     * @param {ghi.mapState} state
     *   The map state object.
     */
    constructor () {
      this.style = drupalSettings.mapbox.style_url ?? null;
      this.token = drupalSettings.mapbox.token ?? null;
    }

    // Check if mapbox is supported.
    supported = function (strict) {
      return this.style && this.token && typeof mapboxgl !== 'undefined' && typeof mapboxgl.supported !== 'undefined' && mapboxgl.supported({
        failIfMajorPerformanceCaveat: strict === true
      });
    }

    // Add a map's settings.
    addMap = function (element, options) {

      // Skip if the browser doesn't support mapbox GL.
      if (!this.supported()) {
        element.removeAttribute('data-map-enabled');
        return;
      }

      // Skip if the map was already processed.
      if (element.hasAttribute('data-map-processed')) {
        return;
      }
      // Mark the map has being processed.
      element.setAttribute('data-map-processed', '');

      // Add the map container.
      var mapContainer = document.createElement('div');
      element.appendChild(mapContainer);
      let height = $(mapContainer).parents('.map-container').css('min-height') ?? '460px';
      mapContainer.setAttribute('style', 'height: ' + height);

      // Replace the mapbox base API with the proxied version.
      if (drupalSettings.mapbox.token == 'token') {
        mapboxgl.baseApiUrl = window.location.origin + '/mapbox';
      }

      let mapbox_options = {
        accessToken: drupalSettings.mapbox.token,
        style: drupalSettings.mapbox.style_url,
        container: mapContainer,
        zoom: options.zoom,
        minZoom: options.zoom_min,
        maxZoom: options.zoom_max,
        doubleClickZoom: true,
        scrollZoom: false,
        cooperativeGestures: true,
        attributionControl: false,
        logoPosition: 'bottom-right',
      };

      // Create a map.
      let map = new mapboxgl.Map(mapbox_options)
        .addControl(new mapboxgl.NavigationControl({
          showCompass: false
        }), 'top-left');

      // Disable map rotation using right click + drag.
      map.dragRotate.disable();

      // Disable map rotation using touch rotation gesture.
      map.touchZoomRotate.disableRotation();

      // Disable map tilting using two-finger gesture.
      map.touchPitch.disable();

      // Remove the mapbox logo from the tab order.
      $(mapContainer).find('a.mapboxgl-ctrl-logo').attr('tabindex', -1);

      return map;

    }
  };

})(jQuery, drupalSettings);
