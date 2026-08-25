<?php

declare(strict_types=1);

namespace Drupal\vs_core\Service;

use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\vs_core\Entity\VotingOptionInterface;
use Drupal\vs_core\Entity\VotingQuestionInterface;

/**
 * Provides structured logging for the VS Core voting module.
 *
 * All log messages include the Correlation ID for request tracing.
 */
class VotingLogger {

  /**
   * Logger channel name for this module.
   */
  private const CHANNEL = 'vs_core';

  /**
   * Constructs a VotingLogger.
   *
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $loggerFactory
   *   The logger channel factory.
   */
  public function __construct(
    private readonly LoggerChannelFactoryInterface $loggerFactory,
  ) {}

  /**
   * Logs a successful vote registration.
   *
   * @param \Drupal\Core\Session\AccountInterface $user
   *   The user who cast the vote.
   * @param \Drupal\vs_core\Entity\VotingQuestionInterface $question
   *   The question that was voted on.
   * @param \Drupal\vs_core\Entity\VotingOptionInterface $option
   *   The option that was selected.
   * @param string $correlationId
   *   The Correlation ID for the current request.
   */
  public function logVote(
    AccountInterface $user,
    VotingQuestionInterface $question,
    VotingOptionInterface $option,
    string $correlationId = '',
  ): void {
    $this->loggerFactory->get(self::CHANNEL)->info(
      'Vote registered: user @uid voted on question @question_uuid (option @option_uuid). Correlation: @cid',
      [
        '@uid' => $user->id(),
        '@question_uuid' => $question->uuid(),
        '@option_uuid' => $option->uuid(),
        '@cid' => $correlationId,
      ]
    );
  }

  /**
   * Logs a duplicate vote attempt.
   *
   * @param \Drupal\Core\Session\AccountInterface $user
   *   The user who attempted the duplicate vote.
   * @param \Drupal\vs_core\Entity\VotingQuestionInterface $question
   *   The question on which duplication occurred.
   * @param string $correlationId
   *   The Correlation ID for the current request.
   */
  public function logDuplicateVote(
    AccountInterface $user,
    VotingQuestionInterface $question,
    string $correlationId = '',
  ): void {
    $this->loggerFactory->get(self::CHANNEL)->warning(
      'Duplicate vote attempt: user @uid already voted on question @question_uuid. Correlation: @cid',
      [
        '@uid' => $user->id(),
        '@question_uuid' => $question->uuid(),
        '@cid' => $correlationId,
      ]
    );
  }

  /**
   * Logs an attempt to access a voting endpoint while voting is disabled.
   *
   * @param string $endpoint
   *   The endpoint path that was accessed.
   * @param \Drupal\Core\Session\AccountInterface $user
   *   The user who made the request.
   * @param string $correlationId
   *   The Correlation ID for the current request.
   */
  public function logVotingDisabled(
    string $endpoint,
    AccountInterface $user,
    string $correlationId = '',
  ): void {
    $this->loggerFactory->get(self::CHANNEL)->notice(
      'Voting disabled: user @uid attempted to access @endpoint. Correlation: @cid',
      [
        '@uid' => $user->id(),
        '@endpoint' => $endpoint,
        '@cid' => $correlationId,
      ]
    );
  }

  /**
   * Logs an unexpected exception.
   *
   * @param \Throwable $exception
   *   The exception to log.
   * @param string $correlationId
   *   The Correlation ID for the current request.
   */
  public function logError(\Throwable $exception, string $correlationId = ''): void {
    $this->loggerFactory->get(self::CHANNEL)->error(
      'Unexpected error: @message in @file:@line. Correlation: @cid',
      [
        '@message' => $exception->getMessage(),
        '@file' => $exception->getFile(),
        '@line' => $exception->getLine(),
        '@cid' => $correlationId,
      ]
    );
  }

}
