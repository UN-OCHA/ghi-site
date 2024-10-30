(($) => {

  'use strict';

  Drupal.TabContainer = {};

  Drupal.TabContainer.updateContent = function(container, index) {
    // Show the new content box.
    $(container).find('.tab-details').hide();
    $(container).find('.tab-details[data-tab-index="' + index + '"]').show();
    // Mark the current navigation item as active.
    $(container).find('.tab-navigation').removeClass('active');
    $(container).find('.tab-navigation[data-tab-index="' + index + '"]').addClass('active');
  }

  Drupal.behaviors.TabContainer = {
    attach: function (context, settings) {
      let $tabContainer = $('.tab-container-wrapper', context);
      $.each($tabContainer, function(i, container) {
        Drupal.TabContainer.updateContent(container, 0);

        // Make the navigation work.
        $(container).find('.tab-navigation').on('click keydown', function (e) {
          if (e.type == 'keydown' && e.keyCode != 13) {
            return;
          }
          let target = $(this).data('tab-index');
          $(document).trigger('ghi-block-setting', {
            element: container,
            settings: {
              target: target
            }
          });
          Drupal.TabContainer.updateContent(container, target);
        });

        // See if stored settings are available.
        let target = Drupal.GhiBlockSettings.getBlockSettingForElement(container, 'target');
        if (target !== null) {
          Drupal.TabContainer.updateContent(container, target);
        }
      });
    }
  };
})(jQuery);
