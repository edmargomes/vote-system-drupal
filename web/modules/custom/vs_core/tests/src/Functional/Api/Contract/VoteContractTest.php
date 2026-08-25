<?php

declare(strict_types=1);

namespace Drupal\Tests\vs_core\Functional\Api\Contract;

use Psr\Http\Message\ResponseInterface;
use Drupal\Tests\BrowserTestBase;
use GuzzleHttp\RequestOptions;

/**
 * Verifies the vote endpoint contract.
 *
 * @group vs_core
 * @group contract
 */
class VoteContractTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['vs_core', 'basic_auth'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Makes a POST request to the given path and returns the response.
   *
   * @param string $path
   *   The site-relative path.
   * @param array<string, mixed> $body
   *   JSON-encodable request body.
   * @param string $authHeader
   *   The Authorization header value, or empty string for unauthenticated.
   *
   * @return \Psr\Http\Message\ResponseInterface
   *   The HTTP response.
   */
  private function apiPost(string $path, array $body, string $authHeader = ''): ResponseInterface {
    $url = $this->buildUrl($path, ['absolute' => TRUE]);
    $options = [
      RequestOptions::JSON => $body,
      RequestOptions::HTTP_ERRORS => FALSE,
    ];
    if ($authHeader !== '') {
      $options[RequestOptions::HEADERS] = ['Authorization' => $authHeader];
    }
    return $this->getHttpClient()->post($url, $options);
  }

  /**
   * POST /api/v1/questions/{uuid}/vote without credentials returns 401.
   */
  public function testVoteWithoutCredentialsReturns401(): void {
    $response = $this->apiPost(
      '/api/v1/questions/some-uuid/vote',
      ['option_uuid' => '00000000-0000-0000-0000-000000000001'],
    );

    $this->assertSame(401, $response->getStatusCode());
  }

  /**
   * POST /api/v1/questions/{uuid}/vote with valid data returns 200.
   */
  public function testValidVoteReturns200(): void {
    $user = $this->drupalCreateUser(['vote']);
    $authHeader = 'Basic ' . base64_encode($user->getAccountName() . ':' . $user->passRaw);

    $questionStorage = $this->container->get('entity_type.manager')->getStorage('voting_question');
    $question = $questionStorage->create(['title' => 'Vote test?', 'status' => TRUE]);
    $question->save();

    $optionStorage = $this->container->get('entity_type.manager')->getStorage('voting_option');
    $option = $optionStorage->create(['label' => 'Yes', 'question_id' => $question->id()]);
    $option->save();

    $response = $this->apiPost(
      '/api/v1/questions/' . $question->uuid() . '/vote',
      ['option_uuid' => $option->uuid()],
      $authHeader,
    );

    $this->assertSame(200, $response->getStatusCode());

    $body = json_decode((string) $response->getBody(), TRUE);
    $this->assertSame('success', $body['status']);
  }

  /**
   * Voting twice with the same credentials returns 409 Conflict.
   */
  public function testDuplicateVoteReturns409(): void {
    $user = $this->drupalCreateUser(['vote']);
    $authHeader = 'Basic ' . base64_encode($user->getAccountName() . ':' . $user->passRaw);

    $questionStorage = $this->container->get('entity_type.manager')->getStorage('voting_question');
    $question = $questionStorage->create(['title' => 'Dup vote?', 'status' => TRUE]);
    $question->save();

    $optionStorage = $this->container->get('entity_type.manager')->getStorage('voting_option');
    $option = $optionStorage->create(['label' => 'Opt', 'question_id' => $question->id()]);
    $option->save();

    $path = '/api/v1/questions/' . $question->uuid() . '/vote';
    $body = ['option_uuid' => $option->uuid()];

    $first = $this->apiPost($path, $body, $authHeader);
    $this->assertSame(200, $first->getStatusCode());

    $second = $this->apiPost($path, $body, $authHeader);
    $this->assertSame(409, $second->getStatusCode());
  }

  /**
   * POST with missing option_uuid returns 422.
   */
  public function testMissingOptionUuidReturns422(): void {
    $user = $this->drupalCreateUser(['vote']);
    $authHeader = 'Basic ' . base64_encode($user->getAccountName() . ':' . $user->passRaw);

    $questionStorage = $this->container->get('entity_type.manager')->getStorage('voting_question');
    $question = $questionStorage->create(['title' => 'No option?', 'status' => TRUE]);
    $question->save();

    $response = $this->apiPost(
      '/api/v1/questions/' . $question->uuid() . '/vote',
      [],
      $authHeader,
    );

    $this->assertSame(422, $response->getStatusCode());
  }

  /**
   * Successful vote response includes the X-Correlation-ID header.
   */
  public function testResponseIncludesCorrelationIdHeader(): void {
    $user = $this->drupalCreateUser(['vote']);
    $authHeader = 'Basic ' . base64_encode($user->getAccountName() . ':' . $user->passRaw);

    $questionStorage = $this->container->get('entity_type.manager')->getStorage('voting_question');
    $question = $questionStorage->create(['title' => 'Header test?', 'status' => TRUE]);
    $question->save();

    $optionStorage = $this->container->get('entity_type.manager')->getStorage('voting_option');
    $option = $optionStorage->create(['label' => 'Yes', 'question_id' => $question->id()]);
    $option->save();

    $response = $this->apiPost(
      '/api/v1/questions/' . $question->uuid() . '/vote',
      ['option_uuid' => $option->uuid()],
      $authHeader,
    );

    $this->assertNotEmpty(
      $response->getHeaderLine('X-Correlation-ID'),
      'X-Correlation-ID header must be present on all API responses.'
    );
  }

  /**
   * When voting is disabled, POST /vote returns 403.
   */
  public function testVoteWhenDisabledReturns403(): void {
    $this->config('vs_core.settings')->set('voting_enabled', FALSE)->save();

    $user = $this->drupalCreateUser(['vote']);
    $authHeader = 'Basic ' . base64_encode($user->getAccountName() . ':' . $user->passRaw);

    $questionStorage = $this->container->get('entity_type.manager')->getStorage('voting_question');
    $question = $questionStorage->create(['title' => 'Disabled?', 'status' => TRUE]);
    $question->save();

    $optionStorage = $this->container->get('entity_type.manager')->getStorage('voting_option');
    $option = $optionStorage->create(['label' => 'Yes', 'question_id' => $question->id()]);
    $option->save();

    $response = $this->apiPost(
      '/api/v1/questions/' . $question->uuid() . '/vote',
      ['option_uuid' => $option->uuid()],
      $authHeader,
    );

    $this->assertSame(403, $response->getStatusCode());
  }

}
