<?php

declare(strict_types=1);

namespace Drupal\Tests\vs_core\Functional\Admin;

use Drupal\file\Entity\File;
use Drupal\Tests\BrowserTestBase;
use Drupal\vs_core\Entity\VotingQuestion;

/**
 * Verifies the admin CRUD forms for voting options.
 *
 * @group vs_core
 * @group admin
 */
class VotingOptionFormTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   *
   * The 'file' and 'image' modules are declared explicitly so the managed_file
   * widget is available even when BrowserTestBase does not auto-resolve module
   * dependencies.
   */
  protected static $modules = ['vs_core', 'file', 'image'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Creates and returns a persisted voting_question entity for use in tests.
   */
  private function createQuestion(string $title = 'Test Question'): VotingQuestion {
    $storage = \Drupal::entityTypeManager()->getStorage('voting_question');
    /** @var \Drupal\vs_core\Entity\VotingQuestion $question */
    $question = $storage->create(['title' => $title, 'status' => 1]);
    $question->save();
    return $question;
  }

  /**
   * Anonymous user cannot access the add-option form.
   */
  public function testAnonymousCannotAccessOptionAddForm(): void {
    $question = $this->createQuestion();

    $url = '/admin/content/voting-questions/' . $question->id() . '/options/add';
    $this->drupalGet($url);
    $this->assertSession()->statusCodeEquals(403);
  }

  /**
   * Unprivileged user cannot access the add-option form.
   */
  public function testUnprivilegedUserCannotAccessOptionAddForm(): void {
    $question = $this->createQuestion();
    $user = $this->drupalCreateUser(['vote']);
    $this->drupalLogin($user);

    $url = '/admin/content/voting-questions/' . $question->id() . '/options/add';
    $this->drupalGet($url);
    $this->assertSession()->statusCodeEquals(403);
  }

  /**
   * Admin can create an option with a valid label.
   */
  public function testAdminCanCreateOption(): void {
    $admin = $this->drupalCreateUser(['administer voting']);
    $this->drupalLogin($admin);
    $question = $this->createQuestion();

    $url = '/admin/content/voting-questions/' . $question->id() . '/options/add';
    $this->drupalGet($url);
    $this->submitForm(['label' => 'My Option', 'weight' => 0], 'Save');

    $storage = \Drupal::entityTypeManager()->getStorage('voting_option');
    $entities = $storage->loadByProperties(['label' => 'My Option']);
    $this->assertCount(1, $entities);
    $option = reset($entities);
    $this->assertEquals($question->id(), $option->get('question_id')->target_id);
  }

  /**
   * Submitting the add-option form with an empty label shows an error.
   */
  public function testCreateOptionRequiresLabel(): void {
    $admin = $this->drupalCreateUser(['administer voting']);
    $this->drupalLogin($admin);
    $question = $this->createQuestion();

    $url = '/admin/content/voting-questions/' . $question->id() . '/options/add';
    $this->drupalGet($url);
    $this->submitForm(['label' => '', 'weight' => 0], 'Save');

    $this->assertSession()->pageTextContains('Label field is required');

    $storage = \Drupal::entityTypeManager()->getStorage('voting_option');
    $entities = $storage->loadByProperties([]);
    $this->assertCount(0, $entities);
  }

  /**
   * When weight is not set the saved option has weight = 0.
   */
  public function testCreateOptionDefaultWeightIsZero(): void {
    $admin = $this->drupalCreateUser(['administer voting']);
    $this->drupalLogin($admin);
    $question = $this->createQuestion();

    $url = '/admin/content/voting-questions/' . $question->id() . '/options/add';
    $this->drupalGet($url);
    $this->submitForm(['label' => 'Default Weight Option'], 'Save');

    $storage = \Drupal::entityTypeManager()->getStorage('voting_option');
    $entities = $storage->loadByProperties(['label' => 'Default Weight Option']);
    $option = reset($entities);
    $this->assertNotFalse($option);
    $this->assertEquals(0, $option->get('weight')->value);
  }

  /**
   * Option created under question A has question_id equal to question A's ID.
   */
  public function testCreateOptionLinksToCorrectQuestion(): void {
    $admin = $this->drupalCreateUser(['administer voting']);
    $this->drupalLogin($admin);
    $questionA = $this->createQuestion('Question A');
    $this->createQuestion('Question B');

    $url = '/admin/content/voting-questions/' . $questionA->id() . '/options/add';
    $this->drupalGet($url);
    $this->submitForm(['label' => 'Linked Option', 'weight' => 0], 'Save');

    $storage = \Drupal::entityTypeManager()->getStorage('voting_option');
    $entities = $storage->loadByProperties(['label' => 'Linked Option']);
    $option = reset($entities);
    $this->assertNotFalse($option);
    $this->assertEquals($questionA->id(), $option->get('question_id')->target_id);
  }

  /**
   * Admin can update an option's label via the edit form.
   */
  public function testAdminCanEditOption(): void {
    $admin = $this->drupalCreateUser(['administer voting']);
    $this->drupalLogin($admin);
    $question = $this->createQuestion();

    $oStorage = \Drupal::entityTypeManager()->getStorage('voting_option');
    $option = $oStorage->create([
      'label' => 'Before Edit',
      'question_id' => $question->id(),
      'weight' => 0,
    ]);
    $option->save();

    $url = '/admin/content/voting-questions/'
      . $question->id() . '/options/' . $option->id() . '/edit';
    $this->drupalGet($url);
    $this->submitForm(['label' => 'After Edit'], 'Save');

    $oStorage->resetCache();
    $updated = $oStorage->load($option->id());
    $this->assertEquals('After Edit', $updated->get('label')->value);
  }

  /**
   * Edit form pre-populates the label field from the entity.
   */
  public function testEditFormPrePopulatesLabel(): void {
    $admin = $this->drupalCreateUser(['administer voting']);
    $this->drupalLogin($admin);
    $question = $this->createQuestion();

    $oStorage = \Drupal::entityTypeManager()->getStorage('voting_option');
    $option = $oStorage->create([
      'label' => 'Pre-pop Label',
      'question_id' => $question->id(),
      'weight' => 0,
    ]);
    $option->save();

    $url = '/admin/content/voting-questions/'
      . $question->id() . '/options/' . $option->id() . '/edit';
    $this->drupalGet($url);
    $this->assertSession()->fieldValueEquals('label', 'Pre-pop Label');
  }

  /**
   * Edit form pre-populates the weight field from the entity.
   */
  public function testEditFormPrePopulatesWeight(): void {
    $admin = $this->drupalCreateUser(['administer voting']);
    $this->drupalLogin($admin);
    $question = $this->createQuestion();

    $oStorage = \Drupal::entityTypeManager()->getStorage('voting_option');
    $option = $oStorage->create([
      'label' => 'Weight Option',
      'question_id' => $question->id(),
      'weight' => 5,
    ]);
    $option->save();

    $url = '/admin/content/voting-questions/'
      . $question->id() . '/options/' . $option->id() . '/edit';
    $this->drupalGet($url);
    $this->assertSession()->fieldValueEquals('weight', '5');
  }

  /**
   * GET edit URL with non-existent option ID returns HTTP 404.
   */
  public function testEditNonExistentOptionReturns404(): void {
    $admin = $this->drupalCreateUser(['administer voting']);
    $this->drupalLogin($admin);
    $question = $this->createQuestion();

    $url = '/admin/content/voting-questions/'
      . $question->id() . '/options/99999/edit';
    $this->drupalGet($url);
    $this->assertSession()->statusCodeEquals(404);
  }

  /**
   * Accessing option B via question A's URL (IDOR) returns HTTP 404.
   */
  public function testEditOptionBelongingToWrongQuestionReturns404(): void {
    $admin = $this->drupalCreateUser(['administer voting']);
    $this->drupalLogin($admin);
    $questionA = $this->createQuestion('Question A');
    $questionC = $this->createQuestion('Question C');

    $oStorage = \Drupal::entityTypeManager()->getStorage('voting_option');
    $optionB = $oStorage->create([
      'label' => 'Option B',
      'question_id' => $questionC->id(),
      'weight' => 0,
    ]);
    $optionB->save();

    // Access option B (belongs to C) via question A's URL.
    $url = '/admin/content/voting-questions/'
      . $questionA->id() . '/options/' . $optionB->id() . '/edit';
    $this->drupalGet($url);
    $this->assertSession()->statusCodeEquals(404);
  }

  /**
   * Admin can delete an option via the delete confirmation form.
   */
  public function testAdminCanDeleteOption(): void {
    $admin = $this->drupalCreateUser(['administer voting']);
    $this->drupalLogin($admin);
    $question = $this->createQuestion();

    $oStorage = \Drupal::entityTypeManager()->getStorage('voting_option');
    $option = $oStorage->create([
      'label' => 'Delete Me',
      'question_id' => $question->id(),
      'weight' => 0,
    ]);
    $option->save();
    $oid = $option->id();

    $url = '/admin/content/voting-questions/'
      . $question->id() . '/options/' . $oid . '/delete';
    $this->drupalGet($url);
    $this->submitForm([], 'Delete');

    $oStorage->resetCache();
    $this->assertNull($oStorage->load($oid));
  }

  /**
   * Attempting to delete an option via the wrong question's URL returns 404.
   */
  public function testDeleteOptionBelongingToWrongQuestionReturns404(): void {
    $admin = $this->drupalCreateUser(['administer voting']);
    $this->drupalLogin($admin);
    $questionA = $this->createQuestion('Question A');
    $questionC = $this->createQuestion('Question C');

    $oStorage = \Drupal::entityTypeManager()->getStorage('voting_option');
    $optionB = $oStorage->create([
      'label' => 'Option B',
      'question_id' => $questionC->id(),
      'weight' => 0,
    ]);
    $optionB->save();

    $url = '/admin/content/voting-questions/'
      . $questionA->id() . '/options/' . $optionB->id() . '/delete';
    $this->drupalGet($url);
    $this->assertSession()->statusCodeEquals(404);
  }

  /**
   * Admin can upload a JPEG image when creating an option.
   */
  public function testAdminCanUploadImageToOption(): void {
    $admin = $this->drupalCreateUser(['administer voting']);
    $this->drupalLogin($admin);
    $question = $this->createQuestion();

    // Generate a minimal valid JPEG in the system temp directory.
    $tempDir = \Drupal::service('file_system')->getTempDirectory();
    $tempPath = $tempDir . '/test_image.jpg';
    // 1×1 pixel white JPEG binary.
    $jpegBytes = "\xff\xd8\xff\xe0\x00\x10JFIF\x00\x01\x01\x00\x00\x01\x00\x01"
      . "\x00\x00\xff\xdb\x00C\x00\x08\x06\x06\x07\x06\x05\x08\x07\x07\x07"
      . "\t\t\x08\n\x0c\x14\r\x0c\x0b\x0b\x0c\x19\x12\x13\x0f\x14\x1d\x1a"
      . "\x1f\x1e\x1d\x1a\x1c\x1c $.' \",#\x1c\x1c(7),01444\x1f'9=82<.342"
      . "\x1e\xc5\xff\xc0\x00\x0b\x08\x00\x01\x00\x01\x01\x01\x11\x00"
      . "\xff\xc4\x00\x1f\x00\x00\x01\x05\x01\x01\x01\x01\x01\x01\x00\x00"
      . "\x00\x00\x00\x00\x00\x00\x01\x02\x03\x04\x05\x06\x07\x08\x09\x0a"
      . "\x0b\xff\xda\x00\x08\x01\x01\x00\x00?\x00\xfb\xd2\xff\xd9";
    file_put_contents($tempPath, $jpegBytes);

    $url = '/admin/content/voting-questions/' . $question->id() . '/options/add';
    $this->drupalGet($url);
    $this->getSession()->getPage()->attachFileToField('files[image]', $tempPath);
    $this->getSession()->getPage()->pressButton('Upload');

    $this->submitForm(['label' => 'Option With Image', 'weight' => 0], 'Save');

    $storage = \Drupal::entityTypeManager()->getStorage('voting_option');
    $entities = $storage->loadByProperties(['label' => 'Option With Image']);
    $option = reset($entities);
    $this->assertNotFalse($option);

    $fid = $option->get('image')->target_id;
    $this->assertNotNull($fid);

    $file = File::load($fid);
    $this->assertNotNull($file);
    $this->assertTrue($file->isPermanent());
  }

}
