(function ($, Drupal) {

  // Attach behaviors.
  Drupal.behaviors.layout_builder_modal_admin = {
    attach: function(context, settings) {
      $layout_builder_modal = $('#layout-builder-modal');
      if ($layout_builder_modal.length == 0) {
        // Nothing to do if there is no modal.
        return;
      }
      // Toggle the class depending on the presence of a second level actions
      // wrapper.
      $action_wrappers = $(context).find('.second-level-actions-wrapper');
      $layout_builder_modal.toggleClass('has-second-level-actions', $action_wrappers.length > 0);
    }
  }

})(jQuery, Drupal);
