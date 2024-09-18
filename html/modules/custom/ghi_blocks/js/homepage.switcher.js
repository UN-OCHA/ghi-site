(function ($, Drupal) {

  // Attach behaviors.
  Drupal.behaviors.GhiHomepageSwitcher = {
    attach: function (context, settings) {
      $section_switcher = $('.section-switcher-wrapper', context);
      if (!$section_switcher.length) {
        return;
      }
      $homepageBlock = $section_switcher.parents('.ghi-block-global-homepages.has-year-switcher');
      if (!$homepageBlock.length) {
        return;
      }
      let block_uuid = $homepageBlock.attr('id').replace('block-', '');

      once('section-switcher', '.ghi-block.has-year-switcher .section-switcher-wrapper ul li a', context).forEach((item) => {
        let ajax_url = '/load-block/global_homepages/' + block_uuid + '?current_uri=' + $(item).attr('href');
        $(item).attr('original-href', $(item).attr('href'));
        $(item).attr('href', ajax_url);
        $(item).addClass('use-ajax');
      });
      if (!!(window.history && history.pushState)) {
        // Replace the current history item in newer browser.
        $section_switcher.find('ul li a').on('click', function () {
          window.history.replaceState(window.history.state, null, $(this).attr('original-href'));
        });
      };

      Drupal.attachBehaviors($section_switcher.get(0));
    }
  }
}(jQuery, Drupal));