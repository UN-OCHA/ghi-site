/**
 * @file
 * Custom scripts for theme.
 */

 (function ($, Drupal) {
  Drupal.behaviors.hpc_toggle = {
    attach: function (context, setting) {
      $('.toggle[data-target-selector]', context).click(function (e) {
        Drupal.hpc_toggle.doToggle(this);
      });
      $('.toggle[data-target-selector]', context).keypress(function(e) {
        if (e.which == 13) {
          Drupal.hpc_toggle.doToggle(this);
        }
      });
    }
  }

  Drupal.hpc_toggle = {};
  Drupal.hpc_toggle.doToggle = function(el) {
    el = el || this;
    var target_selector = $(el).data('target-selector');
    if (!target_selector) {
      return;
    }
    var parent_selector = $(el).data('parent-selector') || 'span.toggle';
    $(el).toggleClass('open');
    $(el).parents(parent_selector).find(target_selector).slideToggle('slow');
  };

})(jQuery, Drupal);