(function ($, Drupal) {
  'use strict';

  // Attach behaviors.
  Drupal.behaviors.subArticle = {
    attach: (context, settings) => {
      once('collapsible-sub-article', '.paragraph--type--sub-article[data-article-collapsible="true"] article', context).forEach((subArticle) => {
        // Find collapsible paragraphs.
        let collapsed = false;
        let $wrapper = $('<div class="collapsed-wrapper" />');
        $wrapper.addClass('collapsible');
        $wrapper.addClass('fade-out');
        $(subArticle).find('.gho-sub-article__content > div').each(function (i, paragraph) {
          if ($(paragraph).hasClass('not-collapsible') && !collapsed) {
            return;
          }
          $wrapper.append($(paragraph));
          collapsed = true;
        });
        $(subArticle).find('.gho-sub-article__content').append($wrapper);

        if (collapsed) {
          // Add collapsible control.
          let $collapseControlOuter = $('<div>').addClass('collapsible-control--outer');
          let $collapseControl = $('<div>').addClass('collapsible-control').addClass('content-width');
          let $expandButton = $('<a />').text(Drupal.t('Read more'))
          .attr('href', '#')
          .addClass('expand-collapsible')
          .addClass('cd-button');
          $expandButton.click(function (e) {
            e.preventDefault();
            $expandButton.addClass('hidden');
            $collapsButton.removeClass('hidden');
            $(subArticle).find('.gho-sub-article__content > div.collapsible').addClass('expanded');
          });

          let $collapsButton = $('<a />').text(Drupal.t('Collapse content'))
          .attr('href', '#')
          .addClass('collaps-collapsible')
          .addClass('cd-button')
          .addClass('hidden');
          $collapsButton.click(function (e) {
            e.preventDefault();
            $expandButton.removeClass('hidden');
            $collapsButton.addClass('hidden');
            let $scroolTarget = $(subArticle).find('.gho-sub-article__title');
            $scroolTarget.get(0).scrollIntoView({behavior: 'smooth', block: 'start'});
            setTimeout(() => {
              $(subArticle).find('.gho-sub-article__content > div.collapsible').removeClass('expanded');
            }, 500);

          });

          $collapseControl.append($expandButton);
          $collapseControl.append($collapsButton);

          $collapseControlOuter.append($collapseControl);
          $(subArticle).append($collapseControlOuter);
          $(subArticle).addClass('collapsible');
        }
      });
    }
  }
})(jQuery, Drupal);
