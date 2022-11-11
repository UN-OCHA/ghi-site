(function ($, Drupal) {

  // Attach behaviors.
  Drupal.behaviors.GhiHomepageSwitcher = {
    attach: function (context, settings) {
      $yearSwitcherBlock = $('.ghi-block.has-year-switcher', context);
      if (!$yearSwitcherBlock.length) {
        return;
      }
      $homepageBlock = $yearSwitcherBlock.parents('.ghi-block-global-homepages');
      let block_uuid = $homepageBlock.attr('id').replace('block-', '');

      $yearSwitcherBlock.find('.section-switcher-wrapper ul li a').each(function () {
        let ajax_url = '/load-block/global_homepages/' + block_uuid + '?current_uri=' + $(this).attr('href');
        $(this).attr('original-href', $(this).attr('href'));
        $(this).attr('href', ajax_url);
        $(this).addClass('use-ajax');
      });
      if (!!(window.history && history.pushState)) {
        // Replace the current history item in newer browser.
        $yearSwitcherBlock.find('.section-switcher-wrapper ul li a').on('click', function () {
          window.history.replaceState(window.history.state, null, $(this).attr('original-href'));
        });
      };

      Drupal.attachBehaviors($yearSwitcherBlock.get(0));
    }
  }
}(jQuery, Drupal));