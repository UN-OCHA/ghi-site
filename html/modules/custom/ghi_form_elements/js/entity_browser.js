/**
 * @file
 * JavaScript behaviors for entity browser.
 */
(function ($, Drupal, once) {
  /**
   * Keep track of selections across paginated lists.
   *
   * @type Object
   */
  let selections = {};

  Drupal.behaviors.EntityBrowserSelections = {
    attach: function (context) {
      once('entity-browser-form', 'form.entity-browser-form', context).forEach((form) => {
        // Reset selections when the form is opened.
        selections = {};

        form.addEventListener('submit', () => {
          Object.keys(selections).forEach((key) => {
            if (!form.contains(selections[key])) {
              // Add previous selections when they don't exist.
              form.appendChild(selections[key]);
            }
          });
        });
      });

      once('entity-browser-input', 'form.entity-browser-form > .view').forEach((view) => {
        const content = view.querySelector('.view-content');
        if (!content) {
          return;
        }
        const form = view.closest('form.entity-browser-form');
        let counter = form?.querySelector('.entity-browser-selection-counter');
        if (!counter) {
          counter = document.createElement('div');
          counter.className = 'entity-browser-selection-counter';
          counter.setAttribute('aria-live', 'polite');
          const actions = form
            ?.querySelector('.is-entity-browser-submit')
            ?.closest('.form-actions');
          // The action bar survives Views AJAX replacements, so reuse its counter.
          (actions ?? content).prepend(counter);
        }
        counter.innerText = Drupal.formatPlural(Object.keys(selections).length, '1 selection', '@count selections');

        view.querySelectorAll('input[type="checkbox"][value]').forEach((element) => {
          if (selections[element.name]) {
            // Restore selection from the previous page.
            element.checked = true;
          }
          $(element).on('change', () => {
            if (element.checked) {
              selections[element.name] = element.cloneNode();
              selections[element.name].type = 'hidden';
            } else {
              delete selections[element.name];
            }
            counter.innerText = Drupal.formatPlural(Object.keys(selections).length, '1 selection', '@count selections');
          });
        });
      });
    }
  };
})(jQuery, Drupal, once);
