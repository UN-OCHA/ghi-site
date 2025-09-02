(function ($, Drupal) {

  // Attach behaviors.
  Drupal.behaviors.ghi_geojson = {
    attach: function(context, settings) {
      $('ul.geojson-directory-listing li.directory').click(function () {
        $(this).toggleClass('expanded');
      });
    }
  }

})(jQuery, Drupal);
