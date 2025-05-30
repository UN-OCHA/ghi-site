(($, Drupal, sorttable, once) => {

  'use strict';

  Drupal.CommonDesignSubtheme = {};

  Drupal.CommonDesignSubtheme.SoftLimit = {};
  Drupal.CommonDesignSubtheme.SoftLimit.applyLimit = function ($table) {
    if ($table.hasClass('expanded') || $table.hasClass('filtered')) {
      return;
    }
    let softLimit = $table.data('soft-limit');
    let $rows = $table.find('> tbody > tr');
    if ($rows.length <= softLimit) {
      return;
    }

    // First show all rows.
    $rows.show();

    // Hide all rows beyond the first ones defined by the soft limit.
    $rows.slice(softLimit).each(function () {
      $(this).hide();
    });
  };

  Drupal.CommonDesignSubtheme.SoftLimit.addExpandButton = function ($table) {
    let tableLength = $table.find('> tbody > tr').length;
    let softLimit = $table.data('soft-limit');
    let softLimitShowDisabled = $table.data('soft-limit-show-disabled');
    let needsButton = tableLength > softLimit;
    let $button = $table.parent().find('.expand-table');

    if (!needsButton) {
      // We don't need a button, so let's get rid of it.
      $table.parent().find('a.expand-table').remove();
      return;
    }

    if ($button.length > 0 && tableLength > softLimit && $button.hasClass('btn--disabled')) {
      $button.removeClass('btn--disabled');
    }

    if (!$button.length) {
      // Add a button to expand the rest of the rows.
      $button = $('<a href="#">')
      .addClass('expand-table')
      .addClass('cd-button')
      .text(Drupal.t('Show all rows'));

      if (softLimitShowDisabled) {
        $button.addClass('btn--disabled');
      }

      if ($table.hasClass('expanded')) {
        $button.hide();
      }

      $table.after($button);
    }

    $button.on('click', function (e) {
      $table.find('tr:hidden').slideDown();
      $(this).hide();
      $table.addClass('expanded');

      // See if this table is part of a block, in which case we want to trigger
      // an event that the frontend settings for the block have been changed.
      if ($table.parents('.ghi-block').length > 0) {
        $(document).trigger('ghi-block-setting', {
          element: $table,
          settings: {
            'soft_limit': 'expanded'
          }
        });
      }
      e.preventDefault();
    });

  };

  // Add overflow logic to entity navigation menus.
  Drupal.CommonDesignSubtheme.EntityNavigation = {};
  Drupal.CommonDesignSubtheme.EntityNavigation.apply = function ($container) {
    var $primary = $container.find('> ul.links--entity-navigation');
    var $primaryItems = $container.find('> ul.links--entity-navigation > li:not(.overflow-item)');
    let $secondary = $container.find('.overflow-navigation');
    let $secondaryItems = $secondary.find('> li');
    let $allItems = $container.find('li');
    let $overflowItem = $primary.find('.overflow-item');
    $overflowItem.removeClass('active');
    let $toggle = $overflowItem.find('> button');
    $allItems.each((i, item) => {
      $(item).removeClass('hidden');
    });

    let hiddenPrimaryItems = [];
    let stopWidth = 0;
    const primaryWidth = $primary.get(0).offsetWidth;
    $($primaryItems.get().reverse()).each((i, item) => {
      stopWidth = $(item).position().left + item.offsetWidth + $toggle.get(0).offsetWidth;
      if (primaryWidth < stopWidth) {
        $(item).addClass('hidden');
        hiddenPrimaryItems.push(i);
      }
    });
    if (!hiddenPrimaryItems.length) {
      $overflowItem.addClass('hidden');
    }
    else {
      $($secondaryItems.get().reverse()).each((i, item) => {
        if (!hiddenPrimaryItems.includes(i)) {
          $(item).addClass('hidden');
        }
        else if ($(item).hasClass('active')) {
          $overflowItem.addClass('active');
        }
      });
    }
  };

  Drupal.behaviors.CommonDesignSubtheme = {
    attach: function (context, settings) {
      if ($(context).hasClass('glb-canvas-form')) {
        $(window).trigger('scroll');
      }

      // For ghi images that can't be found, hide them completely so that the
      // captions or credits don't display all on their own.
      $('.ghi-image-wrapper img').on('error', function () {
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
          dropdownAutoWidth: true
        });
      });

      if (typeof sorttable != 'undefined') {
        if (context == document) {
          sorttable.init();
          once('sortable-table', 'table.sortable');
        }
        else {
          once('sortable-table', 'table.sortable', context).forEach(element => {
            if (context != document && !$(element).data('once').includes('sortable-table')) {
              sorttable.makeSortable(element);
            }
          });
        }
        once('sortable-table', 'table.sortable.autosort', context).forEach(element => {
          if (context != document) {
            sorttable.makeSortable(element);
          }
          let column = $('th:not(.sorttable-nosort):first-child', element).get(0);
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
          let blockTableSort = Drupal.GhiBlockSettings.getBlockSettingForElement(this, 'sort');
          if (blockTableSort && !$(this).hasClass('sorted')) {
            let blockId = $(this).parents('.ghi-block').attr('id');
            let columnSelector = '#' + blockId + ' table.sortable th:nth-child(' + (blockTableSort.column + 1) + ')';
            let column = $(columnSelector).get(0);
            sorttable.innerSortFunction.apply(column, []);
            if (blockTableSort.dir == 'desc') {
              sorttable.innerSortFunction.apply(column, []);
            }
          }

          // Then make sure that we capture and store sorting activity.
          once('sortable-events', $(this).find('> thead th:not(.sorttable-nosort)')).forEach(element => {
            element.addEventListener('click', e => {
              // See if this table is part of a block, in which case we want to trigger
              // an event that the frontend settings for the block have been changed.
              if ($(this).parents('.ghi-block').length == 0) {
                return;
              }
              $(document).trigger('ghi-block-setting', {
                element: this,
                settings: {
                  sort: {
                    column: $(element).index(),
                    dir: $(element).hasClass('sorttable-sorted-reverse') ? 'desc' : 'asc'
                  }
                }
              });
            });
          });
        });

      }

      once('overflow-navigation', $('.block-section-navigation, .block-document-navigation', context)).forEach(element => {
        Drupal.CommonDesignSubtheme.EntityNavigation.apply($(element));
        window.addEventListener('resize', function () {
          Drupal.CommonDesignSubtheme.EntityNavigation.apply($(element));
        });
      });

      once('soft-limit-table-big-pipe', $('table.soft-limit')).forEach(element => {
        let $table = $(element);
        // Check if we have settings for this block element in the URL.
        let blockSoftLimit = Drupal.GhiBlockSettings.getBlockSettingForElement(element, 'soft_limit');
        if (blockSoftLimit != 'expanded') {
          Drupal.CommonDesignSubtheme.SoftLimit.addExpandButton($table);
        }
      });
      once('soft-limit-table', $('.block-content:not(:has(> span[data-big-pipe-placeholder-id])) table.soft-limit', context)).forEach(element => {
        let $table = $(element);
        // Check if we have settings for this block element in the URL.
        let blockSoftLimit = Drupal.GhiBlockSettings.getBlockSettingForElement(element, 'soft_limit');
        if (blockSoftLimit != 'expanded') {
          Drupal.CommonDesignSubtheme.SoftLimit.addExpandButton($table);
          Drupal.CommonDesignSubtheme.SoftLimit.applyLimit($table);

          // Update the list when sorting is used.
          if ($table.hasClass('sortable')) {
            $table.find('> thead th').on('click', function () {
              if ($table.hasClass('filtered')) {
                return;
              }
              Drupal.CommonDesignSubtheme.SoftLimit.applyLimit($table);
            });
          }

          // Update the list when search is used.
          $table.on('tableReset', function () {
            Drupal.CommonDesignSubtheme.SoftLimit.applyLimit($table);
          });
        }
      });

      // Make sure that state changes trigger a dialog resize event so that
      // dialog.position.js can do it's magic of repositioning the modal.
      $(document).on('state:visible', (e) => {
        $(window).trigger('resize.dialogResize');
      });
    }

  };

})(jQuery, Drupal, sorttable, once);
