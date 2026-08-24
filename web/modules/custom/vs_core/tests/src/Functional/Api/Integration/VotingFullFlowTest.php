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
  protected static $modules = ['vs_core', 'basic_auth'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

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
    $this->drupalGet('/api/v1/questions', [
      'headers' => ['Authorization' => $authHeader],
    ]);
    $this->assertSession()->statusCodeEquals(200);

    $list = json_decode($this->getSession()->getPage()->getContent(), TRUE);
    $this->assertArrayHasKey('data', $list);
    $this->assertNotEmpty($list['data']);

    // 4. View question detail.
    $this->drupalGet('/api/v1/questions/' . $question->uuid(), [
      'headers' => ['Authorization' => $authHeader],
    ]);
    $this->assertSession()->statusCodeEquals(200);

    $detail = json_decode($this->getSession()->getPage()->getContent(), TRUE);
    $this->assertSame($question->uuid(), $detail['data']['uuid']);
    $this->assertCount(2, $detail['data']['options']);

    // 5. Cast a vote using the option UUID from the detail response.
    $optAUuid = $optA->uuid();
    $this->drupalGet('/api/v1/questions/' . $question->uuid() . '/vote', [
      'method' => 'POST',
      'body' => json_encode(['option_uuid' => $optAUuid]),
      'headers' => [
        'Content-Type' => 'application/json',
        'Authorization' => $authHeader,
      ],
    ]);
    $this->assertSession()->statusCodeEquals(200);

    $voteResponse = json_decode($this->getSession()->getPage()->getContent(), TRUE);
    $this->assertSame('success', $voteResponse['status']);
    // show_results is TRUE so results must be present in the vote response.
    $this->assertArrayHasKey('results', $voteResponse);

    // 6. Admin retrieves results via Basic Auth.
    $admin = $this->drupalCreateUser(['administer voting']);
    $adminAuth = 'Basic ' . base64_encode($admin->getAccountName() . ':' . $admin->passRaw);

    $this->drupalGet('/api/v1/admin/questions/' . $question->uuid() . '/results', [
      'headers' => ['Authorization' => $adminAuth],
    ]);
    $this->assertSession()->statusCodeEquals(200);

    $results = json_decode($this->getSession()->getPage()->getContent(), TRUE);
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
