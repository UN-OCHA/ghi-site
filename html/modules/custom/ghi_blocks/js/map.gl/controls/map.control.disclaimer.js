(function (Drupal, $) {

  'use strict';

  if (!window.ghi) {
    window.ghi = {};
  }

  /**
   * Define the disclaimer control class.
   *
   * See https://docs.mapbox.com/mapbox-gl-js/api/markers/#icontrol for
   * implementation details.
   */
  window.ghi.disclaimerControl = class {

    /**
     * Constructor for the map state object.
     *
     * @param {ghi.mapState} state
     *   The map state object.
     * @param {string} text
     *   The disclaimer text.
     */
    constructor (state, text) {
      this.state = state;
      this.disclaimerText = text;
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
      return 'bottom-right';
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
      this._container.className = 'mapboxgl-ctrl mapboxgl-ctrl-group disclaimer';

      let text = document.createElement('span');
      text.innerHTML = this.disclaimerText;
      this._container.appendChild(text);
      return this._container;
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