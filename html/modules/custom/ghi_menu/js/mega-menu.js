/* eslint-disable no-bitwise, no-nested-ternary, no-mutable-exports, comma-dangle, strict */

'use strict';

(($, Drupal) => {

  Drupal.behaviors.MegaMenu = {
    attach: (context) => {
      once('mega-menu', '.mega-menu .vertical-tabs__menu-item').forEach((item) => {
        // Prevent early "click-away" caused by the cd-dropdown component.
        $(item).find('a').attr('data-cd-toggler', true);
      })
    }
  }

})(jQuery, Drupal);
