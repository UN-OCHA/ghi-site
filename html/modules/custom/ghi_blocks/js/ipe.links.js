/**
* @file
*/
(function ($, Drupal) {

  /**
   * Attaches the frontend links behavior.
   */
   Drupal.behaviors.templateFrontendLinks = {
    attach: function (context, settings) {
      once('ipe-frontend-links', '.layout-builder-ipe--link.dropbutton-wrapper[tabindex="1"]').forEach((el) => {
        $(el).on('keypress', (event) => {
          if (event.which == 13) {
            $(el).find('.dropbutton-toggle button').trigger('click');
          $(el).find('a:first').trigger('focus');
          }
        });
      });
    }
  }

})(jQuery, Drupal);