(($, Drupal) => {

  'use strict';

  $.expr[':'].containsCaseInsensitive = $.expr.createPseudo(function (arg) {
    return function (elem) {
      return $(elem).text().toUpperCase().indexOf(arg.toUpperCase()) >= 0;
    };
  });

  Drupal.TableSearch = {};

  Drupal.TableSearch.init = function ($table, defaultString) {

    // First make sure we have an id for the table.
    $table.uniqueId();

    if ($table.parent().find('input.table-search-input').length > 0) {
      return;
    }

    // Create search field.
    let $searchField = Drupal.TableSearch.createSearchField($table, defaultString);
    $searchField.on('input propertychange', function () {
      Drupal.TableSearch.applySearch($table);
    });
    let $inputWrapper = $('<div class="table-search-input-wrapper empty"></div>');
    $inputWrapper.append($searchField);
    $table.parent().prepend($inputWrapper);
    if (defaultString) {
      $searchField.trigger('input');
    }
  };

  Drupal.TableSearch.createSearchField = function ($table, searchString) {
    let $input = $('<input class="table-search-input" type="search" placeholder="' + Drupal.t('Filter by keyword') + '" aria-label="' + Drupal.t('Filter the table content by keyword') + '" aria-controls="' + $table.attr('id') + '" />');
    if (searchString) {
      $input.val(searchString);
    }
    return $input;
  };

  Drupal.TableSearch.applySearch = function ($table, searchString) {
    let $input = $table.parent().find('input.table-search-input');
    if (typeof searchString == 'undefined') {
      searchString = $input.val();
    }

    $(document).trigger('ghi-block-setting', {
      element: $table,
      settings: {
        search: searchString
      }
    });

    if (searchString.length == 0) {
      $table.find('tbody tr').show();
      $table.parent().find('.table-search-input-wrapper').toggleClass('empty', true);
      $table.toggleClass('filtered', false);
      $table.trigger('tableReset');
      return;
    }
    $table.find('tbody tr').hide();
    $table.find('tbody tr td:containsCaseInsensitive("' + searchString + '")').map(function () {
      return $(this).closest('tbody tr').show();
    });
    $table.parent().find('.table-search-input-wrapper').toggleClass('empty', false);
    $table.toggleClass('filtered', true);
    $table.trigger('tableFiltered');
  };

  Drupal.behaviors.TableSearch = {
    attach: function (context, settings) {
      let $tables = $('table.searchable', context);
      $.each($tables, function (i, table) {
        let block = $(table).parents('.ghi-block')[0] || null;
        let blockId = block ? $(block).attr('id') : null;
        let blockTableSearch = Drupal.GhiBlockSettings.getBlockSetting(blockId, 'search');
        // Initialise search for this table.
        Drupal.TableSearch.init($(table), blockTableSearch);
      });
    }
  };
})(jQuery, Drupal);
