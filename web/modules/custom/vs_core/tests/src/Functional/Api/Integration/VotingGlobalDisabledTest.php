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
  protected static $modules = ['vs_core', 'basic_auth'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * When voting_enabled is FALSE, POST /vote returns 403.
   */
  public function testVoteReturnsForbiddenWhenDisabled(): void {
    $this->config('vs_core.settings')->set('voting_enabled', FALSE)->save();

    $user = $this->drupalCreateUser(['vote']);
    $authHeader = 'Basic ' . base64_encode($user->getAccountName() . ':' . $user->passRaw);

    $questionStorage = $this->container->get('entity_type.manager')->getStorage('voting_question');
    $question = $questionStorage->create(['title' => 'Disabled test?', 'status' => TRUE]);
    $question->save();

    $optionStorage = $this->container->get('entity_type.manager')->getStorage('voting_option');
    $option = $optionStorage->create(['label' => 'Yes', 'question_id' => $question->id()]);
    $option->save();

    $this->drupalGet('/api/v1/questions/' . $question->uuid() . '/vote', [
      'method' => 'POST',
      'body' => json_encode(['option_uuid' => $option->uuid()]),
      'headers' => [
        'Content-Type' => 'application/json',
        'Authorization' => $authHeader,
      ],
    ]);

    $this->assertSession()->statusCodeEquals(403);
  }

  /**
   * When voting_enabled is TRUE, the same request succeeds with 200.
   */
  public function testVoteSucceedsWhenEnabled(): void {
    $this->config('vs_core.settings')->set('voting_enabled', TRUE)->save();

    $user = $this->drupalCreateUser(['vote']);
    $authHeader = 'Basic ' . base64_encode($user->getAccountName() . ':' . $user->passRaw);

    $questionStorage = $this->container->get('entity_type.manager')->getStorage('voting_question');
    $question = $questionStorage->create(['title' => 'Enabled test?', 'status' => TRUE]);
    $question->save();

    $optionStorage = $this->container->get('entity_type.manager')->getStorage('voting_option');
    $option = $optionStorage->create(['label' => 'Yes', 'question_id' => $question->id()]);
    $option->save();

    $this->drupalGet('/api/v1/questions/' . $question->uuid() . '/vote', [
      'method' => 'POST',
      'body' => json_encode(['option_uuid' => $option->uuid()]),
      'headers' => [
        'Content-Type' => 'application/json',
        'Authorization' => $authHeader,
      ],
    ]);

    $this->assertSession()->statusCodeEquals(200);
  }

  /**
   * Question list endpoint also returns 403 when voting_enabled is FALSE.
   */
  public function testQuestionListReturnsForbiddenWhenVotingDisabled(): void {
    $this->config('vs_core.settings')->set('voting_enabled', FALSE)->save();

    $user = $this->drupalCreateUser(['vote']);
    $authHeader = 'Basic ' . base64_encode($user->getAccountName() . ':' . $user->passRaw);

    $this->drupalGet('/api/v1/questions', [
      'headers' => ['Authorization' => $authHeader],
    ]);

    $this->assertSession()->statusCodeEquals(403);
  }

}
