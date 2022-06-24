(function ($) {
  Drupal.behaviors.CommonDesignSubtheme = {
    attach: function (context, settings) {
      if ($(context).hasClass('glb-canvas-form')) {
        $(window).trigger('scroll');
      }

      $('select').select2({
        width: 'resolve',
        minimumResultsForSearch: 5,
        dropdownAutoWidth: true,
      });

      if (typeof sorttable != 'undefined') {
        if (context == document) {
          sorttable.init();
        }
        $('table.sortable.autosort', context).once('sortable-table').each(function() {
          if (context != document) {
            sorttable.makeSortable(this);
          }
          column = $('th:not(.sorttable_nosort):first-child', this).get(0);
          if (column) {
            sorttable.innerSortFunction.apply(column, []);
          }
        });
      }

      $('table.soft-limit', context).once('soft-limit-table').each(function() {
        let $table = $(this);
        let soft_limit = $table.data('soft-limit');
        let $rows = $table.find('> tbody > tr');
        if ($rows.length > soft_limit) {
          // Hide all rows beyond the first ones defined by the soft limit.
          $rows.slice(soft_limit).each(function () {
            $(this).hide();
          });
          // Add a button to expand the rest of the rows.
          $button = $('<a href="#">')
            .addClass('expand-table')
            .addClass('cd-button')
            .text(Drupal.t('Show all'));
          $button.on('click', function (e) {
            $table.find('tr:hidden').slideDown();
            $(this).hide();
            e.preventDefault();
          });
          $table.after($button);
        }
      });


    }




  };

  // $(document).ajaxSend(function (event, jqxhr, settings) {
  //   console.log(settings);
  //   console.log('start');
  // });

  // $(document).ajaxStop(function (event, s, settings) {
  //   console.log(event, settings);
  //   console.log('stop');
  // });

}(jQuery));