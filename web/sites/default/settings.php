<?php

/**
 * @file
 * Drupal site configuration — reads all secrets from environment variables.
 *
 * Copy .env.example to .env and adjust values for your local environment.
 * Never hard-code credentials in this file.
 */

// ---------------------------------------------------------------------------
// Database
// ---------------------------------------------------------------------------
$databases['default']['default'] = [
  'driver'    => getenv('DB_DRIVER') ?: 'mysql',
  'database'  => getenv('DB_NAME') ?: 'drupal11',
  'username'  => getenv('DB_USER') ?: 'drupal11',
  'password'  => getenv('DB_PASSWORD') ?: 'drupal11',
  'host'      => getenv('DB_HOST') ?: 'database',
  'port'      => getenv('DB_PORT') ?: '3306',
  'prefix'    => '',
  'collation' => 'utf8mb4_general_ci',
  'namespace' => 'Drupal\\mysql\\Driver\\Database\\mysql',
  'autoload'  => 'core/modules/mysql/src/Driver/Database/mysql/',
];

// ---------------------------------------------------------------------------
// Security
// ---------------------------------------------------------------------------
$settings['hash_salt'] = getenv('DRUPAL_HASH_SALT') ?: 'temporary-insecure-salt-change-me';

// SITE_URL accepts one or more comma-separated hostnames.
// Falls back to patterns that cover Lando and localhost for local dev.
$site_url = getenv('SITE_URL');
if ($site_url) {
  $settings['trusted_host_patterns'] = array_map(
    fn($host) => '^' . preg_quote(trim($host)) . '$',
    explode(',', $site_url)
  );
}
else {
  $settings['trusted_host_patterns'] = [
    '^localhost$',
    '^.+\.lndo\.site$',
  ];
}

// ---------------------------------------------------------------------------
// File paths
// ---------------------------------------------------------------------------
$settings['file_public_path']  = 'sites/default/files';
$settings['file_private_path'] = '';

// ---------------------------------------------------------------------------
// Config sync directory
// ---------------------------------------------------------------------------
$settings['config_sync_directory'] = '../config/sync';

// ---------------------------------------------------------------------------
// Local overrides (not versioned)
// ---------------------------------------------------------------------------
if (file_exists($app_root . '/' . $site_path . '/settings.local.php')) {
  include $app_root . '/' . $site_path . '/settings.local.php';
}
