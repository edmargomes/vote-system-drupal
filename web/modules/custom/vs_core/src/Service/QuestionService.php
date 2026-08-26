<?php

declare(strict_types=1);

namespace Drupal\vs_core\Service;

use Drupal\Component\Datetime\TimeInterface;
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
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time service, used to evaluate question expiry without coupling to
   *   the global clock.
   */
  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly TimeInterface $time,
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
   * Returns all active and non-expired voting questions.
   *
   * A question is included when status = 1 AND (closes_at IS NULL OR
   * closes_at > REQUEST_TIME). The entity query API is used so that Drupal's
   * access layer is respected and the result is testable via Kernel tests.
   *
   * @return \Drupal\vs_core\Entity\VotingQuestionInterface[]
   *   Array of open question entities.
   */
  public function listActive(): array {
    $now = $this->time->getRequestTime();
    $storage = $this->entityTypeManager->getStorage('voting_question');

    $ids = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('status', 1)
      ->condition(
        $storage->getQuery()->orConditionGroup()
          ->notExists('closes_at')
          ->condition('closes_at', $now, '>')
      )
      ->execute();

    $entities = $storage->loadMultiple($ids);

    return array_values(
      array_filter($entities, static fn($e) => $e instanceof VotingQuestionInterface)
    );
  }

  /**
   * Returns whether a given question is currently open for voting.
   *
   * Delegates to the entity method to keep the logic in one canonical place.
   *
   * @param \Drupal\vs_core\Entity\VotingQuestionInterface $question
   *   The question to check.
   *
   * @return bool
   *   TRUE when the question is active and not yet expired.
   */
  public function isOpen(VotingQuestionInterface $question): bool {
    if (!(bool) $question->get('status')->value) {
      return FALSE;
    }

    $closesAt = $question->get('closes_at')->value;
    if ($closesAt === NULL) {
      return TRUE;
    }

    return (int) $closesAt > $this->time->getRequestTime();
  }

  /**
   * Returns all options belonging to a given question.
   *
   * @param \Drupal\vs_core\Entity\VotingQuestionInterface $question
   *   The parent question.
   *
   * @return \Drupal\vs_core\Entity\VotingOptionInterface[]
   *   Array of option entities ordered by weight ascending.
   */
  public function getOptions(VotingQuestionInterface $question): array {
    $storage = $this->entityTypeManager->getStorage('voting_option');

    $ids = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('question_id', $question->id())
      ->sort('weight', 'ASC')
      ->execute();

    $results = $storage->loadMultiple($ids);

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
