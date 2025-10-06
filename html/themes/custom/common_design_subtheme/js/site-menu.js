(($, Drupal, once) => {

  'use strict';

  Drupal.CommonDesignSiteMenu = {};

  Drupal.CommonDesignSiteMenu.init = () => {
    let togglers = once('site-menu', '#block-mainnavigation-toggler');
    if (togglers.length !== 1) {
      return;
    }
    let toggler = togglers[0];
    toggler.addEventListener('click', function () {
      if ($(document).width() > 1024) {
        return;
      }
      let expanded = $(this).attr('aria-expanded') === 'true';
      let $nav = $(this).next();
      if (expanded) {
        let offset = $('nav.toolbar-bar').height() + $('header.cd-header').height();
        $(document).scrollTop(0);
        $('body').css('overflow', 'hidden');
        $nav.css('position', 'fixed');
        $nav.css('height', '100%');
        $nav.css('overflow', 'scroll');
        $nav.css('padding-bottom', 'calc(' + offset + 'px + 1rem)');
      }
      else {
        $('body').css('overflow', 'visible');
        Drupal.CommonDesignSiteMenu.reset($nav);
      }
    });

    $(toggler).next().find('> ul > li').each(function () {
      let $button = $(this).children('button');
      let $title = $(this).find('.mega-menu .cd-block-title');
      if ($button.text() == $title.text()) {
        $title.hide();
      }
    });
  };

  Drupal.CommonDesignSiteMenu.reset = ($nav) => {
    let styleObject = $nav.prop('style');
    styleObject.removeProperty('position');
    styleObject.removeProperty('height');
    styleObject.removeProperty('overflow');
    styleObject.removeProperty('padding');
    $nav.find('> ul > li .mega-menu .cd-block-title').show();
  }

  Drupal.CommonDesignSiteMenu.getToggler = () => {
    return $('#block-mainnavigation-toggler')[0];
  }

  Drupal.behaviors.CommonDesignSiteMenu = {
    attach: function (context, settings) {
      once('site-menu-document', 'body').forEach(() => {
        var observer = new MutationObserver((mutations, observer) => {
          Drupal.CommonDesignSiteMenu.init();

          if ($(document).width() > 1024) {
            Drupal.CommonDesignSiteMenu.reset($('#block-mainnavigation'));
          }
        });
        observer.observe(document, {
          subtree: true,
          attributes: true
        });

      });
    }
  };

})(jQuery, Drupal, once);