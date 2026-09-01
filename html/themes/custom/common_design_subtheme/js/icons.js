((Drupal) => {

  'use strict';

  const selector = '[data-hpc-icon]';

  /**
   * Builds the local sprite symbol id for a safe icon token.
   *
   * @param {string} icon
   *   The requested icon name.
   *
   * @returns {string|null}
   *   The SVG symbol id, or null if the icon token is invalid.
   */
  const getSymbolId = (icon) => {
    const symbolName = String(icon || '').replace(/_/g, '-');
    return /^[a-z0-9-]+$/.test(symbolName) ? `material-symbol-${symbolName}` : null;
  };

  /**
   * Hydrates one icon placeholder with the local SVG sprite symbol.
   *
   * @param {HTMLElement} element
   *   The placeholder element.
   */
  const hydrateIcon = (element) => {
    if (element.dataset.hpcIconProcessed === 'true') {
      return;
    }
    const symbolId = getSymbolId(element.dataset.hpcIcon);
    if (!symbolId) {
      return;
    }

    element.textContent = '';
    const svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
    svg.classList.add('material-icon__svg');
    svg.setAttribute('aria-hidden', 'true');
    svg.setAttribute('focusable', 'false');

    const use = document.createElementNS('http://www.w3.org/2000/svg', 'use');
    use.setAttribute('href', `#${symbolId}`);
    use.setAttributeNS('http://www.w3.org/1999/xlink', 'xlink:href', `#${symbolId}`);
    svg.appendChild(use);

    element.appendChild(svg);
    element.dataset.hpcIconProcessed = 'true';
  };

  Drupal.hpcIcons = Drupal.hpcIcons || {};
  Drupal.hpcIcons.attach = (context = document) => {
    const icons = [];
    if (context.matches && context.matches(selector)) {
      icons.push(context);
    }
    icons.push(...context.querySelectorAll(selector));
    icons.forEach(hydrateIcon);
  };

  Drupal.behaviors.hpcIcons = {
    attach(context) {
      Drupal.hpcIcons.attach(context);
    }
  };

})(Drupal);
