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
        const swiper = new Swiper($(container).find('.swiper').get(0), {
          speed: 400,
          spaceBetween: 100,
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