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
      $tab_container = $('.tab-container-wrapper', context);
      $.each($tab_container, function(i, container) {
        Drupal.TabContainer.updateContent(container, 0);

        // Make the navigation work.
        $(container).find('.tab-navigation').on('click', function () {
          target = $(this).data('tab-index');
          Drupal.GhiBlockSettings.setBlockSettingForElement(container, 'target', target);
          Drupal.TabContainer.updateContent(container, target);
        });

        // See if stored settings are available.
        if (target = Drupal.GhiBlockSettings.getBlockSettingForElement(container, 'target')) {
          Drupal.TabContainer.updateContent(container, target);
        }
      });
    }
  };
}(jQuery, Swiper));