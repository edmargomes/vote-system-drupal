<?php

declare(strict_types=1);

namespace Drupal\Tests\vs_core\Unit\Service;

use Drupal\Core\Database\Query\SelectInterface;
use PHPUnit\Framework\MockObject\MockObject;
use Drupal\Core\Database\Query\Delete;
use Drupal\Core\Database\Query\Insert;
use Psr\Log\LoggerInterface;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Database\StatementInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\vs_core\Service\AuthTokenService;

/**
 * @coversDefaultClass \Drupal\vs_core\Service\AuthTokenService
 * @group vs_core
 */
class AuthTokenServiceTest extends UnitTestCase {

  /**
   * Database connection mock.
   *
   * @var \Drupal\Core\Database\Connection|\PHPUnit\Framework\MockObject\MockObject
   */
  private Connection $database;

  /**
   * Logger channel factory mock.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactoryInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  private LoggerChannelFactoryInterface $loggerFactory;

  /**
   * Service under test.
   *
   * @var \Drupal\vs_core\Service\AuthTokenService
   */
  private AuthTokenService $service;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->database = $this->createMock(Connection::class);

    $time = $this->createMock(TimeInterface::class);
    $time->method('getRequestTime')->willReturn(1_700_000_000);

    $this->loggerFactory = $this->createMock(LoggerChannelFactoryInterface::class);
    $this->loggerFactory->method('get')->willReturn(
      $this->createMock(LoggerInterface::class)
    );

    $this->service = new AuthTokenService($this->database, $time, $this->loggerFactory);
  }

  /**
   * @covers ::issue
   */
  public function testIssueReturnsNonEmptyString(): void {
    $insert = $this->createMock(Insert::class);
    $insert->method('fields')->willReturnSelf();
    $insert->method('execute')->willReturn(1);

    $this->database->method('insert')->willReturn($insert);

    $token = $this->service->issue(42);

    $this->assertIsString($token);
    $this->assertNotEmpty($token);
  }

  /**
   * @covers ::issue
   */
  public function testIssueTokenHasExpectedLength(): void {
    $insert = $this->createMock(Insert::class);
    $insert->method('fields')->willReturnSelf();
    $insert->method('execute')->willReturn(1);

    $this->database->method('insert')->willReturn($insert);

    $token = $this->service->issue(1);

    // Token must be a 64-char hex string (32 random bytes).
    $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $token);
  }

  /**
   * @covers ::validate
   */
  public function testValidateReturnsTrueForValidNonExpiredToken(): void {
    $stmt = $this->createMock(StatementInterface::class);
    $stmt->method('fetchObject')->willReturn((object) [
      'uid' => 7,
      'expires_at' => 1_700_000_000 + 3600,
    ]);

    $this->database->method('select')->willReturn(
      $this->buildSelectMock($stmt)
    );

    $result = $this->service->validate('valid-token-string');

    $this->assertTrue($result);
  }

  /**
   * @covers ::validate
   */
  public function testValidateReturnsFalseForExpiredToken(): void {
    $stmt = $this->createMock(StatementInterface::class);
    $stmt->method('fetchObject')->willReturn((object) [
      'uid' => 7,
      'expires_at' => 1_700_000_000 - 1,
    ]);

    $this->database->method('select')->willReturn(
      $this->buildSelectMock($stmt)
    );

    $result = $this->service->validate('expired-token');

    $this->assertFalse($result);
  }

  /**
   * @covers ::validate
   */
  public function testValidateReturnsFalseForUnknownToken(): void {
    $stmt = $this->createMock(StatementInterface::class);
    $stmt->method('fetchObject')->willReturn(FALSE);

    $this->database->method('select')->willReturn(
      $this->buildSelectMock($stmt)
    );

    $result = $this->service->validate('unknown-token');

    $this->assertFalse($result);
  }

  /**
   * @covers ::getUidByToken
   */
  public function testGetUidByTokenReturnsIntOnMatch(): void {
    $stmt = $this->createMock(StatementInterface::class);
    $stmt->method('fetchField')->willReturn('42');

    $this->database->method('select')->willReturn(
      $this->buildSelectMock($stmt)
    );

    $uid = $this->service->getUidByToken('some-token');

    $this->assertSame(42, $uid);
  }

  /**
   * @covers ::getUidByToken
   */
  public function testGetUidByTokenReturnsNullOnNoMatch(): void {
    $stmt = $this->createMock(StatementInterface::class);
    $stmt->method('fetchField')->willReturn(FALSE);

    $this->database->method('select')->willReturn(
      $this->buildSelectMock($stmt)
    );

    $uid = $this->service->getUidByToken('bad-token');

    $this->assertNull($uid);
  }

  /**
   * @covers ::revoke
   */
  public function testRevokeDeletesToken(): void {
    $delete = $this->createMock(Delete::class);
    $delete->method('condition')->willReturnSelf();
    $delete->method('execute')->willReturn(1);

    $this->database->expects($this->once())
      ->method('delete')
      ->with('vs_core_api_token')
      ->willReturn($delete);

    $this->service->revoke('some-token');
  }

  /**
   * @covers ::revoke
   * @covers ::validate
   */
  public function testValidateReturnsFalseAfterRevocation(): void {
    // Select returns no row — DB record was deleted by revoke().
    $stmt = $this->createMock(StatementInterface::class);
    $stmt->method('fetchObject')->willReturn(FALSE);

    $this->database->method('select')->willReturn(
      $this->buildSelectMock($stmt)
    );

    $delete = $this->createMock(Delete::class);
    $delete->method('condition')->willReturnSelf();
    $delete->method('execute')->willReturn(1);
    $this->database->method('delete')->willReturn($delete);

    $this->service->revoke('revoked-token');
    $result = $this->service->validate('revoked-token');

    $this->assertFalse($result);
  }

  /**
   * Builds a chainable select query mock that returns the given statement.
   */
  private function buildSelectMock(StatementInterface $stmt): MockObject {
    $select = $this->getMockBuilder(SelectInterface::class)
      ->disableOriginalConstructor()
      ->getMock();

    $select->method('fields')->willReturnSelf();
    $select->method('condition')->willReturnSelf();
    $select->method('range')->willReturnSelf();
    $select->method('execute')->willReturn($stmt);

    return $select;
  }

}
