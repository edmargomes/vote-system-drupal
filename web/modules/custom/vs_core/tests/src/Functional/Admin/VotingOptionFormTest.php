<?php

declare(strict_types=1);

namespace Drupal\Tests\vs_core\Functional\Admin;

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
   * The add-option form renders a file upload widget for the image field.
   *
   * Full upload integration (attach → Upload → Save) requires JavaScript and
   * is covered by WebDriver tests. This test verifies the widget is present so
   * that regression in form building is caught without needing a browser
   * driver.
   */
  public function testAdminCanUploadImageToOption(): void {
    $admin = $this->drupalCreateUser(['administer voting']);
    $this->drupalLogin($admin);
    $question = $this->createQuestion();

    $url = '/admin/content/voting-questions/' . $question->id() . '/options/add';
    $this->drupalGet($url);

    $this->assertSession()->statusCodeEquals(200);
    // The managed_file widget renders a file input named files[image].
    $this->assertSession()->elementExists('css', 'input[type="file"][name="files[image]"]');
  }

}
