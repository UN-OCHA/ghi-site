(function ($, Drupal) {

  Drupal.GhiBlockSettings = {
    blocks: {}
  };

  Drupal.behaviors.GhiBlockSettings = {
    attach: function (context, settings) {
      if (context != document) {
        return;
      }

      // Store block settings passed in via the URL.
      url = new URL(window.location);
      let block_settings = url.searchParams.get('block_settings') || null;
      if (block_settings) {
        Drupal.GhiBlockSettings.blocks = JSON.parse(block_settings);
      }

      $(document).on('ghi-block-setting', function (event, args) {
        if (!Drupal.GhiBlockSettings.blocks.hasOwnProperty(args.block_selector)) {
          Drupal.GhiBlockSettings.blocks[args.block_selector] = {};
        }
        $.extend(Drupal.GhiBlockSettings.blocks[args.block_selector], args.settings);

        if (history.pushState) {
          url = new URL(window.location);
          url.searchParams.set('block_settings', JSON.stringify(Drupal.GhiBlockSettings.blocks));
          window.history.pushState({path:url.toString()}, '', url.toString());
      }
      });
    }
  }

  Drupal.GhiBlockSettings.getBlockSettings = function(block_id) {
    if (!Drupal.GhiBlockSettings.blocks.hasOwnProperty(block_id)) {
      return null;
    }
    return Drupal.GhiBlockSettings.blocks[block_id];
  }

  Drupal.GhiBlockSettings.getBlockSetting = function(block_id, key) {
    let block_settings = Drupal.GhiBlockSettings.getBlockSettings(block_id);
    if (!block_settings) {
      return null;
    }
    return block_settings[key];
  }

}(jQuery, Drupal));