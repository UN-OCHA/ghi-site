/**
 * @file
 * Allows review-reason tooltips to be dismissed with Escape.
 */
(function (Drupal, once) {
  'use strict';

  Drupal.behaviors.ghiReviewReasons = {
    attach: function () {
      once('ghi-review-reasons-escape', 'html').forEach(function () {
        document.addEventListener('keydown', function (event) {
          if (event.key === 'Escape') {
            document.querySelectorAll('.ghi-review-reason__help').forEach(function (element) {
              element._tippy?.hide();
            });
          }
        });
      });
    },
  };
})(Drupal, once);
