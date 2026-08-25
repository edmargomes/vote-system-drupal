<?php

declare(strict_types=1);

namespace Drupal\Tests\vs_core\Functional\Api\Integration;

use Psr\Http\Message\ResponseInterface;
use Drupal\Tests\BrowserTestBase;
use GuzzleHttp\RequestOptions;

/**
 * Verifies that the global voting_enabled toggle blocks all vote operations.
 *
 * @group vs_core
 * @group integration
 */
class VotingGlobalDisabledTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['vs_core', 'basic_auth'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Makes a GET request with Authorization header.
   *
   * @param string $path
   *   The site-relative path.
   * @param string $authHeader
   *   The Authorization header value.
   *
   * @return \Psr\Http\Message\ResponseInterface
   *   The HTTP response.
   */
  private function apiGet(string $path, string $authHeader): ResponseInterface {
    $url = $this->buildUrl($path, ['absolute' => TRUE]);
    return $this->getHttpClient()->get($url, [
      RequestOptions::HEADERS => ['Authorization' => $authHeader],
      RequestOptions::HTTP_ERRORS => FALSE,
    ]);
  }

  /**
   * Makes a POST request and returns the response.
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
   * When voting_enabled is FALSE, POST /vote returns 403.
   */
  public function testVoteReturnsForbiddenWhenDisabled(): void {
    $this->config('vs_core.settings')->set('voting_enabled', FALSE)->save();

    $user = $this->drupalCreateUser(['vote']);
    $authHeader = 'Basic ' . base64_encode($user->getAccountName() . ':' . $user->passRaw);

    $questionStorage = $this->container->get('entity_type.manager')->getStorage('voting_question');
    $question = $questionStorage->create(['title' => 'Disabled test?', 'status' => TRUE]);
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

  /**
   * When voting_enabled is TRUE, the same request succeeds with 200.
   */
  public function testVoteSucceedsWhenEnabled(): void {
    $this->config('vs_core.settings')->set('voting_enabled', TRUE)->save();

    $user = $this->drupalCreateUser(['vote']);
    $authHeader = 'Basic ' . base64_encode($user->getAccountName() . ':' . $user->passRaw);

    $questionStorage = $this->container->get('entity_type.manager')->getStorage('voting_question');
    $question = $questionStorage->create(['title' => 'Enabled test?', 'status' => TRUE]);
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
  }

  /**
   * Question list endpoint also returns 403 when voting_enabled is FALSE.
   */
  public function testQuestionListReturnsForbiddenWhenVotingDisabled(): void {
    $this->config('vs_core.settings')->set('voting_enabled', FALSE)->save();

    $user = $this->drupalCreateUser(['vote']);
    $authHeader = 'Basic ' . base64_encode($user->getAccountName() . ':' . $user->passRaw);

    $response = $this->apiGet('/api/v1/questions', $authHeader);

    $this->assertSame(403, $response->getStatusCode());
  }

}
