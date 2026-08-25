<?php

declare(strict_types=1);

namespace Drupal\vs_core\Controller\Api;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\File\FileUrlGeneratorInterface;
use Drupal\file\FileInterface;
use Drupal\vs_core\Service\QuestionService;
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
   */
  public function __construct(
    private readonly VotingService $votingService,
    private readonly QuestionService $questionService,
    private readonly FileUrlGeneratorInterface $fileUrlGenerator,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('vs_core.voting'),
      $container->get('vs_core.question'),
      $container->get('file_url_generator'),
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
      return $this->jsonResponse(
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

    return $this->jsonResponse([
      'data' => $data,
      'meta' => ['total' => count($data)],
    ]);
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
      return $this->jsonResponse(
        ['error' => 'voting_disabled', 'message' => 'Voting is currently disabled.'],
        403,
      );
    }

    $question = $this->questionService->findByUuid($uuid);

    if ($question === NULL) {
      return $this->jsonResponse(
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

    return $this->jsonResponse([
      'data' => [
        'uuid' => $question->uuid(),
        'title' => $question->label(),
        'description' => $question->get('description')->value,
        'options' => $serialisedOptions,
      ],
    ]);
  }

  /**
   * Builds a JsonResponse with standard security headers.
   *
   * @param array<string, mixed> $data
   *   The response payload.
   * @param int $status
   *   HTTP status code.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   The response with security headers applied.
   */
  private function jsonResponse(array $data, int $status = 200): JsonResponse {
    $response = new JsonResponse($data, $status);
    $response->headers->set('Cache-Control', 'no-store');
    $response->headers->set('X-Content-Type-Options', 'nosniff');
    $response->headers->set('X-Frame-Options', 'DENY');
    return $response;
  }

}
