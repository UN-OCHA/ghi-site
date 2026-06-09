(function (Drupal, once) {

  const LegacyProject = Drupal.GhiPlansLegacyProject;

  /**
   * Measures the legacy project content, including late-expanded child content.
   */
  const getIframeContentHeight = (iframe) => {
    const doc = LegacyProject.getIframeDocument(iframe);
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
  LegacyProject.cacheIframeContentHeight = (iframe) => {
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
    const height = LegacyProject.cacheIframeContentHeight(iframe);
    if (!height) {
      iframe.style.height = previousHeight;
      return;
    }
    iframe.style.height = `${height}px`;
  };

  /**
   * Warms project iframe measurements and the cloned print source.
   */
  LegacyProject.attachIframePrintHeight = (context) => {
    once(
      'ghi-legacy-project-print-height',
      'iframe.legacy-project-iframe',
      context,
    ).forEach((iframe) => {
      const cacheHeight = () => {
        try {
          LegacyProject.cacheIframeContentHeight(iframe);
          // Warm and refresh the source as the iframe content settles, so
          // invoking print does not race iframe inspection or font loading.
          LegacyProject.buildProjectModalPrintSource?.(iframe, true);
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
          const doc = LegacyProject.getIframeDocument(iframe);
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
  };

  /**
   * Keeps standalone fallback iframes expanded to their legacy content height.
   */
  LegacyProject.attachStandaloneIframeAutosize = (context) => {
    once(
      'ghi-legacy-project-autosize',
      'iframe.legacy-project-iframe[data-legacy-project-autosize="true"]',
      context,
    ).forEach((iframe) => {
      let resizeObserver = null;

      // Re-measures the standalone fallback iframe without assuming the initial
      // legacy page height is final.
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
          const doc = LegacyProject.getIframeDocument(iframe);
          const content = doc.querySelector('.create-project');
          resizeObserver = new ResizeObserver(resize);
          resizeObserver.observe(doc.body);
          resizeObserver.observe(doc.documentElement);
          if (content) {
            resizeObserver.observe(content);
          }
        }

        const doc = LegacyProject.getIframeDocument(iframe);
        if (doc.fonts && doc.fonts.ready) {
          doc.fonts.ready.then(resize);
        }
      });

      window.addEventListener('resize', resize);
    });
  };

})(Drupal, once);
