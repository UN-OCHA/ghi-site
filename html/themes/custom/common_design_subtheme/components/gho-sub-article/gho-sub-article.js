(function ($, Drupal) {
  'use strict';

  // Attach behaviors.
  Drupal.behaviors.subArticle = {
    attach: (context, settings) => {
      once('collapsible-sub-article', '.paragraph--type--sub-article[data-article-collapsible="true"] article', context).forEach((subArticle) => {
        let $subArticle = $(subArticle);
        let $deferredPlaceholder = $subArticle.find('.ghi-subarticle-deferred-placeholder[data-subarticle-load-url]').first();

        // Find collapsible paragraphs.
        let collapsed = false;
        let $wrapper = $('<div class="collapsed-wrapper" />');
        $wrapper.addClass('collapsible');
        $wrapper.addClass('fade-out');
        $subArticle.find('.gho-sub-article__content > div').each(function (i, paragraph) {
          if ($(paragraph).hasClass('not-collapsible') && !collapsed) {
            return;
          }
          $wrapper.append($(paragraph));
          collapsed = true;
        });

        if (collapsed || $deferredPlaceholder.length) {
          if (collapsed) {
            $subArticle.find('.gho-sub-article__content').append($wrapper);
          }
          // Add collapsible control.
          let $collapseControlOuter = $('<div>').addClass('collapsible-control--outer');
          let $collapseControl = $('<div>').addClass('collapsible-control').addClass('content-width');
          let $expandButton = $('<a />').text(Drupal.t('Read more'))
          .attr('href', '#')
          .addClass('expand-collapsible')
          .addClass('cd-button');
          $expandButton.click(function (e) {
            e.preventDefault();
            let expandSubArticle = function () {
              $expandButton.addClass('hidden');
              $collapsButton.removeClass('hidden');
              $subArticle.find('.gho-sub-article__content > div.collapsible').addClass('expanded');
            };

            let $placeholder = $subArticle.find('.ghi-subarticle-deferred-placeholder[data-subarticle-load-url]').first();
            if (!$placeholder.length) {
              expandSubArticle();
              return;
            }

            $expandButton.addClass('is-loading').text(Drupal.t('Loading...'));
            let ajax = Drupal.ajax({
              url: $placeholder.attr('data-subarticle-load-url'),
              element: $expandButton.get(0),
              progress: {
                type: 'throbber'
              }
            });
            let request = ajax.execute();
            if (!request) {
              return;
            }
            request.done(function () {
              Drupal.attachBehaviors(subArticle);
              $expandButton.removeClass('is-loading').text(Drupal.t('Read more'));
              expandSubArticle();
            }).fail(function () {
              $expandButton.removeClass('is-loading').text(Drupal.t('Read more'));
            });
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
            let $scroolTarget = $subArticle.find('.gho-sub-article__title');
            $scroolTarget.get(0).scrollIntoView({behavior: 'smooth', block: 'start'});
            setTimeout(() => {
              $subArticle.find('.gho-sub-article__content > div.collapsible').removeClass('expanded');
            }, 500);

          });

          $collapseControl.append($expandButton);
          $collapseControl.append($collapsButton);

          $collapseControlOuter.append($collapseControl);
          $subArticle.append($collapseControlOuter);
          $subArticle.addClass('collapsible');
        }
      });
    }
  }
})(jQuery, Drupal);
