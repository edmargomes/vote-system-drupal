<?php

declare(strict_types=1);

namespace Drupal\Tests\vs_core\Kernel;

use Drupal\KernelTests\KernelTestBase;
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
  protected static $modules = ['vs_core', 'user', 'system'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('voting_question');
    $this->installEntitySchema('voting_option');
    $this->installEntitySchema('voting_vote');
    $this->installSchema('vs_core', ['vs_core_api_token']);
    $this->installConfig(['vs_core']);
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

    /** @var \Drupal\vs_core\Service\VotingService $votingService */
    $votingService = $this->container->get('vs_core.voting');

    $votingService->castVote(
      questionUuid: $question->uuid(),
      optionId: (int) $option->id(),
      uid: 1,
    );

    $this->expectException(DuplicateVoteException::class);

    $votingService->castVote(
      questionUuid: $question->uuid(),
      optionId: (int) $option->id(),
      uid: 1,
    );
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

    /** @var \Drupal\vs_core\Service\VotingService $votingService */
    $votingService = $this->container->get('vs_core.voting');

    $votingService->castVote(questionUuid: $question->uuid(), optionId: (int) $option->id(), uid: 1);
    $votingService->castVote(questionUuid: $question->uuid(), optionId: (int) $option->id(), uid: 2);

    $voteStorage = $this->container->get('entity_type.manager')->getStorage('voting_vote');
    $votes = $voteStorage->loadByProperties(['question_id' => $question->id()]);

    $this->assertCount(2, $votes);
  }

}
