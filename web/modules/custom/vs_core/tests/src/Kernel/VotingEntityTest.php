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
  protected static $modules = ['vs_core', 'user', 'system'];

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

}
