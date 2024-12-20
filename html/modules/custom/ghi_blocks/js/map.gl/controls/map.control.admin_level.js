(function (Drupal, $) {

  'use strict';

  if (!window.ghi) {
    window.ghi = {};
  }

  /**
   * Define the admin level control class.
   *
   * See https://docs.mapbox.com/mapbox-gl-js/api/markers/#icontrol for
   * implementation details.
   */
  window.ghi.adminLevelControl = class {

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
      return 'bottom-left';
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
      this._container.className = 'mapboxgl-ctrl mapboxgl-ctrl-group admin-level-select';

      let active_level = this.state.getAdminLevel();

      // Create an array with unique values. Sort it, because the order of the
      // locations is not guaranteed to be in the order of their admin level.
      let admin_level = this.state.getAdminLevelOptions();
      let admin_level_max = Math.max.apply(Math, admin_level);
      let admin_level_min = Math.min.apply(Math, admin_level);

      if (admin_level.length == 1 && admin_level_min == 1) {
        return this._container;
      }

      // Add one button per admin level.
      for (let value = 1; value <= admin_level_max; value++) {
        let button = document.createElement('button');
        button.innerHTML = value;
        button.className = 'mapboxgl-ctrl';
        button.setAttribute('data-admin-level', value);
        button.className = 'admin-level-select-button';
        if (value == active_level) {
          button.className += ' active';
        }
        else if (admin_level.indexOf(value) == -1) {
          button.className += ' disabled';
          button.setAttribute('tabindex', -1);
        }
        if (admin_level.indexOf(value) != -1) {
          button.setAttribute('tabindex', 0);
        }
        button.addEventListener('click', (e) => this.changeAdminLevel(e));
        this._container.appendChild(button);
      }
      // Add a tooltip to the control.
      tippy($(this._container).get(0), {
        content: Drupal.t('Select Admin Level View'),
      });

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

    /**
     * Button callback: Change the admin level.
     *
     * @param {Object} e
     *   The event object.
     */
    changeAdminLevel = function(e) {
      e.preventDefault();
      if ($(e.target).hasClass('disabled')) {
        return;
      }
      var button = e.target;
      if (this.state.disabled) {
        return;
      }
      let admin_level = button.getAttribute('data-admin-level');
      if (this.state.getAdminLevel() == admin_level) {
        return;
      }
      this.state.setAdminLevel(admin_level);
    }

    /**
     * Update the admin level control.
     *
     * @param {Number} admin_level
     *   The active admin level.
     */
    updateControl = function (admin_level) {
      $(this._container).find('button[data-admin-level]').removeClass('active');
      $(this._container).find('button[data-admin-level=' + admin_level + ']').addClass('active');
    }

  }

})(Drupal, jQuery);