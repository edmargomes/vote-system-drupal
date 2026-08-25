<?php

declare(strict_types=1);

namespace Drupal\vs_core\Controller\Admin;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

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
   */
  public function __construct(
    EntityTypeManagerInterface $entityTypeManager,
    private readonly DateFormatterInterface $dateFormatter,
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

      $rows[] = [
        $question->get('title')->value,
        $question->get('status')->value ? $this->t('Active') : $this->t('Inactive'),
        $optionsCount,
        $question->get('show_results')->value ? $this->t('Yes') : $this->t('No'),
        $this->dateFormatter->format((int) $question->get('created')->value, 'short'),
        [
          'data' => [
            '#type' => 'inline_template',
            '#template' => '{{ edit }} | {{ delete }} | {{ votes }}',
            '#context' => [
              'edit' => Link::fromTextAndUrl($this->t('Edit'), $editUrl)->toRenderable(),
              'delete' => Link::fromTextAndUrl($this->t('Delete'), $deleteUrl)->toRenderable(),
              'votes' => Link::fromTextAndUrl($this->t('Votes'), $votesUrl)->toRenderable(),
            ],
          ],
        ],
      ];
    }

    return [
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
    ];
  }

}
