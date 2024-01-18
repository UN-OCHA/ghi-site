/**
 * @file
 * Attaches behaviors for the Path module.
 */
(function ($, Drupal) {
  /**
   * Behaviors for settings summaries on path edit forms.
   *
   * @type {Drupal~behavior}
   *
   * @prop {Drupal~behaviorAttach} attach
   *   Attaches summary behavior on path edit forms.
   */
  Drupal.behaviors.pathDetailsSummariesEnforcedAliasPattern = {
    attach(context) {
      // Disable the original behavior of the path details summary.
      Drupal.behaviors.pathDetailsSummaries = {};
      $(context)
        .find('.path-form')
        .drupalSetSummary((context) => {
          const pathElement = document.querySelector(
            '.js-form-item-path-0-enforced-alias input',
          );
          const pathautoElement = document.querySelector(
            '.js-form-item-path-0-pathauto input',
          );
          const path = pathElement && pathElement.value;
          const pathauto = pathautoElement && pathautoElement.checked;
          return pathauto
            ? $(pathElement).attr('generated_alias')
            : (path
              ? Drupal.t('Alias: @alias', { '@alias': '/' + $(pathElement).attr('fixed_prefix') + '/' + path })
              : Drupal.t('No alias')
            );
        });
    },
  };
})(jQuery, Drupal);
