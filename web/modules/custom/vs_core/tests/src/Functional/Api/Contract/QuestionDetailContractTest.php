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
  protected static $modules = ['vs_core'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * GET /api/v1/questions/{uuid} returns 404 for unknown uuid.
   */
  public function testUnknownUuidReturns404(): void {
    $user = $this->drupalCreateUser(['vote']);

    /** @var \Drupal\vs_core\Service\AuthTokenService $tokenService */
    $tokenService = $this->container->get('vs_core.auth_token');
    $token = $tokenService->issue((int) $user->id());

    $this->drupalGet('/api/v1/questions/00000000-0000-0000-0000-000000000000', [
      'headers' => ['Authorization' => 'Bearer ' . $token],
    ]);

    $this->assertSession()->statusCodeEquals(404);
  }

  /**
   * GET /api/v1/questions/{uuid} returns 200 with options for a known question.
   */
  public function testKnownQuestionReturns200WithOptions(): void {
    $user = $this->drupalCreateUser(['vote']);

    /** @var \Drupal\vs_core\Service\AuthTokenService $tokenService */
    $tokenService = $this->container->get('vs_core.auth_token');
    $token = $tokenService->issue((int) $user->id());

    $questionStorage = $this->container->get('entity_type.manager')->getStorage('voting_question');
    $question = $questionStorage->create(['title' => 'Detail question?', 'status' => TRUE]);
    $question->save();

    $optionStorage = $this->container->get('entity_type.manager')->getStorage('voting_option');
    $optionStorage->create(['label' => 'Yes', 'question_id' => $question->id()])->save();
    $optionStorage->create(['label' => 'No', 'question_id' => $question->id()])->save();

    $this->drupalGet('/api/v1/questions/' . $question->uuid(), [
      'headers' => ['Authorization' => 'Bearer ' . $token],
    ]);

    $this->assertSession()->statusCodeEquals(200);

    $body = json_decode($this->getSession()->getPage()->getContent(), TRUE);
    $this->assertArrayHasKey('uuid', $body);
    $this->assertArrayHasKey('title', $body);
    $this->assertArrayHasKey('options', $body);
    $this->assertCount(2, $body['options']);
  }

  /**
   * Response shape includes option id and label fields.
   */
  public function testOptionShapeHasIdAndLabel(): void {
    $user = $this->drupalCreateUser(['vote']);

    /** @var \Drupal\vs_core\Service\AuthTokenService $tokenService */
    $tokenService = $this->container->get('vs_core.auth_token');
    $token = $tokenService->issue((int) $user->id());

    $questionStorage = $this->container->get('entity_type.manager')->getStorage('voting_question');
    $question = $questionStorage->create(['title' => 'Options shape?', 'status' => TRUE]);
    $question->save();

    $optionStorage = $this->container->get('entity_type.manager')->getStorage('voting_option');
    $optionStorage->create(['label' => 'Option A', 'question_id' => $question->id()])->save();

    $this->drupalGet('/api/v1/questions/' . $question->uuid(), [
      'headers' => ['Authorization' => 'Bearer ' . $token],
    ]);

    $body = json_decode($this->getSession()->getPage()->getContent(), TRUE);
    $option = $body['options'][0];
    $this->assertArrayHasKey('id', $option);
    $this->assertArrayHasKey('label', $option);
  }

}
