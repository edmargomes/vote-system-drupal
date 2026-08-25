<?php

declare(strict_types=1);

namespace Drupal\Tests\vs_core\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\user\Entity\User;
use Drupal\vs_core\Exception\DuplicateVoteException;

/**
 * Verifies DB-level deduplication prevents a user from voting twice.
 *
 * @group vs_core
 */
class VotingConcurrentTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['vs_core', 'user', 'system', 'file', 'image', 'text'];

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

    // hook_install() is not invoked automatically in Kernel tests, so the
    // unique constraint must be added explicitly after entity schema creation.
    $this->container->get('database')->schema()->addUniqueKey(
      'voting_vote',
      'uq_user_question',
      ['uid', 'question_id'],
    );
  }

  /**
   * The UNIQUE(uid, question_id) constraint raises DuplicateVoteException.
   */
  public function testDuplicateVoteIsRejected(): void {
    $questionStorage = $this->container->get('entity_type.manager')
      ->getStorage('voting_question');
    $question = $questionStorage->create(['title' => 'Dupe test?', 'status' => TRUE]);
    $question->save();

    $optionStorage = $this->container->get('entity_type.manager')
      ->getStorage('voting_option');
    $option = $optionStorage->create([
      'label' => 'Option A',
      'question_id' => $question->id(),
    ]);
    $option->save();

    $user = User::create(['name' => 'voter1', 'status' => 1]);
    $user->save();

    /** @var \Drupal\vs_core\Service\VotingService $votingService */
    $votingService = $this->container->get('vs_core.voting');

    $votingService->registerVote($user, $question, $option);

    $this->expectException(DuplicateVoteException::class);

    $votingService->registerVote($user, $question, $option);
  }

  /**
   * Different users can vote on the same question without conflict.
   */
  public function testTwoUsersCanVoteOnSameQuestion(): void {
    $questionStorage = $this->container->get('entity_type.manager')
      ->getStorage('voting_question');
    $question = $questionStorage->create(['title' => 'Two-users test?', 'status' => TRUE]);
    $question->save();

    $optionStorage = $this->container->get('entity_type.manager')
      ->getStorage('voting_option');
    $option = $optionStorage->create([
      'label' => 'Option B',
      'question_id' => $question->id(),
    ]);
    $option->save();

    $user1 = User::create(['name' => 'voter1', 'status' => 1]);
    $user1->save();

    $user2 = User::create(['name' => 'voter2', 'status' => 1]);
    $user2->save();

    /** @var \Drupal\vs_core\Service\VotingService $votingService */
    $votingService = $this->container->get('vs_core.voting');

    $votingService->registerVote($user1, $question, $option);
    $votingService->registerVote($user2, $question, $option);

    $voteStorage = $this->container->get('entity_type.manager')->getStorage('voting_vote');
    $votes = $voteStorage->loadByProperties(['question_id' => $question->id()]);

    $this->assertCount(2, $votes);
  }

}
