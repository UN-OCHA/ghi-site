(function ($) {

  // Replace the set throbber method from ajax.js to always append the
  // progress indicator to the body.
  Drupal.Ajax.prototype.setProgressIndicatorThrobber = function () {
    this.progress.element = $(Drupal.theme('ajaxProgressThrobber', this.progress.message));
    $(this.progress.element).prepend('<div class="overlay"></div>');
    $('body').append(this.progress.element);
  };

})(jQuery, Drupal);
