(function ($) {

  'use strict';

  if (!window.ghi) {
    window.ghi = {};
  }

  /**
   * Define the map throbber class.
   */
  window.ghi.throbber = class {

    /**
     * Constructor for the map state object.
     *
     * @param {ghi.mapState} state
     *   The map state object.
     */
    constructor (state) {
      this.state = state;

      let $container = state.getCanvasContainer().parent().parent();
      if ($container.find('.map-throbber').length == 0) {
        let self = this;
        $container.append('<div class="map-throbber--wrapper"><div class="map-throbber ajax-progress__throbber"></div></div>');
        $container.find('.map-throbber--wrapper').css('display', 'none');
      }

      this.containerWrapper = $container.find('.map-throbber--wrapper');
      this.container = $container.find('.map-throbber');
    }

    /**
     * Show the throbber.
     */
    show = function () {
      if (!this.isVisible()) {
        $(this.containerWrapper).show();
      }
    }

    /**
     * Hide the throbber.
     */
    hide = function () {
      $(this.containerWrapper).hide();
    }

    /**
     * Check if the throbber is visible.
     *
     * @returns {Boolean}
     *   TRUE if the throbber is visible, FALSE otherwise.
     */
    isVisible = function () {
      return $(this.container).is(':visible');
    }

  }

})(jQuery);
