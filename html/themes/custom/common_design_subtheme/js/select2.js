/* global once */

((Drupal, $, once) => {

  'use strict';

  Drupal.behaviors.CommonDesignSubthemeSelect2 = {
    attach(context) {
      once('common-design-subtheme-select2', '.ajax-switcher-wrapper select', context).forEach((select) => {
        const $select = $(select);
        const $dropdownParent = $select.closest('.form-item');
        const forceDropdownBelow = () => {
          // Ajax switchers sit in a tight absolute toolbar. Keeping the
          // dropdown inside the form item avoids clipping, but Select2 can
          // still mark it as opening above unless we normalize the classes.
          const select2 = $select.data('select2');
          const $openContainer = $dropdownParent.children('.select2-container--open');
          const $dropdown = select2 && select2.dropdown && select2.dropdown.$dropdown ? select2.dropdown.$dropdown : $openContainer.find('.select2-dropdown');
          $openContainer.removeClass('select2-container--above').addClass('select2-container--below');
          $dropdown.removeClass('select2-dropdown--above').addClass('select2-dropdown--below');
        };

        $select.select2({
          width: 'resolve',
          minimumResultsForSearch: 5,
          dropdownAutoWidth: true,
          dropdownParent: $dropdownParent.length ? $dropdownParent : $(document.body)
        });
        $select.on('select2:open.commonDesignSubtheme', () => {
          window.requestAnimationFrame(forceDropdownBelow);
        });
      });
    }
  };

})(Drupal, jQuery, once);
