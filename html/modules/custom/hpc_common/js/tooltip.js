/**
 * @file
 * Popover functionality.
 */

/**
 * @file
 * Auto-init script ported from tippy.js v4
 *
 * @requires popper.js
 * @requires tippy.js
 */
 (function ($, Drupal) {

  document.addEventListener("DOMContentLoaded", function () {
    once('tooltip', '[data-toggle="tooltip"]').forEach(function (el) {
      Drupal.hpcTooltips.initTippy(el);
    });
  });

  Drupal.hpcTooltips = {};

  Drupal.hpcTooltips.initTippy = function (el) {
    tippy(el, {
      allowHTML: true,
      maxWidth: 'calc(100vw - 20%)',
      role: 'tooltip',
      interactive: true,
      appendTo: document.body,
      aria: {
        expanded: null,
      },
      theme: $(el).attr('data-theme') ?? null,
    });
  }

  Drupal.behaviors.tooltips = {
    attach: function(context, settings) {
      once('tooltip', '[data-toggle="tooltip"]', context).forEach(function (el) {
        Drupal.hpcTooltips.initTippy(el);
      });
    }
  }

})(jQuery, Drupal);
