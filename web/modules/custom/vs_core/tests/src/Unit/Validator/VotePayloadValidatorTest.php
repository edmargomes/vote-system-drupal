<?php

declare(strict_types=1);

namespace Drupal\Tests\vs_core\Unit\Validator;

use Drupal\Tests\UnitTestCase;
use Drupal\vs_core\Validator\VotePayloadValidator;

/**
 * @coversDefaultClass \Drupal\vs_core\Validator\VotePayloadValidator
 * @group vs_core
 */
class VotePayloadValidatorTest extends UnitTestCase {

  /**
   * Validator under test.
   *
   * @var \Drupal\vs_core\Validator\VotePayloadValidator
   */
  private VotePayloadValidator $validator;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->validator = new VotePayloadValidator();
  }

  /**
   * @covers ::validate
   */
  public function testValidatePassesWithValidUuid(): void {
    $errors = $this->validator->validate([
      'option_uuid' => 'aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee',
    ]);

    $this->assertSame([], $errors);
  }

  /**
   * @covers ::validate
   */
  public function testValidateFailsWhenOptionUuidMissing(): void {
    $errors = $this->validator->validate([]);

    $this->assertNotEmpty($errors);
    $this->assertContains('option_uuid is required', $errors);
  }

  /**
   * @covers ::validate
   */
  public function testValidateFailsWhenOptionUuidIsNotString(): void {
    $errors = $this->validator->validate(['option_uuid' => 12345]);

    $this->assertNotEmpty($errors);
    $this->assertContains('option_uuid must be a valid UUID string', $errors);
  }

  /**
   * @covers ::validate
   */
  public function testValidateFailsWhenOptionUuidIsEmptyString(): void {
    $errors = $this->validator->validate(['option_uuid' => '']);

    $this->assertNotEmpty($errors);
    $this->assertContains('option_uuid must be a valid UUID string', $errors);
  }

  /**
   * @covers ::validate
   */
  public function testValidateFailsWhenOptionUuidHasInvalidFormat(): void {
    $errors = $this->validator->validate(['option_uuid' => 'not-a-uuid']);

    $this->assertNotEmpty($errors);
    $this->assertContains('option_uuid must be a valid UUID string', $errors);
  }

  /**
   * @covers ::validate
   */
  public function testValidateRejectsExtraUnknownFields(): void {
    $errors = $this->validator->validate([
      'option_uuid' => 'aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee',
      'injected_field' => 'evil',
    ]);

    $this->assertNotEmpty($errors);
    $this->assertContains('Unknown field: injected_field', $errors);
  }

}
