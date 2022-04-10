/* eslint-disable no-bitwise, no-nested-ternary, no-mutable-exports, comma-dangle, strict */

'use strict';

(($, Drupal, drupalSettings) => {

  Drupal.toolbar.ToolbarVisualView.prototype.updateToolbarHeight = function () {
    const glbToolbar = $('.glb-toolbar');
    const ginToolbar = $('#gin-toolbar-bar');
    const ginToolbarHeight = ginToolbar.outerHeight();
    this.model.set('height', ginToolbarHeight);
    const body = $('body')[0];
    body.style.setProperty('padding-top', this.model.get('height') + 'px', 'important');
    glbToolbar.css('top', ginToolbarHeight);
    glbToolbar.addClass('glb-toolbar--processed');
    this.triggerDisplace();
  }

})(jQuery, Drupal, drupalSettings);
