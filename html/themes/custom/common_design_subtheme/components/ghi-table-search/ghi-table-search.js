(function ($, Drupal) {

  $.expr[":"].contains_case_insensitive = $.expr.createPseudo(function(arg) {
    return function( elem ) {
        return $(elem).text().toUpperCase().indexOf(arg.toUpperCase()) >= 0;
    };
});

  Drupal.TableSearch = {};

  Drupal.TableSearch.init = function ($table, default_string) {

    // First make sure we have an id for the table.
    $table.uniqueId();

    if ($table.parent().find('input.table-search-input').length > 0) {
      return;
    }

    // Create search field.
    let $search_field = Drupal.TableSearch.createSearchField($table, default_string);
    $search_field.on('input propertychange', function () {
      Drupal.TableSearch.applySearch($table, this);
    });
    $input_wrapper = $('<div class="table-search-input-wrapper empty"></div>');
    $input_wrapper.append($search_field);
    $table.parent().prepend($input_wrapper);
    if (default_string) {
      $search_field.trigger('input');
    }

    // Update the list when sorting is used.
    if ($table.hasClass('sortable')) {
      $table.find('> thead th').on('click', function () {
        Drupal.TableSearch.applySearch($table, $table.parent().find('input.table-search-input'));
      });
    }
  }

  Drupal.TableSearch.createSearchField = function ($table, search_string) {
    let $input = $('<input class="table-search-input" type="search" placeholder="' + Drupal.t('Filter by keyword') + '" aria-label="' + Drupal.t('Filter the table content by keyword') + '" aria-controls="' + $table.attr('id') + '" />');
    if (search_string) {
      $input.val(search_string);
    }
    return $input;
  }

  Drupal.TableSearch.applySearch = function ($table, input) {
    let search_string = $(input).val();

    // See if this table is part of a block, in which case we want to trigger
    // an event that the frontend settings for the block have been changed.
    $(document).trigger('ghi-block-setting', {
      element: input,
      settings: {
        search: search_string,
      }
    });

    if (search_string.length == 0) {
      $table.find('tbody tr').show();
      $table.trigger('tableReset');
      $table.parent().find('.table-search-input-wrapper').toggleClass('empty', true);
      return;
    }
    $table.find('tbody tr').hide();
    $table.find('tbody tr td:contains_case_insensitive("' + search_string + '")').map(function() {
      return $(this).closest('tbody tr').show();
    });
    $table.parent().find('.table-search-input-wrapper').toggleClass('empty', false);
    $table.trigger('tableFiltered');
  }

  Drupal.behaviors.TableSearch = {
    attach: function (context, settings) {
      $tables = $('table.searchable', context);
      $.each($tables, function(i, table) {
        let block = $(table).parents('.ghi-block')[0] || null;
        let block_id = block ? $(block).attr('id') : null;
        let block_table_search = Drupal.GhiBlockSettings.getBlockSetting(block_id, 'search');
        // Initialise search for this table.
        Drupal.TableSearch.init($(table), block_table_search);
      });
    }
  };
}(jQuery, Drupal));