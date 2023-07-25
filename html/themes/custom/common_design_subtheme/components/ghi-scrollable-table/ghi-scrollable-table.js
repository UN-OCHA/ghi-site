(function ($, Drupal) {

  Drupal.ScrollableTable = function (table) {
    $(table).uniqueId();
    this.table = table;
    this.timeout = null;
    this.shadowLeft = null;
    this.shadowRight = null;

    this.init = function() {
      this.initShadows();
      this.calcPosition();
      this.addScrollListener();
      this.addResizeListener();
      $(this.table).trigger('resize.shadow');
    }

    this.initShadows = function() {
      $(this.table).wrap($('<div>').addClass('scrollable-table--wrapper'));
      this.shadowLeft = $('<div>')
        .addClass('shadow-left');
      $(this.table).parent().append(this.shadowLeft);
      this.shadowRight = $('<div>')
        .addClass('shadow-right');
      $(this.table).parent().append(this.shadowRight);
      $(this.table).find('table').css('position', 'relative');
    }

    this.getScrollOffset = function() {
      offset = $(this.table).find('table').outerWidth() - $(this.table).outerWidth();
      return Math.floor(offset);
    }

    this.calcPosition = function() {
      width = $(this.table).outerWidth();
      height = $(this.table).find('table').outerHeight();
      position = $(this.table).find('table').position();
      let top_offset = $(this.table).find('table').offset().top - $(this.table).offset().top;

      // update
      this.shadowLeft.css({
        height: height + 'px',
        top: top_offset + 'px',
        left: (-1 * position.left) + 'px'
      });
      this.shadowRight.css({
        height: height + 'px',
        top: top_offset + 'px',
        left: (width + (-1 * position.left) - 20) + 'px',
      });
    }

    this.addScrollListener = function() {
      var self = this;
      $(self.table).off('scroll.shadow');
      $(self.table).on('scroll.shadow', function() {
        let scroll_offset = self.getScrollOffset();
        if ($(self.table).scrollLeft() > 0 && scroll_offset > 0) {
          self.shadowLeft.fadeIn(125);
        } else {
          self.shadowLeft.fadeOut(125);
        }
        if ($(self.table).scrollLeft() >= scroll_offset || scroll_offset == 0) {
          self.shadowRight.fadeOut(125);
        } else {
          self.shadowRight.fadeIn(125);
        }
      });
    }

    this.addResizeListener = function() {
      var self = this;
      $(window).on('resize.shadow', function() {
        clearTimeout(self.timeout);
        self.timeout = setTimeout(function() {
          self.calcPosition();
          $(self.table).trigger('scroll.shadow');
        }, 10);
      });
    }

  }

  Drupal.behaviors.ScrollableTable = {
    attach: function (context, settings) {
      $('.scrollable-table', context).once('sortable-table').each(function(i, table) {
        if ($(table).css('overflow-x') != 'auto') {
          return;
        }
        table = new Drupal.ScrollableTable(table);
        table.init();
      });
    }
  };
}(jQuery, Drupal));