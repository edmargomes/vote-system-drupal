<?php

declare(strict_types=1);

namespace Drupal\vs_core\Validator;

/**
 * Validates the JSON payload for the POST /vote endpoint.
 */
class VotePayloadValidator {

  /**
   * UUID regex pattern (case-insensitive, all variants).
   */
  private const UUID_PATTERN =
    '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i';

  /**
   * Validates the vote payload and returns a list of error messages.
   *
   * An empty array indicates the payload is valid.
   *
   * @param array<string, mixed> $data
   *   The decoded JSON payload from the request body.
   *
   * @return string[]
   *   Validation errors. Empty array means the payload is valid.
   */
  public function validate(array $data): array {
    $errors = [];

    if (!array_key_exists('option_uuid', $data)) {
      $errors[] = 'option_uuid is required';
      return $errors;
    }

    $value = $data['option_uuid'];

    if (!is_string($value) || $value === '' || !preg_match(self::UUID_PATTERN, $value)) {
      $errors[] = 'option_uuid must be a valid UUID string';
    }

    return $errors;
  }

}
