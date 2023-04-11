/**
 * @file
 * Popover functionality.
 */

/**
 * @file
 * Auto-init script ported from tippy.js v4
 *
 * @requires popper.js
 * @requires tippy.js
 */
 (function () {

  const hideOnEsc = {
    name: 'hideOnEsc',
    defaultValue: true,
    fn({hide}) {
      function onKeyDown(event) {
        if (event.keyCode === 27) {
          hide();
        }
      }

      return {
        onShow() {
          document.addEventListener('keydown', onKeyDown);
        },
        onHide() {
          document.removeEventListener('keydown', onKeyDown);
        },
      };
    },
  };

  document.addEventListener("DOMContentLoaded", function () {
    [].slice.call(document.querySelectorAll('.popover')).forEach(function (el) {
      if (el.nextElementSibling) {
        tippy(el, {
          content: el.nextElementSibling.innerHTML,
          trigger: 'click',
          allowHTML: true,
          maxWidth: 'calc(100vw - 20%)',
          interactive: true,
          appendTo: document.body,
          theme: 'light',
          hideOnEsc: true,
          role: 'popover',
        });
      }
    });
  });

})();
