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

    setup = function () {
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
      let $items = state.getContainer().find('.map-legend ul li.legend-item');
      if (this.hiddenTypes.length > 0) {
        this.hiddenTypes.forEach(function (type) {
          self.disableLegendItem(type);
        });
      }
      state.getContainer().find('.map-legend ul').addClass('interactive-legend');
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
      return this.state.getContainer().find('.map-legend ul .legend-item[data-type]').toArray().map((item) => {
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
      let map_id = this.state.getMapId();
      $('#' + map_id + '-legend ul li.legend-item[data-type="' + dataType + '"').attr('disabled', true);
      $('#' + map_id + '-legend ul li.legend-item[data-type="' + dataType + '"]').css('opacity', '0.4');
      // $('#' + map_id + ' .map-svg > [legend-type="' + dataType + '"]').css('display', 'none');
      if (this.hiddenTypes.indexOf(dataType) == -1) {
        this.hiddenTypes.push(dataType);
      }
      this.setHiddenState(dataType);
    }

    /**
     * Enable the given legend item type.
     */
    enableLegendItem = function (dataType) {
      let map_id = this.state.getMapId();
      $('#' + map_id + '-legend ul li.legend-item[data-type="' + dataType + '"').attr('disabled', false);
      $('#' + map_id + '-legend ul li.legend-item[data-type="' + dataType + '"]').css('opacity', '1');
      // $('#' + map_id + ' .map-svg > [legend-type="' + dataType + '"]').css('display', 'inline');
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