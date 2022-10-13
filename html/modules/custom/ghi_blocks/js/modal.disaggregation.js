(function ($, Drupal) {

  // Taken from of https://stackoverflow.com/a/19015262
  function getScrollBarWidth () {
    var $outer = $('<div>').css({visibility: 'hidden', width: 100, overflow: 'scroll'}).appendTo('body'),
        widthWithScroll = $('<div>').css({width: '100%'}).appendTo($outer).outerWidth();
    $outer.remove();
    return 100 - widthWithScroll;
  };

  // Attach behaviors.
  Drupal.behaviors.hpc_disaggregation_modal = {
    attach: function(context, settings) {
      $(window)
        .once('disaggregation-modal-title')
        .on('dialog:aftercreate', function () {
          $('.ui-dialog.disaggregation-modal .ui-dialog-title').html(settings.disaggregation_modal_title);
        });
      // $('.disaggregation-modal-trigger span', context).css('cursor', 'pointer');
      // $('.disaggregation-modal-trigger', context).css('cursor', 'pointer');
      // $('.disaggregation-modal-trigger', context).click(function() {

      //   let page_url = $(this).data('path');
      //   let attachment_id = $(this).data('attachment-id');
      //   let metric = $(this).data('metric');
      //   let reporting_period = $(this).data('reporting-period');
      //   let icon = $(this).data('icon');

      //   // First show the modal, so that the users knows that something is
      //   // happening.
      //   Drupal.CTools.Modal.show('hpc-disaggregation-modal');

      //   $.ajax({
      //     url: '/modal-content/disaggregation/' + attachment_id + '/' + metric + '/' + reporting_period,
      //     type: "get"
      //   })
      //     .done(function(modal_settings) {
      //       $('#modalContent').addClass('disaggregation-modal');
      //       var modal_title = modal_settings.title;
      //       if (icon) {
      //         modal_title = icon + modal_title;
      //       }
      //       $('#modal-title').html(modal_title);
      //       if (modal_settings.reporting_period) {
      //         var options = { year: 'numeric', month: 'long', day: 'numeric' };
      //         let start_date = new Date(modal_settings.reporting_period.startDate).toLocaleDateString('en-US', options);
      //         let end_date = new Date(modal_settings.reporting_period.endDate).toLocaleDateString('en-US', options);
      //         $('.modal-header').append('<div class="reporting-period">' + Drupal.t('Monitoring period !n: !start_date - !end_date', {
      //           '!start_date': start_date,
      //           '!end_date': end_date,
      //           '!n': modal_settings.reporting_period.periodNumber
      //         }) + '</div>');
      //       }

      //       if (modal_settings.content.hasOwnProperty('message')) {
      //         $('#modal-content').html('<div class="modal-table-wrapper"><div class="message">' + modal_settings.content['message'] + '</div></div>');
      //         $('#modal-content .modal-table-wrapper .message').css('margin-top', '1rem');
      //       }

      //       if (modal_settings.content.hasOwnProperty('table_data')) {
      //         var table_data = modal_settings.content['table_data'];
      //         var table = Drupal.theme('table', table_data.header, table_data.rows, {'classes': 'pane-table sortable sticky-enabled disaggregation-modal-table'});
      //         $('#modal-content').html('<div class="modal-table-wrapper">' + table + '</div>');
      //         $('#modal-content').css('overflow-y', 'hidden');
      //         $('#modal-content .modal-table-wrapper').css('overflow-y', 'auto');
      //         $('#modal-content .modal-table-wrapper').css('overflow-x', 'auto');

      //         Drupal.attachBehaviors($('#modal-content')[0], settings);
      //         // Unbind the scroll event because it interferes with our own
      //         // positioning if the page is scrolled all the way to the bottom.
      //         $(window).unbind('scroll.drupal-tableheader', $('#modal-content .disaggregation-modal-table').data("drupal-tableheader").eventhandlerRecalculateStickyHeader);

      //         var body_scroll_top = document.documentElement.scrollTop || document.body.scrollTop;
      //         var modal_position = $('#modalContent').position();
      //         var sticky_header_top = modal_position.top - body_scroll_top + $('.modal-header .modal-title').height() + 1;

      //         // Get the scrollbar width.
      //         var scrollbar_width = $('#modal-content').height() < $('#modal-content .modal-table-wrapper').height() ? getScrollBarWidth() : 0;

      //         var main_table = $('#modal-content').find('.disaggregation-modal-table');
      //         var sticky_header = $('#modal-content').find('.sticky-header');
      //         $(sticky_header).addClass('pane-table');

      //         $('#modal-content .modal-table-wrapper').css('margin-top', $(sticky_header).height() + 'px');
      //         $('#modal-content .modal-table-wrapper table.sticky-enabled').css('margin-top', '-' + $(sticky_header).height() + 'px');

      //         // Adjust position and width.
      //         $(sticky_header).css('width', ($(main_table).find('tbody').width() + scrollbar_width) + 'px');
      //         $(sticky_header).css('top', (sticky_header_top - 15) + 'px');
      //         $(sticky_header).css('visibility', 'visible');

      //         // Hide the original table header.
      //         $(main_table).find('thead').css('visibility', 'hidden');

      //         // Special logic to work around the problem that you can't set overflow
      //         // hidden on fixed elements. We clip the header manually here and
      //         // reposition and reclip the header on scroll.
      //         var sticky_header_original_left = parseInt($(sticky_header).css('left').replace('px', ''));
      //         $(sticky_header).css('clip', 'rect(auto, ' + ($('#modal-content').width() - (scrollbar_width > 0 ? 15 : 0)) + 'px, auto, 0px)');
      //         $('#modal-content .modal-table-wrapper').scroll(function(e) {
      //           var scroll_left = $(e.target).scrollLeft();
      //           $(sticky_header).css('left', (sticky_header_original_left - scroll_left) + 'px');
      //           console.log($('#modal-content').width());
      //           $(sticky_header).css('clip', 'rect(auto, ' + ($('#modal-content').width() + scroll_left - (scrollbar_width > 0 ? 15 : 0)) + 'px, auto, ' + scroll_left + 'px)');
      //         });

      //         // Adjust column widths.
      //         $(main_table).find('tbody tr:first-child td').each(function(i, item) {
      //           $(sticky_header).find('thead tr th:nth-child(' + (i + 1) + ')').width($(item).width());
      //         });

      //         // And finally adjust the width of the last column, to assure good
      //         // looking positioning in the presence of a scrollbar.
      //         var last_column_width = $(sticky_header).find('thead tr th:last-child').width();
      //         var last_column_padding_right = parseInt($(sticky_header).find('thead tr th:last-child').css('padding-right').replace('px', ''));
      //         $(sticky_header).find('thead tr th:last-child').width((last_column_width + scrollbar_width) + 'px');
      //         $(sticky_header).find('thead tr th:last-child').css('padding-right', (last_column_padding_right + scrollbar_width) + 'px');
      //       }
      //     });

      // });
    }
  }

})(jQuery, Drupal);
