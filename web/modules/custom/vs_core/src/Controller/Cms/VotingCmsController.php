<?php

declare(strict_types=1);

namespace Drupal\vs_core\Controller\Cms;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Drupal\vs_core\Entity\VotingQuestionInterface;
use Drupal\vs_core\Form\VotingForm;
use Drupal\vs_core\Service\QuestionService;
use Drupal\vs_core\Service\ResultService;
use Drupal\vs_core\Service\VotingService;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Renders the public CMS voting pages.
 *
 * The controller handles HTTP concerns only: it resolves entities, checks
 * guards (voting disabled, already voted, permission), and delegates
 * business logic to services. It returns render arrays using the two Twig
 * theme hooks registered by VsCoreHooks.
 */
class VotingCmsController extends ControllerBase {

  /**
   * Constructs a VotingCmsController.
   *
   * @param \Drupal\vs_core\Service\VotingService $votingService
   *   The voting service.
   * @param \Drupal\vs_core\Service\QuestionService $questionService
   *   The question service.
   * @param \Drupal\vs_core\Service\ResultService $resultService
   *   The result service.
   */
  public function __construct(
    private readonly VotingService $votingService,
    private readonly QuestionService $questionService,
    private readonly ResultService $resultService,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('vs_core.voting'),
      $container->get('vs_core.question'),
      $container->get('vs_core.result'),
    );
  }

  /**
   * Renders the public list of active voting questions.
   *
   * Any visitor (authenticated or anonymous) may access this page.
   *
   * @return array<string, mixed>
   *   A render array using the vs_core_question_list theme hook.
   */
  public function list(): array {
    $votingEnabled = $this->votingService->isVotingEnabled();
    $isAnonymous = $this->currentUser()->isAnonymous();

    $questions = $this->questionService->listActive();

    $questionItems = [];
    foreach ($questions as $question) {
      $questionItems[] = [
        'uuid' => $question->uuid(),
        'title' => $question->label(),
        'description' => $question->get('description')->value,
        // Pass the Url object to preserve render pipeline cache context.
        'url' => Url::fromRoute(
          'vs_core.cms.question_detail',
          ['uuid' => $question->uuid()],
        ),
      ];
    }

    return [
      '#theme' => 'vs_core_question_list',
      '#voting_enabled' => $votingEnabled,
      '#is_anonymous' => $isAnonymous,
      '#questions' => $questionItems,
    ];
  }

  /**
   * Renders the question detail page with the voting form or state messages.
   *
   * Anonymous users are blocked at the route level (_user_is_logged_in: TRUE),
   * so this method only handles authenticated users. Logged-in users without
   * the 'vote' permission see the question info but not the form.
   *
   * @param string $uuid
   *   The question UUID from the route.
   *
   * @return array<string, mixed>
   *   A render array using the vs_core_question_detail theme hook.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\NotFoundHttpException
   *   When the question UUID does not match any active question.
   */
  public function detail(string $uuid): array {
    $question = $this->questionService->findByUuid($uuid);

    if (!$question instanceof VotingQuestionInterface) {
      throw new NotFoundHttpException();
    }

    $votingEnabled = $this->votingService->isVotingEnabled();
    $currentUser = $this->currentUser();
    $showResults = (bool) $question->get('show_results')->value;

    $alreadyVoted = FALSE;
    $votedOptionLabel = NULL;
    $form = [];
    $results = NULL;

    if ($votingEnabled) {
      $alreadyVoted = $this->votingService->hasVoted($currentUser, $question);

      if ($alreadyVoted) {
        $votedOption = $this->votingService->getUserVote($currentUser, $question);
        $votedOptionLabel = $votedOption?->label();
      }
      elseif ($currentUser->hasPermission('vote')) {
        $form = $this->formBuilder()->getForm(VotingForm::class, $question);
      }
    }

    if ($showResults) {
      $results = $this->resultService->getResults($question);
    }

    return [
      '#theme' => 'vs_core_question_detail',
      '#question_title' => $question->label(),
      '#question_description' => $question->get('description')->value,
      '#voting_enabled' => $votingEnabled,
      '#already_voted' => $alreadyVoted,
      '#voted_option_label' => $votedOptionLabel,
      '#show_results' => $showResults,
      '#results' => $results,
      '#form' => $form,
    ];
  }

}
