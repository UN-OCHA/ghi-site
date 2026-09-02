/**
 * @file
 * Load block previews and make previewed content safe for editing.
 *
 * Preview placeholders are replaced through Ajax so large renders stay out of
 * the configuration form state. Links in the rendered preview are disabled so
 * editors do not accidentally abort block configuration by clicking them.
 *
 * @todo Should this be made configurable? Should there be any indication that
 *   there are actually links in the content but they have been disabled?
 */

(function ($, Drupal, once) {

  Drupal.behaviors.GhiBlockPreview = {
    attach: function(context) {
      once('ghi-block-preview-load', '[data-block-preview-url]', context).forEach(function (element) {
        var ajax = Drupal.ajax({
          url: element.getAttribute('data-block-preview-url'),
          element: element,
          progress: {
            type: 'throbber'
          }
        });
        ajax.execute();
      });
      $('[data-block-preview]', context).find('a').each(function () {
        $(this).removeAttr('href');
      });
    },
  };

})(jQuery, Drupal, once);
