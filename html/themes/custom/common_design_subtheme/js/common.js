(function ($) {
  Drupal.behaviors.CommonDesignSubtheme = {
    attach: function (context, settings) {
      if ($(context).hasClass('glb-canvas-form')) {
        $(window).trigger('scroll');
      }

      if (typeof sorttable != 'undefined') {
        sorttable.init();
        $('table.sortable', context).once('sortable-table').each(function () {
          column = $(this).find('th:not(.sorttable_nosort)')[0];
          sorttable.innerSortFunction.apply(column, []);
        });
      }
    }
  };
}(jQuery));