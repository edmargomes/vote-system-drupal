<?php

declare(strict_types=1);

namespace Drupal\Tests\vs_core\Functional\Admin;

use Drupal\Tests\BrowserTestBase;

/**
 * Browser tests for the admin voting results page.
 *
 * @group vs_core
 * @group admin
 */
class VotingAdminResultsPageTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['vs_core'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Admin user with 'administer voting' permission.
   *
   * @var \Drupal\user\UserInterface
   */
  private $admin;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->admin = $this->drupalCreateUser(['administer voting']);
  }

  /**
   * Creates a voting question and returns it.
   *
   * @param string $title
   *   The question title.
   *
   * @return \Drupal\vs_core\Entity\VotingQuestionInterface
   *   The created question entity.
   */
  private function createQuestion(string $title): object {
    $storage = $this->container->get('entity_type.manager')
      ->getStorage('voting_question');
    $question = $storage->create([
      'title' => $title,
      'status' => TRUE,
    ]);
    $question->save();
    return $question;
  }

  /**
   * Admin can load results page for an existing question — HTTP 200.
   */
  public function testAdminCanLoadResultsPage(): void {
    $question = $this->createQuestion('Results Page Test');
    $this->drupalLogin($this->admin);

    $this->drupalGet('/admin/content/voting-questions/' . $question->id() . '/results');
    $this->assertSession()->statusCodeEquals(200);
  }

  /**
   * Results page shows the question title.
   */
  public function testResultsPageShowsQuestionTitle(): void {
    $question = $this->createQuestion('Best Framework?');
    $this->drupalLogin($this->admin);

    $this->drupalGet('/admin/content/voting-questions/' . $question->id() . '/results');
    $this->assertSession()->pageTextContains('Best Framework?');
  }

  /**
   * Results page contains a "Back to questions" link.
   */
  public function testResultsPageShowsBackLink(): void {
    $question = $this->createQuestion('Back Link Test');
    $this->drupalLogin($this->admin);

    $this->drupalGet('/admin/content/voting-questions/' . $question->id() . '/results');
    $this->assertSession()->linkExists('Back to questions');
  }

  /**
   * Results page for a non-existent question returns HTTP 404.
   */
  public function testResultsPageForNonExistentQuestionReturns404(): void {
    $this->drupalLogin($this->admin);

    $this->drupalGet('/admin/content/voting-questions/99999/results');
    $this->assertSession()->statusCodeEquals(404);
  }

  /**
   * Results page shows a "no votes" message when no votes have been cast.
   */
  public function testResultsPageShowsNoVotesMessage(): void {
    $question = $this->createQuestion('No Votes Yet');
    $this->drupalLogin($this->admin);

    $this->drupalGet('/admin/content/voting-questions/' . $question->id() . '/results');
    $this->assertSession()->pageTextContains('No votes');
  }

  /**
   * Results page shows vote counts and percentage after votes are cast.
   */
  public function testResultsPageShowsVoteCountsAfterVoting(): void {
    $question = $this->createQuestion('Vote Count Test');

    $optionStorage = $this->container->get('entity_type.manager')
      ->getStorage('voting_option');
    $option = $optionStorage->create([
      'label' => 'Option A',
      'question_id' => $question->id(),
    ]);
    $option->save();

    // Insert a vote directly into the database.
    $this->container->get('database')->insert('voting_vote')
      ->fields([
        'uuid' => \Drupal::service('uuid')->generate(),
        'uid' => 1,
        'question_id' => $question->id(),
        'option_id' => $option->id(),
        'source' => 'cms',
        'created' => time(),
      ])
      ->execute();

    $this->drupalLogin($this->admin);
    $this->drupalGet('/admin/content/voting-questions/' . $question->id() . '/results');
    $this->assertSession()->pageTextContains('%');
  }

  /**
   * Unprivileged user cannot access the results page — HTTP 403.
   */
  public function testUnprivilegedUserCannotAccessResultsPage(): void {
    $question = $this->createQuestion('Access Denied Test');

    $user = $this->drupalCreateUser(['vote']);
    $this->drupalLogin($user);

    $this->drupalGet('/admin/content/voting-questions/' . $question->id() . '/results');
    $this->assertSession()->statusCodeEquals(403);
  }

  /**
   * Admin question list shows "Results" action link for each question.
   */
  public function testAdminListShowsResultsLink(): void {
    $this->createQuestion('Linked Question');
    $this->drupalLogin($this->admin);

    $this->drupalGet('/admin/content/voting-questions');
    $this->assertSession()->linkExists('Results');
  }

}
