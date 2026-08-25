<?php

declare(strict_types=1);

namespace Drupal\vs_core\Exception;

/**
 * Thrown when voting is attempted while it is globally disabled.
 */
class VotingDisabledException extends \RuntimeException {

}
