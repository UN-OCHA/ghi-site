(function (Drupal, once, $) {

  /**
   * Legacy project pages come from a public GitHub Pages export, but the
   * controller renders only a sanitized `.create-project` fragment inside the
   * Drupal page. That lets the fallback page, modal, paging, and print output
   * share the same DOM instead of coordinating a same-origin iframe.
   *
   * The project detail dialog can open on top of a project list dialog. Drupal
   * core removes its global dialog resize handler when any dialog closes, so
   * this library mirrors the small part needed to keep the parent project list
   * centered and height-limited after the nested detail dialog closes.
   *
   * Paging captures the current visible project-link order from the project
   * list modal. Subsequent projects are loaded from small sanitized fragment
   * responses with `fetch()`, while adjacent projects are prefetched so the
   * common previous/next path stays responsive.
   */

  Drupal.GhiPlansLegacyProject = Drupal.GhiPlansLegacyProject || {};

  const LegacyProject = Drupal.GhiPlansLegacyProject;

  // Shared state for the component files in this library.
  LegacyProject.state = LegacyProject.state || {
    // Timer used to debounce cleanup after native print preview closes.
    projectPrintRestoreTimer: null,
    // Stored matchMedia('print') object for browsers that signal print via CSS media.
    projectPrintMedia: null,
    // Snapshot of the current project list order used by detail-modal paging.
    projectDetailPagerContext: null,
    // Prevents one horizontal wheel gesture from triggering multiple pages.
    projectDetailPagerWheelLocked: false,
    // Cached fragment requests for the current detail pager siblings.
    projectDetailPagerPreloads: new Map(),
    // Tracks whether the just-closed dialog was the nested project detail modal.
    closingProjectDetailModal: false,
  };

  // Tuning values and jQuery namespaces used by multiple components.
  LegacyProject.constants = Object.assign(LegacyProject.constants || {}, {
    // Namespace for this module's project-count dialog resize listeners.
    projectCountModalResizeNamespace: '.ghiLegacyProjectCountResize',
    // Minimum horizontal gesture distance before paging is attempted.
    projectDetailPagerGestureThreshold: 40,
    // Number of previous/next sibling project fragments to prefetch.
    projectDetailPagerPreloadRadius: 1,
  });

  /**
   * Provides Drupal displace offsets even if the displace library is absent.
   */
  LegacyProject.getDisplaceOffsets = () => Drupal.displace?.offsets || {
    top: 0,
    right: 0,
    bottom: 0,
    left: 0,
  };

  /**
   * Checks dialog wrapper classes even while jQuery UI is being torn down.
   */
  LegacyProject.isDialogWithClass = (element, className) => {
    if (!element || typeof $.fn.dialog !== 'function') {
      return false;
    }
    const $element = $(element);
    try {
      return $element.dialog('widget').hasClass(className);
    }
    catch (e) {
      return $element.closest(`.${className}.ui-dialog`).length > 0;
    }
  };

  /**
   * Returns the jQuery UI dialog wrapper for a dialog content element.
   */
  LegacyProject.getDialogWidget = (element) => {
    if (!element || typeof $.fn.dialog !== 'function') {
      return null;
    }

    const $element = $(element);
    try {
      const $widget = $element.dialog('widget');
      return $widget[0] || null;
    }
    catch (e) {
      return $element.closest('.ui-dialog')[0] || null;
    }
  };

  LegacyProject.isProjectDetailDialog = (element) => (
    LegacyProject.isDialogWithClass(element, 'project-detail-modal')
  );

  LegacyProject.isProjectCountDialog = (element) => (
    LegacyProject.isDialogWithClass(element, 'project-count-modal')
  );

  Drupal.behaviors.ghiLegacyProject = {
    attach() {
      once('ghi-legacy-project-init', 'html', document).forEach(() => {
        LegacyProject.attachPrintLifecycle?.();

        document.addEventListener('click', (event) => {
          const link = event.target.closest?.(
            'a.project-detail-modal[data-legacy-project-id]',
          );
          if (link) {
            LegacyProject.captureProjectDetailPagerContext?.(link);
          }
        }, true);

        window.addEventListener('dialog:aftercreate', (event) => {
          if (LegacyProject.isProjectCountDialog(event.target)) {
            LegacyProject.attachProjectCountModalResize?.();
          }
          if (LegacyProject.isProjectDetailDialog(event.target)) {
            setTimeout(() => {
              LegacyProject.attachProjectDetailPager?.(event.target);
            }, 0);
          }
        });

        window.addEventListener('dialog:beforeclose', (event) => {
          LegacyProject.state.closingProjectDetailModal =
            LegacyProject.isProjectDetailDialog(event.target);
          if (LegacyProject.isProjectCountDialog(event.target)) {
            LegacyProject.clearProjectDetailPagerContext?.();
          }
        });

        window.addEventListener('dialog:afterclose', () => {
          if (LegacyProject.state.closingProjectDetailModal) {
            LegacyProject.state.closingProjectDetailModal = false;
            setTimeout(LegacyProject.attachProjectCountModalResize, 0);
            return;
          }

          setTimeout(LegacyProject.refreshProjectCountModals, 0);
        });

        // The library can be attached after a project list dialog has already
        // opened, so attach once here in addition to the dialog event path.
        LegacyProject.attachProjectCountModalResize?.();
        LegacyProject.attachOpenProjectDetailPagers?.();
      });
    },
  };

})(Drupal, once, jQuery);
