(function ($, Drupal) {

  // Attach behaviors.
  Drupal.behaviors.hpc_ghi_modal = {
    attach: function(context, settings) {
      if (typeof settings.ghi_modal_title == 'undefined') {
        return;
      }
      $(window).on('dialog:aftercreate', (e, dialog, $element) => {
        const $dialog = $element.closest('.ui-dialog.ghi-modal-dialog');
        if ($dialog.hasClass('project-detail-modal')) {
          return;
        }
        if (context !== document && ($element.is(context) || $element.has(context).length)) {
          $dialog.find('.ui-dialog-title').html(settings.ghi_modal_title);
        }
      });
    }
  }

})(jQuery, Drupal);
