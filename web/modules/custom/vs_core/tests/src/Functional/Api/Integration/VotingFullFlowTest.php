<?php

declare(strict_types=1);

namespace Drupal\Tests\vs_core\Functional\Api\Integration;

use Psr\Http\Message\ResponseInterface;
use Drupal\Tests\BrowserTestBase;
use GuzzleHttp\RequestOptions;

/**
 * Exercises the full voting flow end-to-end via the HTTP API.
 *
 * Flow: authenticate → list questions → view detail → cast vote → view results.
 *
 * @group vs_core
 * @group integration
 */
class VotingFullFlowTest extends BrowserTestBase {

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
   * Full happy-path flow from Basic Auth to result retrieval.
   */
  public function testFullVotingFlow(): void {
    // 1. Create a question with options.
    $questionStorage = $this->container->get('entity_type.manager')->getStorage('voting_question');
    $question = $questionStorage->create([
      'title' => 'Best programming language?',
      'show_results' => TRUE,
      'status' => TRUE,
    ]);
    $question->save();

    $optionStorage = $this->container->get('entity_type.manager')->getStorage('voting_option');
    $optA = $optionStorage->create(['label' => 'PHP', 'question_id' => $question->id()]);
    $optA->save();
    $optB = $optionStorage->create(['label' => 'Python', 'question_id' => $question->id()]);
    $optB->save();

    // 2. Create a user and build Basic Auth header.
    $user = $this->drupalCreateUser(['vote']);
    $authHeader = 'Basic ' . base64_encode($user->getAccountName() . ':' . $user->passRaw);

    // 3. List questions.
    $listResponse = $this->apiGet('/api/v1/questions', $authHeader);
    $this->assertSame(200, $listResponse->getStatusCode());

    $list = json_decode((string) $listResponse->getBody(), TRUE);
    $this->assertArrayHasKey('data', $list);
    $this->assertNotEmpty($list['data']);

    // 4. View question detail.
    $detailResponse = $this->apiGet('/api/v1/questions/' . $question->uuid(), $authHeader);
    $this->assertSame(200, $detailResponse->getStatusCode());

    $detail = json_decode((string) $detailResponse->getBody(), TRUE);
    $this->assertSame($question->uuid(), $detail['data']['uuid']);
    $this->assertCount(2, $detail['data']['options']);

    // 5. Cast a vote using the option UUID from the detail response.
    $optAUuid = $optA->uuid();
    $voteResponse = $this->apiPost(
      '/api/v1/questions/' . $question->uuid() . '/vote',
      ['option_uuid' => $optAUuid],
      $authHeader,
    );
    $this->assertSame(200, $voteResponse->getStatusCode());

    $voteBody = json_decode((string) $voteResponse->getBody(), TRUE);
    $this->assertSame('success', $voteBody['status']);
    // show_results is TRUE so results must be present in the vote response.
    $this->assertArrayHasKey('results', $voteBody);

    // 6. Admin retrieves results via Basic Auth.
    $admin = $this->drupalCreateUser(['administer voting']);
    $adminAuth = 'Basic ' . base64_encode($admin->getAccountName() . ':' . $admin->passRaw);

    $resultsResponse = $this->apiGet(
      '/api/v1/admin/questions/' . $question->uuid() . '/results',
      $adminAuth,
    );
    $this->assertSame(200, $resultsResponse->getStatusCode());

    $results = json_decode((string) $resultsResponse->getBody(), TRUE);
    $this->assertSame($question->uuid(), $results['question']['uuid']);
    $this->assertNotEmpty($results['results']);

    // Match by UUID — integer ids must not appear in the API response.
    $counted = array_filter(
      $results['results'],
      static fn($row) => $row['option_uuid'] === $optAUuid
    );
    $this->assertNotEmpty($counted);
    $this->assertSame(1, (int) array_values($counted)[0]['votes']);
  }

}
