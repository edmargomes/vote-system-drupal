<?php

declare(strict_types=1);

namespace Drupal\Tests\vs_core\Unit\Service;

use Drupal\Core\Database\Exception\IntegrityConstraintViolationException;
use Drupal\Core\Database\Query\Insert;
use Drupal\Core\Database\Transaction;
use Drupal\Core\Session\AccountInterface;
use Drupal\vs_core\Entity\VotingOptionInterface;
use Drupal\vs_core\Entity\VotingQuestionInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\vs_core\Exception\DuplicateVoteException;
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
   * @covers ::isVotingEnabled
   */
  public function testIsVotingEnabledReturnsTrueWhenEnabled(): void {
    $config = $this->createMock(ImmutableConfig::class);
    $config->method('get')->with('voting_enabled')->willReturn(TRUE);
    $this->configFactory->method('get')->with('vs_core.settings')->willReturn($config);

    $this->assertTrue($this->service->isVotingEnabled());
  }

  /**
   * @covers ::isVotingEnabled
   */
  public function testIsVotingEnabledReturnsFalseWhenDisabled(): void {
    $config = $this->createMock(ImmutableConfig::class);
    $config->method('get')->with('voting_enabled')->willReturn(FALSE);
    $this->configFactory->method('get')->with('vs_core.settings')->willReturn($config);

    $this->assertFalse($this->service->isVotingEnabled());
  }

  /**
   * @covers ::registerVote
   */
  public function testRegisterVoteThrowsDuplicateVoteExceptionOnConstraintViolation(): void {
    $user = $this->createMock(AccountInterface::class);
    $user->method('id')->willReturn(7);

    $question = $this->createMock(VotingQuestionInterface::class);
    $question->method('id')->willReturn(10);

    $option = $this->createMock(VotingOptionInterface::class);
    $option->method('id')->willReturn(3);

    $this->database->method('startTransaction')->willReturn(
      $this->createMock(Transaction::class)
    );
    $this->database->method('insert')->willThrowException(
      new IntegrityConstraintViolationException('Duplicate entry', 23000, new \Exception())
    );

    $this->expectException(DuplicateVoteException::class);

    $this->service->registerVote($user, $question, $option);
  }

  /**
   * @covers ::registerVote
   */
  public function testRegisterVoteInsertsRowOnSuccess(): void {
    $user = $this->createMock(AccountInterface::class);
    $user->method('id')->willReturn(5);

    $question = $this->createMock(VotingQuestionInterface::class);
    $question->method('id')->willReturn(10);

    $option = $this->createMock(VotingOptionInterface::class);
    $option->method('id')->willReturn(3);

    $transaction = $this->createMock(Transaction::class);
    $this->database->expects($this->once())
      ->method('startTransaction')
      ->willReturn($transaction);

    $insert = $this->createMock(Insert::class);
    $insert->method('fields')->willReturnSelf();
    $insert->method('execute')->willReturn(1);

    $this->database->expects($this->once())->method('insert')->willReturn($insert);

    $this->service->registerVote($user, $question, $option);
  }

  /**
   * @covers ::registerVote
   */
  public function testRegisterVoteWrapsInsertInTransaction(): void {
    $user = $this->createMock(AccountInterface::class);
    $user->method('id')->willReturn(9);

    $question = $this->createMock(VotingQuestionInterface::class);
    $question->method('id')->willReturn(10);

    $option = $this->createMock(VotingOptionInterface::class);
    $option->method('id')->willReturn(1);

    // Transaction must be started exactly once before the insert.
    $transaction = $this->createMock(Transaction::class);
    $this->database->expects($this->once())
      ->method('startTransaction')
      ->willReturn($transaction);

    $insert = $this->createMock(Insert::class);
    $insert->method('fields')->willReturnSelf();
    $insert->method('execute')->willReturn(1);
    $this->database->method('insert')->willReturn($insert);

    $this->service->registerVote($user, $question, $option);
  }

}
