(function ($, Drupal) {

  Drupal.CommonDesignSubtheme = {};

  Drupal.CommonDesignSubtheme.SoftLimit = {};
  Drupal.CommonDesignSubtheme.SoftLimit.applyLimit = function($table) {
    if ($table.hasClass('expanded')) {
      return;
    }
    let soft_limit = $table.data('soft-limit');
    let $rows = $table.find('> tbody > tr');
    if ($rows.length <= soft_limit) {
      return;
    }
    $rows.each(function () {
      $(this).show();
    });
    if ($table.parent().find('a.expand-table:visible').length) {
      // Hide all rows beyond the first ones defined by the soft limit.
      $rows.slice(soft_limit).each(function () {
        $(this).hide();
      });
    }
  }

  Drupal.CommonDesignSubtheme.SoftLimit.addExpandButton = function($table) {
    if ($table.parent().find('.expand-table').length > 0) {
      return;
    }
    // Add a button to expand the rest of the rows.
    $button = $('<a href="#">')
      .addClass('expand-table')
      .addClass('cd-button')
      .text(Drupal.t('Show all rows'));
    $button.on('click', function (e) {
      $table.find('tr:hidden').slideDown();
      $(this).hide();
      $table.toggleClass('expanded');
      e.preventDefault();
    });
    $table.after($button);
  }

  Drupal.behaviors.CommonDesignSubtheme = {
    attach: function (context, settings) {
      if ($(context).hasClass('glb-canvas-form')) {
        $(window).trigger('scroll');
      }

      $('select').filter(function () {
        if ($(this).parents('[data-block-preview]').length) {
          return true;
        }
        return !$(this).parents('.glb-canvas-form').length;
      }).each(function () {
        $(this).select2({
          width: 'resolve',
          minimumResultsForSearch: 5,
          dropdownAutoWidth: true,
        });
      });

      if (typeof sorttable != 'undefined') {
        if (context == document) {
          sorttable.init();
        }
        else {
          $('table.sortable', context).once('sortable-once').each(function() {
            if (context != document) {
              sorttable.makeSortable(this);
            }
          });
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
        Drupal.CommonDesignSubtheme.SoftLimit.addExpandButton($table);
        Drupal.CommonDesignSubtheme.SoftLimit.applyLimit($table);

        // Update the list when sorting is used.
        if ($table.hasClass('sortable')) {
          $table.find('> thead th').on('click', function () {
            Drupal.CommonDesignSubtheme.SoftLimit.applyLimit($table);
          });
        }

        // Update the list when search is used.
        $table.on('tableReset', function () {
          if ($table.parent().find('a.expand-table').length) {
            $table.parent().find('a.expand-table').show();
          }
          Drupal.CommonDesignSubtheme.SoftLimit.applyLimit($table);
        });
        $table.on('tableFiltered', function () {
          if ($table.parent().find('a.expand-table:visible').length) {
            $table.parent().find('a.expand-table:visible').hide();
          }
        });
      });
    }

  };

}(jQuery, Drupal));