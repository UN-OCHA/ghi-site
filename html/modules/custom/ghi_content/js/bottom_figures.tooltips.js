(function ($, Drupal) {

  // Attach behaviors.
  Drupal.behaviors.BottomFiguresTooltips = {
    attach: (context, settings) => {
      once('bottom-figure-footnotes', '.gho-figure__value[data-footnote]', context).forEach((item) => {
        let footnote = $(item).attr('data-footnote').trim();
        if (!footnote) {
          return;
        }
        let footnote_tag = $('<i class="tooltip info" data-toggle="tooltip" data-tippy-content="' + footnote + '"><svg class="cd-icon icon cd-icon--about"><use xlink:href="#cd-icon--about"></use></svg></i>');
        $(item).append(footnote_tag);
      });
    }
  }
}(jQuery, Drupal));