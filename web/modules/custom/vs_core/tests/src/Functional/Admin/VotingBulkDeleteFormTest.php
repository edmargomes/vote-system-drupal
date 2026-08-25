<?php

declare(strict_types=1);

namespace Drupal\Tests\vs_core\Functional\Admin;

use Drupal\Tests\BrowserTestBase;
use Drupal\user\UserInterface;
use Drupal\vs_core\Entity\VotingOption;
use Drupal\vs_core\Entity\VotingQuestion;
use Drupal\vs_core\Entity\VotingVote;

/**
 * Verifies the vote management and bulk-delete form.
 *
 * @group vs_core
 * @group admin
 */
class VotingBulkDeleteFormTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['vs_core'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Creates a voting_question entity and returns it.
   */
  private function createQuestion(string $title = 'Test Question'): VotingQuestion {
    $storage = \Drupal::entityTypeManager()->getStorage('voting_question');
    /** @var \Drupal\vs_core\Entity\VotingQuestion $question */
    $question = $storage->create(['title' => $title, 'status' => 1]);
    $question->save();
    return $question;
  }

  /**
   * Creates a voting_option entity for a given question and returns it.
   */
  private function createOption(VotingQuestion $question, string $label = 'Option A'): VotingOption {
    $storage = \Drupal::entityTypeManager()->getStorage('voting_option');
    /** @var \Drupal\vs_core\Entity\VotingOption $option */
    $option = $storage->create([
      'label' => $label,
      'question_id' => $question->id(),
      'weight' => 0,
    ]);
    $option->save();
    return $option;
  }

  /**
   * Creates a voting_vote entity and returns it.
   *
   * @param \Drupal\vs_core\Entity\VotingQuestion $question
   *   The question the vote belongs to.
   * @param \Drupal\vs_core\Entity\VotingOption $option
   *   The option voted for.
   * @param \Drupal\user\UserInterface $user
   *   The voting user.
   */
  private function createVote(
    VotingQuestion $question,
    VotingOption $option,
    UserInterface $user,
  ): VotingVote {
    $storage = \Drupal::entityTypeManager()->getStorage('voting_vote');
    /** @var \Drupal\vs_core\Entity\VotingVote $vote */
    $vote = $storage->create([
      'question_id' => $question->id(),
      'option_id' => $option->id(),
      'uid' => $user->id(),
      'source' => 'cms',
    ]);
    $vote->save();
    return $vote;
  }

  /**
   * Anonymous user cannot access the vote management page.
   */
  public function testAnonymousCannotAccessVotesPage(): void {
    $question = $this->createQuestion();

    $url = '/admin/content/voting-questions/' . $question->id() . '/votes';
    $this->drupalGet($url);
    $this->assertSession()->statusCodeEquals(403);
  }

  /**
   * Unprivileged user cannot access the vote management page.
   */
  public function testUnprivilegedUserCannotAccessVotesPage(): void {
    $question = $this->createQuestion();
    $user = $this->drupalCreateUser(['vote']);
    $this->drupalLogin($user);

    $url = '/admin/content/voting-questions/' . $question->id() . '/votes';
    $this->drupalGet($url);
    $this->assertSession()->statusCodeEquals(403);
  }

  /**
   * Admin can access the vote management page.
   */
  public function testAdminCanAccessVotesPage(): void {
    $admin = $this->drupalCreateUser(['administer voting']);
    $this->drupalLogin($admin);
    $question = $this->createQuestion();

    $url = '/admin/content/voting-questions/' . $question->id() . '/votes';
    $this->drupalGet($url);
    $this->assertSession()->statusCodeEquals(200);
  }

  /**
   * The vote management page shows the username and option label for each vote.
   */
  public function testVotesPageListsVotes(): void {
    $admin = $this->drupalCreateUser(['administer voting']);
    $this->drupalLogin($admin);
    $question = $this->createQuestion();
    $option = $this->createOption($question);
    $voter = $this->drupalCreateUser([], 'voter_alice');
    $this->createVote($question, $option, $voter);

    $url = '/admin/content/voting-questions/' . $question->id() . '/votes';
    $this->drupalGet($url);
    $this->assertSession()->pageTextContains('voter_alice');
    $this->assertSession()->pageTextContains('Option A');
  }

  /**
   * Votes page for a question with no votes shows an empty state.
   */
  public function testVotesPageWithNoVotesShowsEmptyMessage(): void {
    $admin = $this->drupalCreateUser(['administer voting']);
    $this->drupalLogin($admin);
    $question = $this->createQuestion();

    $url = '/admin/content/voting-questions/' . $question->id() . '/votes';
    $this->drupalGet($url);
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('No votes');
  }

  /**
   * Admin can bulk-delete selected votes.
   */
  public function testAdminCanBulkDeleteVotes(): void {
    $admin = $this->drupalCreateUser(['administer voting']);
    $this->drupalLogin($admin);
    $question = $this->createQuestion();
    $option = $this->createOption($question);
    $voter1 = $this->drupalCreateUser([], 'voter_bob');
    $voter2 = $this->drupalCreateUser([], 'voter_carol');
    $vote1 = $this->createVote($question, $option, $voter1);
    $vote2 = $this->createVote($question, $option, $voter2);

    $url = '/admin/content/voting-questions/' . $question->id() . '/votes';
    $this->drupalGet($url);

    $this->submitForm([
      'votes[' . $vote1->id() . ']' => TRUE,
      'votes[' . $vote2->id() . ']' => TRUE,
    ], 'Delete selected votes');

    $voteStorage = \Drupal::entityTypeManager()->getStorage('voting_vote');
    $voteStorage->resetCache();
    $this->assertNull($voteStorage->load($vote1->id()));
    $this->assertNull($voteStorage->load($vote2->id()));
  }

  /**
   * Bulk-deleting votes shows a confirmation message with the count deleted.
   */
  public function testBulkDeleteShowsConfirmationMessage(): void {
    $admin = $this->drupalCreateUser(['administer voting']);
    $this->drupalLogin($admin);
    $question = $this->createQuestion();
    $option = $this->createOption($question);
    $voter = $this->drupalCreateUser([], 'voter_dan');
    $vote = $this->createVote($question, $option, $voter);

    $url = '/admin/content/voting-questions/' . $question->id() . '/votes';
    $this->drupalGet($url);
    $this->submitForm(['votes[' . $vote->id() . ']' => TRUE], 'Delete selected votes');

    $this->assertSession()->pageTextContains('1 vote(s) deleted');
  }

  /**
   * The delete question form is blocked with an error when votes exist.
   */
  public function testDeleteQuestionBlockedWhenVotesExist(): void {
    $admin = $this->drupalCreateUser(['administer voting']);
    $this->drupalLogin($admin);
    $question = $this->createQuestion('Question With Votes');
    $option = $this->createOption($question);
    $voter = $this->drupalCreateUser();
    $this->createVote($question, $option, $voter);

    $url = '/admin/content/voting-questions/' . $question->id() . '/delete';
    $this->drupalGet($url);

    $this->assertSession()->pageTextContains('cannot be deleted');
    $this->assertSession()->buttonNotExists('Delete');
  }

  /**
   * After all votes are deleted the question delete form shows confirmation.
   */
  public function testDeleteQuestionAllowedAfterVotesDeleted(): void {
    $admin = $this->drupalCreateUser(['administer voting']);
    $this->drupalLogin($admin);
    $question = $this->createQuestion('No More Votes');

    $url = '/admin/content/voting-questions/' . $question->id() . '/delete';
    $this->drupalGet($url);
    $this->assertSession()->buttonExists('Delete');
  }

}
