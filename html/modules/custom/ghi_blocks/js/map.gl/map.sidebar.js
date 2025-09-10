(function ($) {

  'use strict';

  if (!window.ghi) {
    window.ghi = {};
  }

  /**
   * Define the map sidebar class.
   */
  window.ghi.sidebar = class {

    /**
     * Constructor for the map state object.
     *
     * @param {ghi.mapState} state
     *   The map state object.
     */
    constructor (state) {
      this.state = state;

      let $container = state.getCanvasContainer().parent().parent();
      if ($container.find('.map-sidebar').length == 0) {
        let self = this;
        $container.append('<div class="map-sidebar--wrapper"><a class="close" tabindex="0">×</a><div class="map-sidebar"></div></div>');
        $container.find('.map-sidebar--wrapper').css('display', 'none');
        $container.find('a.close').on('click', () => self.hide());
        $container.find('a.close').on('keyup', (e) => self.handleEnter(e, () => self.hide()));
      }

      this.containerWrapper = $container.find('.map-sidebar--wrapper');
      this.container = $container.find('.map-sidebar');
    }

    /**
     * Show the sidebar.
     *
     * @param {Object} object
     *   The data object to show in the sidebar.
     */
    show = function (object, build) {
      build = this.buildSidebar(object, build);
      if (!build) {
        this.hide();
        return;
      }
      $(this.container).html(Drupal.theme('mapPlanCard', build));
      if (!this.isVisible()) {
        $(this.containerWrapper).show();
        this.state.getMap().setPadding({right: this.state.getMapController().config.map.padding + $(this.container).width()});
      }
      this.state.getMap().panTo(new mapboxgl.LngLat(object.latLng[1], object.latLng[0]), {
        essential: true,
        duration: 200,
      });
      this.addEventListeners();
      Drupal.attachBehaviors($(this.container).get(0));
      this.containerWrapper.find(':focusable').first().focus();
    }

    /**
     * Hide the sidebar.
     */
    hide = function () {
      this.state.resetFocus();
      $(this.containerWrapper).hide();
      this.state.getMap().setPadding({right: this.state.getMapController().config.map.padding});
    }

    /**
     * Check if the sidebar is visible.
     *
     * @returns {Boolean}
     *   TRUE if the sidebar is visible, FALSE otherwise.
     */
    isVisible = function () {
      return $(this.container).is(':visible');
    }

    /**
     * Build the sidebar so that Drupal.theme.mapPlanCard can render it.
     *
     * @param {Object} d
     *   The data object.
     *
     * @returns {Object}|null
     *   A build object that Drupal.theme.mapPlanCard can render, or NULL.
     */
    buildSidebar = function(object, build) {
      let state = this.state;
      let data = state.getData();
      let object_id = parseInt(object.object_id);

      var location_data = object.modal_content ?? data.modal_contents[object_id];
      let variant_id = state.getVariantId();
      if (variant_id && state.hasVariant(state.getCurrentIndex(), variant_id)) {
        location_data = data.variants[variant_id].modal_contents[object_id];
      }
      if (!location_data) {
        // The new tab has no data for the currently active location.
        return;
      }

      // Get the locations.
      let current_locations = state.getLocations();

      // Filter by visibility.
      current_locations = current_locations.filter((d) => state.objectIsVisible(d));

      // Get the current index in the sorted list.
      let current_location = current_locations.filter((d) => d.object_id == object.object_id)[0];
      var current_index = current_locations.indexOf(current_location);
      let next_index = current_index < current_locations.length - 1 ? current_index + 1 : 0;
      let previous_index = current_index > 0 ? current_index - 1 : current_locations.length - 1;

      build.current_index = current_index + 1;
      build.total_count = current_locations.length;
      build.next = next_index !== null ? current_locations[next_index] : null;
      build.previous = previous_index !== null ? current_locations[previous_index] : null;
      build.location_data = location_data;
      return build;
    }

    /**
     * Handle a navigation link.
     *
     * @param {Element} element
     *   The link element.
     * @param {Event} event
     *   The event object.
     */
    handleNavigationLink = function (element, event) {
      let state = this.state;
      event.stopPropagation();

      let object_id = $(element).data('object-id') ?? $(element).parent('[data-object-id]').data('object-id');
      var new_active_location = state.getLocationById(object_id);
      if (new_active_location) {
        state.style.showSidebarForObject(new_active_location);
        if ($(element).hasClass('previous')) {
          $(this.container).find('.link.previous').focus();
        }
        else {
          $(this.container).find('.link.next').focus();
        }
      }
    }

    /**
     * Add event listeners to the sidebar.
     *
     * This is mostly for the navigation links.
     */
    addEventListeners = function () {
      let self = this;
      // Add navigation behavior.
      $(this.container).find(once('map-navigation-links', '.navigation .link'))
        .on('click', (e) => self.handleNavigationLink(e.target, e))
        .on('keyup', (e) => self.handleEnter(e, () => self.handleNavigationLink(e.target, e)))
        .on('keyup', (e) => self.handleEsc(e, () => self.hide()));
    }

    /**
     * Handle using the enter key.
     *
     * @param {Event} event
     *   The event object.
     * @param {CallableFunction} callback
     *   A valid callable.
     */
    handleEnter = function (event, callback) {
      if (event.keyCode != 13) {
        return;
      }
      callback();
    }

    /**
     * Handle using the esc key.
     *
     * @param {Event} event
     *   The event object.
     * @param {CallableFunction} callback
     *   A valid callable.
     */
    handleEsc = function (event, callback) {
      if (event.keyCode != 27) {
        return;
      }
      callback();
    }

  }

})(jQuery);
