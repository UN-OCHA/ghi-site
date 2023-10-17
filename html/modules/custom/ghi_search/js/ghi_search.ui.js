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

  Drupal.behaviors.GhiSearchUi = {
    attach: function(context, settings) {

      let search_form_id = 'block-exposedformsearch-solrpage-search-results';
      let toggle = $('[aria-controls="' + search_form_id + '"]');
      $(toggle).once('search-toggle').on('click', function () {
        if ($(this).attr('aria-expanded') == 'true') {
          $('body').addClass('search-form-open');
        }
        else {
          $('body').removeClass('search-form-open');
        }
      });

      if ($(context).hasClass('path-search') && !$(context).hasClass('search-form-open')) {
        $(toggle).trigger('click');
        $('#' + search_form_id).attr('data-cd-hidden', 'false');
        $('body').addClass('search-form-open');
      }
    }
  }

})(jQuery, Drupal, drupalSettings);
