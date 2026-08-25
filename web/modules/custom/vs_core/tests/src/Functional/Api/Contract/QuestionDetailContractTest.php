<?php

declare(strict_types=1);

namespace Drupal\Tests\vs_core\Functional\Api\Contract;

use Drupal\Tests\BrowserTestBase;

/**
 * Verifies the question detail endpoint contract.
 *
 * @group vs_core
 * @group contract
 */
class QuestionDetailContractTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['vs_core', 'basic_auth'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Unauthenticated request returns 401.
   */
  public function testUnauthenticatedRequestReturns401(): void {
    $this->drupalGet('/api/v1/questions/00000000-0000-0000-0000-000000000001');
    $this->assertSession()->statusCodeEquals(401);
  }

  /**
   * GET /api/v1/questions/{uuid} returns 404 for unknown uuid.
   */
  public function testUnknownUuidReturns404(): void {
    $user = $this->drupalCreateUser(['vote']);
    $authHeader = 'Basic ' . base64_encode($user->getAccountName() . ':' . $user->passRaw);

    $this->drupalGet(
      '/api/v1/questions/00000000-0000-0000-0000-000000000000',
      [],
      ['Authorization' => $authHeader],
    );

    $this->assertSession()->statusCodeEquals(404);
  }

  /**
   * GET /api/v1/questions/{uuid} returns 200 with options for a known question.
   */
  public function testKnownQuestionReturns200WithOptions(): void {
    $user = $this->drupalCreateUser(['vote']);
    $authHeader = 'Basic ' . base64_encode($user->getAccountName() . ':' . $user->passRaw);

    $questionStorage = $this->container->get('entity_type.manager')->getStorage('voting_question');
    $question = $questionStorage->create(['title' => 'Detail question?', 'status' => TRUE]);
    $question->save();

    $optionStorage = $this->container->get('entity_type.manager')->getStorage('voting_option');
    $optionStorage->create(['label' => 'Yes', 'question_id' => $question->id()])->save();
    $optionStorage->create(['label' => 'No', 'question_id' => $question->id()])->save();

    $this->drupalGet(
      '/api/v1/questions/' . $question->uuid(),
      [],
      ['Authorization' => $authHeader],
    );

    $this->assertSession()->statusCodeEquals(200);

    $body = json_decode($this->getSession()->getPage()->getContent(), TRUE);
    $this->assertArrayHasKey('data', $body);
    $this->assertArrayHasKey('uuid', $body['data']);
    $this->assertArrayHasKey('title', $body['data']);
    $this->assertArrayHasKey('options', $body['data']);
    $this->assertCount(2, $body['data']['options']);
  }

  /**
   * Successful response includes the X-Correlation-ID header.
   */
  public function testResponseIncludesCorrelationIdHeader(): void {
    $user = $this->drupalCreateUser(['vote']);
    $authHeader = 'Basic ' . base64_encode($user->getAccountName() . ':' . $user->passRaw);

    $questionStorage = $this->container->get('entity_type.manager')->getStorage('voting_question');
    $question = $questionStorage->create(['title' => 'Header test?', 'status' => TRUE]);
    $question->save();

    $this->drupalGet(
      '/api/v1/questions/' . $question->uuid(),
      [],
      ['Authorization' => $authHeader],
    );

    $this->assertNotEmpty(
      $this->getSession()->getResponseHeader('X-Correlation-ID'),
      'X-Correlation-ID header must be present on all API responses.'
    );
  }

  /**
   * Response shape includes option uuid and title, never an integer id.
   */
  public function testOptionShapeHasUuidAndTitle(): void {
    $user = $this->drupalCreateUser(['vote']);
    $authHeader = 'Basic ' . base64_encode($user->getAccountName() . ':' . $user->passRaw);

    $questionStorage = $this->container->get('entity_type.manager')->getStorage('voting_question');
    $question = $questionStorage->create(['title' => 'Options shape?', 'status' => TRUE]);
    $question->save();

    $optionStorage = $this->container->get('entity_type.manager')->getStorage('voting_option');
    $optionStorage->create(['label' => 'Option A', 'question_id' => $question->id()])->save();

    $this->drupalGet(
      '/api/v1/questions/' . $question->uuid(),
      [],
      ['Authorization' => $authHeader],
    );

    $body = json_decode($this->getSession()->getPage()->getContent(), TRUE);
    $option = $body['data']['options'][0];
    $this->assertArrayHasKey('uuid', $option);
    $this->assertArrayHasKey('title', $option);
    $this->assertArrayNotHasKey('id', $option);
    $this->assertArrayNotHasKey('label', $option);
  }

}
