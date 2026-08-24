<?php

declare(strict_types=1);

namespace Drupal\Tests\vs_core\Functional\Admin;

use Drupal\Tests\BrowserTestBase;

/**
 * Verifies the Voting System Settings admin form.
 *
 * @group vs_core
 * @group admin
 */
class VotingSettingsFormTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['vs_core'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Anonymous user cannot access the settings form.
   */
  public function testAnonymousCannotAccessSettingsForm(): void {
    $this->drupalGet('/admin/config/content/vs-core');
    $this->assertSession()->statusCodeEquals(403);
  }

  /**
   * Unprivileged user cannot access the settings form.
   */
  public function testUnprivilegedUserCannotAccessSettingsForm(): void {
    $user = $this->drupalCreateUser(['vote']);
    $this->drupalLogin($user);

    $this->drupalGet('/admin/config/content/vs-core');
    $this->assertSession()->statusCodeEquals(403);
  }

  /**
   * Admin user can access and submit the settings form.
   */
  public function testAdminCanViewAndSubmitSettingsForm(): void {
    $admin = $this->drupalCreateUser(['administer voting']);
    $this->drupalLogin($admin);

    $this->drupalGet('/admin/config/content/vs-core');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->fieldExists('voting_enabled');
  }

  /**
   * Saving the form with voting disabled persists the change.
   */
  public function testSavingDisablesVoting(): void {
    $admin = $this->drupalCreateUser(['administer voting']);
    $this->drupalLogin($admin);

    $this->drupalGet('/admin/config/content/vs-core');
    $this->submitForm(['voting_enabled' => FALSE], 'Save configuration');

    $config = $this->config('vs_core.settings');
    $this->assertFalse((bool) $config->get('voting_enabled'));
  }

  /**
   * Saving the form with voting enabled persists the change.
   */
  public function testSavingEnablesVoting(): void {
    // First disable it.
    $this->config('vs_core.settings')->set('voting_enabled', FALSE)->save();

    $admin = $this->drupalCreateUser(['administer voting']);
    $this->drupalLogin($admin);

    $this->drupalGet('/admin/config/content/vs-core');
    $this->submitForm(['voting_enabled' => TRUE], 'Save configuration');

    $config = $this->config('vs_core.settings');
    $this->assertTrue((bool) $config->get('voting_enabled'));
  }

}
