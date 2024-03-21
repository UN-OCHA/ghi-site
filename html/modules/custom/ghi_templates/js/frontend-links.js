/**
* @file
*/
(function ($, Drupal) {

  /**
   * Attaches the frontend links behavior.
   */
   Drupal.behaviors.templateFrontendLinks = {
    attach: function (context, settings) {
      once('template-frontend-links', '.dropbutton-wrapper[tabindex="1"]').forEach((el) => {
        $(el).on('keypress', (event) => {
          console.log(event.which);
          if (event.which == 13) {
            $(el).find('.dropbutton-toggle button').trigger('click');
            $(el).find('a:first').trigger('focus');
          }
        });
      });
    }
  }

})(jQuery, Drupal);