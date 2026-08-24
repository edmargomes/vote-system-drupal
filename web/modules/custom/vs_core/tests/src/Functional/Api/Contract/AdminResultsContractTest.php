<?php

declare(strict_types=1);

namespace Drupal\Tests\vs_core\Functional\Api\Contract;

use Drupal\Tests\BrowserTestBase;

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
   * GET /api/v1/admin/questions/{uuid}/results without credentials returns 401.
   */
  public function testUnauthenticatedRequestReturns401(): void {
    $this->drupalGet('/api/v1/admin/questions/some-uuid/results');
    $this->assertSession()->statusCodeEquals(401);
  }

  /**
   * Non-admin authenticated request returns 403.
   */
  public function testNonAdminReturns403(): void {
    $user = $this->drupalCreateUser(['vote']);
    $authHeader = 'Basic ' . base64_encode($user->getAccountName() . ':' . $user->passRaw);

    $this->drupalGet('/api/v1/admin/questions/some-uuid/results', [
      'headers' => ['Authorization' => $authHeader],
    ]);

    $this->assertSession()->statusCodeEquals(403);
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

    $this->drupalGet('/api/v1/admin/questions/' . $question->uuid() . '/results', [
      'headers' => ['Authorization' => $authHeader],
    ]);

    $this->assertSession()->statusCodeEquals(200);

    $body = json_decode($this->getSession()->getPage()->getContent(), TRUE);
    $this->assertArrayHasKey('question', $body);
    $this->assertArrayHasKey('results', $body);
    $this->assertSame($question->uuid(), $body['question']['uuid']);
    $this->assertArrayHasKey('total_votes', $body['question']);
    $this->assertIsArray($body['results']);
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

    $this->drupalGet('/api/v1/questions/' . $question->uuid() . '/vote', [
      'method' => 'POST',
      'body' => json_encode(['option_uuid' => $option->uuid()]),
      'headers' => [
        'Content-Type' => 'application/json',
        'Authorization' => $voterAuth,
      ],
    ]);

    $this->drupalGet('/api/v1/admin/questions/' . $question->uuid() . '/results', [
      'headers' => ['Authorization' => $adminAuth],
    ]);

    $body = json_decode($this->getSession()->getPage()->getContent(), TRUE);
    $this->assertNotEmpty($body['results']);

    $row = $body['results'][0];
    $this->assertArrayHasKey('option_uuid', $row);
    $this->assertArrayHasKey('title', $row);
    $this->assertArrayHasKey('votes', $row);
    $this->assertArrayHasKey('percentage', $row);
    $this->assertArrayNotHasKey('option_id', $row);
    $this->assertSame($option->uuid(), $row['option_uuid']);
  }

}
