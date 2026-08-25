<?php

declare(strict_types=1);

namespace Drupal\Tests\vs_core\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\user\Entity\User;
use Drupal\vs_core\Entity\VotingOptionInterface;

/**
 * Verifies the CMS-oriented methods added to VotingService.
 *
 * Covers hasVoted(), getUserVote(), and $source parameter on registerVote().
 *
 * @group vs_core
 */
class VotingServiceCmsTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['vs_core', 'user', 'system', 'file', 'image', 'text'];

  /**
   * The voting service under test.
   *
   * @var \Drupal\vs_core\Service\VotingService
   */
  private object $votingService;

  /**
   * A persisted voting question entity.
   *
   * @var \Drupal\vs_core\Entity\VotingQuestionInterface
   */
  private object $question;

  /**
   * A persisted voting option entity.
   *
   * @var \Drupal\vs_core\Entity\VotingOptionInterface
   */
  private object $option;

  /**
   * A persisted Drupal user.
   *
   * @var \Drupal\user\Entity\User
   */
  private User $user;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installEntitySchema('voting_question');
    $this->installEntitySchema('voting_option');
    $this->installEntitySchema('voting_vote');
    $this->installConfig(['vs_core']);

    // The unique constraint is added by hook_install(), which does not fire in
    // kernel tests — add it explicitly so duplicate-vote detection works.
    $this->container->get('database')->schema()->addUniqueKey(
      'voting_vote',
      'uq_user_question',
      ['uid', 'question_id'],
    );

    $this->votingService = $this->container->get('vs_core.voting');

    $questionStorage = $this->container->get('entity_type.manager')
      ->getStorage('voting_question');

    $this->question = $questionStorage->create([
      'title' => 'CMS test question?',
      'status' => TRUE,
      'show_results' => FALSE,
    ]);
    $this->question->save();

    $optionStorage = $this->container->get('entity_type.manager')
      ->getStorage('voting_option');

    $this->option = $optionStorage->create([
      'label' => 'Option Alpha',
      'question_id' => $this->question->id(),
    ]);
    $this->option->save();

    $this->user = User::create(['name' => 'cms_voter', 'status' => 1]);
    $this->user->save();
  }

  /**
   * HasVoted() returns false when the user has not yet cast a vote.
   */
  public function testHasVotedReturnsFalseBeforeVoting(): void {
    $this->assertFalse(
      $this->votingService->hasVoted($this->user, $this->question),
    );
  }

  /**
   * HasVoted() returns true after a vote has been registered.
   */
  public function testHasVotedReturnsTrueAfterVoting(): void {
    $this->votingService->registerVote($this->user, $this->question, $this->option);

    $this->assertTrue(
      $this->votingService->hasVoted($this->user, $this->question),
    );
  }

  /**
   * GetUserVote() returns null when the user has not voted.
   */
  public function testGetUserVoteReturnsNullBeforeVoting(): void {
    $this->assertNull(
      $this->votingService->getUserVote($this->user, $this->question),
    );
  }

  /**
   * GetUserVote() returns the correct option entity after voting.
   */
  public function testGetUserVoteReturnsCorrectOptionAfterVoting(): void {
    $this->votingService->registerVote($this->user, $this->question, $this->option);

    $voted = $this->votingService->getUserVote($this->user, $this->question);

    $this->assertInstanceOf(VotingOptionInterface::class, $voted);
    $this->assertSame((int) $this->option->id(), (int) $voted->id());
  }

  /**
   * RegisterVote() with source='cms' persists the cms source value.
   */
  public function testRegisterVoteWithCmsSourcePersistsCmsSource(): void {
    $this->votingService->registerVote(
      $this->user,
      $this->question,
      $this->option,
      'cms',
    );

    $row = $this->container->get('database')
      ->select('voting_vote', 'v')
      ->fields('v', ['source'])
      ->condition('v.uid', $this->user->id())
      ->condition('v.question_id', $this->question->id())
      ->execute()
      ->fetchAssoc();

    $this->assertIsArray($row);
    $this->assertSame('cms', $row['source']);
  }

  /**
   * RegisterVote() defaults source to 'api' when the parameter is omitted.
   */
  public function testRegisterVoteDefaultsToApiSource(): void {
    $this->votingService->registerVote($this->user, $this->question, $this->option);

    $row = $this->container->get('database')
      ->select('voting_vote', 'v')
      ->fields('v', ['source'])
      ->condition('v.uid', $this->user->id())
      ->condition('v.question_id', $this->question->id())
      ->execute()
      ->fetchAssoc();

    $this->assertIsArray($row);
    $this->assertSame('api', $row['source']);
  }

}
