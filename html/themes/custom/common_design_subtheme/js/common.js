(function ($) {
  Drupal.behaviors.CommonDesignSubtheme = {
    attach: function (context, settings) {
      if ($(context).hasClass('glb-canvas-form')) {
        $(window).trigger('scroll');
      }

      if (typeof sorttable != 'undefined') {
        if (context == document) {
          sorttable.init();
        }
        $('table.sortable', context).once('sortable-table').each(function() {
          if (context != document) {
            sorttable.makeSortable(this);
          }
          column = $('th:not(.sorttable_nosort):first-child', this).get(0);
          if (column) {
            sorttable.innerSortFunction.apply(column, []);
          }
        });
      }
    }
  };
}(jQuery));