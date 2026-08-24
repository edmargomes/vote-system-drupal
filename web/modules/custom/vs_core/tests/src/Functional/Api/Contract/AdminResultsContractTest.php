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
  protected static $modules = ['vs_core'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * GET /api/v1/admin/questions/{uuid}/results without admin token returns 403.
   */
  public function testNonAdminTokenReturns403(): void {
    $user = $this->drupalCreateUser(['vote']);

    /** @var \Drupal\vs_core\Service\AuthTokenService $tokenService */
    $tokenService = $this->container->get('vs_core.auth_token');
    $token = $tokenService->issue((int) $user->id());

    $this->drupalGet('/api/v1/admin/questions/some-uuid/results', [
      'headers' => ['Authorization' => 'Bearer ' . $token],
    ]);

    $this->assertSession()->statusCodeEquals(403);
  }

  /**
   * GET /api/v1/admin/questions/{uuid}/results without token returns 401.
   */
  public function testUnauthenticatedRequestReturns401(): void {
    $this->drupalGet('/api/v1/admin/questions/some-uuid/results');
    $this->assertSession()->statusCodeEquals(401);
  }

  /**
   * Admin token on existing question returns 200 with aggregated results.
   */
  public function testAdminTokenReturns200WithResults(): void {
    $admin = $this->drupalCreateUser(['administer voting']);

    /** @var \Drupal\vs_core\Service\AuthTokenService $tokenService */
    $tokenService = $this->container->get('vs_core.auth_token');
    $token = $tokenService->issue((int) $admin->id());

    $questionStorage = $this->container->get('entity_type.manager')->getStorage('voting_question');
    $question = $questionStorage->create(['title' => 'Results test?', 'status' => TRUE]);
    $question->save();

    $this->drupalGet('/api/v1/admin/questions/' . $question->uuid() . '/results', [
      'headers' => ['Authorization' => 'Bearer ' . $token],
    ]);

    $this->assertSession()->statusCodeEquals(200);

    $body = json_decode($this->getSession()->getPage()->getContent(), TRUE);
    $this->assertArrayHasKey('question_uuid', $body);
    $this->assertArrayHasKey('results', $body);
    $this->assertIsArray($body['results']);
  }

  /**
   * Each result row exposes option_uuid and total, never an integer option_id.
   */
  public function testResultRowsHaveRequiredFields(): void {
    $admin = $this->drupalCreateUser(['administer voting']);

    /** @var \Drupal\vs_core\Service\AuthTokenService $tokenService */
    $tokenService = $this->container->get('vs_core.auth_token');
    $token = $tokenService->issue((int) $admin->id());

    $questionStorage = $this->container->get('entity_type.manager')->getStorage('voting_question');
    $question = $questionStorage->create(['title' => 'Row shape?', 'status' => TRUE]);
    $question->save();

    $optionStorage = $this->container->get('entity_type.manager')->getStorage('voting_option');
    $option = $optionStorage->create(['label' => 'Opt1', 'question_id' => $question->id()]);
    $option->save();

    // Cast one vote so results are non-empty.
    $voter = $this->drupalCreateUser(['vote']);
    $voterToken = $tokenService->issue((int) $voter->id());

    $this->drupalGet('/api/v1/questions/' . $question->uuid() . '/vote', [
      'method' => 'POST',
      'body' => json_encode(['option_uuid' => $option->uuid()]),
      'headers' => [
        'Content-Type' => 'application/json',
        'Authorization' => 'Bearer ' . $voterToken,
      ],
    ]);

    $this->drupalGet('/api/v1/admin/questions/' . $question->uuid() . '/results', [
      'headers' => ['Authorization' => 'Bearer ' . $token],
    ]);

    $body = json_decode($this->getSession()->getPage()->getContent(), TRUE);
    $this->assertNotEmpty($body['results']);
    $row = $body['results'][0];
    $this->assertArrayHasKey('option_uuid', $row);
    $this->assertArrayHasKey('total', $row);
    $this->assertArrayNotHasKey('option_id', $row);
    // Confirm the UUID matches the option that was voted on.
    $this->assertSame($option->uuid(), $row['option_uuid']);
  }

}
