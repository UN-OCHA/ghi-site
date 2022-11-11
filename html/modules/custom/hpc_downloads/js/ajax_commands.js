/**
* @file
*/

(function ($, Drupal) {
Drupal.AjaxCommands.prototype.startDownloadObserver = function (ajax, response, status) {
  if (Drupal.HpcDownloads.active_download && Drupal.HpcDownloads.active_download == response.download_id) {
    return;
  }
  Drupal.HpcDownloads.download_status = 'pending';
  setTimeout(function () {
    Drupal.HpcDownloads.active_download = response.download_id;
    Drupal.HpcDownloads.checkDownloadStatus(response.download_id);
  }, 2000);
}
Drupal.AjaxCommands.prototype.setDownLoadStatus = function (ajax, response, status) {
  Drupal.HpcDownloads.download_status = response.status;
}

})(jQuery, Drupal);
