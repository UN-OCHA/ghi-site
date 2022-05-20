(function ($, Swiper) {

  Drupal.TabContainer = {};

  Drupal.TabContainer.updateContent = function(container, index) {
    // Show the new content box.
    $(container).find('.tab-details').hide();
    $(container).find('.tab-details[data-tab-index="' + index + '"').show();
    // Mark the current navigation item as active.
    $(container).find('.tab-navigation').removeClass('active');
    $(container).find('.tab-navigation[data-tab-index="' + index + '"').addClass('active');
  }

  Drupal.behaviors.TabContainer = {
    attach: function (context, settings) {
      $carousel = $('.tab-container-wrapper', context);
      $.each($carousel, function(i, container) {
        Drupal.TabContainer.updateContent(container, 0);

        // Make the navigation work.
        $(container).find('.tab-navigation').on('click', function () {
          target = $(this).data('tab-index');
          Drupal.TabContainer.updateContent(container, target);
        });
      });
    }
  };
}(jQuery, Swiper));