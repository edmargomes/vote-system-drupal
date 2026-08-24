<?php

/**
 * @file
 * Drupal site configuration — reads all secrets from environment variables.
 *
 * Copy .env.example to .env and adjust values for your local environment.
 * Never hard-code credentials in this file.
 */

use Dotenv\Dotenv;

// ---------------------------------------------------------------------------
// Load .env — works in any environment without relying on the web server
// ---------------------------------------------------------------------------
$dotenv_path = dirname(DRUPAL_ROOT);
if (file_exists($dotenv_path . '/.env')) {
  $dotenv = Dotenv::createImmutable($dotenv_path);
  $dotenv->safeLoad();
}

// ---------------------------------------------------------------------------
// Database
// ---------------------------------------------------------------------------
$databases['default']['default'] = [
  'driver' => $_ENV['DB_DRIVER'] ?? 'mysql',
  'database' => $_ENV['DB_NAME'] ?? 'drupal11',
  'username' => $_ENV['DB_USER'] ?? 'drupal11',
  'password' => $_ENV['DB_PASSWORD'] ?? 'drupal11',
  'host' => $_ENV['DB_HOST'] ?? 'database',
  'port' => $_ENV['DB_PORT'] ?? '3306',
  'prefix' => '',
  'collation' => 'utf8mb4_general_ci',
  'namespace' => 'Drupal\\mysql\\Driver\\Database\\mysql',
  'autoload' => 'core/modules/mysql/src/Driver/Database/mysql/',
];

// ---------------------------------------------------------------------------
// Security
// ---------------------------------------------------------------------------
$settings['hash_salt'] = $_ENV['DRUPAL_HASH_SALT'] ?? 'temporary-insecure-salt-change-me';

// SITE_URL accepts one or more comma-separated hostnames.
// Falls back to patterns that cover Lando and localhost when not set.
if (!empty($_ENV['SITE_URL'])) {
  $settings['trusted_host_patterns'] = array_map(
    fn($host) => '^' . preg_quote(trim($host)) . '$',
    explode(',', $_ENV['SITE_URL'])
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
$settings['file_public_path'] = 'sites/default/files';
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
