<?php

declare(strict_types=1);

namespace Drupal\vs_core\Exception;

/**
 * Thrown when a voting entity (question, option) cannot be found by UUID.
 */
class VotingNotFoundException extends \RuntimeException {

}
