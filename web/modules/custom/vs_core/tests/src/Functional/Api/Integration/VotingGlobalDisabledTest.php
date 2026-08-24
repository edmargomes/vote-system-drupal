<?php

declare(strict_types=1);

namespace Drupal\Tests\vs_core\Functional\Api\Integration;

use Drupal\Tests\BrowserTestBase;

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
  protected static $modules = ['vs_core'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * When voting_enabled is FALSE, POST /vote returns 503.
   */
  public function testVoteReturnedServiceUnavailableWhenDisabled(): void {
    // Disable voting globally.
    $this->config('vs_core.settings')->set('voting_enabled', FALSE)->save();

    $user = $this->drupalCreateUser(['vote']);

    /** @var \Drupal\vs_core\Service\AuthTokenService $tokenService */
    $tokenService = $this->container->get('vs_core.auth_token');
    $token = $tokenService->issue((int) $user->id());

    $questionStorage = $this->container->get('entity_type.manager')->getStorage('voting_question');
    $question = $questionStorage->create(['title' => 'Disabled test?', 'status' => TRUE]);
    $question->save();

    $optionStorage = $this->container->get('entity_type.manager')->getStorage('voting_option');
    $option = $optionStorage->create(['label' => 'Yes', 'question_id' => $question->id()]);
    $option->save();

    $this->drupalGet('/api/v1/questions/' . $question->uuid() . '/vote', [
      'method' => 'POST',
      'body' => json_encode(['option_id' => (int) $option->id()]),
      'headers' => [
        'Content-Type' => 'application/json',
        'Authorization' => 'Bearer ' . $token,
      ],
    ]);

    $this->assertSession()->statusCodeEquals(503);
  }

  /**
   * When voting_enabled is TRUE the same request succeeds with 201.
   */
  public function testVoteSucceedsWhenEnabled(): void {
    $this->config('vs_core.settings')->set('voting_enabled', TRUE)->save();

    $user = $this->drupalCreateUser(['vote']);

    /** @var \Drupal\vs_core\Service\AuthTokenService $tokenService */
    $tokenService = $this->container->get('vs_core.auth_token');
    $token = $tokenService->issue((int) $user->id());

    $questionStorage = $this->container->get('entity_type.manager')->getStorage('voting_question');
    $question = $questionStorage->create(['title' => 'Enabled test?', 'status' => TRUE]);
    $question->save();

    $optionStorage = $this->container->get('entity_type.manager')->getStorage('voting_option');
    $option = $optionStorage->create(['label' => 'Yes', 'question_id' => $question->id()]);
    $option->save();

    $this->drupalGet('/api/v1/questions/' . $question->uuid() . '/vote', [
      'method' => 'POST',
      'body' => json_encode(['option_id' => (int) $option->id()]),
      'headers' => [
        'Content-Type' => 'application/json',
        'Authorization' => 'Bearer ' . $token,
      ],
    ]);

    $this->assertSession()->statusCodeEquals(201);
  }

  /**
   * Question list endpoint still works regardless of voting_enabled flag.
   */
  public function testQuestionListIsUnaffectedByVotingDisabled(): void {
    $this->config('vs_core.settings')->set('voting_enabled', FALSE)->save();

    $user = $this->drupalCreateUser(['vote']);

    /** @var \Drupal\vs_core\Service\AuthTokenService $tokenService */
    $tokenService = $this->container->get('vs_core.auth_token');
    $token = $tokenService->issue((int) $user->id());

    $this->drupalGet('/api/v1/questions', [
      'headers' => ['Authorization' => 'Bearer ' . $token],
    ]);

    $this->assertSession()->statusCodeEquals(200);
  }

}
