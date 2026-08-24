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
  protected static $modules = ['vs_core'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * GET /api/v1/questions without a token returns 401.
   */
  public function testUnauthenticatedRequestReturns401(): void {
    $this->drupalGet('/api/v1/questions');
    $this->assertSession()->statusCodeEquals(401);
  }

  /**
   * GET /api/v1/questions with a valid token returns 200 and a JSON array.
   */
  public function testAuthenticatedRequestReturns200WithArray(): void {
    $user = $this->drupalCreateUser(['vote']);

    /** @var \Drupal\vs_core\Service\AuthTokenService $tokenService */
    $tokenService = $this->container->get('vs_core.auth_token');
    $token = $tokenService->issue((int) $user->id());

    $this->drupalGet('/api/v1/questions', [
      'headers' => ['Authorization' => 'Bearer ' . $token],
    ]);

    $this->assertSession()->statusCodeEquals(200);

    $body = json_decode($this->getSession()->getPage()->getContent(), TRUE);
    $this->assertIsArray($body);
  }

  /**
   * Each question in the list has the required fields.
   */
  public function testQuestionListItemsHaveRequiredFields(): void {
    $user = $this->drupalCreateUser(['vote']);

    /** @var \Drupal\vs_core\Service\AuthTokenService $tokenService */
    $tokenService = $this->container->get('vs_core.auth_token');
    $token = $tokenService->issue((int) $user->id());

    // Create a question so the list is non-empty.
    $storage = $this->container->get('entity_type.manager')->getStorage('voting_question');
    $storage->create(['title' => 'Contract question', 'status' => TRUE])->save();

    $this->drupalGet('/api/v1/questions', [
      'headers' => ['Authorization' => 'Bearer ' . $token],
    ]);

    $body = json_decode($this->getSession()->getPage()->getContent(), TRUE);
    $this->assertNotEmpty($body);

    $first = $body[0];
    $this->assertArrayHasKey('uuid', $first);
    $this->assertArrayHasKey('title', $first);
    $this->assertArrayHasKey('show_results', $first);
  }

}
