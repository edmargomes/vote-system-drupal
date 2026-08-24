<?php

declare(strict_types=1);

namespace Drupal\Tests\vs_core\Functional\Api\Contract;

use Drupal\Tests\BrowserTestBase;

/**
 * Verifies the vote endpoint contract.
 *
 * @group vs_core
 * @group contract
 */
class VoteContractTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['vs_core', 'basic_auth'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * POST /api/v1/questions/{uuid}/vote without credentials returns 401.
   */
  public function testVoteWithoutCredentialsReturns401(): void {
    $this->drupalGet('/api/v1/questions/some-uuid/vote', [
      'method' => 'POST',
      'body' => json_encode(['option_uuid' => '00000000-0000-0000-0000-000000000001']),
      'headers' => ['Content-Type' => 'application/json'],
    ]);

    $this->assertSession()->statusCodeEquals(401);
  }

  /**
   * POST /api/v1/questions/{uuid}/vote with valid data returns 200.
   */
  public function testValidVoteReturns200(): void {
    $user = $this->drupalCreateUser(['vote']);
    $authHeader = 'Basic ' . base64_encode($user->getAccountName() . ':' . $user->passRaw);

    $questionStorage = $this->container->get('entity_type.manager')->getStorage('voting_question');
    $question = $questionStorage->create(['title' => 'Vote test?', 'status' => TRUE]);
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

    $body = json_decode($this->getSession()->getPage()->getContent(), TRUE);
    $this->assertSame('success', $body['status']);
  }

  /**
   * Voting twice with the same credentials returns 409 Conflict.
   */
  public function testDuplicateVoteReturns409(): void {
    $user = $this->drupalCreateUser(['vote']);
    $authHeader = 'Basic ' . base64_encode($user->getAccountName() . ':' . $user->passRaw);

    $questionStorage = $this->container->get('entity_type.manager')->getStorage('voting_question');
    $question = $questionStorage->create(['title' => 'Dup vote?', 'status' => TRUE]);
    $question->save();

    $optionStorage = $this->container->get('entity_type.manager')->getStorage('voting_option');
    $option = $optionStorage->create(['label' => 'Opt', 'question_id' => $question->id()]);
    $option->save();

    $url = '/api/v1/questions/' . $question->uuid() . '/vote';
    $body = json_encode(['option_uuid' => $option->uuid()]);
    $headers = [
      'Content-Type' => 'application/json',
      'Authorization' => $authHeader,
    ];

    $this->drupalGet($url, ['method' => 'POST', 'body' => $body, 'headers' => $headers]);
    $this->assertSession()->statusCodeEquals(200);

    $this->drupalGet($url, ['method' => 'POST', 'body' => $body, 'headers' => $headers]);
    $this->assertSession()->statusCodeEquals(409);
  }

  /**
   * POST with missing option_uuid returns 422.
   */
  public function testMissingOptionUuidReturns422(): void {
    $user = $this->drupalCreateUser(['vote']);
    $authHeader = 'Basic ' . base64_encode($user->getAccountName() . ':' . $user->passRaw);

    $questionStorage = $this->container->get('entity_type.manager')->getStorage('voting_question');
    $question = $questionStorage->create(['title' => 'No option?', 'status' => TRUE]);
    $question->save();

    $this->drupalGet('/api/v1/questions/' . $question->uuid() . '/vote', [
      'method' => 'POST',
      'body' => json_encode([]),
      'headers' => [
        'Content-Type' => 'application/json',
        'Authorization' => $authHeader,
      ],
    ]);

    $this->assertSession()->statusCodeEquals(422);
  }

  /**
   * When voting is disabled, POST /vote returns 403.
   */
  public function testVoteWhenDisabledReturns403(): void {
    $this->config('vs_core.settings')->set('voting_enabled', FALSE)->save();

    $user = $this->drupalCreateUser(['vote']);
    $authHeader = 'Basic ' . base64_encode($user->getAccountName() . ':' . $user->passRaw);

    $questionStorage = $this->container->get('entity_type.manager')->getStorage('voting_question');
    $question = $questionStorage->create(['title' => 'Disabled?', 'status' => TRUE]);
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

}
