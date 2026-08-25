<?php

declare(strict_types=1);

namespace Drupal\Tests\vs_core\Functional\Api\Contract;

use Psr\Http\Message\ResponseInterface;
use Drupal\Tests\BrowserTestBase;
use GuzzleHttp\RequestOptions;

/**
 * Verifies the admin results endpoint contract.
 *
 * @group vs_core
 * @group contract
 */
class AdminResultsContractTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['vs_core', 'basic_auth'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Makes a GET request with optional Authorization header.
   *
   * @param string $path
   *   The site-relative path.
   * @param string $authHeader
   *   The Authorization header value, or empty string for unauthenticated.
   *
   * @return \Psr\Http\Message\ResponseInterface
   *   The HTTP response.
   */
  private function apiGet(string $path, string $authHeader = ''): ResponseInterface {
    $url = $this->buildUrl($path, ['absolute' => TRUE]);
    $options = [RequestOptions::HTTP_ERRORS => FALSE];
    if ($authHeader !== '') {
      $options[RequestOptions::HEADERS] = ['Authorization' => $authHeader];
    }
    return $this->getHttpClient()->get($url, $options);
  }

  /**
   * Makes a POST request to the given path and returns the response.
   *
   * @param string $path
   *   The site-relative path.
   * @param array<string, mixed> $body
   *   JSON-encodable request body.
   * @param string $authHeader
   *   The Authorization header value.
   *
   * @return \Psr\Http\Message\ResponseInterface
   *   The HTTP response.
   */
  private function apiPost(string $path, array $body, string $authHeader): ResponseInterface {
    $url = $this->buildUrl($path, ['absolute' => TRUE]);
    return $this->getHttpClient()->post($url, [
      RequestOptions::JSON => $body,
      RequestOptions::HEADERS => ['Authorization' => $authHeader],
      RequestOptions::HTTP_ERRORS => FALSE,
    ]);
  }

  /**
   * GET /api/v1/admin/questions/{uuid}/results without credentials returns 401.
   */
  public function testUnauthenticatedRequestReturns401(): void {
    $response = $this->apiGet('/api/v1/admin/questions/some-uuid/results');
    $this->assertSame(401, $response->getStatusCode());
  }

  /**
   * Non-admin authenticated request returns 403.
   */
  public function testNonAdminReturns403(): void {
    $user = $this->drupalCreateUser(['vote']);
    $authHeader = 'Basic ' . base64_encode($user->getAccountName() . ':' . $user->passRaw);

    $response = $this->apiGet('/api/v1/admin/questions/some-uuid/results', $authHeader);

    $this->assertSame(403, $response->getStatusCode());
  }

  /**
   * Admin on existing question returns 200 with structured results body.
   */
  public function testAdminReturns200WithStructuredBody(): void {
    $admin = $this->drupalCreateUser(['administer voting']);
    $authHeader = 'Basic ' . base64_encode($admin->getAccountName() . ':' . $admin->passRaw);

    $questionStorage = $this->container->get('entity_type.manager')->getStorage('voting_question');
    $question = $questionStorage->create(['title' => 'Results test?', 'status' => TRUE]);
    $question->save();

    $response = $this->apiGet(
      '/api/v1/admin/questions/' . $question->uuid() . '/results',
      $authHeader,
    );

    $this->assertSame(200, $response->getStatusCode());

    $body = json_decode((string) $response->getBody(), TRUE);
    $this->assertArrayHasKey('question', $body);
    $this->assertArrayHasKey('results', $body);
    $this->assertSame($question->uuid(), $body['question']['uuid']);
    $this->assertArrayHasKey('total_votes', $body['question']);
    $this->assertIsArray($body['results']);
  }

  /**
   * Admin results response includes the X-Correlation-ID header.
   */
  public function testResponseIncludesCorrelationIdHeader(): void {
    $admin = $this->drupalCreateUser(['administer voting']);
    $authHeader = 'Basic ' . base64_encode($admin->getAccountName() . ':' . $admin->passRaw);

    $questionStorage = $this->container->get('entity_type.manager')->getStorage('voting_question');
    $question = $questionStorage->create(['title' => 'Header test?', 'status' => TRUE]);
    $question->save();

    $response = $this->apiGet(
      '/api/v1/admin/questions/' . $question->uuid() . '/results',
      $authHeader,
    );

    $this->assertNotEmpty(
      $response->getHeaderLine('X-Correlation-ID'),
      'X-Correlation-ID header must be present on all API responses.'
    );
  }

  /**
   * Result rows have option_uuid, title, votes, percentage; no option_id.
   */
  public function testResultRowsHaveRequiredFields(): void {
    $admin = $this->drupalCreateUser(['administer voting']);
    $adminAuth = 'Basic ' . base64_encode($admin->getAccountName() . ':' . $admin->passRaw);

    $questionStorage = $this->container->get('entity_type.manager')->getStorage('voting_question');
    $question = $questionStorage->create(['title' => 'Row shape?', 'status' => TRUE]);
    $question->save();

    $optionStorage = $this->container->get('entity_type.manager')->getStorage('voting_option');
    $option = $optionStorage->create(['label' => 'Opt1', 'question_id' => $question->id()]);
    $option->save();

    // Cast one vote so results are non-empty.
    $voter = $this->drupalCreateUser(['vote']);
    $voterAuth = 'Basic ' . base64_encode($voter->getAccountName() . ':' . $voter->passRaw);

    $this->apiPost(
      '/api/v1/questions/' . $question->uuid() . '/vote',
      ['option_uuid' => $option->uuid()],
      $voterAuth,
    );

    $response = $this->apiGet(
      '/api/v1/admin/questions/' . $question->uuid() . '/results',
      $adminAuth,
    );

    $body = json_decode((string) $response->getBody(), TRUE);
    $this->assertNotEmpty($body['results']);

    $row = $body['results'][0];
    $this->assertArrayHasKey('option_uuid', $row);
    $this->assertArrayHasKey('title', $row);
    $this->assertArrayHasKey('votes', $row);
    $this->assertArrayHasKey('percentage', $row);
    $this->assertArrayNotHasKey('option_id', $row);
    $this->assertSame($option->uuid(), $row['option_uuid']);
  }

  /**
   * GET 200 admin results response does not carry Cache-Control: no-store.
   */
  public function testGet200ResponseDoesNotHaveNoStoreDirective(): void {
    $admin = $this->drupalCreateUser(['administer voting']);
    $authHeader = 'Basic ' . base64_encode($admin->getAccountName() . ':' . $admin->passRaw);

    $questionStorage = $this->container->get('entity_type.manager')
      ->getStorage('voting_question');
    $question = $questionStorage->create(['title' => 'CC no-store?', 'status' => TRUE]);
    $question->save();

    $response = $this->apiGet(
      '/api/v1/admin/questions/' . $question->uuid() . '/results',
      $authHeader,
    );

    $this->assertSame(200, $response->getStatusCode());
    $cacheControl = $response->getHeaderLine('Cache-Control');
    $this->assertStringNotContainsString('no-store', $cacheControl);
  }

  /**
   * GET 200 admin results response Cache-Control allows revalidation.
   */
  public function testGet200ResponseCacheControlAllowsRevalidation(): void {
    $admin = $this->drupalCreateUser(['administer voting']);
    $authHeader = 'Basic ' . base64_encode($admin->getAccountName() . ':' . $admin->passRaw);

    $questionStorage = $this->container->get('entity_type.manager')
      ->getStorage('voting_question');
    $question = $questionStorage->create([
      'title' => 'CC revalidate admin?',
      'status' => TRUE,
    ]);
    $question->save();

    $response = $this->apiGet(
      '/api/v1/admin/questions/' . $question->uuid() . '/results',
      $authHeader,
    );

    $this->assertSame(200, $response->getStatusCode());
    $cacheControl = $response->getHeaderLine('Cache-Control');
    $hasNoCache = str_contains($cacheControl, 'no-cache');
    $hasMustRevalidate = str_contains($cacheControl, 'must-revalidate');
    $this->assertTrue(
      $hasNoCache || $hasMustRevalidate,
      "Cache-Control must contain 'no-cache' or 'must-revalidate'; got: $cacheControl"
    );
  }

}
