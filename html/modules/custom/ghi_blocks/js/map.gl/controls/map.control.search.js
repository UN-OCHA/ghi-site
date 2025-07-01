(function (Drupal, $) {

  'use strict';

  const REGEXP = '/[.*+?^${}()|[\]\\]/g';

  if (!window.ghi) {
    window.ghi = {};
  }

  /**
   * Define the search control class.
   *
   * See https://docs.mapbox.com/mapbox-gl-js/api/markers/#icontrol for
   * implementation details.
   */
  window.ghi.searchControl = class {

    /**
     * Constructor for the map state object.
     *
     * @param {ghi.mapState} state
     *   The map state object.
     * @param {Object} options
     *   The search options.
     */
    constructor (state, options) {
      let self = this;
      this.state = state;

      let defaults = {
        placeholder: Drupal.t('Start search by typing'),
        search_button_title: Drupal.t('Click to search'),
        empty_message: '',
        initial: false,
        casesensitive: false,
      };
      this.options = Object.assign({}, defaults, options);
      this.updateSearchIndex(this.state.getLocations(false));
      this.state.getMap().on('data', (event) => {
        let source_id = event.sourceId ?? null;
        let source_loaded = event.isSourceLoaded ?? false;
        let transition = event.source?.data?.properties?.transition ?? false;
        if (source_id != state.getMapId() || !source_loaded || transition) {
          // Only act when data changes on the main layer, source is fully
          // loaded and we are not currently in a transition animation.
          return;
        }
        self.updateSearchIndex();
      });
    }

    /**
     * Update the search index.
     */
    updateSearchIndex = function (locations = null) {
      // Add all locations to our search index.
      this.searchIndex = [];
      locations = locations ?? this.state.getLocations(false);
      for (let location of locations) {
        this.searchIndex[location.object_id] = {
          loc: location.latLng,
          object_id: location.object_id,
          object_title: location.object_title ?? location.location_name,
          pcode: location.pcode ?? null,
          plan_type: location.plan_type ?? null,
          admin_level: location.admin_level ?? null,
        };
      }
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
      return 'top-left';
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
      this._container.className = 'mapboxgl-ctrl mapboxgl-ctrl-group search';

      let wrapper = document.createElement('div');
      wrapper.className = 'search-input-wrapper';

      let input = document.createElement('input');
      input.className = 'search-input';
      input.setAttribute('placeholder', this.getSearchPlaceholder());
      input.addEventListener('keyup', (e) => this.handleKeyPress(e));
      input.addEventListener('focus', (e) => this.handleTextInput(e.target.value));
      input.addEventListener('blur', (e) => this.handleBlur(e));
      wrapper.appendChild(input);

      let cancel = document.createElement('a');
      cancel.innerHTML = '<span class="material-icon">cancel</span>';
      cancel.className = 'search-cancel';
      cancel.setAttribute('title', Drupal.t('Cancel'));
      cancel.addEventListener('click', (e) => this.cancelSearch());
      wrapper.appendChild(cancel);

      let submit = document.createElement('a');
      submit.className = 'search-button';
      submit.setAttribute('title', this.getSearchButtonTitle());
      wrapper.appendChild(submit);

      this._container.appendChild(wrapper);

      let results = document.createElement('div');
      results.className = 'search-results';
      results.setAttribute('style', 'display: none');
      this._container.appendChild(results);

      let alert = document.createElement('div');
      alert.className = 'search-alert';
      alert.setAttribute('style', 'display: none');
      this._container.appendChild(alert);

      window.addEventListener('click', (e) => this.handleWindowClick(e));

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
     * Get the title for the search button.
     *
     * @returns {String}
     *   The title to use for the search button.
     */
    getSearchButtonTitle = function () {
      return this.options.search_button_title;
    }

    /**
     * Get the placeholder string for the search input.
     *
     * @returns {String}
     *   The placeholder string to use for the search input.
     */
    getSearchPlaceholder = function () {
      return this.options.placeholder;
    }

    /**
     * Get the no results message.
     *
     * @returns {String}
     *   The message to show if no results can be found.
     */
    getNoResultsMessage = function () {
      return this.options.empty_message;
    }

    /**
     * Check if the search results are visible.
     *
     * @returns {Boolean}
     *   TRUE if the search results are visible, FALSE otherwise.
     */
    searchResultsAreVisible = function () {
      return $(this._container).find('.search-results').is(':visible');
    }

    /**
     * Check if the search alert is visible.
     *
     * @returns {Boolean}
     *   TRUE if the search alert is visible, FALSE otherwise.
     */
    searchAlertIsVisible = function () {
      return $(this._container).find('.search-alert').is(':visible');
    }

    /**
     * Cancel the search.
     *
     * Remove value from input and close the search results.
     */
    cancelSearch = function () {
      $(this._container).find('.search-input').val('');
      this.hideResults();
      this.hideAlert();
    }

    /**
     * Search for the given text.
     *
     * @param {String} text
     *   The text to search for.
     */
    search = function (text) {
      let state = this.state;
      let records = this.searchIndex;
      var results = {};

      text = text.replace(REGEXP, '');  // Sanitize remove all special characters.
      if (text === '') {
        return [];
      }
      let regular_expression = new RegExp(this.options.initial ? '^' : '' + text, !this.options.casesensitive ? 'i' : undefined);
      for (var object_id in records) {
        let record = records[object_id];
        if (regular_expression.test(record.object_title)) {
          results[object_id] = records[object_id];
          continue;
        }
        if (state.options.pcodes_enabled && typeof record.pcode != 'undefined' && regular_expression.test(record.pcode)) {
          results[object_id] = records[object_id];
        }
        else if (typeof record.plan_type != 'undefined' && record.plan_type && regular_expression.test(record.plan_type)) {
          results[object_id] = records[object_id];
        }
      }

      if (Object.keys(results).length > 0) {
        // Hide the error alert in case it is currently shown.
        this.hideAlert();
      }
      else {
        // Show the error alert if no results have been found.
        this.showAlert();
      }
      return results;
    }

    /**
     * Show the alert.
     */
    showAlert = function () {
      let message = Drupal.t("No results for '<strong>@text</strong>'", {
        '@text': $(this._container).find('.search-input').val(),
      }) + '<br />' + this.getNoResultsMessage();
      let $alert = $(this._container).find('.search-alert');
      $alert.html(message);
      $(this._container).find('.search-alert').show();
    }

    /**
     * Hide the alert.
     */
    hideAlert = function () {
      $(this._container).find('.search-alert').hide();
      $(this._container).find('.search-alert').html('');
    }

    /**
     * Show the results.
     *
     * @param {Map} results
     */
    showResults = function (results) {
      $(this._container).find('.search-results ul').remove();
      let list = document.createElement('ul');
      for (let object_id in results) {
        list.appendChild(this.buildResultItem(results[object_id]));
      }

      $(this._container).find('.search-results').append(list);
      $(this._container).find('.search-results').show();
    }

    /**
     * Hide the results.
     */
    hideResults = function () {
      $(this._container).find('.search-results').hide();
      $(this._container).find('.search-results ul').remove();
    }

    /**
     * Build a result item.
     *
     * @param {Object} record
     *   A result record.
     *
     * @returns {Element}
     *   An list item element.
     */
    buildResultItem = function (record) {
      let input = $(this._container).find('.search-input').val();
      let search_text = input.replace(REGEXP, '');
      let regex = new RegExp(search_text, "gi");
      let state = this.state;
      let item = document.createElement('li');
      item.className = 'search-result-item';
      item.setAttribute('object-id', record.object_id);
      item.addEventListener('mouseover', (e) => this.handleHover(e.target));
      item.addEventListener('click', (e) => this.handleSelection(e.target));

      let object_title = record.object_title.replace(regex, "<b>$&</b>");
      var subline = null;
      if (record.admin_level) {
        var subline = Drupal.t('Admin Level !level', {
          '!level': record.admin_level
        });
        if (state.options.pcodes_enabled && typeof record.pcode != 'undefined' && record.pcode && record.pcode.length) {
          subline = Drupal.t('Admin Level !level | !pcode', {
            '!level': record.admin_level,
            '!pcode': record.pcode.replace(regex, "<b>$&</b>"),
          });
        }
      }
      else if (record.plan_type) {
        object_title = (record.object_title + ' ' + record.plan_type.toUpperCase()).replace(regex, "<b>$&</b>");
      }

      if (subline) {
        item.innerHTML = '<span class="location-name">' + object_title + '</span><br />' + '<span class="subline">' + subline + '</span>';
      }
      else {
        item.innerHTML = '<span class="location-name">' + object_title + '</span>';
      }
      return item;
    }

    /**
     * Handle the given text input.
     *
     * @param {String} text
     *   The text to search for.
     */
    handleTextInput = function (text) {
      let results = this.search(text);
      if (Object.keys(results).length) {
        this.showResults(results);
      }
      else if (text.length > 0) {
        this.hideResults();
        this.showAlert();
      }
      else if (text.length == 0) {
        this.hideResults();
        this.hideAlert();
      }
    }

    /**
     * Handle hovering over a result item.
     *
     * @param {Element} element
     *   The element hovered above.
     */
    handleHover = function (element) {
      $(this._container).find('li').removeClass('hover');
      if ($(element).is('li')) {
        $(element).addClass('hover');
      }
      else {
        $(element).parents('li').addClass('hover');
      }
    }

    /**
     * Handle selection using the arrow keys.
     *
     * @param {Number} direction
     *   -1 is up, 1, is down.
     */
    handleArrowSelect = function (direction) {
      let $current = $(this._container).find('li.hover');
      if (!$current.length) {
        if (direction == 1) {
          $(this._container).find('li').first().addClass('hover');
        }
        else {
          $(this._container).find('li').last().addClass('hover');
        }
      }
      else {
        if (direction == 1) {
          $current.removeClass('hover');
          $current.next().addClass('hover');
        }
        else {
          $current.removeClass('hover');
          $current.prev().addClass('hover');
        }
      }
    }

    /**
     * Handle the selection of a result item.
     *
     * @param {Element} element
     *   The element that has been selected.
     */
    handleSelection = function (element) {
      // Open the location.
      let object_id = $(element).is('li') ? $(element).attr('object-id') : $(element).parents('li').attr('object-id');
      if (!object_id) {
        return;
      }
      let state = this.state;
      let object = state.getLocationById(object_id, false);
      if (!state.objectIsVisible(object) && state.legend?.hasHiddenTypes()) {
        // Show all legend items again.
        state.legend?.reset();
      }

      state.showSidebarForObject(object);
      this.hideResults();
    }

    /**
     * Handle a click somewhere else in the window.
     *
     * @param {Event} event
     *   The triggering event.
     */
    handleWindowClick = function (event) {
      if (!$(event.target).parents('.mapboxgl-ctrl').length) {
        this.hideResults();
        this.hideAlert();
      }
    }

    /**
     * Handle a blur event of the search field.
     *
     * @param {Event} event
     *   The triggering event.
     */
    handleBlur = function (event) {
      if (event.relatedTarget !== null) {
        this.hideResults();
      }
      this.hideAlert();
    }

    /**
     * Handle a key press in the search input field.
     *
     * @param {Event} event
     *   The triggering event.
     */
    handleKeyPress = function (event) {
      // Show or hide the cancel button.
      if (event.target.value.length) {
        $(this._container).find('.search-cancel').show();
      }
      else {
        $(this._container).find('.search-cancel').hide();
      }

      // Handle the actual key press.
      switch(event.keyCode) {
        case 27: // Esc
          if (!this.searchResultsAreVisible() && !this.searchAlertIsVisible() && event.target.value.length) {
            this.cancelSearch();
          }
          else {
            this.hideResults();
            this.hideAlert();
          }
          break;

        case 13: // Enter
          let $current = $(this._container).find('li.hover');
          if ($current.length) {
            this.handleSelection($current.get(0));
          }
          else {
            this.handleTextInput(event.target.value);
          }
          break;

        case 38: // Up
          this.handleArrowSelect(-1);
          break;

        case 40: // Down
          this.handleArrowSelect(1);
          break;

        default:
          this.handleTextInput(event.target.value);
      }
    }

  }

})(Drupal, jQuery);