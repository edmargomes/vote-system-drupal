<?php

declare(strict_types=1);

namespace Drupal\vs_core\Controller\Api;

use Drupal\Core\Cache\CacheableJsonResponse;
use Drupal\Core\Controller\ControllerBase;
use Drupal\vs_core\Service\QuestionService;
use Drupal\vs_core\Service\ResultService;
use Drupal\vs_core\Service\VotingCacheService;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Handles the GET /api/v1/admin/questions/{uuid}/results endpoint.
 *
 * Access is enforced at route level via _permission: 'administer voting'.
 * This controller always returns full result data regardless of show_results.
 */
class AdminResultsController extends ControllerBase {

  /**
   * Constructs an AdminResultsController.
   *
   * @param \Drupal\vs_core\Service\QuestionService $questionService
   *   The question service.
   * @param \Drupal\vs_core\Service\ResultService $resultService
   *   The result service.
   * @param \Drupal\vs_core\Service\VotingCacheService $cacheService
   *   The cache metadata builder service.
   */
  public function __construct(
    private readonly QuestionService $questionService,
    private readonly ResultService $resultService,
    private readonly VotingCacheService $cacheService,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('vs_core.question'),
      $container->get('vs_core.result'),
      $container->get('vs_core.cache'),
    );
  }

  /**
   * Returns aggregated vote results for a question.
   *
   * @param string $uuid
   *   The question UUID from the route.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The current HTTP request.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   JSON response with question metadata and per-option vote tallies.
   */
  public function results(string $uuid, Request $request): JsonResponse {
    $question = $this->questionService->findByUuid($uuid);

    if ($question === NULL) {
      return $this->errorResponse(
        ['error' => 'not_found', 'message' => 'Question not found.'],
        404,
      );
    }

    $results = $this->resultService->getResults($question);
    $totalVotes = $this->resultService->getTotalVotes($question);

    $response = new CacheableJsonResponse([
      'question' => [
        'uuid' => $question->uuid(),
        'title' => $question->label(),
        'show_results' => (bool) $question->get('show_results')->value,
        'total_votes' => $totalVotes,
      ],
      'results' => $results,
    ]);
    $response->addCacheableDependency($this->cacheService->forAdminResults($question));
    return $response;
  }

  /**
   * Builds a plain JsonResponse for error paths (4xx).
   *
   * Security headers are applied unconditionally by VotingRequestSubscriber.
   * Error responses do not carry cache tags and are not stored by compliant
   * proxies without an explicit directive.
   *
   * @param array<string, mixed> $data
   *   The response payload.
   * @param int $status
   *   HTTP status code.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   A plain (non-cacheable) JSON response.
   */
  private function errorResponse(array $data, int $status): JsonResponse {
    return new JsonResponse($data, $status);
  }

}
