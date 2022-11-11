/**
 * @file
 * Make modifications to previewed content in GHI blocks.
 *
 * For the moment this only disables links so that editors do not accidentally
 * click on them and abort block configuration by mistake.
 *
 * @todo Should this be made configurable? Should there be any indication that
 *   there are actually links in the content but they have been disabled?
 */

 (function ($, Drupal, drupalSettings) {

  Drupal.behaviors.GhiBlockPreview = {
    attach: function(context, settings) {
      $('[data-block-preview]', context).find('a').each(function () {
        $(this).removeAttr('href');
      });
    }
  }

})(jQuery, Drupal, drupalSettings);
