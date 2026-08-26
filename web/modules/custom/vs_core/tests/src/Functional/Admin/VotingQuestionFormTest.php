<?php

declare(strict_types=1);

namespace Drupal\Tests\vs_core\Functional\Admin;

use Drupal\Tests\BrowserTestBase;

/**
 * Verifies the admin CRUD forms for voting questions.
 *
 * @group vs_core
 * @group admin
 */
class VotingQuestionFormTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['vs_core'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Anonymous user cannot access the question list.
   */
  public function testAnonymousCannotAccessQuestionList(): void {
    $this->drupalGet('/admin/content/voting-questions');
    $this->assertSession()->statusCodeEquals(403);
  }

  /**
   * Unprivileged user (only 'vote' permission) cannot access the question list.
   */
  public function testUnprivilegedUserCannotAccessQuestionList(): void {
    $user = $this->drupalCreateUser(['vote']);
    $this->drupalLogin($user);

    $this->drupalGet('/admin/content/voting-questions');
    $this->assertSession()->statusCodeEquals(403);
  }

  /**
   * Admin user with 'administer voting' can access the question list.
   */
  public function testAdminCanAccessQuestionList(): void {
    $admin = $this->drupalCreateUser(['administer voting']);
    $this->drupalLogin($admin);

    $this->drupalGet('/admin/content/voting-questions');
    $this->assertSession()->statusCodeEquals(200);
  }

  /**
   * Anonymous user cannot access the add form.
   */
  public function testAnonymousCannotAccessAddForm(): void {
    $this->drupalGet('/admin/content/voting-questions/add');
    $this->assertSession()->statusCodeEquals(403);
  }

  /**
   * Unprivileged user cannot access the add form.
   */
  public function testUnprivilegedUserCannotAccessAddForm(): void {
    $user = $this->drupalCreateUser(['vote']);
    $this->drupalLogin($user);

    $this->drupalGet('/admin/content/voting-questions/add');
    $this->assertSession()->statusCodeEquals(403);
  }

  /**
   * Admin can create a voting question via the add form.
   */
  public function testAdminCanCreateQuestion(): void {
    $admin = $this->drupalCreateUser(['administer voting']);
    $this->drupalLogin($admin);

    $this->drupalGet('/admin/content/voting-questions/add');
    $this->submitForm([
      'title' => 'My Test Question',
      'status' => TRUE,
    ], 'Save');

    $this->assertSession()->statusCodeEquals(200);

    $storage = \Drupal::entityTypeManager()->getStorage('voting_question');
    $entities = $storage->loadByProperties(['title' => 'My Test Question']);
    $this->assertCount(1, $entities);
  }

  /**
   * Default status when creating a question is active (1).
   */
  public function testCreateQuestionDefaultStatusIsActive(): void {
    $admin = $this->drupalCreateUser(['administer voting']);
    $this->drupalLogin($admin);

    $this->drupalGet('/admin/content/voting-questions/add');
    $this->submitForm(['title' => 'Active By Default'], 'Save');

    $storage = \Drupal::entityTypeManager()->getStorage('voting_question');
    $entities = $storage->loadByProperties(['title' => 'Active By Default']);
    $entity = reset($entities);
    $this->assertNotFalse($entity);
    $this->assertEquals(1, $entity->get('status')->value);
  }

  /**
   * Question saved with show_results checked has show_results = 1.
   */
  public function testCreateQuestionWithShowResults(): void {
    $admin = $this->drupalCreateUser(['administer voting']);
    $this->drupalLogin($admin);

    $this->drupalGet('/admin/content/voting-questions/add');
    $this->submitForm([
      'title' => 'Show Results Question',
      'show_results' => TRUE,
    ], 'Save');

    $storage = \Drupal::entityTypeManager()->getStorage('voting_question');
    $entities = $storage->loadByProperties(['title' => 'Show Results Question']);
    $entity = reset($entities);
    $this->assertNotFalse($entity);
    $this->assertEquals(1, $entity->get('show_results')->value);
  }

  /**
   * Submitting the add form without a title shows a validation error.
   */
  public function testCreateQuestionRequiresTitle(): void {
    $admin = $this->drupalCreateUser(['administer voting']);
    $this->drupalLogin($admin);

    $this->drupalGet('/admin/content/voting-questions/add');
    $this->submitForm(['title' => ''], 'Save');

    $this->assertSession()->pageTextContains('Title field is required');

    $storage = \Drupal::entityTypeManager()->getStorage('voting_question');
    $entities = $storage->loadByProperties([]);
    $this->assertCount(0, $entities);
  }

  /**
   * A created question appears in the question list.
   */
  public function testCreatedQuestionAppearsInList(): void {
    $admin = $this->drupalCreateUser(['administer voting']);
    $this->drupalLogin($admin);

    $this->drupalGet('/admin/content/voting-questions/add');
    $this->submitForm(['title' => 'Listed Question'], 'Save');

    $this->drupalGet('/admin/content/voting-questions');
    $this->assertSession()->pageTextContains('Listed Question');
  }

  /**
   * Admin can change a question's title via the edit form.
   */
  public function testAdminCanEditQuestion(): void {
    $admin = $this->drupalCreateUser(['administer voting']);
    $this->drupalLogin($admin);

    $storage = \Drupal::entityTypeManager()->getStorage('voting_question');
    $entity = $storage->create([
      'title' => 'Original Title',
      'status' => 1,
    ]);
    $entity->save();

    $this->drupalGet('/admin/content/voting-questions/' . $entity->id() . '/edit');
    $this->submitForm(['title' => 'Updated Title'], 'Save');

    $storage->resetCache();
    $updated = $storage->load($entity->id());
    $this->assertEquals('Updated Title', $updated->get('title')->value);
  }

  /**
   * Edit form pre-populates the title field from the entity.
   */
  public function testEditFormPrePopulatesTitle(): void {
    $admin = $this->drupalCreateUser(['administer voting']);
    $this->drupalLogin($admin);

    $storage = \Drupal::entityTypeManager()->getStorage('voting_question');
    $entity = $storage->create(['title' => 'Pre-populated Title', 'status' => 1]);
    $entity->save();

    $this->drupalGet('/admin/content/voting-questions/' . $entity->id() . '/edit');
    $this->assertSession()->fieldValueEquals('title', 'Pre-populated Title');
  }

  /**
   * Edit form shows the status checkbox unchecked for an inactive question.
   */
  public function testEditFormPrePopulatesStatus(): void {
    $admin = $this->drupalCreateUser(['administer voting']);
    $this->drupalLogin($admin);

    $storage = \Drupal::entityTypeManager()->getStorage('voting_question');
    $entity = $storage->create(['title' => 'Inactive Question', 'status' => 0]);
    $entity->save();

    $this->drupalGet('/admin/content/voting-questions/' . $entity->id() . '/edit');
    $this->assertSession()->checkboxNotChecked('status');
  }

  /**
   * GET edit URL for a non-existent question returns HTTP 404.
   */
  public function testEditNonExistentQuestionReturns404(): void {
    $admin = $this->drupalCreateUser(['administer voting']);
    $this->drupalLogin($admin);

    $this->drupalGet('/admin/content/voting-questions/99999/edit');
    $this->assertSession()->statusCodeEquals(404);
  }

  /**
   * Admin can delete a voting question via the delete confirmation form.
   */
  public function testAdminCanDeleteQuestion(): void {
    $admin = $this->drupalCreateUser(['administer voting']);
    $this->drupalLogin($admin);

    $storage = \Drupal::entityTypeManager()->getStorage('voting_question');
    $entity = $storage->create(['title' => 'To Be Deleted', 'status' => 1]);
    $entity->save();
    $id = $entity->id();

    $this->drupalGet('/admin/content/voting-questions/' . $id . '/delete');
    $this->submitForm([], 'Delete');

    $storage->resetCache();
    $this->assertNull($storage->load($id));
  }

  /**
   * Deleting a question also deletes all its child options.
   */
  public function testDeleteQuestionAlsoDeletesItsOptions(): void {
    $admin = $this->drupalCreateUser(['administer voting']);
    $this->drupalLogin($admin);

    $qStorage = \Drupal::entityTypeManager()->getStorage('voting_question');
    $oStorage = \Drupal::entityTypeManager()->getStorage('voting_option');

    $question = $qStorage->create([
      'title' => 'Question With Options',
      'status' => 1,
    ]);
    $question->save();

    $opt1 = $oStorage->create([
      'label' => 'Option A',
      'question_id' => $question->id(),
      'weight' => 0,
    ]);
    $opt1->save();
    $opt2 = $oStorage->create([
      'label' => 'Option B',
      'question_id' => $question->id(),
      'weight' => 1,
    ]);
    $opt2->save();

    $this->drupalGet('/admin/content/voting-questions/' . $question->id() . '/delete');
    $this->submitForm([], 'Delete');

    $oStorage->resetCache();
    $this->assertNull($oStorage->load($opt1->id()));
    $this->assertNull($oStorage->load($opt2->id()));
  }

  /**
   * GET delete URL for a non-existent question returns HTTP 404.
   */
  public function testDeleteNonExistentQuestionReturns404(): void {
    $admin = $this->drupalCreateUser(['administer voting']);
    $this->drupalLogin($admin);

    $this->drupalGet('/admin/content/voting-questions/99999/delete');
    $this->assertSession()->statusCodeEquals(404);
  }

  /**
   * Edit form renders the closes_at date/time field.
   */
  public function testEditFormRendersClosesAtField(): void {
    $admin = $this->drupalCreateUser(['administer voting']);
    $this->drupalLogin($admin);

    $storage = \Drupal::entityTypeManager()->getStorage('voting_question');
    $entity = $storage->create(['title' => 'Expiry Field Test', 'status' => 1]);
    $entity->save();

    $this->drupalGet('/admin/content/voting-questions/' . $entity->id() . '/edit');
    $this->assertSession()->fieldExists('closes_at[date]');
  }

  /**
   * Saving the form with a closes_at value persists the timestamp.
   */
  public function testSavingClosesAtPersistsTimestamp(): void {
    $admin = $this->drupalCreateUser(['administer voting']);
    $this->drupalLogin($admin);

    $storage = \Drupal::entityTypeManager()->getStorage('voting_question');
    $entity = $storage->create(['title' => 'Closes At Persist Test', 'status' => 1]);
    $entity->save();

    $this->drupalGet('/admin/content/voting-questions/' . $entity->id() . '/edit');
    $this->submitForm([
      'title' => 'Closes At Persist Test',
      'closes_at[date]' => '2030-12-31',
      'closes_at[time]' => '23:59:00',
    ], 'Save');

    $storage->resetCache();
    $updated = $storage->load($entity->id());
    $this->assertNotNull($updated->get('closes_at')->value);
  }

  /**
   * Saving the form without closes_at persists NULL.
   */
  public function testSavingWithoutClosesAtPersistsNull(): void {
    $admin = $this->drupalCreateUser(['administer voting']);
    $this->drupalLogin($admin);

    $storage = \Drupal::entityTypeManager()->getStorage('voting_question');
    $entity = $storage->create(['title' => 'No Expiry Test', 'status' => 1]);
    $entity->save();

    $this->drupalGet('/admin/content/voting-questions/' . $entity->id() . '/edit');
    $this->submitForm([
      'title' => 'No Expiry Test',
      'closes_at[date]' => '',
      'closes_at[time]' => '',
    ], 'Save');

    $storage->resetCache();
    $updated = $storage->load($entity->id());
    $this->assertNull($updated->get('closes_at')->value);
  }

  /**
   * Edit form renders the options sub-form section.
   */
  public function testEditFormRendersOptionsSection(): void {
    $admin = $this->drupalCreateUser(['administer voting']);
    $this->drupalLogin($admin);

    $storage = \Drupal::entityTypeManager()->getStorage('voting_question');
    $entity = $storage->create(['title' => 'Options Section Test', 'status' => 1]);
    $entity->save();

    $this->drupalGet('/admin/content/voting-questions/' . $entity->id() . '/edit');
    $this->assertSession()->pageTextContains('Options');
  }

  /**
   * Adding an option inline saves it linked to the question.
   */
  public function testAddingOptionInlineSavesItLinkedToQuestion(): void {
    $admin = $this->drupalCreateUser(['administer voting']);
    $this->drupalLogin($admin);

    $storage = \Drupal::entityTypeManager()->getStorage('voting_question');
    $entity = $storage->create(['title' => 'Inline Option Test', 'status' => 1]);
    $entity->save();

    $this->drupalGet('/admin/content/voting-questions/' . $entity->id() . '/edit');
    $this->getSession()->getPage()->pressButton('Add option');
    $this->assertSession()->waitForElement('css', '[name="options_table[0][label]"]');

    $this->submitForm([
      'options_table[0][label]' => 'My Inline Option',
    ], 'Save');

    $optionStorage = \Drupal::entityTypeManager()->getStorage('voting_option');
    $options = $optionStorage->loadByProperties([
      'question_id' => $entity->id(),
      'label' => 'My Inline Option',
    ]);
    $this->assertCount(1, $options);
  }

  /**
   * Removing an option with existing votes is blocked; entity remains intact.
   *
   * The AJAX remove callback checks for votes before deleting. When votes
   * exist, it adds an error message and aborts so data integrity is preserved.
   */
  public function testRemoveOptionWithVotesIsBlockedAndOptionStillExists(): void {
    $admin = $this->drupalCreateUser(['administer voting']);
    $this->drupalLogin($admin);

    $qStorage = \Drupal::entityTypeManager()->getStorage('voting_question');
    $question = $qStorage->create(['title' => 'Deletion Guard Test', 'status' => 1]);
    $question->save();

    $oStorage = \Drupal::entityTypeManager()->getStorage('voting_option');
    $option = $oStorage->create([
      'label' => 'Option with votes',
      'question_id' => $question->id(),
    ]);
    $option->save();

    // Insert a vote directly so the guard triggers without a full vote flow.
    \Drupal::database()->insert('voting_vote')
      ->fields([
        'uuid' => \Drupal::service('uuid')->generate(),
        'uid' => $admin->id(),
        'question_id' => $question->id(),
        'option_id' => $option->id(),
        'source' => 'cms',
        'created' => time(),
      ])
      ->execute();

    // Trigger the remove AJAX button. BrowserTestBase resolves the AJAX
    // callback synchronously via drupalGet + the button name.
    $this->drupalGet('/admin/content/voting-questions/' . $question->id() . '/edit');
    $this->getSession()->getPage()->pressButton('Remove');

    // The error message must be displayed.
    $this->assertSession()->pageTextContains('cannot be deleted because it has existing votes');

    // The option entity must still exist in the database.
    $oStorage->resetCache();
    $this->assertNotNull($oStorage->load($option->id()));
  }

}
