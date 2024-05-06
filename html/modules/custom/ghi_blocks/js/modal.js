(function ($, Drupal) {

  // Attach behaviors.
  Drupal.behaviors.hpc_ghi_modal = {
    attach: function(context, settings) {
      if (typeof settings.ghi_modal_title == 'undefined') {
        return;
      }
      $(window).on('dialog:aftercreate', (e, dialog, $element) => {
        $('.ui-dialog.ghi-modal-dialog .ui-dialog-title').html(settings.ghi_modal_title);
      });
    }
  }

})(jQuery, Drupal);
