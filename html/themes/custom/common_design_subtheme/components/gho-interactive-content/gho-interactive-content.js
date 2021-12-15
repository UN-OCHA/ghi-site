(function () {
  'use strict';

  Drupal.behaviors.ghoInteractiveContent = {
    attach: function (context, settings) {
      // Measure width of vertical scrollbar.
      this.setScrollBarWidth();

      // Listen for window.resize and recalculate scrollbar width.
      window.addEventListener('resize', this.setScrollBarWidth);
    },

    /**
     * @see https://stackoverflow.com/a/986977
     */
    setScrollBarWidth: function () {
      // So we can store our final result.
      var scrollBarWidth;

      // Set up a <p> and <div> to create scrollable content.
      var inner = document.createElement('p');
      inner.style.width = '100%';
      inner.style.height = '200px';

      var outer = document.createElement('div');
      outer.style.position = 'absolute';
      outer.style.top = '0px';
      outer.style.left = '0px';
      outer.style.visibility = 'hidden';
      outer.style.width = '200px';
      outer.style.height = '150px';
      outer.style.overflow = 'hidden';
      outer.appendChild(inner);

      // Insert into DOM and calculate scrollbar.
      document.body.appendChild(outer);
      var w1 = inner.offsetWidth;
      outer.style.overflow = 'scroll';
      var w2 = inner.offsetWidth;
      if (w1 === w2) {
        w2 = outer.clientWidth;
      }

      // Store final result.
      scrollBarWidth = w1 - w2;

      // Clean up DOM.
      document.body.removeChild(outer);

      // Set on :root so that all components have access to this number.
      document.documentElement.style.setProperty('--scrollbar-width', scrollBarWidth + 'px');
    }
  };
})();
