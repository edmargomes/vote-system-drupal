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

$settings['trusted_host_patterns'] = [
  '^' . preg_quote(getenv('SITE_URL') ?: 'localhost') . '$',
];

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
