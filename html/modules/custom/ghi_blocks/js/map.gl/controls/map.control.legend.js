(function (Drupal, $) {

  'use strict';

  if (!window.ghi) {
    window.ghi = {};
  }

  /**
   * Define the legend control class.
   *
   * See https://docs.mapbox.com/mapbox-gl-js/api/markers/#icontrol for
   * implementation details.
   */
  window.ghi.legendControl = class {

    /**
     * Constructor for the map state object.
     *
     * @param {ghi.mapState} state
     *   The map state object.
     */
    constructor (state) {
      this.state = state;
    }

    /**
     * Optionally provide a default position for this control.
     *
     * If this method is implemented and Map#addControl is called without the
     * position parameter, the value returned by getDefaultPosition will be
     * used as the control's position.
     *
     * @returns {String}
     *   A control position, one of the values valid in addControl.
     */
    getDefaultPosition = function () {
      return this.state.getOptions().legend_position ?? 'bottom-left';
    }

    /**
     * Register a control on the map.
     *
     * Give it a chance to register event listeners and resources. This method
     * is called by Map#addControl internally.
     *
     * @param {Object} map
     *   The mapbox object.
     *
     * @returns {Element}
     *   The control's container element. This should be created by the control
     *   and returned by onAdd without being attached to the DOM: the map will
     *   insert the control's element into the DOM as necessary.
     */
    onAdd = function (map) {
      this._map = map;
      this._container = document.createElement('div');
      this._container.className = 'mapboxgl-ctrl mapboxgl-ctrl-group legend';
      let $legend_container = $('<div>');
      $legend_container.addClass('map-legend');
      this._container.appendChild($legend_container[0]);
      this.update($legend_container);
      return this._container;
    }

    /**
     * Update the legend.
     */
    update = function ($legend_container = null) {
      let updateCallback = 'updateLegend';
      let style = this.state.getMapStyle();
      if (typeof style != 'object' || !style.hasOwnProperty(updateCallback) || typeof style[updateCallback] != 'function') {
        return;
      }
      style[updateCallback]($legend_container);
    }

    /**
     * Unregister a control on the map.
     *
     * Give it a chance to detach event listeners and resources. This method is
     * called by Map#removeControl internally.
     */
    onRemove = function () {
      this._container.parentNode.removeChild(this._container);
      this._map = undefined;
    }

  }

})(Drupal, jQuery);