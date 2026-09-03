/* eslint-disable no-bitwise, no-nested-ternary, no-mutable-exports, comma-dangle, strict */

'use strict';

(($, Drupal, drupalSettings) => {

  const suspendFrontendActions = (activeAction) => {
    const actionContainer = activeAction.closest('.layout-builder-ipe-actions');
    if (!actionContainer) {
      return;
    }
    const suspendedActions = [...actionContainer.children].filter((action) => action !== activeAction);
    actionContainer.setAttribute('aria-busy', 'true');
    suspendedActions.forEach((action) => {
      action.setAttribute('aria-disabled', 'true');
      action.style.pointerEvents = 'none';
      action.style.opacity = '0.5';
    });

    // Restore the current links if opening the editor fails. A successful
    // response replaces them before this fallback is needed.
    window.setTimeout(() => {
      actionContainer.removeAttribute('aria-busy');
      suspendedActions.forEach((action) => {
        action.removeAttribute('aria-disabled');
        action.style.removeProperty('pointer-events');
        action.style.removeProperty('opacity');
      });
    }, 10000);
  };

  Drupal.toolbar.ToolbarVisualView.prototype.updateToolbarHeight = function () {
    const $glbToolbar = $('.gin-secondary-toolbar');
    if ($glbToolbar.length) {
      $('body').addClass('has-secondary-toolbar');
      $glbToolbar.addClass('gin-secondary-toolbar--processed');
      this.triggerDisplace();
    }
  };

  Drupal.behaviors.ghiGinLbToolbar = {
    attach: (context) => {
      once('ghi-ipe-customize-transition', '.layout-builder-ipe-actions > .layout-builder-ipe--link-customize:not(.dropbutton-wrapper)', context).forEach((item) => {
        item.addEventListener('click', () => {
          // Only one canvas transition may run at a time. Otherwise a rapid
          // discard can overwrite the editor-opening Ajax response or vice
          // versa, leaving the page in a partially initialized state.
          suspendFrontendActions(item);
        }, true);
      });
      once('glb-button-close-editor', '.glb-button-close-editor').forEach((item) => {
        item.addEventListener('click', function () {
          const closeLink = document.querySelector('.layout-builder-ipe-close-editor');
          if (closeLink) {
            window.location.assign(closeLink.href);
          }
        });
      });
      once('glb-button-discard', '.glb-button-discard ').forEach((item) => {
        item.addEventListener('click', function () {
          const frontendDiscardLink = document.querySelector('.layout-builder-ipe-actions > .layout-builder-ipe--link-discard');
          if (frontendDiscardLink) {
            // Page Manager's embedded cancel button submits immediately. Use
            // IPE's frontend link so the shared confirmation is shown first.
            frontendDiscardLink.click();
            return;
          }
          // The discard changes button for page manager pages.
          const cancelButton = document.querySelector('#gin_sidebar .form-actions .glb-button[data-drupal-selector="edit-cancel"]');
          if (cancelButton) {
            cancelButton.click();
          }
          // The discard changes button for entity pages.
          const discardButton = document.querySelector('#gin_sidebar .form-actions .glb-button[data-drupal-selector="edit-discard-changes"]');
          if (discardButton) {
            discardButton.click();
          }
        });
      });
    },
  };

})(jQuery, Drupal, drupalSettings);
