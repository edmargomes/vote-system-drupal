<?php

declare(strict_types=1);

namespace Drupal\vs_core\Entity;

use Drupal\Core\Entity\ContentEntityInterface;

/**
 * Defines the interface for the voting_question entity.
 */
interface VotingQuestionInterface extends ContentEntityInterface {

  /**
   * Returns TRUE if this question is currently open for voting.
   *
   * A question is open when status = 1 AND (closes_at IS NULL OR
   * closes_at > REQUEST_TIME). This check is the canonical definition of
   * "open" shared between QuestionService and the CMS detail controller.
   *
   * @return bool
   *   TRUE when voting is still possible, FALSE when closed or inactive.
   */
  public function isOpen(): bool;

}
