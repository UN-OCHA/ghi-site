(function ($, Swiper) {

  Drupal.LinkCarousel = {};

  Drupal.LinkCarousel.updateContent = function(container, index) {
    // Show the new content box.
    $(container).find('.slide-details').hide();
    $(container).find('.slide-details[data-slide-index="' + index + '"').show();
    // Mark the current navigation item as active.
    $(container).find('.slide-navigation').removeClass('active');
    $(container).find('.slide-navigation[data-slide-index="' + index + '"').addClass('active');
  }

  Drupal.behaviors.LinkCarousel = {
    attach: function (context, settings) {
      $carousel = $('.link-carousel-wrapper', context);
      $.each($carousel, function(i, container) {
        // The main image area.
        const swiper = new Swiper($(container).find('.swiper').get(0), {
          speed: 400,
          spaceBetween: 100,
          autoHeight: true,
        });
        // Navidation swiper if necessary.
        let nav_slider = new Swiper($(container).find('.swiper').get(1), {
          speed: 400,
          spaceBetween: 0,
          navigation: {
            prevEl: '.swiper-button-prev',
            nextEl: '.swiper-button-next',
          },
          createElements: true,
          slidesPerView: 1,
          enabled: false,
          observer: true,
          observeParents: true,
          observeSlideChildren: true,
          breakpoints: {
            1024: {
              slidesPerView: 2,
              enabled: true,
              spaceBetween: 30,
            },
            1400: {
              slidesPerView: 3,
              enabled: true,
              spaceBetween: 30,
            }
          }
        });
        // This should not be necessary according to the swiper docs, but when
        // resizing the browser window, the breakpoints do not seem to get
        // applied without this.
        $(window).on('resize', function() {
          nav_slider.update();
      });
        $(container).addClass('swiper-processed');

        Drupal.LinkCarousel.updateContent(container, swiper.activeIndex);

        // Make the navigation work.
        $(container).find('.slide-navigation').on('click', function () {
          target = $(this).data('slide-index');
          swiper.slideTo(target);
        });
        swiper.on('activeIndexChange', function () {
          Drupal.LinkCarousel.updateContent(container, swiper.activeIndex);
        });
      });
    }
  };
}(jQuery, Swiper));