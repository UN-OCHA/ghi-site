/* eslint-disable no-bitwise, no-nested-ternary, no-mutable-exports, comma-dangle, strict */

'use strict';

(($, Drupal, drupalSettings) => {

  Drupal.toolbar.ToolbarVisualView.prototype.updateToolbarHeight = function () {
    const $glbToolbar = $('.gin-secondary-toolbar');
    if ($glbToolbar.length) {
      $('body').addClass('has-secondary-toolbar');
      $glbToolbar.addClass('gin-secondary-toolbar--processed');
      this.triggerDisplace();
    }
  }

  Drupal.behaviors.ghiGinLbToolbar = {
    attach: (context) => {
      once('glb-button-discard', '.glb-button-discard ').forEach((item)=>{
        item.addEventListener('click', function (event) {
          // The discard changes button for page manager pages.
          let cancel_button = document.querySelector('#gin_sidebar .form-actions .glb-button[data-drupal-selector="edit-cancel"]');
          if (cancel_button) {
            cancel_button.click();
          }
          // The discard changes button for entity pages.
          let discard_button = document.querySelector('#gin_sidebar .form-actions .glb-button[data-drupal-selector="edit-discard-changes"]');
          if (discard_button) {
            discard_button.click();
          }
        });
      })
    }
  }

})(jQuery, Drupal, drupalSettings);
