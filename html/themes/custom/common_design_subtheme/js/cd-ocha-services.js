(function (Drupal) {
  'use strict';

  Drupal.behaviors.cdOchaServices = {
    attach: function (context, settings) {
      console.log('cdOchaServices.attach');
      // Move the OCHA services section to the header.
      this.moveToHeader('#cd-ocha-services', '#cd-global-header__actions');
    },

    /**
     * Hide and move OCHA Services to the top of the header after the target.
    */
    moveToHeader: function (id, target) {
      var section = document.querySelector(id);
      var sibling = document.querySelector(target);
      if (section && sibling) {
        // Ensure the element is hidden before moving it to avoid flickering.
        this.toggleVisibility(section, true);
        sibling.parentNode.insertBefore(section, sibling.nextSibling);
      } else {
        console.debug('OCHA Services was not moved from Footer to Header.');
      }
    },

    toggleVisibility: function (element, hide) {
      element.setAttribute('cd-data-hidden', hide === true);
    }
  };
})(Drupal);
