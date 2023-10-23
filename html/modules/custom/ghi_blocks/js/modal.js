(function ($, Drupal) {

  // Attach behaviors.
  Drupal.behaviors.hpc_ghi_modal = {
    attach: function(context, settings) {
      once('ghi-modal-title', 'body').forEach(() => {
        document.addEventListener('dialog:aftercreate', e => {
          $('.ui-dialog.ghi-modal-dialog .ui-dialog-title').html(settings.ghi_modal_title);
        });
      });
    }
  }

})(jQuery, Drupal);
