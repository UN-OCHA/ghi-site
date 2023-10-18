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
      let search_toggle_id = 'block-exposedformsearch-solrpage-search-results-toggler';
      let toggle = $('[aria-controls="' + search_form_id + '"]');

      if ($('body', context).hasClass('path-search') && !$('body', context).hasClass('search-form-open')) {
        $(toggle).trigger('click');
        $('#' + search_form_id).attr('data-cd-hidden', 'false');
        $('body').addClass('search-form-open');
      }

      if ($(context).find('#' + search_toggle_id)) {
        var search_toggle = document.getElementById(search_toggle_id);
        var observer = new MutationObserver(function(mutations) {
          if ($(search_toggle).attr('aria-expanded') == 'true') {
            $('body').addClass('search-form-open');
          }
          else {
            $('body').removeClass('search-form-open');
          }
        });
        observer.observe(search_toggle, {
          attributes: true,
          attributeFilter: ['aria-expanded']
        });
      }
    }
  }

})(jQuery, Drupal, drupalSettings);
