<?php

declare(strict_types=1);

namespace Drupal\vs_core\Service;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Component\Uuid\Php as UuidGenerator;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Database\IntegrityConstraintViolationException;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\vs_core\Entity\VotingOptionInterface;
use Drupal\vs_core\Entity\VotingQuestionInterface;
use Drupal\vs_core\Exception\DuplicateVoteException;

/**
 * Handles vote registration and the global voting-enabled gate.
 */
class VotingService {

  /**
   * Constructs a VotingService.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager, reserved for future admin vote operations.
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The config factory.
   * @param \Drupal\vs_core\Service\VotingLogger $logger
   *   The voting logger service.
   * @param \Drupal\Component\Datetime\TimeInterface|null $time
   *   The time service. When NULL, falls back to the system clock.
   */
  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly Connection $database,
    private readonly ConfigFactoryInterface $configFactory,
    private readonly VotingLogger $logger,
    private readonly ?TimeInterface $time = NULL,
  ) {}

  /**
   * Returns whether voting is globally enabled.
   *
   * @return bool
   *   TRUE if voting is enabled, FALSE if it has been disabled via settings.
   */
  public function isVotingEnabled(): bool {
    return (bool) $this->configFactory->get('vs_core.settings')->get('voting_enabled');
  }

  /**
   * Registers a vote inside a database transaction.
   *
   * Uses a raw DB insert rather than entity storage so that the
   * IntegrityConstraintViolationException from the UNIQUE(uid, question_id)
   * constraint can be caught and converted to a domain exception. Entity
   * storage wraps exceptions in a different way that prevents clean
   * interception.
   *
   * @param \Drupal\Core\Session\AccountInterface $user
   *   The user casting the vote.
   * @param \Drupal\vs_core\Entity\VotingQuestionInterface $question
   *   The question being voted on.
   * @param \Drupal\vs_core\Entity\VotingOptionInterface $option
   *   The option selected by the user.
   * @param string $source
   *   The origin of the vote: 'api' for REST API submissions, 'cms' for the
   *   CMS browser form. Defaults to 'api' for backward compatibility.
   *
   * @throws \Drupal\vs_core\Exception\DuplicateVoteException
   *   When the user has already voted on this question.
   */
  public function registerVote(
    AccountInterface $user,
    VotingQuestionInterface $question,
    VotingOptionInterface $option,
    string $source = 'api',
  ): void {
    $transaction = $this->database->startTransaction();

    try {
      $this->database->insert('voting_vote')
        ->fields([
          'uuid' => (new UuidGenerator())->generate(),
          'question_id' => $question->id(),
          'option_id' => $option->id(),
          'uid' => $user->id(),
          'source' => $source,
          'created' => $this->time?->getRequestTime() ?? time(),
        ])
        ->execute();

      $this->logger->logVote($user, $question, $option);
    }
    catch (IntegrityConstraintViolationException $e) {
      // Unsetting $transaction triggers the RAII rollback via __destruct().
      // Calling ->rollBack() directly is the anti-pattern documented in
      // Drupal's Transaction class: it can interfere with nested transactions
      // and causes __destruct() to fire again on an already-rolled-back state.
      unset($transaction);
      throw new DuplicateVoteException(
        'User has already voted on this question.',
        0,
        $e,
      );
    }
  }

  /**
   * Returns whether the given user has already voted on the given question.
   *
   * This is a read-only query used to conditionally hide or show the voting
   * form in the CMS interface without triggering write operations.
   *
   * @param \Drupal\Core\Session\AccountInterface $user
   *   The user to check.
   * @param \Drupal\vs_core\Entity\VotingQuestionInterface $question
   *   The question to check against.
   *
   * @return bool
   *   TRUE if a vote row exists for this user/question pair, FALSE otherwise.
   */
  public function hasVoted(AccountInterface $user, VotingQuestionInterface $question): bool {
    $count = $this->database->select('voting_vote', 'v')
      ->condition('v.uid', $user->id())
      ->condition('v.question_id', $question->id())
      ->countQuery()
      ->execute()
      ->fetchField();

    return (int) $count > 0;
  }

  /**
   * Returns the option the user voted for on the given question, or null.
   *
   * Used in the CMS detail page to display "You voted for: X" when the user
   * has already cast a vote.
   *
   * @param \Drupal\Core\Session\AccountInterface $user
   *   The user to look up.
   * @param \Drupal\vs_core\Entity\VotingQuestionInterface $question
   *   The question to look up.
   *
   * @return \Drupal\vs_core\Entity\VotingOptionInterface|null
   *   The option entity the user voted for, or NULL if no vote exists.
   */
  public function getUserVote(AccountInterface $user, VotingQuestionInterface $question): ?VotingOptionInterface {
    $optionId = $this->database->select('voting_vote', 'v')
      ->fields('v', ['option_id'])
      ->condition('v.uid', $user->id())
      ->condition('v.question_id', $question->id())
      ->execute()
      ->fetchField();

    if ($optionId === FALSE || $optionId === NULL) {
      return NULL;
    }

    $option = $this->entityTypeManager
      ->getStorage('voting_option')
      ->load($optionId);

    return $option instanceof VotingOptionInterface ? $option : NULL;
  }

}
