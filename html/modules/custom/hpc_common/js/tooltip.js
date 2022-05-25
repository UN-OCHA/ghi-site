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
    [].slice.call(document.querySelectorAll('[data-toggle="tooltip"]')).forEach(function (el) {
      tippy(el, {
        allowHTML: true,
        maxWidth: 'calc(100vw - 20%)',
        role: 'tooltip',
      });
    });
  });

  Drupal.behaviors.tooltips = {
    attach: function(context, settings) {
      [].slice.call($(context).find('[data-toggle="tooltip"]')).forEach(function (el) {
        tippy(el, {
          allowHTML: true,
          maxWidth: 'calc(100vw - 20%)',
          role: 'tooltip',
        });
      });
    }
  }

})(jQuery, Drupal);
