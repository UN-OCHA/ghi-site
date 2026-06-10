(function (Drupal) {

  const LegacyProject = Drupal.GhiPlansLegacyProject;
  const { state } = LegacyProject;

  /**
   * Returns the currently open project detail dialog wrapper, if any.
   */
  const getProjectDetailDialog = () => document.querySelector(
    '.project-detail-modal.ui-dialog',
  );

  /**
   * Temporarily sets document.title so "Save as PDF" uses the project title.
   */
  const prepareProjectPrintTitle = (dialog) => {
    if (
      !dialog ||
      typeof document.documentElement.dataset.legacyProjectPrintTitle !== 'undefined'
    ) {
      return;
    }

    const modalTitle = dialog
      .querySelector('.ui-dialog-title')
      ?.textContent.trim();
    if (!modalTitle) {
      return;
    }

    document.documentElement.dataset.legacyProjectPrintTitle = document.title;
    document.title = modalTitle;
  };

  /**
   * Marks the active legacy project context before native print starts.
   */
  const prepareProjectPrint = () => {
    const dialog = getProjectDetailDialog();

    document.body.classList.toggle(
      'legacy-project-printing-modal',
      Boolean(dialog),
    );

    prepareProjectPrintTitle(dialog);
  };

  /**
   * Restores host-page state after native print preview has really closed.
   */
  const restoreProjectPrint = () => {
    if (typeof document.documentElement.dataset.legacyProjectPrintTitle !== 'undefined') {
      document.title = document.documentElement.dataset.legacyProjectPrintTitle;
      delete document.documentElement.dataset.legacyProjectPrintTitle;
    }

    document.body.classList.remove(
      'legacy-project-printing-modal',
    );
  };

  /**
   * Debounces print cleanup across inconsistent browser print-preview events.
   */
  const scheduleProjectPrintRestore = () => {
    clearTimeout(state.projectPrintRestoreTimer);
    state.projectPrintRestoreTimer = setTimeout(() => {
      // Chrome may fire focus/afterprint while print preview is still open.
      if (state.projectPrintMedia && state.projectPrintMedia.matches) {
        return;
      }
      restoreProjectPrint();
    }, 250);
  };

  /**
   * Registers print lifecycle listeners once for the legacy project library.
   */
  LegacyProject.attachPrintLifecycle = () => {
    window.addEventListener('beforeprint', prepareProjectPrint);
    window.addEventListener('afterprint', scheduleProjectPrintRestore);
    window.addEventListener('focus', scheduleProjectPrintRestore);
    document.addEventListener('visibilitychange', () => {
      if (!document.hidden) {
        scheduleProjectPrintRestore();
      }
    });

    if (window.matchMedia) {
      state.projectPrintMedia = window.matchMedia('print');

      // Handles browsers that switch print media independently of the
      // beforeprint/afterprint event pair.
      const handlePrintMediaChange = (event) => {
        if (event.matches) {
          prepareProjectPrint();
        }
        else {
          scheduleProjectPrintRestore();
        }
      };
      if (state.projectPrintMedia.addEventListener) {
        state.projectPrintMedia.addEventListener(
          'change',
          handlePrintMediaChange,
        );
      }
      else if (state.projectPrintMedia.addListener) {
        state.projectPrintMedia.addListener(handlePrintMediaChange);
      }
    }
  };

})(Drupal);
