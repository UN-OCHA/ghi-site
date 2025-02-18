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
    attach: function (context, settings) {
      once("entity-browser-form", 'form.entity-browser-form', context).forEach((form) => {
        // Reset selections when the form is opened.
        selections = {};

        form.addEventListener("submit", (event) => {
          Object.keys(selections).forEach((key) => {
            if (!form.contains(selections[key])) {
              // Add previous selections when they don't exist.
              form.appendChild(selections[key]);
            }
          });
        });
      });

      once("entity-browser-input", 'form.entity-browser-form > .view').forEach((view) => {
        const counter = document.createElement("div");
        counter.className = "entity-browser-selection-counter";
        counter.innerText = Drupal.formatPlural(Object.keys(selections).length, "1 selection", "@count selections");
        const table = view.querySelector(".view-content table.views-table");
        table.parentNode.insertBefore(counter, table);

        view.querySelectorAll('input[type="checkbox"][value]').forEach((element) => {
          if (selections[element.name]) {
            // Restore selection from the previous page.
            element.checked = true;
          }
          $(element).on("change", () => {
            if (element.checked) {
              selections[element.name] = element.cloneNode();
              selections[element.name].type = "hidden";
            } else {
              delete selections[element.name];
            }
            counter.innerText = Drupal.formatPlural(Object.keys(selections).length, "1 selection", "@count selections");
          });
        });
      });
    }
  };
})(jQuery, Drupal, once);