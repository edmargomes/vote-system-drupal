<?php

declare(strict_types=1);

namespace Drupal\Tests\vs_core\Functional\Api\Contract;

use Drupal\Tests\BrowserTestBase;

/**
 * Verifies the question list endpoint contract.
 *
 * @group vs_core
 * @group contract
 */
class QuestionListContractTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['vs_core', 'basic_auth'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * GET /api/v1/questions without credentials returns 401.
   */
  public function testUnauthenticatedRequestReturns401(): void {
    $this->drupalGet('/api/v1/questions');
    $this->assertSession()->statusCodeEquals(401);
  }

  /**
   * Authenticated request returns 200 with structured body.
   */
  public function testAuthenticatedRequestReturns200WithStructuredResponse(): void {
    $user = $this->drupalCreateUser(['vote']);
    $authHeader = 'Basic ' . base64_encode($user->getAccountName() . ':' . $user->passRaw);

    $this->drupalGet('/api/v1/questions', [], ['Authorization' => $authHeader]);

    $this->assertSession()->statusCodeEquals(200);

    $body = json_decode($this->getSession()->getPage()->getContent(), TRUE);
    $this->assertArrayHasKey('data', $body);
    $this->assertArrayHasKey('meta', $body);
    $this->assertIsArray($body['data']);
  }

  /**
   * Each question in the list has the required fields.
   */
  public function testQuestionListItemsHaveRequiredFields(): void {
    $user = $this->drupalCreateUser(['vote']);
    $authHeader = 'Basic ' . base64_encode($user->getAccountName() . ':' . $user->passRaw);

    // Create a question so the list is non-empty.
    $storage = $this->container->get('entity_type.manager')->getStorage('voting_question');
    $storage->create(['title' => 'Contract question', 'status' => TRUE])->save();

    $this->drupalGet('/api/v1/questions', [], ['Authorization' => $authHeader]);

    $body = json_decode($this->getSession()->getPage()->getContent(), TRUE);
    $this->assertNotEmpty($body['data']);

    $first = $body['data'][0];
    $this->assertArrayHasKey('uuid', $first);
    $this->assertArrayHasKey('title', $first);
    $this->assertArrayHasKey('options_count', $first);
    $this->assertArrayNotHasKey('id', $first);
  }

  /**
   * Meta.total reflects the number of active questions.
   */
  public function testMetaTotalMatchesQuestionCount(): void {
    $user = $this->drupalCreateUser(['vote']);
    $authHeader = 'Basic ' . base64_encode($user->getAccountName() . ':' . $user->passRaw);

    $storage = $this->container->get('entity_type.manager')->getStorage('voting_question');
    $storage->create(['title' => 'Question one', 'status' => TRUE])->save();
    $storage->create(['title' => 'Question two', 'status' => TRUE])->save();

    $this->drupalGet('/api/v1/questions', [], ['Authorization' => $authHeader]);

    $body = json_decode($this->getSession()->getPage()->getContent(), TRUE);
    $this->assertSame(2, $body['meta']['total']);
  }

  /**
   * Successful response includes the X-Correlation-ID header.
   */
  public function testResponseIncludesCorrelationIdHeader(): void {
    $user = $this->drupalCreateUser(['vote']);
    $authHeader = 'Basic ' . base64_encode($user->getAccountName() . ':' . $user->passRaw);

    $this->drupalGet('/api/v1/questions', [], ['Authorization' => $authHeader]);

    $this->assertNotEmpty(
      $this->getSession()->getResponseHeader('X-Correlation-ID'),
      'X-Correlation-ID header must be present on all API responses.'
    );
  }

  /**
   * GET 200 response does not carry Cache-Control: no-store.
   *
   * GET responses carry Drupal cache tags and must never be marked no-store —
   * that would prevent Varnish/CDN from using them.
   */
  public function testGet200ResponseDoesNotHaveNoStoreDirective(): void {
    $user = $this->drupalCreateUser(['vote']);
    $authHeader = 'Basic ' . base64_encode($user->getAccountName() . ':' . $user->passRaw);

    $this->drupalGet('/api/v1/questions', [], ['Authorization' => $authHeader]);

    $this->assertSession()->statusCodeEquals(200);
    $cacheControl = $this->getSession()->getResponseHeader('Cache-Control') ?? '';
    $this->assertStringNotContainsString('no-store', $cacheControl);
  }

  /**
   * GET 200 response Cache-Control allows revalidation.
   *
   * Drupal sets must-revalidate or no-cache for authenticated GET responses
   * when a CacheableResponse is returned.
   */
  public function testGet200ResponseCacheControlAllowsRevalidation(): void {
    $user = $this->drupalCreateUser(['vote']);
    $authHeader = 'Basic ' . base64_encode($user->getAccountName() . ':' . $user->passRaw);

    $this->drupalGet('/api/v1/questions', [], ['Authorization' => $authHeader]);

    $this->assertSession()->statusCodeEquals(200);
    $cacheControl = $this->getSession()->getResponseHeader('Cache-Control') ?? '';
    $hasNoCache = str_contains($cacheControl, 'no-cache');
    $hasMustRevalidate = str_contains($cacheControl, 'must-revalidate');
    $this->assertTrue(
      $hasNoCache || $hasMustRevalidate,
      "Cache-Control must contain 'no-cache' or 'must-revalidate'; got: $cacheControl"
    );
  }

}
