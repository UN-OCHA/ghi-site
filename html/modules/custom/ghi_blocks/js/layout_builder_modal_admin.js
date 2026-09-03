(function ($, Drupal) {
  'use strict';

  // Attach behaviors.
  Drupal.behaviors.layout_builder_modal_admin = {
    attach: function (context, settings) {
      const $layoutBuilderModal = $('#layout-builder-modal');
      if ($layoutBuilderModal.length === 0) {
        // Nothing to do if there is no modal.
        return;
      }

      // The action wrapper is moved outside its AJAX replacement container so
      // that it can remain fixed above the main form actions. Remove it when
      // that container has been replaced, otherwise a stale action row remains
      // visible while editing a nested item or group.
      $layoutBuilderModal.find('.second-level-actions-wrapper').each(function () {
        const placeholder = $.data(this, 'layoutBuilderModalAdminPlaceholder');
        if (placeholder && !placeholder.isConnected) {
          $(this).remove();
        }
      });

      // Toggle the class depending on the presence of a second level actions
      // wrapper.
      const $actionWrappers = $layoutBuilderModal.find('.second-level-actions-wrapper:not(.glb-visually-hidden)');
      $actionWrappers.each(function () {
        const $wrapper = $(this);
        const $form = $wrapper.closest('form.glb-canvas-form');
        const $primaryActions = $form.children('.glb-canvas-form__actions');
        if ($primaryActions.length && this.parentElement !== $form.get(0)) {
          // Keep nested actions outside the scrollable settings area while
          // retaining them inside their form for Ajax submissions.
          const placeholder = document.createComment('second-level-actions-wrapper');
          $wrapper.before(placeholder);
          $.data(this, 'layoutBuilderModalAdminPlaceholder', placeholder);
          $wrapper.insertBefore($primaryActions);
        }
      });
      $layoutBuilderModal.toggleClass('has-second-level-actions', $actionWrappers.length > 0);
    },
  };

})(jQuery, Drupal);
