/* global once */

(function ($, Drupal, once) {
  'use strict';

  Drupal.behaviors.EntityLogframe = {
    attach: function (context, settings) {
      function updateItemState(item) {
        let state = item.getAttribute('data-logframe-state');
        let $toggle = $(item).find('.table-toggle');
        let $noData = $(item).find('.table-no-data');

        if (state === 'content') {
          $toggle.css('visibility', 'visible');
          $noData.css('display', 'none');
        }
        else if (state === 'empty') {
          $toggle.css('display', 'none');
          $noData.css('visibility', 'visible');
          $noData.css('display', 'block');
        }
      }

      function resolvePendingItems(items) {
        let maxConcurrent = 3;
        let active = 0;
        let index = 0;

        function resolveDone() {
          active--;
          resolveNext();
        }

        function resolveNext() {
          while (active < maxConcurrent && index < items.length) {
            let item = items[index];
            let ajaxUrl = item.getAttribute('data-logframe-item-url');
            index++;
            if (!ajaxUrl) {
              continue;
            }

            active++;
            item.setAttribute('data-logframe-state', 'resolving');
            let ajax = Drupal.ajax({
              url: ajaxUrl,
              element: item,
              progress: false
            });
            let request = ajax.execute();
            if (request && request.always) {
              request.always(resolveDone);
            }
            else {
              active--;
            }
          }
        }

        resolveNext();
      }

      let $items = $('.item-list--entity-logframe .item-wrapper');
      $items.each(function (i, item) {
        updateItemState(item);

        let $toggle = $(item).find('.table-toggle');
        let openMessage = $toggle.data('tippy-content-open');
        let closedMessage = $toggle.data('tippy-content');

        // React to clicks and key presses.
        $(once('logframe-toggle', item)).find('.table-toggle').on('keypress click', function (e) {
          if (e.which !== 13 && e.type !== 'click') {
            return;
          }

          let $currentWrapper = $(item).find('.attachment-tables-wrapper');
          // Toggle the "open" class on the toggle.
          $(this).toggleClass('open');
          // Update the tooltip.
          if ($(this)[0].hasOwnProperty('_tippy')) {
            $(this)[0]._tippy.setContent($(this).hasClass('open') ? openMessage : closedMessage);
          }
          // Toggle the table display.
          $currentWrapper.slideToggle({
            duration: 300
          });
        });
      });

      let pendingItems = once('logframe-resolve', '.item-list--entity-logframe .item-wrapper[data-logframe-state="pending"][data-logframe-item-url]', context);
      resolvePendingItems(pendingItems);
    }
  };

})(jQuery, Drupal, once);
