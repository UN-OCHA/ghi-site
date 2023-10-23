(function ($, Drupal, drupalSettings) {

  Drupal.hpc_downloads_batch = {};

  Drupal.hpc_downloads_batch.pb = null;

  /**
   * Attaches the batch behavior to progress bars.
   */
  Drupal.behaviors.hpc_downloads_batch = {
    attach: function (context, settings) {

      $(window).off('dialog:beforeclose.hpc_downloads').on('dialog:beforeclose.hpc_downloads', function (dialog, $element) {
        // Download window has been closed, make sure that the batch process
        // also stops.
        if (!Drupal.hpc_downloads_batch.pb) {
          // Already done, nothing to do.
          return;
        }
        Drupal.hpc_downloads_batch.pb.stopMonitoring();
        $.ajax({
          url: '/download/' + Drupal.hpc_active_download + '/abort',
          type: "get"
        });
        Drupal.hpc_active_download = null;
      });

      once('batch', $('.hpc-download-batch-progress-bar', context)).forEach(function () {

        var holder = $(this);
        var wrapper = $(holder).parents('.hpc-download-dialog-wrapper');

        // Success: redirect to the summary.
        var updateCallback = function (progress, status, pb) {

          if (progress == 100) {

            // Stop monitoring the progress.
            pb.stopMonitoring();

            // Prevent recursion.
            pb.updateCallback = null;

            $.ajax({
              type: 'GET',
              url: settings.batch.uri + '&op=finished',
              data: '',
              error: function (xmlhttp) {
                $(holder).parent().html(Drupal.t('Error: Could not generate data file'));
              },
              complete: function () {
                $(wrapper).find('input[type="submit"]').show();
                $(holder).hide();
                $(wrapper).find('.progress-wrapper').html('');
                $(wrapper).find('.batch-processed').removeClass('batch-processed');
              }
            });
            Drupal.hpc_downloads_batch.pb = null;
          }
        };

        var errorCallback = function (pb) {
          holder.prepend($('<p class="error"></p>').html(Drupal.t('There was a problem creating your requested download.<br />Please try again. If the problem persists please reach out to our team.')));
          $('#wait').hide();
        };

        $.extend(Drupal.ProgressBar.prototype, {
          setProgress: function (percentage, message, label) {
            $(this.element).find('div.progress__label').remove();

            if (percentage >= 0 && percentage <= 100) {
              $(this.element).find('div.progress__bar').css('width', percentage + '%');
              $(this.element).find('div.progress__percentage').html(percentage + '%');
            }
            if (percentage < 100) {
              // Processing the download.
              $('div.progress__description', this.element).html(label ? label : message);
            }
            else {
              // Finished, waiting for the file to start downloading.
              $('div.progress__description', this.element).html(Drupal.t('Preparing the download file ...'));
            }

            if (this.updateCallback) {
              this.updateCallback(percentage, label ? label : message, this);
            }
          }
        });

        var progress = new Drupal.ProgressBar('updateprogress', updateCallback, 'POST', errorCallback);
        progress.setProgress(-1, Drupal.t('Preparing download ...'), '');
        $(holder).append(progress.element);
        progress.startMonitoring(settings.batch.uri + '&op=do', 10);
        $(holder).show();

        Drupal.hpc_downloads_batch.pb = progress;
      });
    }
  };

  })(jQuery, Drupal, drupalSettings);
