(function ($, Drupal, once) {
  'use strict';

  Drupal.ScrollableTable = function (table) {
    $(table).uniqueId();
    this.table = table;
    this.tableElement = $(table).find('table').get(0);
    this.timeout = null;
    this.shadowLeft = null;
    this.shadowRight = null;
    this.resizeObserver = null;

    this.init = function () {
      this.initShadows();
      this.addScrollListener();
      this.addResizeListener();
      this.addTableResizeObserver();
      this.refresh();
      setTimeout(() => this.refresh(), 0);
    };

    this.initShadows = function () {
      const $table = $(this.table);
      if (!$table.parent().hasClass('scrollable-table--wrapper')) {
        $table.wrap($('<div>').addClass('scrollable-table--wrapper'));
      }

      const $wrapper = $table.parent();
      this.shadowLeft = $wrapper.find('> .shadow-left');
      if (!this.shadowLeft.length) {
        this.shadowLeft = $('<div>').addClass('shadow-left');
        $wrapper.append(this.shadowLeft);
      }
      this.shadowRight = $wrapper.find('> .shadow-right');
      if (!this.shadowRight.length) {
        this.shadowRight = $('<div>').addClass('shadow-right');
        $wrapper.append(this.shadowRight);
      }
      $(this.tableElement).css('position', 'relative');
    };

    this.getScrollOffset = function () {
      return Math.max(0, Math.floor(this.table.scrollWidth - this.table.clientWidth));
    };

    this.refresh = function () {
      this.calcPosition();
      this.updateShadows();
    };

    this.calcPosition = function () {
      const scrollRect = this.table.getBoundingClientRect();
      const wrapperRect = $(this.table).parent().get(0).getBoundingClientRect();
      const height = $(this.tableElement).outerHeight();
      const topOffset = $(this.tableElement).offset().top - $(this.table).offset().top;
      const leftOffset = scrollRect.left - wrapperRect.left;
      const rightOffset = scrollRect.right - wrapperRect.left - this.shadowRight.outerWidth();

      this.shadowLeft.css({
        height: height + 'px',
        top: topOffset + 'px',
        left: leftOffset + 'px'
      });
      this.shadowRight.css({
        height: height + 'px',
        top: topOffset + 'px',
        left: rightOffset + 'px'
      });
    };

    this.updateShadows = function () {
      const scrollOffset = this.getScrollOffset();
      const scrollLeft = Math.ceil($(this.table).scrollLeft());
      const isScrollable = scrollOffset > 1;

      $(this.table).parent().toggleClass('is-scrollable', isScrollable);
      this.shadowLeft.toggleClass('is-visible', isScrollable && scrollLeft > 0);
      this.shadowRight.toggleClass('is-visible', isScrollable && scrollLeft < scrollOffset - 1);
    };

    this.addScrollListener = function () {
      var self = this;
      $(self.table).off('scroll.shadow');
      $(self.table).on('scroll.shadow', function () {
        self.updateShadows();
      });
    };

    this.addResizeListener = function () {
      var self = this;
      $(window).on('resize.shadow', function () {
        clearTimeout(self.timeout);
        self.timeout = setTimeout(function () {
          self.refresh();
        }, 10);
      });
    };

    this.addTableResizeObserver = function () {
      if (typeof window.ResizeObserver == 'undefined') {
        return;
      }

      var self = this;
      this.resizeObserver = new window.ResizeObserver(function () {
        clearTimeout(self.timeout);
        self.timeout = setTimeout(function () {
          self.refresh();
        }, 10);
      });
      this.resizeObserver.observe(this.table);
      this.resizeObserver.observe(this.tableElement);
    };

  };

  Drupal.behaviors.ScrollableTable = {
    attach: function (context, settings) {
      once('ghi-scrollable-table', '.scrollable-table', context).forEach(element => {
        const table = new Drupal.ScrollableTable(element);
        table.init();
      });
    }
  };
})(jQuery, Drupal, window.once);
