(function (Drupal) {

  const LegacyProject = Drupal.GhiPlansLegacyProject;
  const { state } = LegacyProject;

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
  LegacyProject.removeProjectModalPrintSource = () => {
    document.querySelectorAll('.legacy-project-print-source').forEach((element) => {
      element.remove();
    });
    document.body.classList.remove('legacy-project-printing-source');
  };

  /**
   * Drops the warmed print source once the legacy project modal is gone.
   */
  const cleanupStaleProjectModalPrintSource = () => {
    if (state.legacyProjectPrintActive) {
      return;
    }

    // Keep the warmed source while the modal remains open, but remove it after
    // Drupal destroys the dialog so its print-only styles and cloned content do
    // not linger for the next unrelated print action.
    if (!document.querySelector('.project-detail-modal iframe.legacy-project-iframe')) {
      LegacyProject.removeProjectModalPrintSource();
    }
  };

  /**
   * Clones the iframe content into the host page so print can target it alone.
   */
  LegacyProject.buildProjectModalPrintSource = (iframe, force = false) => {
    const iframeSource = iframe.getAttribute('src') || '';
    const existing = document.querySelector('.legacy-project-print-source');
    if (
      existing &&
      !force &&
      existing.dataset.legacyProjectIframeSrc === iframeSource
    ) {
      return;
    }
    LegacyProject.removeProjectModalPrintSource();

    const doc = LegacyProject.getIframeDocument(iframe);
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
    state.legacyProjectPrintActive = iframes.length > 0;
    document.body.classList.toggle(
      'legacy-project-printing-modal',
      modalIframes.length > 0,
    );
    prepareProjectModalPrintTitle(modalIframes);

    iframes.forEach((iframe) => {
      try {
        LegacyProject.buildProjectModalPrintSource(iframe);
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
          iframe.dataset.legacyProjectPrintScrolling =
            iframe.getAttribute('scrolling') || '';
        }
        const height = Number(iframe.dataset.legacyProjectContentHeight) ||
          LegacyProject.cacheIframeContentHeight(iframe);
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
    state.legacyProjectPrintActive = false;
    cleanupStaleProjectModalPrintSource();
  };

  /**
   * Debounces print cleanup across inconsistent browser print-preview events.
   */
  const scheduleProjectModalPrintRestore = () => {
    clearTimeout(state.projectModalPrintRestoreTimer);
    state.projectModalPrintRestoreTimer = setTimeout(() => {
      // Chrome may fire focus/afterprint-like signals while print preview is
      // still open. Restoring at that point makes later preview setting changes
      // paginate a partially restored page.
      if (state.projectModalPrintMedia && state.projectModalPrintMedia.matches) {
        return;
      }
      restoreProjectModalPrint();
    }, 250);
  };

  /**
   * Registers print lifecycle listeners once for the legacy project library.
   */
  LegacyProject.attachPrintLifecycle = () => {
    if (window.MutationObserver) {
      const observer = new MutationObserver(cleanupStaleProjectModalPrintSource);
      observer.observe(document.body, { childList: true, subtree: true });
    }

    window.addEventListener('beforeprint', prepareProjectModalPrint);
    window.addEventListener('afterprint', scheduleProjectModalPrintRestore);
    window.addEventListener('focus', scheduleProjectModalPrintRestore);
    document.addEventListener('visibilitychange', () => {
      if (!document.hidden) {
        scheduleProjectModalPrintRestore();
      }
    });

    if (window.matchMedia) {
      state.projectModalPrintMedia = window.matchMedia('print');

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
      if (state.projectModalPrintMedia.addEventListener) {
        state.projectModalPrintMedia.addEventListener(
          'change',
          handlePrintMediaChange,
        );
      }
      else if (state.projectModalPrintMedia.addListener) {
        state.projectModalPrintMedia.addListener(handlePrintMediaChange);
      }
    }
  };

})(Drupal);
