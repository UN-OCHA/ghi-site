/**
 * @file
 * UI additions for the GHI search.
 *
 * This makes the search bar always stay visible on on the search results page.
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
        $(toggle).attr('disabled', 'disabled');
      }

      var search_toggle = document.getElementById(search_toggle_id);
      if ($(context).find('#' + search_toggle_id) && search_toggle) {
        var observer = new MutationObserver(function(mutations) {
          if ($('body').hasClass('path-search')) {
            if ($(search_toggle).attr('aria-expanded') == 'false') {
              $(search_toggle).attr('aria-expanded', 'true');
              $('body').addClass('search-form-open');
              $('#' + search_form_id).attr('data-cd-hidden', 'false');
            }
          }
          else {
            if ($(search_toggle).attr('aria-expanded') == 'true') {
              $('body').addClass('search-form-open');
            }
            else {
              $('body').removeClass('search-form-open');
            }
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
