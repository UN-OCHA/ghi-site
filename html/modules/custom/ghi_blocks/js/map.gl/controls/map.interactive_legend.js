(function ($) {

  'use strict';

  if (!window.ghi) {
    window.ghi = {};
  }

  /**
   * Define the map state class.
   */
  window.ghi.interactiveLegend = class {

    /**
     * Constructor for the map state object.
     *
     * @param {String} id
     *   The ID of the map container.
     */
    constructor (state) {
      let self = this;
      this.state = state;
      this.hiddenTypes = [];

      // Attach zoom handling. Don't do this as part of the setup as that is
      // called also by state.updateMap().
      state.getMap().on('zoomend', () => {
        // Update the hidden state when zooming. This is important.
        self.updateHiddenState();
      });
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
      this.attachBehaviors();
    }

    /**
     * Attach the interactive behaviors.
     */
    attachBehaviors = function () {
      let self = this;
      let state = this.state;
      let options = state.getOptions();
      if (!options.interactive_legend) {
        return;
      }
      if (!options.legend) {
        return;
      }
      // Add an interactive legend.
      let $items = $(this._container).find('.map-legend ul li.legend-item');
      if (this.hiddenTypes.length > 0) {
        this.hiddenTypes.forEach(function (type) {
          self.disableLegendItem(type);
        });
      }
      $(this._container).find('.map-legend ul').addClass('interactive-legend');
      $items.on('click', function (event) {
        state.sidebar?.hide();

        // Then get the data type and the disabled state.
        let $legendItem = $(event.target).hasClass('legend-item') ? $(event.target) : $(event.target).parent('.legend-item');
        let dataType = $legendItem.attr('data-type');
        let disabled = $legendItem.attr('disabled');

        let dataTypes = self.getDataTypes();
        if (!disabled && self.hiddenTypes.length == 0) {
          dataTypes.filter((type) => type != dataType).forEach((type) => {
            self.disableLegendItem(type);
          });
        }
        else if (!disabled && self.hiddenTypes.length == dataTypes.length - 1) {
          dataTypes.filter((type) => type != dataType).forEach((type) => {
            self.enableLegendItem(type);
          });
        }
        else if (!disabled) {
          // Let's disable this.
          self.disableLegendItem(dataType);
        }
        else {
          // Let's enable this again.
          self.enableLegendItem(dataType);
        }
        // Now update the map.
        state.style.renderLocations();
      });
    }

    /**
     * Get the available data types for the legend.
     *
     * @returns {Array}
     *   An array of strings.
     */
    getDataTypes = function () {
      return $(this._container).find('.map-legend ul .legend-item[data-type]').toArray().map((item) => {
        return $(item).attr('data-type');
      });
    }

    /**
     * Check if there are currently any hidden types.
     *
     * @returns {Boolean}
     *   TRUE if at least one type is hidden, FALSE otherwise.
     */
    hasHiddenTypes = function () {
      return this.hiddenTypes.length > 0;
    }

    isHiddenType = function (dataType) {
      return this.hiddenTypes.indexOf(dataType) != -1;
    }

    /**
     * Disable the given legend item type.
     */
    disableLegendItem = function (dataType) {
      $(this._container).find('ul li.legend-item[data-type="' + dataType + '"]').attr('disabled', true);
      $(this._container).find('ul  li.legend-item[data-type="' + dataType + '"]').css('opacity', '0.4');
      if (this.hiddenTypes.indexOf(dataType) == -1) {
        this.hiddenTypes.push(dataType);
      }
      this.setHiddenState(dataType);
    }

    /**
     * Enable the given legend item type.
     */
    enableLegendItem = function (dataType) {
      $(this._container).find('ul  li.legend-item[data-type="' + dataType + '"]').attr('disabled', false);
      $(this._container).find('ul  li.legend-item[data-type="' + dataType + '"]').css('opacity', '1');
      this.hiddenTypes = this.hiddenTypes.filter(function(type) { return dataType != type });
      this.setHiddenState(dataType, false);
    }

    /**
     * Set the hidden state for the given data type.
     *
     * @param {String} dataType
     *   The data type identifying the legend item.
     * @param {Boolean} value
     *   The value to set.
     */
    setHiddenState = function (dataType, value = true) {
      let state = this.state;
      let source_id = state.style.sourceId;
      let layer_id = state.style.featureLayerId;
      let features = state.querySourceFeatures(layer_id, source_id, ["==", "legend_type", dataType]);
      for (let feature of features) {
        state.setFeatureState(feature.id, 'hidden', value);
      }
    }

    /**
     * Show all legend items again.
     */
    reset = function () {
      if (!this.hiddenTypes.length) {
        return;
      }
      let state = this.state;
      this.hiddenTypes.forEach(function (type) {
        state.legend.enableLegendItem(type);
      });
      state.style.updateLegend(state);
      state.style.renderLocations();
    }

    /**
     * Update the hidden state for all data types.
     *
     * This is triggered by the zoom event on the map. It's needed because
     * setHiddenState uses querySourceFeatures to find features in the
     * currently displayed portion of the map and sets a feature state on them.
     * When zooming out, we might need to update features that haven't been
     * available before.
     */
    updateHiddenState = function () {
      let self = this;
      let state = this.state;
      this.getDataTypes().forEach((type) => {
        self.setHiddenState(type, false);
      });
      this.hiddenTypes.forEach(function (type) {
        self.setHiddenState(type, true);
      });
      state.style.renderLocations();
    }

  }

})(jQuery);