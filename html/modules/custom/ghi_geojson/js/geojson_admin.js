(function ($, Drupal) {

  // Attach behaviors.
  Drupal.behaviors.ghi_geojson = {
    attach: function(context, settings) {
      $('ul.geojson-directory-listing li.directory').click(function () {
        $(this).toggleClass('expanded');
      });
      let $button = $('.local-actions__item.dropdown > div.button');
      $button.click(function () {
        $button.toggleClass('open');
      });
      $(document).click(function (e) {
        let selector = '.local-actions__item.dropdown > div.button, .local-actions__item.dropdown > div.button > .item-list';
        if (!$button.hasClass('open') || e.target == $button.get(0)) {
          return;
        }
        if (!$(e.target).find(selector).length) {
          $button.toggleClass('open');
        }
      });
    }
  }

})(jQuery, Drupal);
