(function ($, Drupal, once) {
  'use strict';

  Drupal.behaviors.EntityLogframe = {
    attach: function (context, settings) {
      let $items = $('.item-list--entity-logframe .item-wrapper');
      $items.each(function (i, item) {
        let $tableWrapper = $(item).find('.attachment-tables-wrapper');
        if (!$tableWrapper.html() || $tableWrapper.html().includes('data-big-pipe-placeholder-id')) {
          return;
        }
        let $toggle = $tableWrapper.parents('.item-wrapper').find('.table-toggle');
        let $noData = $tableWrapper.parents('.item-wrapper').find('.table-no-data');

        if ($tableWrapper.html().trim() !== '') {
          // We have data, so display the toggle and completely hide the
          // no-data icon.
          $toggle.css('visibility', 'visible');
          $noData.css('display', 'none');

          let openMessage = $toggle.data('tippy-content-open');
          let closedMessage = $toggle.data('tippy-content');

          // React to clicks and key presses.
          $(once('logframe-toggle', item)).find('.table-toggle').on('keypress click', function (e) {
            if (e.which !== 13 && e.type !== 'click') {
              return;
            }
            // Toggle the "open" class on the toggle.
            $(this).toggleClass('open');
            // Update the tooltip.
            if ($(this)[0].hasOwnProperty('_tippy')) {
              $(this)[0]._tippy.setContent($(this).hasClass('open') ? openMessage : closedMessage);
            }
            // Toggle the table display.
            $(item).find('.attachment-tables-wrapper').slideToggle({
              duration: 300
            });
          });
        }
        else {
          // We don't have data, so hide the toggle completely and display the
          // no-data icon.
          $toggle.css('display', 'none');
          $noData.css('visibility', 'visible');
          $noData.css('display', 'block');
        }
      });
    }
  };

})(jQuery, Drupal, once);