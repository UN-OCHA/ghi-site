(function (Drupal, once, $) {

  /**
   * Legacy project pages are loaded through same-origin iframes so the external
   * GitHub Pages HTML can be proxied, lightly normalized, and displayed without
   * folding its markup into the Drupal page. The standalone fallback page can
   * resize the iframe to the project content. The modal keeps a fixed viewport
   * height and lets the iframe scroll, because large project pages otherwise
   * make the dialog taller than the browser window.
   *
   * Browser printing is handled separately from the visible iframe. When a
   * legacy project modal or standalone fallback page is printed, this behavior
   * builds a hidden print source in the host document by cloning the iframe's
   * project content and stylesheet references. Drupal print CSS then prints
   * only that clone. This avoids printing modal chrome, avoids iframe
   * scrollbars and pagination quirks, and makes Chromium's print preview more
   * stable when users change settings such as orientation or headers and
   * footers.
   *
   * Legacy project detail dialogs are opened on top of the project list dialog.
   * Drupal core's dialog resize handler is global and is removed when any
   * dialog closes. Core also avoids recentering modal dialogs on resize. This
   * behavior therefore mirrors the relevant viewport tracking for the project
   * list modal under its own namespace and adds the missing modal recentering
   * while that dialog remains open. The parent project list also supplies the
   * ordered paging context for the nested project detail dialog. During paging,
   * the current iframe stays mounted while the next project loads in a hidden
   * staging iframe; the staging iframe is promoted only after it has loaded, so
   * users do not see a blank iframe between projects. The immediate previous
   * and next siblings are warmed in hidden preload iframes, keeping the common
   * one-step paging path fast without loading the whole project list.
   */

  Drupal.GhiPlansLegacyProject = Drupal.GhiPlansLegacyProject || {};

  const LegacyProject = Drupal.GhiPlansLegacyProject;

  // Module-scoped state shared between the component files in this library.
  LegacyProject.state = LegacyProject.state || {
    projectModalPrintRestoreTimer: null,
    projectModalPrintMedia: null,
    legacyProjectPrintActive: false,
    projectDetailPagerContext: null,
    projectDetailPagerWheelLocked: false,
    closingProjectDetailModal: false,
  };

  // Small tuning values and jQuery namespaces used by multiple components.
  LegacyProject.constants = Object.assign(LegacyProject.constants || {}, {
    projectCountModalResizeNamespace: '.ghiLegacyProjectCountResize',
    projectDetailPagerGestureThreshold: 40,
    projectDetailPagerPreloadRadius: 1,
  });

  /**
   * Returns the same-origin iframe document used for measuring and cloning.
   */
  LegacyProject.getIframeDocument = (iframe) => iframe.contentDocument || (
    iframe.contentWindow && iframe.contentWindow.document
  );

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
    attach(context) {
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

        // This library can be attached by content loaded into an already-open
        // project list dialog, after that dialog's `dialog:aftercreate` event
        // has fired. Attach once here as well so initial resize tracking is not
        // dependent on script load order.
        LegacyProject.attachProjectCountModalResize?.();
        LegacyProject.attachOpenProjectDetailPagers?.();
      });

      LegacyProject.attachIframePrintHeight?.(context);
      LegacyProject.attachStandaloneIframeAutosize?.(context);
    },
  };

})(Drupal, once, jQuery);
