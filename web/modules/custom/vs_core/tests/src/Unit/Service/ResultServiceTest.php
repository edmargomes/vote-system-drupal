<?php

declare(strict_types=1);

namespace Drupal\Tests\vs_core\Unit\Service;

use PHPUnit\Framework\MockObject\MockObject;
use Drupal\Core\Database\Query\SelectInterface;
use Drupal\vs_core\Entity\VotingQuestionInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Database\StatementInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Tests\UnitTestCase;
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
  public function testGetResultsReturnsAggregatedCountsWithPercentages(): void {
    $question = $this->createMock(VotingQuestionInterface::class);
    $question->method('id')->willReturn(5);

    // DB returns raw vote counts per option with UUID and title from a JOIN.
    $rows = [
      (object) ['option_uuid' => 'uuid-a', 'title' => 'Option A', 'votes' => '10'],
      (object) ['option_uuid' => 'uuid-b', 'title' => 'Option B', 'votes' => '5'],
    ];

    $stmt = $this->createMock(StatementInterface::class);
    $stmt->method('fetchAll')->willReturn($rows);

    $select = $this->buildSelectMock($stmt);
    $this->database->method('select')->willReturn($select);

    $results = $this->service->getResults($question);

    $this->assertCount(2, $results);

    $this->assertSame('uuid-a', $results[0]['option_uuid']);
    $this->assertSame('Option A', $results[0]['title']);
    $this->assertSame(10, $results[0]['votes']);
    $this->assertArrayHasKey('percentage', $results[0]);

    $this->assertSame('uuid-b', $results[1]['option_uuid']);
    $this->assertSame(5, $results[1]['votes']);
    $this->assertArrayHasKey('percentage', $results[1]);

    // Percentages must sum to 100 (within float rounding).
    $sum = $results[0]['percentage'] + $results[1]['percentage'];
    $this->assertEqualsWithDelta(100.0, $sum, 0.01);
  }

  /**
   * @covers ::getResults
   */
  public function testGetResultsReturnsEmptyArrayWhenNoVotesCast(): void {
    $question = $this->createMock(VotingQuestionInterface::class);
    $question->method('id')->willReturn(9);

    $stmt = $this->createMock(StatementInterface::class);
    $stmt->method('fetchAll')->willReturn([]);

    $select = $this->buildSelectMock($stmt);
    $this->database->method('select')->willReturn($select);

    $results = $this->service->getResults($question);

    $this->assertSame([], $results);
  }

  /**
   * @covers ::getResults
   */
  public function testGetResultsNeverReturnsIntegerOptionId(): void {
    $question = $this->createMock(VotingQuestionInterface::class);
    $question->method('id')->willReturn(5);

    $rows = [
      (object) ['option_uuid' => 'uuid-x', 'title' => 'X', 'votes' => '3'],
    ];

    $stmt = $this->createMock(StatementInterface::class);
    $stmt->method('fetchAll')->willReturn($rows);

    $select = $this->buildSelectMock($stmt);
    $this->database->method('select')->willReturn($select);

    $results = $this->service->getResults($question);

    $this->assertArrayNotHasKey('option_id', $results[0]);
    $this->assertArrayHasKey('option_uuid', $results[0]);
  }

  /**
   * Builds a chainable select query mock that returns the given statement.
   *
   * @param \Drupal\Core\Database\StatementInterface $stmt
   *   Statement to return from execute().
   *
   * @return \PHPUnit\Framework\MockObject\MockObject
   *   Select query mock.
   */
  private function buildSelectMock(StatementInterface $stmt): MockObject {
    $select = $this->getMockBuilder(SelectInterface::class)
      ->disableOriginalConstructor()
      ->getMock();

    $select->method('fields')->willReturnSelf();
    $select->method('join')->willReturn('o');
    $select->method('condition')->willReturnSelf();
    $select->method('groupBy')->willReturnSelf();
    $select->method('addExpression')->willReturn('vote_count');
    $select->method('execute')->willReturn($stmt);

    return $select;
  }

}
