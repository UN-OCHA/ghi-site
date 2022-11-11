(function ($, Drupal) {

  // Attach behaviors.
  Drupal.behaviors.hpc_ghi_modal = {
    attach: function(context, settings) {
      $(window)
        .once('ghi-modal-title')
        .on('dialog:aftercreate', function () {
          $('.ui-dialog.ghi-modal-dialog .ui-dialog-title').html(settings.ghi_modal_title);
        });
    }
  }

})(jQuery, Drupal);
