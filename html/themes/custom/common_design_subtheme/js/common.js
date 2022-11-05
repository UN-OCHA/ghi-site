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

      // See if this table is part of a block, in which case we want to trigger
      // an event that the frontend settings for the block have been changed.
      if ($table.parents('.ghi-block').length > 0) {
        Drupal.GhiBlockSettings.setBlockSettingForElement($table, 'soft_limit', 'expanded');
      }

      e.preventDefault();
    });
    $table.after($button);
  }

  Drupal.behaviors.CommonDesignSubtheme = {
    attach: function (context, settings) {
      if ($(context).hasClass('glb-canvas-form')) {
        $(window).trigger('scroll');
      }

      // For ghi images that can't be found, hide them completely so that the
      // captions or credits don't display all on their own.
      $('.ghi-image-wrapper img').on('error', function() {
        $(this).parents('.ghi-image-wrapper').hide();
      });

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
          column = $('th:not(.sorttable-nosort):first-child', this).get(0);
          if (column) {
            sorttable.innerSortFunction.apply(column, []);
          }
        });

        // Record sorting activity and store it in the block settings. Apply
        // these settings on page load if the respective arguments are present
        // in query string. This assumes a single table per block.
        $('.ghi-block table.sortable').each(function () {
          if (context != document) {
            return;
          }
          // First apply settings according to what the url requests.
          let block_table_sort = Drupal.GhiBlockSettings.getBlockSettingForElement(this, 'sort');
          if (block_table_sort) {
            let block_id = $(this).parents('.ghi-block').attr('id');
            let column_selector = '#' + block_id + ' table.sortable th:nth-child(' + (block_table_sort.column + 1) + ')';
            let column = $(column_selector).get(0);
            sorttable.innerSortFunction.apply(column, []);
            if (block_table_sort.dir == 'desc') {
              sorttable.innerSortFunction.apply(column, []);
            }
          }

          // Then make sure that we capture and store sorting activity.
          $(this).find('> thead th:not(.sorttable-nosort)').once('sortable-events').on('click', function () {
            // See if this table is part of a block, in which case we want to trigger
            // an event that the frontend settings for the block have been changed.
            if ($(this).parents('.ghi-block').length == 0) {
              return;
            }
            Drupal.GhiBlockSettings.setBlockSettingForElement(this, 'sort', {
              column: $(this).index(),
              dir: $(this).hasClass('sorttable-sorted-reverse') ? 'desc' : 'asc',
            });
          });
        });

      }

      $('table.soft-limit', context).once('soft-limit-table').each(function() {
        let $table = $(this);
        // Check if we have settings for this block element in the URL.
        let block_soft_limit = Drupal.GhiBlockSettings.getBlockSettingForElement(this, 'soft_limit');
        if (block_soft_limit != 'expanded') {

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
        }
      });
    }

  };

}(jQuery, Drupal));