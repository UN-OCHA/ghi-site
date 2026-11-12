(function (Drupal, once) {
  'use strict';

  const selector = 'textarea[data-description-length-feedback]';

  /**
   * Refreshes advisory feedback without changing the value or validity.
   */
  function updateFeedback(field, messages) {
    const feedback = document.getElementById(field.dataset.descriptionLengthFeedback);
    if (!feedback) {
      return;
    }
    const recommended = Number(field.dataset.descriptionLengthRecommended);
    const long = Number(field.dataset.descriptionLengthLong);
    // Array.from counts Unicode code points, matching PHP's mb_strlen.
    const length = Array.from(field.value.replace(/\r\n?/g, '\n')).length;
    let state = 'recommended';
    if (length > long) {
      state = 'very-long';
    }
    else if (length > recommended) {
      state = 'long';
    }
    const status = messages[state];
    const count = feedback.querySelector('[data-description-length-count]');
    const message = feedback.querySelector('[data-description-length-status]');
    field.dataset.descriptionLengthState = state;
    feedback.dataset.descriptionLengthState = state;
    count.textContent = Drupal.formatPlural(length, '@count character', '@count characters');
    if (message.textContent !== status) {
      message.textContent = status;
    }
  }

  Drupal.behaviors.descriptionLengthFeedback = {
    attach: function (context) {
      const fields = [];
      if (context.matches && context.matches(selector)) {
        fields.push(context);
      }
      fields.push(...context.querySelectorAll(selector));
      once('description-length-feedback', fields).forEach((field) => {
        const feedback = document.getElementById(field.dataset.descriptionLengthFeedback);
        if (!feedback) {
          return;
        }
        // Read the same translated messages used for the initial markup.
        const messages = JSON.parse(feedback.dataset.descriptionLengthMessages);
        const update = () => updateFeedback(field, messages);
        field.addEventListener('input', update);
        field.addEventListener('change', update);
        update();
      });
    }
  };
})(Drupal, once);
