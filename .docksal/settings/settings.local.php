<?php

// If everything is setup correctly, all we need to do is to include the
// docksal specific settings that will get all necessary credentials from
// ./docksal/docksal-local.env
if (file_exists($app_root . '/' . $site_path . '/settings.docksal.php')) {
  include $app_root . '/' . $site_path . '/settings.docksal.php';
}
