(function (Drupal, once) {

  Drupal.behaviors.ghiLegacyProject = {
    attach(context) {
      once('ghi-legacy-project-autosize', 'iframe.legacy-project-iframe[data-legacy-project-autosize="true"]', context).forEach((iframe) => {
        let resizeObserver = null;

        const resize = () => {
          try {
            const doc = iframe.contentDocument || iframe.contentWindow.document;
            const body = doc.body;
            const html = doc.documentElement;
            const content = doc.querySelector('.create-project');
            const previousHeight = iframe.style.height;
            iframe.style.height = '1px';
            const height = Math.max(
              body.scrollHeight,
              body.offsetHeight,
              html.scrollHeight,
              html.offsetHeight,
              content ? content.scrollHeight : 0,
              content ? content.offsetHeight : 0,
              content ? Math.ceil(content.getBoundingClientRect().bottom) : 0,
            );
            if (height) {
              iframe.style.height = `${height + 2}px`;
            }
            else {
              iframe.style.height = previousHeight;
            }
          }
          catch (e) {
            // Keep the fallback iframe usable if the browser blocks inspection.
          }
        };

        iframe.addEventListener('load', () => {
          resize();
          [50, 250, 1000, 2000].forEach((delay) => {
            setTimeout(resize, delay);
          });

          if ('ResizeObserver' in window && !resizeObserver) {
            const doc = iframe.contentDocument || iframe.contentWindow.document;
            const content = doc.querySelector('.create-project');
            resizeObserver = new ResizeObserver(resize);
            resizeObserver.observe(doc.body);
            resizeObserver.observe(doc.documentElement);
            if (content) {
              resizeObserver.observe(content);
            }
          }

          const doc = iframe.contentDocument || iframe.contentWindow.document;
          if (doc.fonts && doc.fonts.ready) {
            doc.fonts.ready.then(resize);
          }
        });

        window.addEventListener('resize', resize);
      });
    },
  };

})(Drupal, once);
