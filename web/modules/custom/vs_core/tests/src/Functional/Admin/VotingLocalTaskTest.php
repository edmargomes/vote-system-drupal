<?php

declare(strict_types=1);

namespace Drupal\Tests\vs_core\Functional\Admin;

use Drupal\Tests\BrowserTestBase;

/**
 * Verifies the "Voting Questions" local task tab on /admin/content.
 *
 * @group vs_core
 * @group admin
 */
class VotingLocalTaskTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['vs_core', 'node'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Voting Questions tab appears on /admin/content for privileged users.
   *
   * The local task tab is only rendered when the user has access to the
   * target route, which requires the 'administer voting' permission.
   */
  public function testVotingQuestionsTabAppearsOnAdminContent(): void {
    $admin = $this->drupalCreateUser([
      'administer voting',
      'access administration pages',
      'administer content types',
    ]);
    $this->drupalLogin($admin);

    $this->drupalGet('/admin/content');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->linkExists('Voting Questions');
  }

  /**
   * Voting Questions tab is not visible to users without the permission.
   *
   * Drupal hides local task tabs when the current user lacks access to the
   * tab's route, so a user without 'administer voting' must not see the tab.
   */
  public function testVotingQuestionsTabHiddenWithoutPermission(): void {
    // 'access administration pages' alone does not grant administer voting.
    $user = $this->drupalCreateUser([
      'access administration pages',
      'administer content types',
    ]);
    $this->drupalLogin($user);

    $this->drupalGet('/admin/content');
    $this->assertSession()->linkNotExists('Voting Questions');
  }

}
