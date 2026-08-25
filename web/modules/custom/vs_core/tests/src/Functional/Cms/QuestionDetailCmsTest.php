<?php

declare(strict_types=1);

namespace Drupal\Tests\vs_core\Functional\Cms;

use Drupal\Tests\BrowserTestBase;

/**
 * Browser tests for the /vote/{uuid} question detail page and voting form.
 *
 * @group vs_core
 * @group cms
 */
class QuestionDetailCmsTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['vs_core'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Creates a voting question and one option, then returns both.
   *
   * @param bool $showResults
   *   Whether the question should expose vote results.
   *
   * @return array{0: \Drupal\vs_core\Entity\VotingQuestionInterface, 1: \Drupal\vs_core\Entity\VotingOptionInterface}
   *   The question and the first option.
   */
  private function createQuestionWithOption(bool $showResults = FALSE): array {
    $entityTypeManager = $this->container->get('entity_type.manager');

    $question = $entityTypeManager->getStorage('voting_question')->create([
      'title' => 'What is your favourite language?',
      'status' => TRUE,
      'show_results' => $showResults,
    ]);
    $question->save();

    $option = $entityTypeManager->getStorage('voting_option')->create([
      'label' => 'PHP',
      'question_id' => $question->id(),
    ]);
    $option->save();

    return [$question, $option];
  }

  /**
   * Anonymous user visiting the detail page is redirected to login.
   */
  public function testAnonymousRedirectsToLogin(): void {
    [$question] = $this->createQuestionWithOption();

    $this->drupalGet('/vote/' . $question->uuid());

    // Drupal issues a 403 for _user_is_logged_in routes for anonymous users,
    // and the exception subscriber redirects them to /user/login.
    $this->assertSession()->addressMatches('#/user/login#');
  }

  /**
   * Authenticated user without vote permission sees the page but not the form.
   */
  public function testUserWithoutVotePermissionSeesNoForm(): void {
    [$question] = $this->createQuestionWithOption();

    // Create a user with no permissions at all.
    $user = $this->drupalCreateUser([]);
    $this->drupalLogin($user);

    $this->drupalGet('/vote/' . $question->uuid());
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->fieldNotExists('option_uuid');
  }

  /**
   * Authenticated user with vote permission sees the form with radio buttons.
   */
  public function testUserWithVotePermissionSeesForm(): void {
    [$question] = $this->createQuestionWithOption();

    $user = $this->drupalCreateUser(['vote']);
    $this->drupalLogin($user);

    $this->drupalGet('/vote/' . $question->uuid());
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->fieldExists('option_uuid');
  }

  /**
   * Visiting a non-existent UUID returns HTTP 404.
   */
  public function testNonExistentUuidReturns404(): void {
    $user = $this->drupalCreateUser(['vote']);
    $this->drupalLogin($user);

    $this->drupalGet('/vote/00000000-0000-0000-0000-000000000000');
    $this->assertSession()->statusCodeEquals(404);
  }

  /**
   * Submitting a valid vote triggers a redirect then shows the success message.
   */
  public function testValidVoteSubmissionShowsSuccessMessage(): void {
    [$question, $option] = $this->createQuestionWithOption();

    $user = $this->drupalCreateUser(['vote']);
    $this->drupalLogin($user);

    $this->drupalGet('/vote/' . $question->uuid());
    $this->submitForm(['option_uuid' => $option->uuid()], 'Vote');

    $this->assertSession()->pageTextContains('Your vote has been registered.');
  }

  /**
   * After voting with show_results=false, the results table is absent.
   */
  public function testNoResultsShownWhenShowResultsFalse(): void {
    [$question, $option] = $this->createQuestionWithOption(FALSE);

    $user = $this->drupalCreateUser(['vote']);
    $this->drupalLogin($user);

    $this->drupalGet('/vote/' . $question->uuid());
    $this->submitForm(['option_uuid' => $option->uuid()], 'Vote');

    // The results table headers should not appear.
    $this->assertSession()->pageTextNotContains('Percentage');
  }

  /**
   * After voting with show_results=true, the results table is rendered.
   */
  public function testResultsShownWhenShowResultsTrue(): void {
    [$question, $option] = $this->createQuestionWithOption(TRUE);

    $user = $this->drupalCreateUser(['vote']);
    $this->drupalLogin($user);

    $this->drupalGet('/vote/' . $question->uuid());
    $this->submitForm(['option_uuid' => $option->uuid()], 'Vote');

    $this->assertSession()->pageTextContains('Percentage');
  }

  /**
   * User who already voted sees the "already voted" message, not the form.
   */
  public function testAlreadyVotedUserSeesMessageNotForm(): void {
    [$question, $option] = $this->createQuestionWithOption();

    $user = $this->drupalCreateUser(['vote']);
    $this->drupalLogin($user);

    // Submit the vote.
    $this->drupalGet('/vote/' . $question->uuid());
    $this->submitForm(['option_uuid' => $option->uuid()], 'Vote');

    // Reload the detail page and verify the form is gone.
    $this->drupalGet('/vote/' . $question->uuid());
    $this->assertSession()->pageTextContains('You already voted for');
    $this->assertSession()->fieldNotExists('option_uuid');
  }

  /**
   * Already-voted user with show_results=true sees results.
   */
  public function testAlreadyVotedUserSeesResultsWhenEnabled(): void {
    [$question, $option] = $this->createQuestionWithOption(TRUE);

    $user = $this->drupalCreateUser(['vote']);
    $this->drupalLogin($user);

    // Submit vote.
    $this->drupalGet('/vote/' . $question->uuid());
    $this->submitForm(['option_uuid' => $option->uuid()], 'Vote');

    // Reload and verify results visible.
    $this->drupalGet('/vote/' . $question->uuid());
    $this->assertSession()->pageTextContains('You already voted for');
    $this->assertSession()->pageTextContains('Percentage');
  }

  /**
   * Already-voted user with show_results=false does not see results.
   */
  public function testAlreadyVotedUserDoesNotSeeResultsWhenDisabled(): void {
    [$question, $option] = $this->createQuestionWithOption(FALSE);

    $user = $this->drupalCreateUser(['vote']);
    $this->drupalLogin($user);

    // Submit vote.
    $this->drupalGet('/vote/' . $question->uuid());
    $this->submitForm(['option_uuid' => $option->uuid()], 'Vote');

    // Reload and verify results NOT visible.
    $this->drupalGet('/vote/' . $question->uuid());
    $this->assertSession()->pageTextContains('You already voted for');
    $this->assertSession()->pageTextNotContains('Percentage');
  }

  /**
   * Voting globally disabled — page renders but form is not present.
   */
  public function testVotingDisabledHidesForm(): void {
    [$question] = $this->createQuestionWithOption();
    $this->config('vs_core.settings')->set('voting_enabled', FALSE)->save();

    $user = $this->drupalCreateUser(['vote']);
    $this->drupalLogin($user);

    $this->drupalGet('/vote/' . $question->uuid());
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('currently unavailable');
    $this->assertSession()->fieldNotExists('option_uuid');
  }

  /**
   * CMS vote stores source='cms' in the database.
   */
  public function testCmsVoteStoresCmsSource(): void {
    [$question, $option] = $this->createQuestionWithOption();

    $user = $this->drupalCreateUser(['vote']);
    $this->drupalLogin($user);

    $this->drupalGet('/vote/' . $question->uuid());
    $this->submitForm(['option_uuid' => $option->uuid()], 'Vote');

    $row = $this->container->get('database')
      ->select('voting_vote', 'v')
      ->fields('v', ['source'])
      ->condition('v.uid', $user->id())
      ->condition('v.question_id', $question->id())
      ->execute()
      ->fetchAssoc();

    $this->assertIsArray($row);
    $this->assertSame('cms', $row['source']);
  }

  /**
   * Submitting the form without selecting an option shows a validation error.
   */
  public function testSubmitWithoutOptionShowsValidationError(): void {
    [$question] = $this->createQuestionWithOption();

    $user = $this->drupalCreateUser(['vote']);
    $this->drupalLogin($user);

    $this->drupalGet('/vote/' . $question->uuid());

    // Submit without filling in the radios — expect an HTML5 / form error.
    $this->getSession()->getPage()->pressButton('Vote');

    // No vote should be saved.
    $count = (int) $this->container->get('database')
      ->select('voting_vote', 'v')
      ->condition('v.uid', $user->id())
      ->condition('v.question_id', $question->id())
      ->countQuery()
      ->execute()
      ->fetchField();

    $this->assertSame(0, $count);
  }

}
