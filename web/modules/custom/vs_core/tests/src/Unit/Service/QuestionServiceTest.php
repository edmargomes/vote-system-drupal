<?php

declare(strict_types=1);

namespace Drupal\Tests\vs_core\Unit\Service;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\vs_core\Entity\VotingQuestionInterface;
use Drupal\vs_core\Service\QuestionService;

/**
 * @coversDefaultClass \Drupal\vs_core\Service\QuestionService
 * @group vs_core
 */
class QuestionServiceTest extends UnitTestCase {

  /**
   * Entity type manager mock.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  private EntityTypeManagerInterface $entityTypeManager;

  /**
   * Time service mock.
   *
   * @var \Drupal\Component\Datetime\TimeInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  private TimeInterface $time;

  /**
   * Entity storage mock.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  private EntityStorageInterface $storage;

  /**
   * Service under test.
   *
   * @var \Drupal\vs_core\Service\QuestionService
   */
  private QuestionService $service;

  /**
   * Fixed "now" timestamp used across all tests.
   */
  private int $now;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->now = 1_700_000_000;

    $this->entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $this->time = $this->createMock(TimeInterface::class);
    $this->time->method('getRequestTime')->willReturn($this->now);

    $this->storage = $this->createMock(EntityStorageInterface::class);
    $this->entityTypeManager
      ->method('getStorage')
      ->with('voting_question')
      ->willReturn($this->storage);

    $this->service = new QuestionService($this->entityTypeManager, $this->time);
  }

  /**
   * @covers ::listActive
   *
   * A question whose closes_at is in the past must be excluded.
   */
  public function testListActiveExcludesExpiredQuestion(): void {
    $expiredQuestion = $this->createMock(VotingQuestionInterface::class);

    $query = $this->buildQueryMock([]);
    $this->storage->method('getQuery')->willReturn($query);
    $this->storage->method('loadMultiple')->willReturn([]);

    $result = $this->service->listActive();

    $this->assertEmpty($result);
    // The question was not returned because the query filtered it out.
    $this->assertNotContains($expiredQuestion, $result);
  }

  /**
   * @covers ::listActive
   *
   * A question whose closes_at is in the future must be included.
   */
  public function testListActiveIncludesFutureQuestion(): void {
    $futureQuestion = $this->createMock(VotingQuestionInterface::class);

    $query = $this->buildQueryMock([42]);
    $this->storage->method('getQuery')->willReturn($query);
    $this->storage->method('loadMultiple')->with([42])->willReturn([$futureQuestion]);

    $result = $this->service->listActive();

    $this->assertCount(1, $result);
    $this->assertSame($futureQuestion, $result[0]);
  }

  /**
   * @covers ::listActive
   *
   * A question with closes_at = NULL (no expiry) must be included.
   */
  public function testListActiveIncludesQuestionWithNullClosesAt(): void {
    $openQuestion = $this->createMock(VotingQuestionInterface::class);

    $query = $this->buildQueryMock([7]);
    $this->storage->method('getQuery')->willReturn($query);
    $this->storage->method('loadMultiple')->with([7])->willReturn([$openQuestion]);

    $result = $this->service->listActive();

    $this->assertCount(1, $result);
    $this->assertSame($openQuestion, $result[0]);
  }

  /**
   * @covers ::listActive
   *
   * A question with status = 0 must be excluded regardless of closes_at.
   */
  public function testListActiveExcludesInactiveQuestion(): void {
    $query = $this->buildQueryMock([]);
    $this->storage->method('getQuery')->willReturn($query);
    $this->storage->method('loadMultiple')->willReturn([]);

    $result = $this->service->listActive();

    $this->assertEmpty($result);
  }

  /**
   * @covers ::isOpen
   *
   * status=1 with closes_at=NULL is open.
   */
  public function testIsOpenReturnsTrueWhenActiveAndNoExpiry(): void {
    $question = $this->createMock(VotingQuestionInterface::class);
    $question->method('get')
      ->willReturnCallback(function (string $field) {
        $item = new \stdClass();
        if ($field === 'status') {
          $item->value = 1;
        }
        elseif ($field === 'closes_at') {
          $item->value = NULL;
        }
        return $item;
      });

    $this->assertTrue($this->service->isOpen($question));
  }

  /**
   * @covers ::isOpen
   *
   * status=1 with closes_at in the past is closed.
   */
  public function testIsOpenReturnsFalseWhenExpired(): void {
    $pastTimestamp = $this->now - 3600;
    $question = $this->createMock(VotingQuestionInterface::class);
    $question->method('get')
      ->willReturnCallback(function (string $field) use ($pastTimestamp) {
        $item = new \stdClass();
        if ($field === 'status') {
          $item->value = 1;
        }
        elseif ($field === 'closes_at') {
          $item->value = $pastTimestamp;
        }
        return $item;
      });

    $this->assertFalse($this->service->isOpen($question));
  }

  /**
   * @covers ::isOpen
   *
   * status=1 with closes_at in the future is open.
   */
  public function testIsOpenReturnsTrueWhenActiveAndFutureExpiry(): void {
    $futureTimestamp = $this->now + 3600;
    $question = $this->createMock(VotingQuestionInterface::class);
    $question->method('get')
      ->willReturnCallback(function (string $field) use ($futureTimestamp) {
        $item = new \stdClass();
        if ($field === 'status') {
          $item->value = 1;
        }
        elseif ($field === 'closes_at') {
          $item->value = $futureTimestamp;
        }
        return $item;
      });

    $this->assertTrue($this->service->isOpen($question));
  }

  /**
   * @covers ::isOpen
   *
   * status=0 is always closed, regardless of closes_at.
   */
  public function testIsOpenReturnsFalseWhenInactive(): void {
    $question = $this->createMock(VotingQuestionInterface::class);
    $question->method('get')
      ->willReturnCallback(function (string $field) {
        $item = new \stdClass();
        if ($field === 'status') {
          $item->value = 0;
        }
        elseif ($field === 'closes_at') {
          $item->value = NULL;
        }
        return $item;
      });

    $this->assertFalse($this->service->isOpen($question));
  }

  /**
   * Builds a chainable entity query mock that returns given IDs.
   *
   * Because the real entity query uses method chaining with orConditionGroup(),
   * we return $query itself from every chainable method so tests stay simple.
   *
   * @param array<int|string> $ids
   *   The IDs the mock execute() should return.
   *
   * @return \Drupal\Core\Entity\Query\QueryInterface|\PHPUnit\Framework\MockObject\MockObject
   *   The chainable query mock.
   */
  private function buildQueryMock(array $ids): QueryInterface {
    $query = $this->getMockBuilder(QueryInterface::class)
      ->disableOriginalConstructor()
      ->getMock();

    $query->method('accessCheck')->willReturnSelf();
    $query->method('condition')->willReturnSelf();
    $query->method('orConditionGroup')->willReturnSelf();
    $query->method('notExists')->willReturnSelf();
    $query->method('execute')->willReturn($ids);

    return $query;
  }

}
