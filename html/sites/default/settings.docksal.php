<?php

error_reporting(E_ALL);
ini_set('display_errors', TRUE);
ini_set('display_startup_errors', TRUE);

$config['system.logging']['error_level'] = 'verbose';
$settings['skip_permissions_hardening'] = TRUE;

// Docksal DB connection settings.
$databases['default']['default'] = [
  'database' => getenv('MYSQL_DATABASE'),
  'username' => getenv('MYSQL_USER'),
  'password' => getenv('MYSQL_PASSWORD'),
  'host' => getenv('MYSQL_HOST'),
  'driver' => 'mysql',
  'namespace' => 'Drupal\\Core\\Database\\Driver\\mysql',
  'port' => 3306,
  'init_commands' => [
    'isolation_level' => 'SET SESSION TRANSACTION ISOLATION LEVEL READ COMMITTED',
  ],
];

$config['hpc_api.settings'] = [
  'url' => 'https://api.hpc.tools',
  'auth_username' => getenv('HPC_API_USER'),
  'auth_password' => getenv('HPC_API_PASS'),
  'api_key' => getenv('HPC_API_KEY'),
  'timeout' => 300,
];

$config['ghi_content.remote_sources'] = [
  'hpc_content_module' => [
    'base_url' => 'https://content.hpc.tools',
    'endpoint' => 'ncms',
    'access_key' => getenv('CM_KEY'),
  ],
];

// Setup HID.
$config['social_auth_hid.settings']['client_id'] = getenv('HID_CLIENT_ID');
$config['social_auth_hid.settings']['client_secret'] = getenv('HID_CLIENT_SECRET');
$config['social_auth_hid.settings']['base_url'] = 'https://auth.humanitarian.id';

// Solr config.
$config['search_api.server.solr_server']['backend_config']['connector'] = 'standard';
$config['search_api.server.solr_server']['backend_config']['connector_config']['host'] = 'solr';
$config['search_api.server.solr_server']['backend_config']['connector_config']['port'] = 8983;
$config['search_api.server.solr_server']['backend_config']['connector_config']['core'] = 'ghi';

$settings['social_auth.settings']['redirect_user_form'] = true;

$config['stage_file_proxy.settings']['origin'] = 'https://humanitarianaction.info/';
$config['stage_file_proxy.settings']['hotlink'] = FALSE;

$settings['config_sync_directory'] =  '/var/www/config';
$settings['hash_salt'] = 'ghi-test-site-salt';

// Use the dev config.
$config['config_split.config_split.config_dev']['status'] = TRUE;

$config['system.performance']['css']['preprocess'] = FALSE;
$config['system.performance']['js']['preprocess'] = FALSE;

// Memcache.
$settings['memcache']['servers'] = ['memcached:11211' => 'default'];
$settings['memcache']['bins'] = ['default' => 'default'];
$settings['memcache']['key_prefix'] = '';
$settings['cache']['default'] = 'cache.backend.memcache';

// Reverse proxy configuration (Docksal vhost-proxy)
if (PHP_SAPI !== 'cli') {
  $settings['reverse_proxy'] = TRUE;
  $settings['reverse_proxy_addresses'] = array($_SERVER['REMOTE_ADDR']);
  // HTTPS behind reverse-proxy
  if (
    isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] == 'https' &&
    !empty($settings['reverse_proxy']) && in_array($_SERVER['REMOTE_ADDR'], $settings['reverse_proxy_addresses'])
  ) {
    $_SERVER['HTTPS'] = 'on';
    // This is hardcoded because there is no header specifying the original port.
    $_SERVER['SERVER_PORT'] = 443;
  }
}

$config['ocha_snap.settings']['url'] = getenv('VIRTUAL_HOST') . ':8442/snap';

$settings['trusted_host_patterns'] = array(
  '^' . addslashes(getenv('VIRTUAL_HOST')) . '$',
  '^browser\.' . addslashes(getenv('VIRTUAL_HOST')) . '$',
);

// Disable seckit locally until the header size exceed problem can be adressed.
// This doesn't seem to be an issue on the dev environments for some reason
// CONFIRM!.
$config['seckit.settings']['seckit_xss']['csp']['checkbox'] = FALSE;

ini_set('session.cookie_samesite', 'lax');

$settings['config_exclude_modules'] = [
  'dblog',
  'debug_tools',
  'views_ui',
  'upgrade_status',
];