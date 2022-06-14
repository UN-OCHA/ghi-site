/**
* @file
*/

(function ($, Drupal) {
Drupal.AjaxCommands.prototype.startDownloadObserver = function (ajax, response, status) {
  if (Drupal.hpc_active_download && Drupal.hpc_active_download == response.download_id) {
    return;
  }
  Drupal.hpc_download_status = 'pending';
  setTimeout(function () {
    Drupal.hpc_active_download = response.download_id;
    Drupal.hpc_check_download_status(response.download_id);
  }, 2000);
}
Drupal.AjaxCommands.prototype.setDownLoadStatus = function (ajax, response, status) {
  Drupal.hpc_download_status = response.status;
}

})(jQuery, Drupal);
