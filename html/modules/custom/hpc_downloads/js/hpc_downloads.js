(function ($, Drupal, drupalSettings) {

  Drupal.hpc_active_download = null;

  Drupal.behaviors.hpc_downloads = {
    attach: function (context, settings) {
      $('.hpc-download-dialog-content a.btn-download', context).once('hpc-download-buttons-processed').click(function () {
        Drupal.hpc_download_status = 'starting';
        if (!$(this).parents('.hpc-download-dialog-wrapper').hasClass('views-query-batched')) {
          // Non-batched downloads don't have a progress bar and can live with
          // a simple text message.
          Drupal.hpc_downloads_set_modal_content(Drupal.t('The download process has been started. Please note that generating the download file may take a few moments.'));
          Drupal.hpc_downloads_set_modal_footer(Drupal.t('Preparing download'));
        }
        else {
          // Batched processes have a progressbar that appears quickly, so we
          // don't want to put a text message in between that will be gone too
          // fast. So we display a throbber which will be replaced by the
          // progress bar.
          Drupal.hpc_downloads_set_modal_content('<span class="throbber"></span>');
        }
      });
    }
  }

  // Generic download-start function.
  // Credits to https://gist.github.com/DavidMah/3533415
  Drupal.hpc_start_download = function (download_id) {
    if (Drupal.hpc_active_download != download_id) {
      return;
    }
    var form = $('<form id="download-form-' + download_id + '"></form>').attr('action', '/download/' + download_id + '/download').attr('method', 'get');
    form.appendTo('body').trigger('submit').remove();
    Drupal.hpc_download_status = 'finished';
  }

  Drupal.hpc_check_download_status = function (download_id) {
    if (Drupal.hpc_active_download != download_id) {
      return;
    }
    var download_link_selector = '*[data-download-id="' + download_id + '"]';
    $.ajax({
      url: '/download/' + download_id + '/check',
      type: "get"
    })
    .done(function (data) {
      Drupal.hpc_download_status = data.status;
      if (data.status == 'success') {
        // For non batched downloads we add a footer message. This must come
        // before the content update obviously, otherwhise the batch class
        // can't be found anymore.
        if ($('.hpc-download-dialog-wrapper .hpc-download-batch-progress-bar-wrapper').length == 0) {
          Drupal.hpc_downloads_set_modal_footer(Drupal.t('Download finished'));
        }

        // Start the actual file download.
        Drupal.hpc_downloads_set_panel_class(download_link_selector, false);
        Drupal.hpc_downloads_set_modal_content(Drupal.t('The file should have been downloaded automatically to your computer. If this is not the case, you can also download it here directly: <a href="!url">Download link</a>', {'!url': '/download/' + download_id + '/download'}));
        Drupal.hpc_start_download(download_id);
      }
      else if (data.status == 'pending') {
        // Try again a bit later.
        setTimeout(function () {
          Drupal.hpc_check_download_status(download_id);
        }, 2000);
      }
      else {
        // Show an error message.
        Drupal.hpc_downloads_set_modal_content(Drupal.t('There was a problem creating your requested download.<br />Please try again. If the problem persists please reach out to our team.'));
        Drupal.hpc_downloads_set_modal_footer(Drupal.t('Download error'));
        Drupal.hpc_downloads_set_panel_class(download_link_selector, false);
        Drupal.hpc_download_status = 'error';
      }
    })
    .fail(function (jqXHR, textStatus, errorThrown) {
      // Show an error message.
      Drupal.hpc_downloads_set_modal_content(Drupal.t('There was a problem creating your requested download.<br />Please try again. If the problem persists please reach out to our team.'));
      Drupal.hpc_downloads_set_modal_footer(Drupal.t('Download error'));
      Drupal.hpc_downloads_set_panel_class(download_link_selector, false);
      Drupal.hpc_download_status = 'error';
    });
  }

  Drupal.hpc_downloads_set_modal_content = function (message) {
    $('.hpc-download-dialog-wrapper .hpc-download-dialog-content').html('<p>' + message + '</p>');
  }
  Drupal.hpc_downloads_set_modal_footer = function (message) {
    var footer = $('.hpc-download-dialog-wrapper .hpc-download-dialog-footer');
    if (message && footer.hasClass('hidden')) {
      $(footer).removeClass('hidden');
    }
    $(footer).html(message);
  }

  Drupal.hpc_downloads_set_panel_class = function (child_selector, class_status) {
    var panel_pane = $(child_selector).parents('.panel-pane');
    if (!panel_pane) {
      return;
    }
    if (class_status == true) {
      $(panel_pane).addClass('download-active');
    }
    else {
      $(panel_pane).removeClass('download-active');
    }
  }

})(jQuery, Drupal, drupalSettings);
