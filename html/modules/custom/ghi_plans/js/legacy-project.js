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
   * The clone stays available while the modal is open and is refreshed as the
   * iframe settles. This is intentional: print preview can reflow repeatedly,
   * and rebuilding/restoring too aggressively can cause Chrome to paginate a
   * half-restored host page. The cloned legacy styles are marked media="print"
   * so broad legacy selectors, for example Bootstrap's `.dropdown`, do not leak
   * into the host page and alter normal screen UI while the clone is hidden.
   *
   * Legacy project detail dialogs are opened on top of the project list dialog.
   * Drupal core's dialog resize handler is global and is removed when any
   * dialog closes. Core also avoids recentering modal dialogs on resize. This
   * behavior therefore mirrors the relevant viewport tracking for the project
   * list modal under its own namespace and adds the missing modal recentering
   * while that dialog remains open.
   */

  // Delays print cleanup after browser print-preview signals. Chromium can
  // emit focus/afterprint-style events before the preview is really closed.
  let projectModalPrintRestoreTimer = null;

  // Cached print media query used to detect whether print preview is still
  // active when a delayed cleanup callback runs.
  let projectModalPrintMedia = null;

  // Set while browser print preview is expected to use a cloned project source.
  // This keeps the temporary source alive for standalone fallback pages, where
  // there is no modal wrapper to otherwise mark the source as intentional.
  let legacyProjectPrintActive = false;

  // Tracks the dialog type between `beforeclose` and `afterclose`. The latter
  // is where we can safely reattach resize tracking to the parent list modal.
  let closingProjectDetailModal = false;

  // Separate namespace for project-list resize handlers. Drupal core removes
  // its global dialog resize namespace when any dialog closes, so this survives
  // the nested legacy-project modal closing.
  const projectCountModalResizeNamespace = '.ghiLegacyProjectCountResize';

  /*
   * Shared iframe and viewport helpers.
   */

  /**
   * Returns the same-origin iframe document used for measuring and cloning.
   */
  const getIframeDocument = (iframe) => iframe.contentDocument || (
    iframe.contentWindow && iframe.contentWindow.document
  );

  /**
   * Provides Drupal displace offsets even if the displace library is absent.
   */
  const getDisplaceOffsets = () => Drupal.displace?.offsets || {
    top: 0,
    right: 0,
    bottom: 0,
    left: 0,
  };

  /*
   * Project-count modal resize handling.
   */

  /**
   * Builds the jQuery UI position object used by Drupal dialog positioning.
   *
   * Core skips this for modal dialogs, but the project-count modal needs it
   * after nested legacy project dialogs remove core's global resize handlers.
   */
  const getDialogCenterPosition = () => {
    const offsets = getDisplaceOffsets();
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
    const offsets = getDisplaceOffsets();
    return Math.round(
      0.95 * (window.innerHeight - offsets.top - offsets.bottom),
    );
  };

  /**
   * Checks dialog wrapper classes even while jQuery UI is being torn down.
   */
  const isDialogWithClass = (element, className) => {
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

  const isProjectDetailDialog = (element) => (
    isDialogWithClass(element, 'project-detail-modal')
  );

  const isProjectCountDialog = (element) => (
    isDialogWithClass(element, 'project-count-modal')
  );

  /**
   * Finds legacy project iframes that should be replaced by cloned print DOM.
   */
  const getLegacyProjectPrintIframes = () => Array.from(
    document.querySelectorAll(
      [
        '.project-detail-modal iframe.legacy-project-iframe',
        '.legacy-project-iframe-wrapper--standalone iframe.legacy-project-iframe',
      ].join(', '),
    ),
  );

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
  const detachProjectCountModalResize = () => {
    $(window).off(projectCountModalResizeNamespace);
    $(document).off(projectCountModalResizeNamespace);
  };

  /**
   * Reapplies viewport-dependent dialog options to the project list modal.
   */
  const refreshProjectCountModals = () => {
    const elements = getProjectCountModalContents();
    if (!elements.length) {
      detachProjectCountModalResize();
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
    refreshProjectCountModals,
    20,
  );

  /**
   * Mirrors Drupal core's dialog resize attachment for the project-count modal.
   */
  const attachProjectCountModalResize = () => {
    const elements = getProjectCountModalContents();
    if (!elements.length) {
      detachProjectCountModalResize();
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
      .off(projectCountModalResizeNamespace)
      .on(
        `resize${projectCountModalResizeNamespace} scroll${projectCountModalResizeNamespace}`,
        debouncedRefreshProjectCountModals,
      );
    $(document)
      .off(projectCountModalResizeNamespace)
      .on(
        `drupalViewportOffsetChange${projectCountModalResizeNamespace}`,
        debouncedRefreshProjectCountModals,
      );

    refreshProjectCountModals();
  };

  /*
   * Legacy iframe measurement and standalone fallback autosizing.
   */

  /**
   * Measures the legacy project content, including late-expanded child content.
   */
  const getIframeContentHeight = (iframe) => {
    const doc = getIframeDocument(iframe);
    const body = doc.body;
    const html = doc.documentElement;
    const content = doc.querySelector('.create-project');
    return Math.max(
      body.scrollHeight,
      body.offsetHeight,
      html.scrollHeight,
      html.offsetHeight,
      content ? content.scrollHeight : 0,
      content ? content.offsetHeight : 0,
      content ? Math.ceil(content.getBoundingClientRect().bottom) : 0,
    );
  };

  /**
   * Stores iframe content height for both standalone autosizing and print use.
   */
  const cacheIframeContentHeight = (iframe) => {
    const height = getIframeContentHeight(iframe);
    if (height) {
      // Cache the content height so printing can fall back to an expanded
      // iframe without re-measuring during the synchronous print lifecycle.
      iframe.dataset.legacyProjectContentHeight = String(height + 2);
    }
    return height ? height + 2 : 0;
  };

  /**
   * Expands the standalone fallback iframe to remove its internal scrollbar.
   */
  const resizeIframeToContent = (iframe) => {
    const previousHeight = iframe.style.height;
    iframe.style.height = '1px';
    const height = cacheIframeContentHeight(iframe);
    if (!height) {
      iframe.style.height = previousHeight;
      return;
    }
    iframe.style.height = `${height}px`;
  };

  /*
   * Browser print preparation for legacy project detail modals.
   */

  /**
   * Temporarily sets the host document title for browser "Save as PDF" names.
   */
  const prepareProjectModalPrintTitle = (iframes) => {
    if (
      !iframes.length ||
      typeof document.documentElement.dataset.legacyProjectPrintTitle !== 'undefined'
    ) {
      return;
    }

    const iframe = iframes[0];
    const dialog = iframe.closest('.project-detail-modal');
    const modalTitle = dialog
      ?.querySelector('.ui-dialog-title')
      ?.textContent.trim();
    if (!modalTitle) {
      return;
    }

    // Browsers use document.title as the default "Save as PDF" filename.
    // While printing the modal, replace the plan page title with the project
    // title, then restore it after print preview closes.
    document.documentElement.dataset.legacyProjectPrintTitle = document.title;
    document.title = modalTitle;
  };

  /**
   * Removes cloned print content and the body marker that activates it.
   */
  const removeProjectModalPrintSource = () => {
    document.querySelectorAll('.legacy-project-print-source').forEach((element) => {
      element.remove();
    });
    document.body.classList.remove('legacy-project-printing-source');
  };

  /**
   * Drops the warmed print source once the legacy project modal is gone.
   */
  const cleanupStaleProjectModalPrintSource = () => {
    if (legacyProjectPrintActive) {
      return;
    }

    // Keep the warmed source while the modal remains open, but remove it after
    // Drupal destroys the dialog so its print-only styles and cloned content do
    // not linger for the next unrelated print action.
    if (!document.querySelector('.project-detail-modal iframe.legacy-project-iframe')) {
      removeProjectModalPrintSource();
    }
  };

  /**
   * Clones the iframe content into the host page so print can target it alone.
   */
  const buildProjectModalPrintSource = (iframe, force = false) => {
    const iframeSource = iframe.getAttribute('src') || '';
    const existing = document.querySelector('.legacy-project-print-source');
    if (
      existing &&
      !force &&
      existing.dataset.legacyProjectIframeSrc === iframeSource
    ) {
      return;
    }
    removeProjectModalPrintSource();

    const doc = getIframeDocument(iframe);
    const printSource = document.createElement('div');
    printSource.className = 'legacy-project-print-source';
    printSource.dataset.legacyProjectIframeSrc = iframeSource;

    // Clone the iframe's own style dependencies next to the cloned content so
    // the print result resembles the source project page without depending on
    // iframe rendering support in print preview.
    doc.querySelectorAll('link[rel="stylesheet"], style').forEach((element) => {
      const clone = element.cloneNode(true);
      if (clone.tagName === 'LINK') {
        clone.setAttribute('href', element.href);
      }
      // Keep legacy styles from leaking into the host page while the prepared
      // print source is hidden. Some legacy selectors are intentionally broad.
      clone.setAttribute('media', 'print');
      printSource.appendChild(clone);
    });

    const content = doc.querySelector('.create-project') || doc.body;
    // Clone only the meaningful project content when possible. The iframe body
    // is the fallback for unexpected legacy markup.
    printSource.appendChild(content.cloneNode(true));
    document.body.appendChild(printSource);
    document.body.classList.add('legacy-project-printing-source');
  };

  /**
   * Prepares the best available print source before browser print starts.
   */
  const prepareProjectModalPrint = () => {
    const iframes = getLegacyProjectPrintIframes();
    const modalIframes = iframes.filter((iframe) => (
      iframe.closest('.project-detail-modal')
    ));
    legacyProjectPrintActive = iframes.length > 0;
    document.body.classList.toggle('legacy-project-printing-modal', modalIframes.length > 0);
    prepareProjectModalPrintTitle(modalIframes);

    iframes.forEach((iframe) => {
      try {
        buildProjectModalPrintSource(iframe);
      }
      catch (e) {
        // Fall back to printing the expanded iframe below if the iframe cannot
        // be inspected or cloned.
      }

      if (document.body.classList.contains('legacy-project-printing-source')) {
        return;
      }

      try {
        // Fallback path only: expand the visible iframe to its content height
        // and remove iframe scrolling so the browser can print more than the
        // modal viewport.
        if (typeof iframe.dataset.legacyProjectPrintHeight === 'undefined') {
          iframe.dataset.legacyProjectPrintHeight = iframe.style.height;
        }
        if (typeof iframe.dataset.legacyProjectPrintScrolling === 'undefined') {
          iframe.dataset.legacyProjectPrintScrolling = iframe.getAttribute('scrolling') || '';
        }
        const height = Number(iframe.dataset.legacyProjectContentHeight) ||
          cacheIframeContentHeight(iframe);
        if (height) {
          iframe.style.height = `${height}px`;
        }
        iframe.setAttribute('scrolling', 'no');
      }
      catch (e) {
        // Keep browser printing available if iframe inspection is blocked.
      }
    });
  };

  /**
   * Restores host-page state after browser print preview has really closed.
   */
  const restoreProjectModalPrint = () => {
    if (typeof document.documentElement.dataset.legacyProjectPrintTitle !== 'undefined') {
      document.title = document.documentElement.dataset.legacyProjectPrintTitle;
      delete document.documentElement.dataset.legacyProjectPrintTitle;
    }

    // The source-clone path does not modify iframe dimensions, but the fallback
    // path above does. Restore only attributes that were actually captured.
    document.querySelectorAll('iframe.legacy-project-iframe').forEach((iframe) => {
      if (typeof iframe.dataset.legacyProjectPrintHeight !== 'undefined') {
        iframe.style.height = iframe.dataset.legacyProjectPrintHeight;
        delete iframe.dataset.legacyProjectPrintHeight;
      }
      if (typeof iframe.dataset.legacyProjectPrintScrolling !== 'undefined') {
        if (iframe.dataset.legacyProjectPrintScrolling) {
          iframe.setAttribute('scrolling', iframe.dataset.legacyProjectPrintScrolling);
        }
        else {
          iframe.removeAttribute('scrolling');
        }
        delete iframe.dataset.legacyProjectPrintScrolling;
      }
    });
    document.body.classList.remove('legacy-project-printing-modal');
    legacyProjectPrintActive = false;
    cleanupStaleProjectModalPrintSource();
  };

  /**
   * Debounces print cleanup across inconsistent browser print-preview events.
   */
  const scheduleProjectModalPrintRestore = () => {
    clearTimeout(projectModalPrintRestoreTimer);
    projectModalPrintRestoreTimer = setTimeout(() => {
      // Chrome may fire focus/afterprint-like signals while print preview is
      // still open. Restoring at that point makes later preview setting changes
      // paginate a partially restored page.
      if (projectModalPrintMedia && projectModalPrintMedia.matches) {
        return;
      }
      restoreProjectModalPrint();
    }, 250);
  };

  Drupal.behaviors.ghiLegacyProject = {
    attach(context) {
      once('ghi-legacy-project-print', 'html', document).forEach(() => {
        if (window.MutationObserver) {
          const observer = new MutationObserver(cleanupStaleProjectModalPrintSource);
          observer.observe(document.body, { childList: true, subtree: true });
        }

        window.addEventListener('beforeprint', prepareProjectModalPrint);
        window.addEventListener('afterprint', scheduleProjectModalPrintRestore);
        window.addEventListener('focus', scheduleProjectModalPrintRestore);
        window.addEventListener('dialog:aftercreate', (event) => {
          if (isProjectCountDialog(event.target)) {
            attachProjectCountModalResize();
          }
        });
        window.addEventListener('dialog:beforeclose', (event) => {
          closingProjectDetailModal = isProjectDetailDialog(event.target);
        });
        window.addEventListener('dialog:afterclose', () => {
          if (closingProjectDetailModal) {
            closingProjectDetailModal = false;
            setTimeout(attachProjectCountModalResize, 0);
            return;
          }

          setTimeout(refreshProjectCountModals, 0);
        });
        // This library can be attached by content loaded into an already-open
        // project list dialog, after that dialog's `dialog:aftercreate` event
        // has fired. Attach once here as well so that initial resize tracking is
        // not dependent on script load order.
        attachProjectCountModalResize();
        document.addEventListener('visibilitychange', () => {
          if (!document.hidden) {
            scheduleProjectModalPrintRestore();
          }
        });

        if (window.matchMedia) {
          projectModalPrintMedia = window.matchMedia('print');

          // Handles browsers that switch print media independently of the
          // beforeprint/afterprint event pair.
          const handlePrintMediaChange = (event) => {
            if (event.matches) {
              // Some browsers enter print media before firing beforeprint.
              prepareProjectModalPrint();
            }
            else {
              scheduleProjectModalPrintRestore();
            }
          };
          if (projectModalPrintMedia.addEventListener) {
            projectModalPrintMedia.addEventListener('change', handlePrintMediaChange);
          }
          else if (projectModalPrintMedia.addListener) {
            projectModalPrintMedia.addListener(handlePrintMediaChange);
          }
        }
      });

      once('ghi-legacy-project-print-height', 'iframe.legacy-project-iframe', context).forEach((iframe) => {
        // Warms measurements and the cloned print source after iframe content
        // has loaded, shifted, or finished web font layout.
        const cacheHeight = () => {
          try {
            cacheIframeContentHeight(iframe);
            if (iframe.closest('.project-detail-modal')) {
              // Warm and refresh the source as the iframe content settles, so
              // invoking print does not race iframe inspection or font loading.
              buildProjectModalPrintSource(iframe, true);
            }
          }
          catch (e) {
            // Printing still falls back to the visible iframe if inspection fails.
          }
        };

        iframe.addEventListener('load', () => {
          cacheHeight();
          [50, 250, 1000, 2000].forEach((delay) => {
            setTimeout(cacheHeight, delay);
          });

          try {
            const doc = getIframeDocument(iframe);
            if (doc.fonts && doc.fonts.ready) {
              doc.fonts.ready.then(cacheHeight);
            }
          }
          catch (e) {
            // Printing still falls back to the visible iframe if inspection fails.
          }
        });

        cacheHeight();
      });

      once(
        'ghi-legacy-project-autosize',
        'iframe.legacy-project-iframe[data-legacy-project-autosize="true"]',
        context,
      ).forEach((iframe) => {
        let resizeObserver = null;

        // Re-measures the standalone fallback iframe without assuming the
        // initial legacy page height is final.
        const resize = () => {
          try {
            resizeIframeToContent(iframe);
          }
          catch (e) {
            // Keep the fallback iframe usable if the browser blocks inspection.
          }
        };

        iframe.addEventListener('load', () => {
          resize();
          // Legacy project pages can shift after deferred CSS, images, or fonts
          // finish loading. A few delayed measurements cover those late shifts
          // even when ResizeObserver misses them.
          [50, 250, 1000, 2000].forEach((delay) => {
            setTimeout(resize, delay);
          });

          if ('ResizeObserver' in window && !resizeObserver) {
            const doc = getIframeDocument(iframe);
            const content = doc.querySelector('.create-project');
            resizeObserver = new ResizeObserver(resize);
            resizeObserver.observe(doc.body);
            resizeObserver.observe(doc.documentElement);
            if (content) {
              resizeObserver.observe(content);
            }
          }

          const doc = getIframeDocument(iframe);
          if (doc.fonts && doc.fonts.ready) {
            doc.fonts.ready.then(resize);
          }
        });

        window.addEventListener('resize', resize);
      });
    },
  };

})(Drupal, once, jQuery);
