(function (Drupal, $) {

  const LegacyProject = Drupal.GhiPlansLegacyProject;
  const { constants } = LegacyProject;

  /**
   * Builds the jQuery UI position object used by Drupal dialog positioning.
   *
   * Core skips this for modal dialogs, but the project-count modal needs it
   * after nested legacy project dialogs remove core's global resize handlers.
   */
  const getDialogCenterPosition = () => {
    const offsets = LegacyProject.getDisplaceOffsets();
    const left = offsets.left - offsets.right;
    const top = offsets.top - offsets.bottom;
    const leftString = `${
      (left > 0 ? '+' : '-') + Math.abs(Math.round(left / 2))
    }px`;
    const topString = `${
      (top > 0 ? '+' : '-') + Math.abs(Math.round(top / 2))
    }px`;

    return {
      my: `center${left !== 0 ? leftString : ''} center${
        top !== 0 ? topString : ''
      }`,
      of: window,
    };
  };

  /**
   * Calculates the same 95% viewport height clamp that core applies to dialogs.
   */
  const getDialogMaxHeight = () => {
    const offsets = LegacyProject.getDisplaceOffsets();
    return Math.round(
      0.95 * (window.innerHeight - offsets.top - offsets.bottom),
    );
  };

  /**
   * Finds open project-count dialog contents that still have a jQuery UI dialog.
   */
  const getProjectCountModalContents = () => {
    if (typeof $.fn.dialog !== 'function') {
      return [];
    }

    return Array.from(
      document.querySelectorAll(
        '.project-count-modal.ui-dialog .ui-dialog-content',
      ),
    ).filter((element) => $(element).data('ui-dialog'));
  };

  /**
   * Removes only this module's resize listeners, leaving core handlers alone.
   */
  LegacyProject.detachProjectCountModalResize = () => {
    $(window).off(constants.projectCountModalResizeNamespace);
    $(document).off(constants.projectCountModalResizeNamespace);
  };

  /**
   * Reapplies viewport-dependent dialog options to the project list modal.
   */
  LegacyProject.refreshProjectCountModals = () => {
    const elements = getProjectCountModalContents();
    if (!elements.length) {
      LegacyProject.detachProjectCountModalResize();
      return;
    }

    elements.forEach((element) => {
      const $element = $(element);
      $element.dialog('option', {
        maxHeight: getDialogMaxHeight(),
        position: getDialogCenterPosition(),
      });
      element.dispatchEvent(
        new CustomEvent('dialogContentResize', { bubbles: true }),
      );
    });
  };

  // Match core's 20 ms debounce to avoid excessive work while resizing.
  const debouncedRefreshProjectCountModals = Drupal.debounce(
    LegacyProject.refreshProjectCountModals,
    20,
  );

  /**
   * Mirrors Drupal core's dialog resize attachment for the project-count modal.
   */
  LegacyProject.attachProjectCountModalResize = () => {
    const elements = getProjectCountModalContents();
    if (!elements.length) {
      LegacyProject.detachProjectCountModalResize();
      return;
    }

    elements.forEach((element) => {
      const $element = $(element);
      const $dialog = $element
        .dialog('option', { resizable: false, draggable: false })
        .dialog('widget');

      if ($dialog[0]) {
        $dialog[0].style.position = 'fixed';
      }
    });

    // Mirror core's dialog.position.js resize hooks for the surviving project
    // list dialog. We keep a module-specific namespace so closing a nested
    // dialog cannot detach this handler from the parent.
    $(window)
      .off(constants.projectCountModalResizeNamespace)
      .on(
        `resize${constants.projectCountModalResizeNamespace} scroll${constants.projectCountModalResizeNamespace}`,
        debouncedRefreshProjectCountModals,
      );
    $(document)
      .off(constants.projectCountModalResizeNamespace)
      .on(
        `drupalViewportOffsetChange${constants.projectCountModalResizeNamespace}`,
        debouncedRefreshProjectCountModals,
      );

    LegacyProject.refreshProjectCountModals();
  };

})(Drupal, jQuery);
