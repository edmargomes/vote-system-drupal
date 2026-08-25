<?php

declare(strict_types=1);

namespace Drupal\vs_core\Controller\Api;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Session\AccountInterface;
use Drupal\vs_core\Exception\DuplicateVoteException;
use Drupal\vs_core\Service\QuestionService;
use Drupal\vs_core\Service\ResultService;
use Drupal\vs_core\Service\VotingService;
use Drupal\vs_core\Validator\VotePayloadValidator;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Handles the POST /api/v1/questions/{uuid}/vote endpoint.
 *
 * The user identity is always resolved from the authenticated session.
 * The request body must never be trusted for the user identity.
 */
class VoteApiController extends ControllerBase {

  /**
   * The currently authenticated user.
   *
   * Uses a distinct property name to avoid conflict with ControllerBase's
   * protected $currentUser which is untyped and non-readonly.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  private AccountInterface $account;

  /**
   * Constructs a VoteApiController.
   *
   * @param \Drupal\vs_core\Service\VotingService $votingService
   *   The voting service.
   * @param \Drupal\vs_core\Service\QuestionService $questionService
   *   The question service.
   * @param \Drupal\vs_core\Service\ResultService $resultService
   *   The result service.
   * @param \Drupal\vs_core\Validator\VotePayloadValidator $validator
   *   The vote payload validator.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The currently authenticated user.
   */
  public function __construct(
    private readonly VotingService $votingService,
    private readonly QuestionService $questionService,
    private readonly ResultService $resultService,
    private readonly VotePayloadValidator $validator,
    AccountInterface $account,
  ) {
    $this->account = $account;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('vs_core.voting'),
      $container->get('vs_core.question'),
      $container->get('vs_core.result'),
      $container->get('vs_core.validator'),
      $container->get('current_user'),
    );
  }

  /**
   * Casts a vote for the given question.
   *
   * @param string $uuid
   *   The question UUID from the route.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The current HTTP request.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   JSON response indicating success or the relevant error.
   */
  public function vote(string $uuid, Request $request): JsonResponse {
    if (!$this->votingService->isVotingEnabled()) {
      return $this->jsonResponse(
        ['error' => 'voting_disabled', 'message' => 'Voting is currently disabled.'],
        403,
      );
    }

    $body = $request->getContent();
    $data = json_decode($body, TRUE) ?? [];

    $errors = $this->validator->validate($data);
    if (!empty($errors)) {
      $firstError = reset($errors);
      $errorKey = str_contains($firstError, 'is required') ? 'validation_error' : 'invalid_option';
      return $this->jsonResponse(
        ['error' => $errorKey, 'message' => $firstError],
        422,
      );
    }

    $question = $this->questionService->findByUuid($uuid);
    if ($question === NULL) {
      return $this->jsonResponse(
        ['error' => 'not_found', 'message' => 'Question not found.'],
        404,
      );
    }

    $option = $this->questionService->findOptionByUuid($data['option_uuid']);
    if ($option === NULL) {
      return $this->jsonResponse(
        ['error' => 'invalid_option', 'message' => 'The provided option does not belong to this question.'],
        422,
      );
    }

    // Verify the option belongs to the requested question.
    if ((int) $option->get('question_id')->target_id !== (int) $question->id()) {
      return $this->jsonResponse(
        ['error' => 'invalid_option', 'message' => 'The provided option does not belong to this question.'],
        422,
      );
    }

    try {
      $this->votingService->registerVote($this->account, $question, $option);
    }
    catch (DuplicateVoteException) {
      return $this->jsonResponse(
        ['error' => 'duplicate_vote', 'message' => 'User has already voted on this question.'],
        409,
      );
    }

    $responseData = [
      'status' => 'success',
      'message' => 'Vote registered successfully.',
    ];

    if ((bool) $question->get('show_results')->value) {
      $responseData['results'] = $this->resultService->getResults($question);
    }

    return $this->jsonResponse($responseData);
  }

  /**
   * Builds a JsonResponse for this write endpoint.
   *
   * Security headers (X-Content-Type-Options, X-Frame-Options) and
   * Cache-Control: no-store are applied by VotingRequestSubscriber for all
   * non-GET responses to /api/v1/ — no per-controller header logic is needed.
   *
   * @param array<string, mixed> $data
   *   The response payload.
   * @param int $status
   *   HTTP status code.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   The response.
   */
  private function jsonResponse(array $data, int $status = 200): JsonResponse {
    return new JsonResponse($data, $status);
  }

}
