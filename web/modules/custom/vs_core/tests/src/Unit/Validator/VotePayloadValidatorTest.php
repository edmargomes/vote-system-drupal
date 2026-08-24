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
  public function testValidatePassesWithValidPayload(): void {
    $errors = $this->validator->validate(['option_id' => 1]);

    $this->assertSame([], $errors);
  }

  /**
   * @covers ::validate
   */
  public function testValidateFailsWhenOptionIdMissing(): void {
    $errors = $this->validator->validate([]);

    $this->assertNotEmpty($errors);
    $this->assertContains('option_id is required', $errors);
  }

  /**
   * @covers ::validate
   */
  public function testValidateFailsWhenOptionIdIsZero(): void {
    $errors = $this->validator->validate(['option_id' => 0]);

    $this->assertNotEmpty($errors);
  }

  /**
   * @covers ::validate
   */
  public function testValidateFailsWhenOptionIdIsNegative(): void {
    $errors = $this->validator->validate(['option_id' => -5]);

    $this->assertNotEmpty($errors);
  }

  /**
   * @covers ::validate
   */
  public function testValidateFailsWhenOptionIdIsNotAnInteger(): void {
    $errors = $this->validator->validate(['option_id' => 'abc']);

    $this->assertNotEmpty($errors);
  }

  /**
   * @covers ::validate
   */
  public function testValidateFailsWhenOptionIdIsFloat(): void {
    $errors = $this->validator->validate(['option_id' => 1.5]);

    $this->assertNotEmpty($errors);
  }

  /**
   * @covers ::validate
   */
  public function testValidateRejectsExtraUnknownFields(): void {
    $errors = $this->validator->validate([
      'option_id' => 1,
      'injected_field' => 'evil',
    ]);

    $this->assertNotEmpty($errors);
    $this->assertContains('Unknown field: injected_field', $errors);
  }

}
