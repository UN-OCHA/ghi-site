(function (Drupal, once) {
  'use strict';

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

  Drupal.behaviors.ghiIpeFrontendActions = {
    attach: (context) => {
      once('ghi-ipe-customize-transition', '.layout-builder-ipe-actions > .layout-builder-ipe--link-customize:not(.dropbutton-wrapper)', context).forEach((item) => {
        item.addEventListener('click', () => {
          // Only one canvas transition may run at a time. Otherwise a rapid
          // discard can overwrite the editor-opening Ajax response or vice
          // versa, leaving the page in a partially initialized state.
          suspendFrontendActions(item);
        }, true);
      });
    },
  };

})(Drupal, once);
