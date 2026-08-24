<?php

declare(strict_types=1);

namespace Drupal\Tests\vs_core\Functional\Api\Integration;

use Drupal\Tests\BrowserTestBase;

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
  protected static $modules = ['vs_core'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Full happy-path flow from token issuance to result retrieval.
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

    // 2. Obtain a token via the auth endpoint.
    $user = $this->drupalCreateUser(['vote']);
    $body = json_encode(['username' => $user->getAccountName(), 'password' => $user->passRaw]);

    $this->drupalGet('/api/v1/auth/token', [
      'method' => 'POST',
      'body' => $body,
      'headers' => ['Content-Type' => 'application/json'],
    ]);
    $this->assertSession()->statusCodeEquals(200);

    $authResponse = json_decode($this->getSession()->getPage()->getContent(), TRUE);
    $this->assertArrayHasKey('token', $authResponse);
    $token = $authResponse['token'];

    // 3. List questions.
    $this->drupalGet('/api/v1/questions', [
      'headers' => ['Authorization' => 'Bearer ' . $token],
    ]);
    $this->assertSession()->statusCodeEquals(200);

    $list = json_decode($this->getSession()->getPage()->getContent(), TRUE);
    $this->assertNotEmpty($list);

    // 4. View question detail.
    $this->drupalGet('/api/v1/questions/' . $question->uuid(), [
      'headers' => ['Authorization' => 'Bearer ' . $token],
    ]);
    $this->assertSession()->statusCodeEquals(200);

    $detail = json_decode($this->getSession()->getPage()->getContent(), TRUE);
    $this->assertSame($question->uuid(), $detail['uuid']);
    $this->assertCount(2, $detail['options']);

    // 5. Cast a vote.
    $this->drupalGet('/api/v1/questions/' . $question->uuid() . '/vote', [
      'method' => 'POST',
      'body' => json_encode(['option_id' => (int) $optA->id()]),
      'headers' => [
        'Content-Type' => 'application/json',
        'Authorization' => 'Bearer ' . $token,
      ],
    ]);
    $this->assertSession()->statusCodeEquals(201);

    // 6. Admin retrieves results.
    $admin = $this->drupalCreateUser(['administer voting']);
    /** @var \Drupal\vs_core\Service\AuthTokenService $tokenService */
    $tokenService = $this->container->get('vs_core.auth_token');
    $adminToken = $tokenService->issue((int) $admin->id());

    $this->drupalGet('/api/v1/admin/questions/' . $question->uuid() . '/results', [
      'headers' => ['Authorization' => 'Bearer ' . $adminToken],
    ]);
    $this->assertSession()->statusCodeEquals(200);

    $results = json_decode($this->getSession()->getPage()->getContent(), TRUE);
    $this->assertSame($question->uuid(), $results['question_uuid']);
    $this->assertNotEmpty($results['results']);

    // Verify the vote was counted for option A.
    $counted = array_filter(
      $results['results'],
      static fn($row) => (int) $row['option_id'] === (int) $optA->id()
    );
    $this->assertNotEmpty($counted);
    $this->assertSame(1, (int) array_values($counted)[0]['total']);
  }

}
