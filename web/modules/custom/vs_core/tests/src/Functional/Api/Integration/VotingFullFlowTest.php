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

    // 5. Cast a vote using the option UUID from the detail response.
    $optAUuid = $optA->uuid();
    $this->drupalGet('/api/v1/questions/' . $question->uuid() . '/vote', [
      'method' => 'POST',
      'body' => json_encode(['option_uuid' => $optAUuid]),
      'headers' => [
        'Content-Type' => 'application/json',
        'Authorization' => 'Bearer ' . $token,
      ],
    ]);
    $this->assertSession()->statusCodeEquals(201);

    // 6. Admin obtains token via the auth endpoint, then retrieves results.
    $admin = $this->drupalCreateUser(['administer voting']);
    $adminBody = json_encode(['username' => $admin->getAccountName(), 'password' => $admin->passRaw]);
    $this->drupalGet('/api/v1/auth/token', [
      'method' => 'POST',
      'body' => $adminBody,
      'headers' => ['Content-Type' => 'application/json'],
    ]);
    $this->assertSession()->statusCodeEquals(200);
    $adminToken = json_decode($this->getSession()->getPage()->getContent(), TRUE)['token'];

    $this->drupalGet('/api/v1/admin/questions/' . $question->uuid() . '/results', [
      'headers' => ['Authorization' => 'Bearer ' . $adminToken],
    ]);
    $this->assertSession()->statusCodeEquals(200);

    $results = json_decode($this->getSession()->getPage()->getContent(), TRUE);
    $this->assertSame($question->uuid(), $results['question_uuid']);
    $this->assertNotEmpty($results['results']);

    // Match by UUID — integer ids must not appear in the API response.
    $counted = array_filter(
      $results['results'],
      static fn($row) => $row['option_uuid'] === $optAUuid
    );
    $this->assertNotEmpty($counted);
    $this->assertSame(1, (int) array_values($counted)[0]['total']);
  }

}
