<?php

declare(strict_types=1);

namespace Drupal\Tests\vs_core\Functional\Cms;

use Drupal\Tests\BrowserTestBase;

/**
 * Browser tests for the /vote question list CMS page.
 *
 * @group vs_core
 * @group cms
 */
class QuestionListCmsTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['vs_core'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Anonymous user can load /vote and receives HTTP 200.
   */
  public function testAnonymousUserCanLoadListPage(): void {
    $this->drupalGet('/vote');
    $this->assertSession()->statusCodeEquals(200);
  }

  /**
   * Anonymous user sees the login/register CTA banner on the list page.
   */
  public function testAnonymousUserSeesCtaBanner(): void {
    $this->drupalGet('/vote');
    $this->assertSession()->pageTextContains('Log in');
    $this->assertSession()->pageTextContains('create an account');
  }

  /**
   * Anonymous user sees the "no active questions" message when none exist.
   */
  public function testAnonymousUserSeesNoQuestionsMessage(): void {
    $this->drupalGet('/vote');
    $this->assertSession()->pageTextContains('no active questions');
  }

  /**
   * Active question title appears as a link on the list page.
   */
  public function testActiveQuestionAppearsInList(): void {
    $question = $this->container->get('entity_type.manager')
      ->getStorage('voting_question')
      ->create([
        'title' => 'Best programming language?',
        'status' => TRUE,
        'show_results' => FALSE,
      ]);
    $question->save();

    $this->drupalGet('/vote');
    $this->assertSession()->pageTextContains('Best programming language?');
    $this->assertSession()->linkByHrefExists('/vote/' . $question->uuid());
  }

  /**
   * Voting disabled — anonymous visitor still gets HTTP 200 on the list page.
   */
  public function testVotingDisabledPageStillLoads(): void {
    $this->config('vs_core.settings')->set('voting_enabled', FALSE)->save();

    $this->drupalGet('/vote');
    $this->assertSession()->statusCodeEquals(200);
  }

  /**
   * Voting disabled — page shows the "currently unavailable" notice.
   */
  public function testVotingDisabledShowsNotice(): void {
    $this->config('vs_core.settings')->set('voting_enabled', FALSE)->save();

    $this->drupalGet('/vote');
    $this->assertSession()->pageTextContains('currently unavailable');
  }

  /**
   * Voting disabled — question titles are still listed.
   */
  public function testVotingDisabledStillListsQuestions(): void {
    $this->config('vs_core.settings')->set('voting_enabled', FALSE)->save();

    $question = $this->container->get('entity_type.manager')
      ->getStorage('voting_question')
      ->create([
        'title' => 'Still visible when disabled?',
        'status' => TRUE,
        'show_results' => FALSE,
      ]);
    $question->save();

    $this->drupalGet('/vote');
    $this->assertSession()->pageTextContains('Still visible when disabled?');
  }

  /**
   * Authenticated user does not see the anonymous CTA banner.
   */
  public function testAuthenticatedUserDoesNotSeeCta(): void {
    $user = $this->drupalCreateUser(['vote']);
    $this->drupalLogin($user);

    $this->drupalGet('/vote');
    $this->assertSession()->pageTextNotContains('Log in or create an account');
  }

  /**
   * Authenticated user sees active question title on the list page.
   */
  public function testAuthenticatedUserSeesQuestion(): void {
    $user = $this->drupalCreateUser(['vote']);
    $this->drupalLogin($user);

    $question = $this->container->get('entity_type.manager')
      ->getStorage('voting_question')
      ->create([
        'title' => 'Favourite season?',
        'status' => TRUE,
        'show_results' => FALSE,
      ]);
    $question->save();

    $this->drupalGet('/vote');
    $this->assertSession()->pageTextContains('Favourite season?');
  }

}
