<?php

declare(strict_types=1);

namespace Drupal\vs_core\Service;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\vs_core\Entity\VotingOptionInterface;
use Drupal\vs_core\Entity\VotingQuestionInterface;

/**
 * Provides entity lookup operations for questions and options.
 */
class QuestionService {

  /**
   * Constructs a QuestionService.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   */
  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * Finds a voting question by its UUID.
   *
   * @param string $uuid
   *   The UUID to look up.
   *
   * @return \Drupal\vs_core\Entity\VotingQuestionInterface|null
   *   The question entity, or NULL if not found.
   */
  public function findByUuid(string $uuid): ?VotingQuestionInterface {
    $results = $this->entityTypeManager
      ->getStorage('voting_question')
      ->loadByProperties(['uuid' => $uuid]);

    $entity = reset($results);

    return $entity instanceof VotingQuestionInterface ? $entity : NULL;
  }

  /**
   * Returns all active (status = 1) voting questions.
   *
   * @return \Drupal\vs_core\Entity\VotingQuestionInterface[]
   *   Array of active question entities.
   */
  public function listActive(): array {
    $results = $this->entityTypeManager
      ->getStorage('voting_question')
      ->loadByProperties(['status' => 1]);

    return array_values(
      array_filter($results, static fn($e) => $e instanceof VotingQuestionInterface)
    );
  }

  /**
   * Returns all options belonging to a given question.
   *
   * @param \Drupal\vs_core\Entity\VotingQuestionInterface $question
   *   The parent question.
   *
   * @return \Drupal\vs_core\Entity\VotingOptionInterface[]
   *   Array of option entities.
   */
  public function getOptions(VotingQuestionInterface $question): array {
    $results = $this->entityTypeManager
      ->getStorage('voting_option')
      ->loadByProperties(['question_id' => $question->id()]);

    return array_values(
      array_filter($results, static fn($e) => $e instanceof VotingOptionInterface)
    );
  }

  /**
   * Finds a voting option by its UUID.
   *
   * @param string $uuid
   *   The UUID to look up.
   *
   * @return \Drupal\vs_core\Entity\VotingOptionInterface|null
   *   The option entity, or NULL if not found.
   */
  public function findOptionByUuid(string $uuid): ?VotingOptionInterface {
    $results = $this->entityTypeManager
      ->getStorage('voting_option')
      ->loadByProperties(['uuid' => $uuid]);

    $entity = reset($results);

    return $entity instanceof VotingOptionInterface ? $entity : NULL;
  }

}
