(function ($, Drupal) {

  Drupal.behaviors.EntityLogframe = {
    attach: function (context, settings) {
      $items = $('.item-list--entity-logframe .item-wrapper', context);
      $items.each(function (i, item) {
        $(item).find('.attachment-tables-wrapper').hide();
        $(once('logframe-toggle', item)).find('.table-toggle').click(function() {
          $(item).find('.attachment-tables-wrapper').slideToggle({
            duration: 300,
          });
        });
      });
    }
  };

}(jQuery, Drupal));