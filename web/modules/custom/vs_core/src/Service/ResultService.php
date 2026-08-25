<?php

declare(strict_types=1);

namespace Drupal\vs_core\Service;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\vs_core\Entity\VotingQuestionInterface;

/**
 * Provides aggregated vote result data for a given question.
 */
class ResultService {

  /**
   * Constructs a ResultService.
   *
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   */
  public function __construct(
    private readonly Connection $database,
    private readonly EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * Returns aggregated vote counts and percentages for each option.
   *
   * Returns an empty array when no votes have been cast, guarding against
   * division-by-zero during percentage calculation.
   *
   * @param \Drupal\vs_core\Entity\VotingQuestionInterface $question
   *   The question to fetch results for.
   *
   * @return array<int, array{option_uuid: string, title: string, votes: int, percentage: float}>
   *   Result rows, each containing option_uuid, title, vote count, and
   *   percentage. Never contains option_id.
   */
  public function getResults(VotingQuestionInterface $question): array {
    $query = $this->database->select('voting_vote', 'v');
    $query->join('voting_option', 'o', 'o.id = v.option_id');
    $query->addField('o', 'uuid', 'option_uuid');
    $query->addField('o', 'label', 'title');
    $query->addExpression('COUNT(v.id)', 'votes');
    $query->condition('v.question_id', $question->id());
    $query->groupBy('v.option_id');
    $query->groupBy('o.uuid');
    $query->groupBy('o.label');

    $rows = $query->execute()->fetchAll();

    if (empty($rows)) {
      return [];
    }

    $total = array_sum(array_map(static fn($r) => (int) $r->votes, $rows));

    $results = [];
    foreach ($rows as $row) {
      $votes = (int) $row->votes;
      $results[] = [
        'option_uuid' => $row->option_uuid,
        'title' => $row->title,
        'votes' => $votes,
        'percentage' => $total > 0 ? round(($votes / $total) * 100, 2) : 0.0,
      ];
    }

    return $results;
  }

  /**
   * Returns the total number of votes cast on the given question.
   *
   * @param \Drupal\vs_core\Entity\VotingQuestionInterface $question
   *   The question to count votes for.
   *
   * @return int
   *   Total vote count.
   */
  public function getTotalVotes(VotingQuestionInterface $question): int {
    $count = $this->database->select('voting_vote', 'v')
      ->condition('v.question_id', $question->id())
      ->countQuery()
      ->execute()
      ->fetchField();

    return (int) $count;
  }

}
