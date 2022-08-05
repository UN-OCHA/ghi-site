(function ($, Drupal) {

  $.expr[":"].contains_case_insensitive = $.expr.createPseudo(function(arg) {
    return function( elem ) {
        return $(elem).text().toUpperCase().indexOf(arg.toUpperCase()) >= 0;
    };
});

  Drupal.TableSearch = {};

  Drupal.TableSearch.init = function ($table) {

    // First make sure we have an id for the table.
    $table.uniqueId();

    if ($table.parent().find('input.table-search-input').length > 0) {
      return;
    }

    // Create search field.
    let $search_field = Drupal.TableSearch.createSearchField($table);
    $search_field.on('input propertychange', function () {
      let search_string = $(this).val();

      if (search_string.length == 0) {
        $table.find('tbody tr').show();
        $table.trigger('tableReset');
        return;
      }
      $table.find('tbody tr').hide();
      $table.find('tbody tr td:contains_case_insensitive("' + search_string + '")').map(function(){
        return $(this).closest('tbody tr').show();
      });
      $table.trigger('tableFiltered');
    });
    $input_wrapper = $('<div class="table-search-input-wrapper"></div>');
    $input_wrapper.append($search_field);
    $table.parent().prepend($input_wrapper);
  }

  Drupal.TableSearch.createSearchField = function ($table) {
    let $input = $('<input class="table-search-input" type="search" placeholder="' + Drupal.t('Filter by keyword') + '" aria-label="' + Drupal.t('Filter the table content by keyword') + '" aria-controls="' + $table.attr('id') + '" />');
    return $input;
  }

  Drupal.behaviors.TableSearch = {
    attach: function (context, settings) {
      $tables = $('table.searchable', context);
      $.each($tables, function(i, table) {
        // Initialise search for this table.
        Drupal.TableSearch.init($(table));
      });
    }
  };
}(jQuery, Drupal));