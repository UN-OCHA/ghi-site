/* global once */

(function (Drupal, $, once) {

  'use strict';

  /**
   * Build map init options for operational presence maps.
   *
   * @param {Object} mapConfig
   *   The map settings.
   *
   * @return {Object}
   *   Map init options.
   */
  function buildOptions(mapConfig) {
    var options = {
      style: 'choropleth',
      admin_level_selector: true,
      search_enabled: true,
      search_options: {
        placeholder: Drupal.t('Filter by location name'),
        empty_message: Drupal.t('Be sure to enter a location name within the current response plan.')
      }
    };
    if (typeof mapConfig.pcodes_enabled != 'undefined') {
      options.pcodes_enabled = mapConfig.pcodes_enabled;
      options.search_options.placeholder = Drupal.t('Filter by location name or pcode');
      options.search_options.search_button_title = Drupal.t('Filter by location name or pcode');
      options.search_options.empty_message = Drupal.t('Be sure to enter a location name or pcode within the current response plan.');
    }
    if (typeof mapConfig.outline_country != 'undefined') {
      options.outline_country = mapConfig.outline_country;
    }
    if (typeof mapConfig.disclaimer != 'undefined') {
      options.disclaimer = mapConfig.disclaimer ?? null;
    }
    if (typeof mapConfig.modal_data_url != 'undefined') {
      options.modal_data_url = mapConfig.modal_data_url;
    }
    return options;
  }

  /**
   * Get the operational presence map state for an object filter select.
   *
   * @param {HTMLElement} select
   *   The object filter select element.
   *
   * @return {ghi.mapState|null}
   *   The map state, if the map has been initialized.
   */
  function getMapState(select) {
    var block = select.closest('.block-plan-operational-presence-map');
    if (!block || !window.ghi?.map) {
      return null;
    }
    var mapElement = block.querySelector('.plan-operational-presence-map-wrapper [id^="plan-operational-presence-map"]');
    return mapElement ? window.ghi.map.getMapState(mapElement.id) : null;
  }

  /**
   * Keep the sidebar content aligned after the object filter changes.
   *
   * @param {ghi.mapState} state
   *   The map state.
   * @param {Number|null} previousFocusId
   *   The previously focused location id.
   */
  function refreshSidebar(state, previousFocusId) {
    if (previousFocusId === null) {
      return;
    }

    var focusedLocation = state.getLocationById(previousFocusId);
    if (focusedLocation) {
      state.focusedLocation = focusedLocation;
      state.showSidebarForObject(focusedLocation);
      return;
    }

    if (state.sidebar?.isVisible()) {
      state.hideSidebar();
    }
  }

  /**
   * Hide the count legend while a single object filter is selected.
   *
   * @param {ghi.mapState} state
   *   The map state.
   * @param {String|null} objectId
   *   The selected object id, if any.
   */
  function refreshLegendVisibility(state, objectId) {
    state.getContainer().find('.map-legend').toggle(!objectId);
  }

  /**
   * Apply the selected object filter to the map state.
   *
   * @param {HTMLElement} select
   *   The object filter select element.
   */
  function applyObjectFilter(select) {
    var state = getMapState(select);
    if (!state) {
      return;
    }

    var objectId = select.value || null;
    if (state.setObjectFilterVariant(objectId) === false) {
      return;
    }

    var previousFocusId = state.focusId;
    // Changing objects can also change which admin levels have data. Refresh
    // that first so sidebar restoration looks up the focused location in the
    // currently visible level.
    state.refreshAdminLevelForCurrentData();
    refreshLegendVisibility(state, objectId);
    refreshSidebar(state, previousFocusId);
  }

  /**
   * Bind local object filtering for one select element.
   *
   * @param {HTMLElement} select
   *   The object filter select element.
   */
  function bindObjectFilter(select) {
    $(select).on('change.planOperationalPresenceObjectFilter', function() {
      applyObjectFilter(select);
    });

    var block = select.closest('.block-plan-operational-presence-map');
    var mapElement = block ? block.querySelector('.plan-operational-presence-map-wrapper [id^="plan-operational-presence-map"]') : null;
    if (mapElement) {
      // The lazy map may not exist when Drupal attaches this behavior. Reapply
      // the select value after initialization so URL-provided defaults work.
      $(mapElement).on('ghi:map-ready.operationalPresenceObjectFilter', function() {
        applyObjectFilter(select);
      });
    }

    applyObjectFilter(select);
  }

  // Attach behaviors.
  Drupal.behaviors.planOperationalPresenceMap = {
    attach: function(context, settings) {
      if (!window.ghi || !window.ghi.mapLazy) {
        return;
      }
      window.ghi.mapLazy.attach('plan_operational_presence_map', context, settings, {
        wrapperSelector: '.plan-operational-presence-map-wrapper',
        buildOptions: buildOptions
      });
      once('plan-operational-presence-object-filter', '.block-plan-operational-presence-map .form-item-object-id select', context).forEach(bindObjectFilter);
    },
    detach: function(context, settings, trigger) {
      if (!window.ghi || !window.ghi.mapLazy) {
        return;
      }
      window.ghi.mapLazy.detach('plan_operational_presence_map', context, settings, trigger);
    }
  };

})(Drupal, jQuery, once);
