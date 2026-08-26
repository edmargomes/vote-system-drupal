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
   * Anonymous user accessing /vote is redirected to /user/login.
   *
   * The route now requires _user_is_logged_in: TRUE. Drupal's access layer
   * redirects anonymous users to the login page with a destination parameter.
   */
  public function testAnonymousUserIsRedirectedToLogin(): void {
    $this->drupalGet('/vote');
    $this->assertSession()->addressMatches('#/user/login#');
  }

  /**
   * Authenticated user sees open question titles on the list page.
   */
  public function testAuthenticatedUserSeesOpenQuestions(): void {
    $user = $this->drupalCreateUser(['vote']);
    $this->drupalLogin($user);

    $question = $this->container->get('entity_type.manager')
      ->getStorage('voting_question')
      ->create([
        'title' => 'Open Question Visible',
        'status' => TRUE,
        'show_results' => FALSE,
      ]);
    $question->save();

    $this->drupalGet('/vote');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('Open Question Visible');
  }

  /**
   * Expired question (closes_at in the past) does not appear in the list.
   */
  public function testExpiredQuestionDoesNotAppearInList(): void {
    $user = $this->drupalCreateUser(['vote']);
    $this->drupalLogin($user);

    $storage = $this->container->get('entity_type.manager')
      ->getStorage('voting_question');

    $storage->create([
      'title' => 'Already Expired Question',
      'status' => TRUE,
      'closes_at' => time() - 3600,
    ])->save();

    $this->drupalGet('/vote');
    $this->assertSession()->pageTextNotContains('Already Expired Question');
  }

  /**
   * Question the user has voted on shows a "Voted" badge.
   */
  public function testVotedQuestionShowsVotedBadge(): void {
    $user = $this->drupalCreateUser(['vote']);

    $entityTypeManager = $this->container->get('entity_type.manager');

    $question = $entityTypeManager->getStorage('voting_question')->create([
      'title' => 'Voted Badge Test',
      'status' => TRUE,
    ]);
    $question->save();

    $option = $entityTypeManager->getStorage('voting_option')->create([
      'label' => 'Choice A',
      'question_id' => $question->id(),
    ]);
    $option->save();

    // Insert vote directly to avoid going through the full form flow.
    $this->container->get('database')->insert('voting_vote')
      ->fields([
        'uuid' => \Drupal::service('uuid')->generate(),
        'uid' => $user->id(),
        'question_id' => $question->id(),
        'option_id' => $option->id(),
        'source' => 'cms',
        'created' => time(),
      ])
      ->execute();

    $this->drupalLogin($user);
    $this->drupalGet('/vote');
    $this->assertSession()->pageTextContains('Voted');
  }

  /**
   * Question the user has not voted on does not show the "Voted" badge.
   */
  public function testUnvotedQuestionDoesNotShowVotedBadge(): void {
    $user = $this->drupalCreateUser(['vote']);
    $this->drupalLogin($user);

    $this->container->get('entity_type.manager')
      ->getStorage('voting_question')
      ->create([
        'title' => 'Unvoted Question',
        'status' => TRUE,
      ])
      ->save();

    $this->drupalGet('/vote');
    $this->assertSession()->pageTextNotContains('Voted');
  }

  /**
   * Active question title appears as a link on the list page.
   */
  public function testActiveQuestionAppearsInList(): void {
    $user = $this->drupalCreateUser(['vote']);
    $this->drupalLogin($user);

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
   * Authenticated user does not see an anonymous CTA banner.
   */
  public function testAuthenticatedUserDoesNotSeeCta(): void {
    $user = $this->drupalCreateUser(['vote']);
    $this->drupalLogin($user);

    $this->drupalGet('/vote');
    $this->assertSession()->pageTextNotContains('Log in or create an account');
  }

  /**
   * Voting disabled — authenticated user gets HTTP 200 on the list page.
   */
  public function testVotingDisabledPageStillLoads(): void {
    $user = $this->drupalCreateUser(['vote']);
    $this->drupalLogin($user);
    $this->config('vs_core.settings')->set('voting_enabled', FALSE)->save();

    $this->drupalGet('/vote');
    $this->assertSession()->statusCodeEquals(200);
  }

  /**
   * Voting disabled — page shows the "currently unavailable" notice.
   */
  public function testVotingDisabledShowsNotice(): void {
    $user = $this->drupalCreateUser(['vote']);
    $this->drupalLogin($user);
    $this->config('vs_core.settings')->set('voting_enabled', FALSE)->save();

    $this->drupalGet('/vote');
    $this->assertSession()->pageTextContains('currently unavailable');
  }

  /**
   * Voting disabled — question titles are still listed for authenticated users.
   */
  public function testVotingDisabledStillListsQuestions(): void {
    $user = $this->drupalCreateUser(['vote']);
    $this->drupalLogin($user);
    $this->config('vs_core.settings')->set('voting_enabled', FALSE)->save();

    $this->container->get('entity_type.manager')
      ->getStorage('voting_question')
      ->create([
        'title' => 'Still visible when disabled?',
        'status' => TRUE,
        'show_results' => FALSE,
      ])
      ->save();

    $this->drupalGet('/vote');
    $this->assertSession()->pageTextContains('Still visible when disabled?');
  }

  /**
   * Authenticated user sees active question title on the list page.
   */
  public function testAuthenticatedUserSeesQuestion(): void {
    $user = $this->drupalCreateUser(['vote']);
    $this->drupalLogin($user);

    $this->container->get('entity_type.manager')
      ->getStorage('voting_question')
      ->create([
        'title' => 'Favourite season?',
        'status' => TRUE,
        'show_results' => FALSE,
      ])
      ->save();

    $this->drupalGet('/vote');
    $this->assertSession()->pageTextContains('Favourite season?');
  }

  /**
   * Authenticated user sees empty-state message when no open questions exist.
   */
  public function testAuthenticatedUserSeesNoQuestionsMessage(): void {
    $user = $this->drupalCreateUser(['vote']);
    $this->drupalLogin($user);

    $this->drupalGet('/vote');
    $this->assertSession()->pageTextContains('no active questions');
  }

}
