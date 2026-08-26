<?php

/**
 * @file
 * Creates loadtest_user_1 through loadtest_user_2000 for k6 load testing.
 *
 * Run with:
 * lando drush --php-options="-d memory_limit=512M" \
 *   php-script scripts/create-loadtest-users.php.
 */

use Drupal\user\Entity\User;

$total   = 2000;
$created = 0;
$skipped = 0;

for ($i = 1; $i <= $total; $i++) {
  $name = "loadtest_user_{$i}";

  $exists = \Drupal::database()->query(
    "SELECT 1 FROM {users_field_data} WHERE name = :name",
    [':name' => $name]
  )->fetchField();
  if ($exists) {
    $skipped++;
    continue;
  }

  $attempts = 0;
  while ($attempts < 5) {
    try {
      $user = User::create([
        'name'   => $name,
        'mail'   => "loadtest_{$i}@test.local",
        'pass'   => 'loadtest_pass',
        'status' => 1,
        'roles'  => ['authenticated'],
      ]);
      $user->save();
      $created++;
      break;
    }
    catch (\Exception $e) {
      // Retry on MySQL deadlock (SQLSTATE 40001).
      $isDeadlock = str_contains($e->getMessage(), '1213')
        || str_contains($e->getMessage(), 'Deadlock');
      if ($isDeadlock) {
        $attempts++;
        usleep(100000 * $attempts);
        continue;
      }
      // Skip if entry already exists from a previous partial run.
      $isDuplicate = str_contains($e->getMessage(), '1062')
        || str_contains($e->getMessage(), 'Duplicate entry');
      if ($isDuplicate) {
        $skipped++;
        break;
      }
      throw $e;
    }
  }

  if ($created % 100 === 0 && $created > 0) {
    echo "Progress: {$created} created so far (i={$i})...\n";
  }
}

echo "Done. Created: {$created}, Skipped (existed): {$skipped}\n";
