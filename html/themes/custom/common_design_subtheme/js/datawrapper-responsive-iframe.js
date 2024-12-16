((window) => {

  'use strict';

  /*
  * Datawrapper script to handle responsive iframe.
  *
  * @see https://developer.datawrapper.de/docs/responsive-iframe
  */
  window.addEventListener('message', function (event) {
    if (typeof event.data['datawrapper-height'] !== 'undefined') {
      var iframes = document.querySelectorAll('iframe');
      for (var chartId in event.data['datawrapper-height']) {
        for (var i = 0; i < iframes.length; i++) {
          if (iframes[i].contentWindow === event.source) {
            var iframe = iframes[i]
            iframe.style.height = event.data['datawrapper-height'][chartId] + 'px';
          }
        }
      }
    }
  });

})(window);
