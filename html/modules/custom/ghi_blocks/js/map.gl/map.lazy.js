(function ($, Drupal) {

  'use strict';

  if (!window.ghi) {
    window.ghi = {};
  }

  var registry = {};

  /**
   * Get the map element for a map config.
   *
   * @param {Object} mapConfig
   *   The map settings.
   *
   * @return {HTMLElement|null}
   *   The map element.
   */
  function getElement(mapConfig) {
    return mapConfig && mapConfig.id ? document.getElementById(mapConfig.id) : null;
  }

  /**
   * Get the registered map config for a lazy map payload.
   *
   * @param {Object} mapConfig
   *   The map settings.
   *
   * @return {Object|null}
   *   The registered map config.
   */
  function getRegisteredMapConfig(mapConfig) {
    if (!mapConfig || !mapConfig.settings_key || !registry[mapConfig.settings_key]) {
      return null;
    }
    return registry[mapConfig.settings_key].maps[mapConfig.id] ?? null;
  }

  /**
   * Mark a map as initialized both locally and in the registered settings.
   *
   * @param {Object} mapConfig
   *   The map settings.
   */
  function markInitialized(mapConfig) {
    mapConfig.initialized = true;
    var registeredMapConfig = getRegisteredMapConfig(mapConfig);
    if (registeredMapConfig) {
      registeredMapConfig.initialized = true;
    }
  }

  /**
   * Mark a map as no longer initialized.
   *
   * @param {Object} mapConfig
   *   The map settings.
   */
  function markUninitialized(mapConfig) {
    mapConfig.initialized = false;
    var registeredMapConfig = getRegisteredMapConfig(mapConfig);
    if (registeredMapConfig) {
      registeredMapConfig.initialized = false;
    }
  }

  /**
   * Initialize the map once its data and assets are available.
   *
   * @param {Object} mapConfig
   *   The map settings.
   */
  function initMap(mapConfig) {
    if (!mapConfig || !mapConfig.id || mapConfig.initialized) {
      return;
    }

    var element = getElement(mapConfig);
    if (!element || element.hasAttribute('data-map-processed')) {
      markInitialized(mapConfig);
      return;
    }

    if (typeof mapConfig.json == 'undefined' || mapConfig.json === null) {
      element.setAttribute('data-map-empty', '');
      return;
    }

    if (!window.ghi || !window.ghi.mapbox || !window.ghi.map) {
      element.setAttribute('data-map-error', '');
      return;
    }

    var registered = registry[mapConfig.settings_key] || {};
    var buildOptions = registered.buildOptions || function () {
      return {};
    };
    var options = buildOptions(mapConfig);
    if (isPngCaptureMode()) {
      options.png_capture_mode = true;
      options.preserve_drawing_buffer = true;
    }
    markInitialized(mapConfig);
    ghi.map.init(mapConfig.id, mapConfig.json, options);
  }

  /**
   * Load the map data and shared map library through Drupal Ajax.
   *
   * @param {Object} mapConfig
   *   The map settings.
   */
  function requestMap(mapConfig) {
    if (!mapConfig || !mapConfig.id || !mapConfig.data_url) {
      return;
    }

    var element = getElement(mapConfig);
    if (!element || element.hasAttribute('data-map-loading') || element.hasAttribute('data-map-assets-loaded')) {
      return;
    }

    element.setAttribute('data-map-loading', '');
    var ajax = Drupal.ajax({
      url: mapConfig.data_url,
      httpMethod: 'GET',
      progress: false
    });
    var request = ajax.execute();
    if (request && request.fail) {
      request.fail(function(error) {
        element.removeAttribute('data-map-loading');
        element.setAttribute('data-map-error', '');
        if (window.console && console.error) {
          console.error(error);
        }
      });
    }
  }

  /**
   * Determine whether the current page is being rendered for Snap PNG output.
   *
   * @return {Boolean}
   *   TRUE when this is a Snap PNG render.
   */
  function isPngCaptureMode() {
    if (document.documentElement.classList.contains('snap--png')) {
      return true;
    }
    try {
      return new URLSearchParams(window.location.search).get('hpc_download') === 'png';
    }
    catch (e) {
      return false;
    }
  }

  /**
   * Initialize a map when it nears the viewport.
   *
   * @param {Object} mapConfig
   *   The map settings.
   * @param {Object} options
   *   Lazy-loading options.
   */
  function initWhenVisible(mapConfig, options) {
    var element = getElement(mapConfig);
    if (!element) {
      return;
    }

    var observedElement = element.closest(options.observedSelector || '.map-container') || element;
    var wrapper = options.wrapperSelector ? element.closest(options.wrapperSelector) : null;

    if (wrapper && options.triggerSelector && !wrapper.hasAttribute('data-map-lazy-click-bound')) {
      wrapper.setAttribute('data-map-lazy-click-bound', '');
      wrapper.addEventListener('click', function(event) {
        if (!event.target.closest(options.triggerSelector) || mapConfig.initialized || element.hasAttribute('data-map-processed')) {
          return;
        }
        event.preventDefault();
        requestMap(mapConfig);
      });
    }

    if (isPngCaptureMode()) {
      if (typeof mapConfig.json != 'undefined') {
        initMap(mapConfig);
        return;
      }
      requestMap(mapConfig);
      return;
    }

    if (typeof mapConfig.json != 'undefined' || !('IntersectionObserver' in window)) {
      if (typeof mapConfig.json != 'undefined') {
        initMap(mapConfig);
        return;
      }
      requestMap(mapConfig);
      return;
    }

    var rect = observedElement.getBoundingClientRect();
    if (rect.top < window.innerHeight + 400 && rect.bottom > -400) {
      requestMap(mapConfig);
      return;
    }

    var observer = new IntersectionObserver(function(entries) {
      entries.forEach(function(entry) {
        if (!entry.isIntersecting) {
          return;
        }
        observer.disconnect();
        mapConfig.observer = null;
        requestMap(mapConfig);
      });
    }, {
      rootMargin: '400px 0px'
    });
    mapConfig.observer = observer;
    observer.observe(observedElement);
  }

  /**
   * Attach lazy loading for map settings.
   *
   * @param {String} settingsKey
   *   The Drupal settings key.
   * @param {Object} context
   *   The behavior context.
   * @param {Object} settings
   *   Drupal behavior settings.
   * @param {Object} options
   *   Lazy-loading options.
   */
  function attach(settingsKey, context, settings, options) {
    if (!settings[settingsKey] || !Object.keys(settings[settingsKey]).length) {
      return;
    }

    registry[settingsKey] = registry[settingsKey] || {
      maps: {},
    };
    registry[settingsKey].buildOptions = options.buildOptions;

    Object.keys(settings[settingsKey]).forEach(function(mapKey) {
      var mapConfig = settings[settingsKey][mapKey];
      if (!mapConfig.id) {
        return;
      }
      mapConfig.settings_key = mapConfig.settings_key || settingsKey;
      registry[settingsKey].maps[mapConfig.id] = mapConfig;

      var $element = $(context).is('#' + mapConfig.id) ? $(context) : $('#' + mapConfig.id, context);
      if (!context || !$element.length) {
        return;
      }
      initWhenVisible(mapConfig, options);
    });
  }

  /**
   * Detach lazy-loaded maps before Drupal removes their markup.
   *
   * @param {String} settingsKey
   *   The Drupal settings key.
   * @param {Object} context
   *   The behavior context.
   * @param {Object} settings
   *   Drupal behavior settings.
   * @param {String} trigger
   *   The Drupal detach trigger.
   */
  function detach(settingsKey, context, settings, trigger) {
    if (trigger !== 'unload' || !settings || !settings[settingsKey] || !window.ghi?.map) {
      return;
    }
    Object.keys(settings[settingsKey]).forEach(function(mapKey) {
      var mapConfig = settings[settingsKey][mapKey];
      if (!mapConfig.id) {
        return;
      }
      var $element = $(context).is('#' + mapConfig.id) ? $(context) : $('#' + mapConfig.id, context);
      if (!$element.length) {
        return;
      }
      if (mapConfig.observer) {
        mapConfig.observer.disconnect();
        mapConfig.observer = null;
      }
      window.ghi.map.destroy(mapConfig.id);
      markUninitialized(mapConfig);
      var registered = registry[mapConfig.settings_key || settingsKey] ?? null;
      if (registered?.maps) {
        delete registered.maps[mapConfig.id];
      }
    });
  }

  window.ghi.mapLazy = {
    attach: attach,
    detach: detach,
    initMap: initMap,
    requestMap: requestMap
  };

  /**
   * Initialize the map after Drupal Ajax has loaded the shared map library.
   */
  Drupal.AjaxCommands.prototype.ghiMapInit = function(ajax, response) {
    var mapConfig = response.map || {};
    var element = getElement(mapConfig);
    if (element) {
      element.removeAttribute('data-map-loading');
      element.setAttribute('data-map-data-loaded', '');
      element.setAttribute('data-map-assets-loaded', '');
    }
    initMap(mapConfig);
  };

})(jQuery, Drupal);
