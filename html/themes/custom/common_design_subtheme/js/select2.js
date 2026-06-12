/* global once */

((Drupal, $, once) => {

  'use strict';

  Drupal.behaviors.CommonDesignSubthemeSelect2 = {
    attach(context) {
      once('common-design-subtheme-select2', '.ajax-switcher-wrapper select', context).forEach((select) => {
        $(select).select2({
          width: 'resolve',
          minimumResultsForSearch: 5,
          dropdownAutoWidth: true
        });
      });
    }
  };

})(Drupal, jQuery, once);
