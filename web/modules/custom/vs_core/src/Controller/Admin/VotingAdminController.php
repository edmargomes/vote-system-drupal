<?php

declare(strict_types=1);

namespace Drupal\vs_core\Controller\Admin;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\vs_core\Entity\VotingQuestionInterface;
use Drupal\vs_core\Service\ResultService;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Renders admin pages for voting questions and votes.
 */
class VotingAdminController extends ControllerBase {

  /**
   * Constructs a VotingAdminController.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\Core\Datetime\DateFormatterInterface $dateFormatter
   *   The date formatter service.
   * @param \Drupal\vs_core\Service\ResultService $resultService
   *   The result service for aggregating vote data.
   */
  public function __construct(
    EntityTypeManagerInterface $entityTypeManager,
    private readonly DateFormatterInterface $dateFormatter,
    private readonly ResultService $resultService,
  ) {
    $this->entityTypeManager = $entityTypeManager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('date.formatter'),
      $container->get('vs_core.result'),
    );
  }

  /**
   * Renders the voting question listing table.
   *
   * Loads all questions regardless of status so administrators can manage
   * both active and inactive questions from a single page.
   *
   * @return array<string, mixed>
   *   A render array with a table listing all voting questions.
   */
  public function list(): array {
    $storage = $this->entityTypeManager->getStorage('voting_question');
    $optionStorage = $this->entityTypeManager->getStorage('voting_option');

    /** @var \Drupal\vs_core\Entity\VotingQuestion[] $questions */
    $questions = $storage->loadByProperties([]);

    $rows = [];
    foreach ($questions as $question) {
      $options = $optionStorage->loadByProperties(['question_id' => $question->id()]);
      $optionsCount = count($options);

      $editUrl = Url::fromRoute('vs_core.admin.question_edit', ['id' => $question->id()]);
      $deleteUrl = Url::fromRoute('vs_core.admin.question_delete', ['id' => $question->id()]);
      $votesUrl = Url::fromRoute('vs_core.admin.question_votes', ['id' => $question->id()]);
      $resultsUrl = Url::fromRoute('vs_core.admin.question_results', ['id' => $question->id()]);

      $rows[] = [
        $question->get('title')->value,
        $question->get('status')->value ? $this->t('Active') : $this->t('Inactive'),
        $optionsCount,
        $question->get('show_results')->value ? $this->t('Yes') : $this->t('No'),
        $this->dateFormatter->format((int) $question->get('created')->value, 'short'),
        [
          'data' => [
            '#type' => 'inline_template',
            '#template' => '{{ edit }} | {{ delete }} | {{ votes }} | {{ results }}',
            '#context' => [
              'edit' => Link::fromTextAndUrl($this->t('Edit'), $editUrl)->toRenderable(),
              'delete' => Link::fromTextAndUrl($this->t('Delete'), $deleteUrl)->toRenderable(),
              'votes' => Link::fromTextAndUrl($this->t('Votes'), $votesUrl)->toRenderable(),
              'results' => Link::fromTextAndUrl($this->t('Results'), $resultsUrl)->toRenderable(),
            ],
          ],
        ],
      ];
    }

    return [
      'add_link' => [
        '#type' => 'link',
        '#title' => $this->t('Add voting question'),
        '#url' => Url::fromRoute('vs_core.admin.question_add'),
        '#attributes' => ['class' => ['button', 'button--primary', 'button--action']],
      ],
      'table' => [
        '#type' => 'table',
        '#header' => [
          $this->t('Title'),
          $this->t('Status'),
          $this->t('Options'),
          $this->t('Show Results'),
          $this->t('Created'),
          $this->t('Actions'),
        ],
        '#rows' => $rows,
        '#empty' => $this->t('No voting questions found.'),
      ],
    ];
  }

  /**
   * Renders the admin results page for a single voting question.
   *
   * @param string $id
   *   The integer entity ID from the route parameter.
   *
   * @return array<string, mixed>
   *   A render array using the vs_core_admin_results theme hook.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\NotFoundHttpException
   *   When no voting_question entity with the given ID exists.
   */
  public function results(string $id): array {
    $question = $this->entityTypeManager->getStorage('voting_question')->load($id);

    if (!$question instanceof VotingQuestionInterface) {
      throw new NotFoundHttpException();
    }

    $results = $this->resultService->getResults($question);
    $totalVotes = $this->resultService->getTotalVotes($question);

    return [
      '#theme' => 'vs_core_admin_results',
      '#question_title' => $question->label(),
      '#total_votes' => $totalVotes,
      '#results' => $results,
      '#back_url' => Url::fromRoute('vs_core.admin.questions_list'),
    ];
  }

}
