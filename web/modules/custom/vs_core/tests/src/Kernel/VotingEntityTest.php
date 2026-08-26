<?php

declare(strict_types=1);

namespace Drupal\Tests\vs_core\Kernel;

use Drupal\KernelTests\KernelTestBase;

/**
 * Verifies that voting entities can be created, saved, and loaded from the DB.
 *
 * @group vs_core
 */
class VotingEntityTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['vs_core', 'user', 'system', 'file', 'image', 'text'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('voting_question');
    $this->installEntitySchema('voting_option');
    $this->installEntitySchema('voting_vote');
    $this->installConfig(['vs_core']);
  }

  /**
   * A voting_question entity can be persisted and reloaded.
   */
  public function testVotingQuestionCanBeSavedAndLoaded(): void {
    $storage = $this->container->get('entity_type.manager')
      ->getStorage('voting_question');

    /** @var \Drupal\vs_core\Entity\VotingQuestionInterface $question */
    $question = $storage->create([
      'title' => 'What is your favourite colour?',
      'show_results' => TRUE,
      'status' => TRUE,
    ]);
    $question->save();

    $loaded = $storage->load($question->id());

    $this->assertNotNull($loaded);
    $this->assertSame('What is your favourite colour?', $loaded->label());
  }

  /**
   * A voting_option entity is linked to its parent question.
   */
  public function testVotingOptionLinksToQuestion(): void {
    $questionStorage = $this->container->get('entity_type.manager')
      ->getStorage('voting_question');

    /** @var \Drupal\vs_core\Entity\VotingQuestionInterface $question */
    $question = $questionStorage->create(['title' => 'Colours?', 'status' => TRUE]);
    $question->save();

    $optionStorage = $this->container->get('entity_type.manager')
      ->getStorage('voting_option');

    /** @var \Drupal\vs_core\Entity\VotingOptionInterface $option */
    $option = $optionStorage->create([
      'label' => 'Blue',
      'question_id' => $question->id(),
    ]);
    $option->save();

    $loaded = $optionStorage->load($option->id());

    $this->assertNotNull($loaded);
    $this->assertSame((int) $question->id(), (int) $loaded->get('question_id')->target_id);
  }

  /**
   * Casting a voting_vote entity records the user and option.
   */
  public function testVotingVoteCanBeCreated(): void {
    $questionStorage = $this->container->get('entity_type.manager')
      ->getStorage('voting_question');
    $question = $questionStorage->create(['title' => 'Vote me?', 'status' => TRUE]);
    $question->save();

    $optionStorage = $this->container->get('entity_type.manager')
      ->getStorage('voting_option');
    $option = $optionStorage->create([
      'label' => 'Yes',
      'question_id' => $question->id(),
    ]);
    $option->save();

    $voteStorage = $this->container->get('entity_type.manager')
      ->getStorage('voting_vote');

    /** @var \Drupal\vs_core\Entity\VotingVoteInterface $vote */
    $vote = $voteStorage->create([
      'uid' => 1,
      'question_id' => $question->id(),
      'option_id' => $option->id(),
    ]);
    $vote->save();

    $loaded = $voteStorage->load($vote->id());

    $this->assertNotNull($loaded);
    $this->assertSame(1, (int) $loaded->get('uid')->target_id);
  }

  /**
   * Voting_question with closes_at persists and reloads the timestamp intact.
   */
  public function testClosesAtTimestampPersistsAndReloads(): void {
    $storage = $this->container->get('entity_type.manager')
      ->getStorage('voting_question');

    $timestamp = 1_700_000_000;

    /** @var \Drupal\vs_core\Entity\VotingQuestionInterface $question */
    $question = $storage->create([
      'title' => 'Expiring question',
      'status' => TRUE,
      'closes_at' => $timestamp,
    ]);
    $question->save();

    $storage->resetCache([$question->id()]);
    $loaded = $storage->load($question->id());

    $this->assertNotNull($loaded);
    $this->assertSame($timestamp, (int) $loaded->get('closes_at')->value);
  }

  /**
   * Voting_question with closes_at = NULL can be saved and reloaded cleanly.
   */
  public function testClosesAtNullPersistsAndReloads(): void {
    $storage = $this->container->get('entity_type.manager')
      ->getStorage('voting_question');

    /** @var \Drupal\vs_core\Entity\VotingQuestionInterface $question */
    $question = $storage->create([
      'title' => 'Open-ended question',
      'status' => TRUE,
      'closes_at' => NULL,
    ]);
    $question->save();

    $storage->resetCache([$question->id()]);
    $loaded = $storage->load($question->id());

    $this->assertNotNull($loaded);
    $this->assertNull($loaded->get('closes_at')->value);
  }

  /**
   * QuestionService::listActive() excludes an expired question at the DB level.
   */
  public function testListActiveExcludesExpiredQuestion(): void {
    $storage = $this->container->get('entity_type.manager')
      ->getStorage('voting_question');

    $pastTimestamp = time() - 3600;

    $storage->create([
      'title' => 'Already closed',
      'status' => TRUE,
      'closes_at' => $pastTimestamp,
    ])->save();

    /** @var \Drupal\vs_core\Service\QuestionService $service */
    $service = $this->container->get('vs_core.question');

    $result = $service->listActive();

    $this->assertCount(0, $result);
  }

  /**
   * QuestionService::listActive() includes a question with a future closes_at.
   */
  public function testListActiveIncludesFutureQuestion(): void {
    $storage = $this->container->get('entity_type.manager')
      ->getStorage('voting_question');

    $futureTimestamp = time() + 3600;

    $storage->create([
      'title' => 'Still open',
      'status' => TRUE,
      'closes_at' => $futureTimestamp,
    ])->save();

    /** @var \Drupal\vs_core\Service\QuestionService $service */
    $service = $this->container->get('vs_core.question');

    $result = $service->listActive();

    $this->assertCount(1, $result);
  }

  /**
   * VotingOption weight field persists and loads correctly in ascending order.
   */
  public function testOptionWeightFieldPersistsAndSortsCorrectly(): void {
    $questionStorage = $this->container->get('entity_type.manager')
      ->getStorage('voting_question');
    $question = $questionStorage->create(['title' => 'Weight test?', 'status' => TRUE]);
    $question->save();

    $optionStorage = $this->container->get('entity_type.manager')
      ->getStorage('voting_option');

    $optionB = $optionStorage->create([
      'label' => 'Option B',
      'question_id' => $question->id(),
      'weight' => 10,
    ]);
    $optionB->save();

    $optionA = $optionStorage->create([
      'label' => 'Option A',
      'question_id' => $question->id(),
      'weight' => 1,
    ]);
    $optionA->save();

    // Query by weight ascending to verify ordering.
    $ids = $optionStorage->getQuery()
      ->accessCheck(FALSE)
      ->condition('question_id', $question->id())
      ->sort('weight', 'ASC')
      ->execute();

    $options = array_values($optionStorage->loadMultiple($ids));

    $this->assertCount(2, $options);
    $this->assertSame('Option A', $options[0]->label());
    $this->assertSame('Option B', $options[1]->label());
  }

}
