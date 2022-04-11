/**
 * @file
 * Attach destination to login link.
 */
(function () {
  'use strict';

  var loginLink = document.querySelector('a[href="/user/login/hid"]');
  if (loginLink) {
    loginLink.href += '?destination=' + location.pathname + encodeURIComponent(location.search) + location.hash;
  }
})();
