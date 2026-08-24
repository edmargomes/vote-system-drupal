<?php

declare(strict_types=1);

namespace Drupal\Tests\vs_core\Unit\Service;

use Drupal\Core\Database\Query\SelectInterface;
use Drupal\vs_core\Entity\VotingQuestionInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Database\StatementInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\vs_core\Exception\VotingNotFoundException;
use Drupal\vs_core\Service\ResultService;

/**
 * @coversDefaultClass \Drupal\vs_core\Service\ResultService
 * @group vs_core
 */
class ResultServiceTest extends UnitTestCase {

  /**
   * Database connection mock.
   *
   * @var \Drupal\Core\Database\Connection|\PHPUnit\Framework\MockObject\MockObject
   */
  private Connection $database;

  /**
   * Entity type manager mock.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  private EntityTypeManagerInterface $entityTypeManager;

  /**
   * Service under test.
   *
   * @var \Drupal\vs_core\Service\ResultService
   */
  private ResultService $service;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->database = $this->createMock(Connection::class);
    $this->entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);

    $this->service = new ResultService($this->database, $this->entityTypeManager);
  }

  /**
   * @covers ::getResults
   */
  public function testGetResultsThrowsWhenQuestionNotFound(): void {
    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->method('loadByProperties')
      ->with(['uuid' => 'bad-uuid'])
      ->willReturn([]);

    $this->entityTypeManager->method('getStorage')
      ->with('voting_question')
      ->willReturn($storage);

    $this->expectException(VotingNotFoundException::class);

    $this->service->getResults('bad-uuid');
  }

  /**
   * @covers ::getResults
   */
  public function testGetResultsReturnsAggregatedCounts(): void {
    $question = $this->createMock(VotingQuestionInterface::class);
    $question->method('id')->willReturn(5);

    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->method('loadByProperties')->willReturn([$question]);

    $this->entityTypeManager->method('getStorage')
      ->with('voting_question')
      ->willReturn($storage);

    $rows = [
      (object) ['option_id' => 1, 'total' => '10'],
      (object) ['option_id' => 2, 'total' => '5'],
    ];

    $stmt = $this->createMock(StatementInterface::class);
    $stmt->method('fetchAll')->willReturn($rows);

    $select = $this->getMockBuilder(SelectInterface::class)
      ->disableOriginalConstructor()
      ->getMock();
    $select->method('fields')->willReturnSelf();
    $select->method('condition')->willReturnSelf();
    $select->method('groupBy')->willReturnSelf();
    $select->method('addExpression')->willReturnSelf();
    $select->method('execute')->willReturn($stmt);

    $this->database->method('select')->willReturn($select);

    $results = $this->service->getResults('some-uuid');

    $this->assertCount(2, $results);
    $this->assertSame(10, $results[0]['total']);
    $this->assertSame(5, $results[1]['total']);
  }

  /**
   * @covers ::getResults
   */
  public function testGetResultsReturnsEmptyArrayWhenNoVotesCast(): void {
    $question = $this->createMock(VotingQuestionInterface::class);
    $question->method('id')->willReturn(9);

    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->method('loadByProperties')->willReturn([$question]);

    $this->entityTypeManager->method('getStorage')
      ->with('voting_question')
      ->willReturn($storage);

    $stmt = $this->createMock(StatementInterface::class);
    $stmt->method('fetchAll')->willReturn([]);

    $select = $this->getMockBuilder(SelectInterface::class)
      ->disableOriginalConstructor()
      ->getMock();
    $select->method('fields')->willReturnSelf();
    $select->method('condition')->willReturnSelf();
    $select->method('groupBy')->willReturnSelf();
    $select->method('addExpression')->willReturnSelf();
    $select->method('execute')->willReturn($stmt);

    $this->database->method('select')->willReturn($select);

    $results = $this->service->getResults('empty-uuid');

    $this->assertSame([], $results);
  }

}
