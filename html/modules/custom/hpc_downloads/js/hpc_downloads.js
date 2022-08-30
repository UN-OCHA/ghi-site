(function ($, Drupal, drupalSettings) {

  Drupal.HpcDownloads = Drupal.HpcDownloads || {
    download_status: null,
    active_download: null,
  };

  Drupal.behaviors.HpcDownloads = {
    attach: function (context, settings) {

      $('.hpc-download-dialog-content a.btn-download', context).once('hpc-download-buttons-processed').each(function() {
        block_id = $(this).attr('data-block-uuid');
        if (Drupal.hasOwnProperty('GhiBlockSettings') && Drupal.GhiBlockSettings.hasOwnProperty(block_id)) {
          let block_settings = Drupal.GhiBlockSettings[block_id];
          var href = $(this).attr('href');
        }
        $(this).click(function (e) {
          Drupal.HpcDownloads.download_status = 'starting';
          if (!$(this).parents('.hpc-download-dialog-wrapper').hasClass('views-query-batched')) {
            // Non-batched downloads don't have a progress bar and can live with
            // a simple text message.
            Drupal.HpcDownloads.setModalContent(Drupal.t('The download process has been started. Please note that generating the download file may take a few moments.'));
            Drupal.HpcDownloads.setModalFooter(Drupal.t('Preparing download'));
          }
          else {
            // Batched processes have a progressbar that appears quickly, so we
            // don't want to put a text message in between that will be gone too
            // fast. So we display a throbber which will be replaced by the
            // progress bar.
            Drupal.HpcDownloads.setModalContent('<span class="throbber"></span>');
          }
        });
      });
    }
  }

  // Generic download-start function.
  // Credits to https://gist.github.com/DavidMah/3533415
  Drupal.HpcDownloads.startDownload = function (download_id) {
    if (Drupal.HpcDownloads.active_download != download_id) {
      return;
    }
    var form = $('<form id="download-form-' + download_id + '"></form>').attr('action', '/download/' + download_id + '/download').attr('method', 'get');
    form.appendTo('body').trigger('submit').remove();
    Drupal.HpcDownloads.download_status = 'finished';
  }

  // Check the status of an active download process.
  Drupal.HpcDownloads.checkDownloadStatus = function (download_id) {
    if (Drupal.HpcDownloads.active_download != download_id) {
      return;
    }
    $.ajax({
      url: '/download/' + download_id + '/check',
      type: "get"
    })
    .done(function (data) {
      Drupal.HpcDownloads.download_status = data.status;
      if (data.status == 'success') {
        // For non batched downloads we add a footer message. This must come
        // before the content update obviously, otherwhise the batch class
        // can't be found anymore.
        if ($('.hpc-download-dialog-wrapper .hpc-download-batch-progress-bar-wrapper').length == 0) {
          Drupal.HpcDownloads.setModalFooter(Drupal.t('Download finished'));
        }

        // Start the actual file download.
        Drupal.HpcDownloads.setModalContent(Drupal.t('The file should have been downloaded automatically to your computer. If this is not the case, you can also download it here directly: <a href="!url">Download link</a>', {'!url': '/download/' + download_id + '/download'}));
        Drupal.HpcDownloads.startDownload(download_id);
      }
      else if (data.status == 'pending') {
        // Try again a bit later.
        setTimeout(function () {
          Drupal.HpcDownloads.checkDownloadStatus(download_id);
        }, 2000);
      }
      else {
        // Show an error message.
        Drupal.HpcDownloads.setModalContent(Drupal.t('There was a problem creating your requested download.<br />Please try again. If the problem persists please reach out to our team.'));
        Drupal.HpcDownloads.setModalFooter(Drupal.t('Download error'));
        Drupal.HpcDownloads.download_status = 'error';
      }
    })
    .fail(function (jqXHR, textStatus, errorThrown) {
      // Show an error message.
      Drupal.HpcDownloads.setModalContent(Drupal.t('There was a problem creating your requested download.<br />Please try again. If the problem persists please reach out to our team.'));
      Drupal.HpcDownloads.setModalFooter(Drupal.t('Download error'));
      Drupal.HpcDownloads.download_status = 'error';
    });
  }

  Drupal.HpcDownloads.setModalContent = function (message) {
    $('.hpc-download-dialog-wrapper .hpc-download-dialog-content').html('<p>' + message + '</p>');
  }
  Drupal.HpcDownloads.setModalFooter = function (message) {
    var footer = $('.hpc-download-dialog-wrapper .hpc-download-dialog-footer');
    if (message && footer.hasClass('hidden')) {
      $(footer).removeClass('hidden');
    }
    $(footer).html(message);
  }

})(jQuery, Drupal, drupalSettings);
