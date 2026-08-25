<?php

declare(strict_types=1);

namespace Drupal\vs_core\Controller\Api;

use Drupal\Core\Cache\CacheableJsonResponse;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\File\FileUrlGeneratorInterface;
use Drupal\file\FileInterface;
use Drupal\vs_core\Service\QuestionService;
use Drupal\vs_core\Service\VotingCacheService;
use Drupal\vs_core\Service\VotingService;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Handles the question list and question detail API endpoints.
 */
class QuestionApiController extends ControllerBase {

  /**
   * Constructs a QuestionApiController.
   *
   * @param \Drupal\vs_core\Service\VotingService $votingService
   *   The voting service.
   * @param \Drupal\vs_core\Service\QuestionService $questionService
   *   The question service.
   * @param \Drupal\Core\File\FileUrlGeneratorInterface $fileUrlGenerator
   *   The file URL generator.
   * @param \Drupal\vs_core\Service\VotingCacheService $cacheService
   *   The cache metadata builder service.
   */
  public function __construct(
    private readonly VotingService $votingService,
    private readonly QuestionService $questionService,
    private readonly FileUrlGeneratorInterface $fileUrlGenerator,
    private readonly VotingCacheService $cacheService,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('vs_core.voting'),
      $container->get('vs_core.question'),
      $container->get('file_url_generator'),
      $container->get('vs_core.cache'),
    );
  }

  /**
   * Returns the list of active voting questions.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The current HTTP request.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   JSON response with the question list.
   */
  public function list(Request $request): JsonResponse {
    if (!$this->votingService->isVotingEnabled()) {
      return $this->errorResponse(
        ['error' => 'voting_disabled', 'message' => 'Voting is currently disabled.'],
        403,
      );
    }

    $questions = $this->questionService->listActive();

    $data = [];
    foreach ($questions as $question) {
      $options = $this->questionService->getOptions($question);
      $data[] = [
        'uuid' => $question->uuid(),
        'title' => $question->label(),
        'description' => $question->get('description')->value,
        'options_count' => count($options),
      ];
    }

    $response = new CacheableJsonResponse([
      'data' => $data,
      'meta' => ['total' => count($data)],
    ]);
    $response->addCacheableDependency($this->cacheService->forQuestionList($questions));
    return $response;
  }

  /**
   * Returns the detail of a single voting question including its options.
   *
   * @param string $uuid
   *   The question UUID from the route.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The current HTTP request.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   JSON response with question detail.
   */
  public function detail(string $uuid, Request $request): JsonResponse {
    if (!$this->votingService->isVotingEnabled()) {
      return $this->errorResponse(
        ['error' => 'voting_disabled', 'message' => 'Voting is currently disabled.'],
        403,
      );
    }

    $question = $this->questionService->findByUuid($uuid);

    if ($question === NULL) {
      return $this->errorResponse(
        ['error' => 'not_found', 'message' => 'Question not found.'],
        404,
      );
    }

    $options = $this->questionService->getOptions($question);
    $serialisedOptions = [];

    foreach ($options as $option) {
      $imageUrl = NULL;
      $imageField = $option->get('image');
      if (!$imageField->isEmpty()) {
        $file = $imageField->entity;
        if ($file instanceof FileInterface) {
          $imageUrl = $this->fileUrlGenerator->generateAbsoluteString(
            $file->getFileUri()
          );
        }
      }

      $serialisedOptions[] = [
        'uuid' => $option->uuid(),
        'title' => $option->label(),
        'description' => $option->get('description')->value,
        'image_url' => $imageUrl,
      ];
    }

    $response = new CacheableJsonResponse([
      'data' => [
        'uuid' => $question->uuid(),
        'title' => $question->label(),
        'description' => $question->get('description')->value,
        'options' => $serialisedOptions,
      ],
    ]);
    $response->addCacheableDependency($this->cacheService->forQuestionDetail($question));
    return $response;
  }

  /**
   * Builds a plain JsonResponse for error paths (4xx).
   *
   * Security headers (X-Content-Type-Options, X-Frame-Options) are applied
   * unconditionally by VotingRequestSubscriber; Cache-Control is set by the
   * subscriber for non-GET methods only. Error responses on GET do not need
   * cache tags and are not stored by compliant proxies without an explicit
   * directive.
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
