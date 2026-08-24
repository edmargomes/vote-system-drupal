<?php

declare(strict_types=1);

namespace Drupal\Tests\vs_core\Unit\Service;

use Drupal\vs_core\Entity\VotingOptionInterface;
use Drupal\Core\Database\Exception\IntegrityConstraintViolationException;
use Drupal\Core\Database\Query\Insert;
use Drupal\Core\Database\Transaction;
use Drupal\vs_core\Entity\VotingQuestionInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\vs_core\Exception\DuplicateVoteException;
use Drupal\vs_core\Exception\VotingDisabledException;
use Drupal\vs_core\Exception\VotingNotFoundException;
use Drupal\vs_core\Service\VotingLogger;
use Drupal\vs_core\Service\VotingService;

/**
 * @coversDefaultClass \Drupal\vs_core\Service\VotingService
 * @group vs_core
 */
class VotingServiceTest extends UnitTestCase {

  /**
   * Entity type manager mock.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  private EntityTypeManagerInterface $entityTypeManager;

  /**
   * Database connection mock.
   *
   * @var \Drupal\Core\Database\Connection|\PHPUnit\Framework\MockObject\MockObject
   */
  private Connection $database;

  /**
   * Config factory mock.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  private ConfigFactoryInterface $configFactory;

  /**
   * Voting logger mock.
   *
   * @var \Drupal\vs_core\Service\VotingLogger|\PHPUnit\Framework\MockObject\MockObject
   */
  private VotingLogger $logger;

  /**
   * Service under test.
   *
   * @var \Drupal\vs_core\Service\VotingService
   */
  private VotingService $service;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $this->database = $this->createMock(Connection::class);
    $this->configFactory = $this->createMock(ConfigFactoryInterface::class);
    $this->logger = $this->createMock(VotingLogger::class);

