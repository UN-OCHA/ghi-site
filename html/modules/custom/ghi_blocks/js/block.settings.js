(function ($, Drupal) {

  // Define the namespace.
  Drupal.GhiBlockSettings = {
    blocks: {}
  };

  // Attach behaviors.
  Drupal.behaviors.GhiBlockSettings = {
    attach: function (context, settings) {
      if (context != document) {
        return;
      }

      // Store block settings passed in via the URL.
      url = new URL(window.location);
      let block_settings = url.searchParams.get('bs') || null;
      if (block_settings) {
        Drupal.GhiBlockSettings.blocks = JSON.parse(atob(decodeURI(block_settings)));
      }

      // Store block settings whenever an element changes and decides to
      // trigger the ghi-block-settings event.
      // The event needs an object argument with 2 properties:
      // "element": This is the triggering element and must be nested inside a
      //            GHI block.
      // "settings": A settings object.
      $(document).on('ghi-block-setting', function (event, args) {
        Drupal.GhiBlockSettings.setBlockSettingsForElement(args.element, args.settings);
      });
    }
  }

  /**
   * Get all block settings for a specific block id.
   *
   * @param {string} block_id
   *   The block id.
   *
   * @returns {Object}
   *   A settings object.
   */
  Drupal.GhiBlockSettings.getBlockSettings = function(block_id) {
    if (!Drupal.GhiBlockSettings.blocks.hasOwnProperty(block_id)) {
      return null;
    }
    return Drupal.GhiBlockSettings.blocks[block_id];
  }

  /**
   * Get block settings for a specific block id and settings key.
   *
   * @param {string} block_id
   *   The block id.
   * @param {string} key
   *   The settings key.
   *
   * @returns {Object}
   *   A settings object.
   */
  Drupal.GhiBlockSettings.getBlockSetting = function(block_id, key) {
    let block_settings = Drupal.GhiBlockSettings.getBlockSettings(block_id);
    if (!block_settings) {
      return null;
    }
    return block_settings[key];
  }

  /**
   * Get block settings for a specific DOM element and settings key.
   *
   * This will try to find a GHI block in the parents list of the given node,
   * and return the settings stored for that block if (a) there is a parent
   * block and if (b) there are settings for it.
   *
   * @param {Node} node
   *   The node object.
   * @param {string} key
   *   The settings key.
   *
   * @returns {Object}
   *   A settings object.
   */
  Drupal.GhiBlockSettings.getBlockSettingForElement = function(node, key) {
    let block = $(node).parents('.ghi-block')[0] || null;
    let block_id = block ? $(block).attr('id') : null;
    return block_id ? Drupal.GhiBlockSettings.getBlockSetting(block_id, key) : null;
  }

  /**
   * Set block settings for a specific DOM element.
   *
   * This will try to find a GHI block in the parents list of the given node,
   * and store the settings for that block if there is a parent block.
   *
   * @param {Node} node
   *   The node object.
   * @param {Object} settings
   *   The settings object.
   */
   Drupal.GhiBlockSettings.setBlockSettingsForElement = function(node, settings) {
    let block = $(node).parents('.ghi-block')[0] || null;
    let block_id = block ? $(block).attr('id') : null;
    if (!block_id) {
      return;
    }
    if (!Drupal.GhiBlockSettings.blocks.hasOwnProperty(block_id)) {
      Drupal.GhiBlockSettings.blocks[block_id] = {};
    }
    $.extend(Drupal.GhiBlockSettings.blocks[block_id], settings);
    if (history.pushState) {
      url = new URL(window.location);
      url.searchParams.set('bs', btoa(JSON.stringify(Drupal.GhiBlockSettings.blocks)));
      window.history.pushState({path:url.toString()}, '', url.toString());
      $(document).trigger('ghi-url-updated', url.toString());
    }
  }

  /**
   * Set block settings for a specific DOM element and settings key.
   *
   * This will try to find a GHI block in the parents list of the given node,
   * and store the settings for that block if there is a parent block.
   *
   * @param {Node} node
   *   The node object.
   * @param {string} key
   *   The settings key.
   * @param {Object} settings
   *   The settings object.
   */
  Drupal.GhiBlockSettings.setBlockSettingForElement = function(node, key, settings) {
    Drupal.GhiBlockSettings.setBlockSettingsForElement(node, {
      [key]: settings,
    });
  }

}(jQuery, Drupal));