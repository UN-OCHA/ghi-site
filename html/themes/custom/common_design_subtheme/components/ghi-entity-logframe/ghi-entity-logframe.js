(function ($, Drupal) {

  Drupal.behaviors.EntityLogframe = {
    attach: function (context, settings) {
      $items = $('.item-list--entity-logframe .item-wrapper');
      $items.each(function (i, item) {
        $table_wrapper = $(item).find('.attachment-tables-wrapper');
        if (!$table_wrapper.html() || $table_wrapper.html().includes('data-big-pipe-placeholder-id')) {
          return;
        }
        if ($table_wrapper.html() !== '') {
          $table_wrapper.parents('.item-wrapper').find('.table-toggle').css('visibility', 'visible');
          $(once('logframe-toggle', item)).find('.table-toggle').click(function() {
            $(item).find('.attachment-tables-wrapper').slideToggle({
              duration: 300,
            });
          });
        }
      });
    }
  };

}(jQuery, Drupal));