<?php

declare(strict_types=1);

namespace Drupal\vs_core\Exception;

/**
 * Thrown when a user attempts to vote on a question they have already voted on.
 *
 * The underlying cause is an IntegrityConstraintViolationException from the
 * UNIQUE(uid, question_id) constraint on the voting_vote table.
 */
class DuplicateVoteException extends \RuntimeException {

}