    $this->service = new VotingService(
      $this->entityTypeManager,
      $this->database,
      $this->configFactory,
      $this->logger,
    );
  }

  /**
   * @covers ::castVote
   */
  public function testCastVoteThrowsWhenVotingDisabled(): void {
    $config = $this->createMock(ImmutableConfig::class);
    $config->method('get')->with('voting_enabled')->willReturn(FALSE);

    $this->configFactory->method('get')->with('vs_core.settings')->willReturn($config);

    $this->expectException(VotingDisabledException::class);

    $this->service->castVote(questionUuid: 'some-uuid', optionUuid: '00000000-0000-0000-0000-000000000001', uid: 42);
  }

  /**
   * @covers ::castVote
   */
  public function testCastVoteThrowsWhenQuestionNotFound(): void {
    $config = $this->createMock(ImmutableConfig::class);
    $config->method('get')->with('voting_enabled')->willReturn(TRUE);
    $this->configFactory->method('get')->willReturn($config);

    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->method('loadByProperties')
      ->with(['uuid' => 'missing-uuid'])
      ->willReturn([]);

    $this->entityTypeManager->method('getStorage')
      ->with('voting_question')
      ->willReturn($storage);

    $this->expectException(VotingNotFoundException::class);

    $this->service->castVote(questionUuid: 'missing-uuid', optionUuid: '00000000-0000-0000-0000-000000000002', uid: 42);
  }

  /**
   * @covers ::castVote
   */
  public function testCastVoteThrowsOnDuplicateVote(): void {
    $config = $this->createMock(ImmutableConfig::class);
    $config->method('get')->with('voting_enabled')->willReturn(TRUE);
    $this->configFactory->method('get')->willReturn($config);

    $question = $this->createMock(VotingQuestionInterface::class);
    $question->method('id')->willReturn(10);

    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->method('loadByProperties')->willReturn([$question]);

    $this->entityTypeManager->method('getStorage')
      ->with('voting_question')
      ->willReturn($storage);

    $optionStorage = $this->createMock(EntityStorageInterface::class);
    $optionStorage->method('loadByProperties')
      ->with(['uuid' => 'aaaaaaaa-0000-0000-0000-000000000001'])
      ->willReturn([$this->createMock(VotingOptionInterface::class)]);

    $this->entityTypeManager->method('getStorage')
      ->willReturnMap([
        ['voting_question', $storage],
        ['voting_option', $optionStorage],
      ]);

    // Simulate a UNIQUE constraint violation from the DB layer.
    $this->database->method('startTransaction')->willReturn(
      $this->createMock(Transaction::class)
    );
    $this->database->method('insert')->willThrowException(
      new IntegrityConstraintViolationException('Duplicate entry', 23000, new \Exception())
    );

    $this->expectException(DuplicateVoteException::class);

    $this->service->castVote(questionUuid: 'dup-uuid', optionUuid: 'aaaaaaaa-0000-0000-0000-000000000001', uid: 7);
  }

  /**
   * @covers ::castVote
   */
  public function testCastVoteInsertsRowOnSuccess(): void {
    $config = $this->createMock(ImmutableConfig::class);
    $config->method('get')->with('voting_enabled')->willReturn(TRUE);
    $this->configFactory->method('get')->willReturn($config);

    $question = $this->createMock(VotingQuestionInterface::class);
    $question->method('id')->willReturn(10);

    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->method('loadByProperties')->willReturn([$question]);

    $this->entityTypeManager->method('getStorage')
      ->with('voting_question')
      ->willReturn($storage);

    $option = $this->createMock(VotingOptionInterface::class);
    $option->method('id')->willReturn(3);

    $optionStorage = $this->createMock(EntityStorageInterface::class);
    $optionStorage->method('loadByProperties')
      ->with(['uuid' => 'bbbbbbbb-0000-0000-0000-000000000001'])
      ->willReturn([$option]);

    $this->entityTypeManager->method('getStorage')
      ->willReturnMap([
        ['voting_question', $storage],
        ['voting_option', $optionStorage],
      ]);

    $transaction = $this->createMock(Transaction::class);
    $this->database->expects($this->once())
      ->method('startTransaction')
      ->willReturn($transaction);

    $insert = $this->createMock(Insert::class);
    $insert->method('fields')->willReturnSelf();
    $insert->method('execute')->willReturn(1);

    $this->database->expects($this->once())->method('insert')->willReturn($insert);

    $this->service->castVote(questionUuid: 'ok-uuid', optionUuid: 'bbbbbbbb-0000-0000-0000-000000000001', uid: 5);
  }

  /**
   * @covers ::castVote
   */
  public function testCastVoteWrapsInsertInTransaction(): void {
    $config = $this->createMock(ImmutableConfig::class);
    $config->method('get')->with('voting_enabled')->willReturn(TRUE);
    $this->configFactory->method('get')->willReturn($config);

    $question = $this->createMock(VotingQuestionInterface::class);
    $question->method('id')->willReturn(10);

    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->method('loadByProperties')->willReturn([$question]);

    $option = $this->createMock(VotingOptionInterface::class);
    $option->method('id')->willReturn(1);

    $optionStorage = $this->createMock(EntityStorageInterface::class);
    $optionStorage->method('loadByProperties')->willReturn([$option]);

    $this->entityTypeManager->method('getStorage')
      ->willReturnMap([
        ['voting_question', $storage],
        ['voting_option', $optionStorage],
      ]);

    // Transaction must be started exactly once before the insert.
    $transaction = $this->createMock(Transaction::class);
    $this->database->expects($this->once())
      ->method('startTransaction')
      ->willReturn($transaction);

    $insert = $this->createMock(Insert::class);
    $insert->method('fields')->willReturnSelf();
    $insert->method('execute')->willReturn(1);
    $this->database->method('insert')->willReturn($insert);

    $this->service->castVote(questionUuid: 'tx-uuid', optionUuid: 'cccccccc-0000-0000-0000-000000000001', uid: 9);
  }

}
