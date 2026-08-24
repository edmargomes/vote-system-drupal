<?php

declare(strict_types=1);

namespace Drupal\Tests\vs_core\Functional\Api\Contract;

use Drupal\Tests\BrowserTestBase;

/**
 * Verifies the auth token endpoint contract (request/response shape).
 *
 * @group vs_core
 * @group contract
 */
class AuthContractTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['vs_core'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * POST /api/v1/auth/token with valid credentials returns 200 and a token.
   */
  public function testAuthTokenEndpointReturns200WithToken(): void {
    $user = $this->drupalCreateUser(['vote']);
    $body = json_encode(['username' => $user->getAccountName(), 'password' => $user->passRaw]);

    $this->drupalGet('/api/v1/auth/token', [
      'method' => 'POST',
      'body' => $body,
      'headers' => ['Content-Type' => 'application/json'],
    ]);

    $this->assertSession()->statusCodeEquals(200);

    $response = json_decode($this->getSession()->getPage()->getContent(), TRUE);
    $this->assertArrayHasKey('token', $response);
    $this->assertNotEmpty($response['token']);
  }

  /**
   * POST /api/v1/auth/token with bad credentials returns 401.
   */
  public function testAuthTokenEndpointReturns401OnBadCredentials(): void {
    $body = json_encode(['username' => 'nobody', 'password' => 'wrong']);

    $this->drupalGet('/api/v1/auth/token', [
      'method' => 'POST',
      'body' => $body,
      'headers' => ['Content-Type' => 'application/json'],
    ]);

    $this->assertSession()->statusCodeEquals(401);
  }

  /**
   * POST /api/v1/auth/token without body returns 400.
   */
  public function testAuthTokenEndpointReturns400WhenBodyMissing(): void {
    $this->drupalGet('/api/v1/auth/token', [
      'method' => 'POST',
      'headers' => ['Content-Type' => 'application/json'],
    ]);

    $this->assertSession()->statusCodeEquals(400);
  }

  /**
   * Every response includes the X-Correlation-ID header.
   */
  public function testResponseContainsCorrelationIdHeader(): void {
    $body = json_encode(['username' => 'nobody', 'password' => 'wrong']);

    $this->drupalGet('/api/v1/auth/token', [
      'method' => 'POST',
      'body' => $body,
      'headers' => ['Content-Type' => 'application/json'],
    ]);

    $this->assertSession()->responseHeaderExists('X-Correlation-ID');
  }

}
