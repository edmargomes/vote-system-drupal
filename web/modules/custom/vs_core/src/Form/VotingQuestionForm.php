<?php

declare(strict_types=1);

namespace Drupal\vs_core\Form;

use Drupal\Core\Database\Connection;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\vs_core\Entity\VotingOption;
use Drupal\vs_core\Entity\VotingQuestion;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Provides create/edit form for voting_question entities.
 *
 * The form embeds an inline options sub-form with tabledrag ordering. Options
 * are managed in $form_state under the key 'options_rows' — a list of arrays
 * each describing one option row. Rows may be added via AJAX or carry an
 * 'option_id' key when they correspond to an existing entity.
 */
class VotingQuestionForm extends FormBase {

  /**
   * Constructs a VotingQuestionForm.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection, used to check for votes before option removal.
   */
  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly Connection $database,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('database'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'vs_core_voting_question_form';
  }

  /**
   * {@inheritdoc}
   *
   * @param array<string, mixed> $form
   *   The form structure.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   * @param string|null $id
   *   The question entity ID from the route, or NULL on the add route.
   *
   * @return array<string, mixed>
   *   The built form.
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?string $id = NULL): array {
    $entity = NULL;

    if ($id !== NULL) {
      $loaded = $this->entityTypeManager->getStorage('voting_question')->load($id);
      if (!$loaded instanceof VotingQuestion) {
        throw new NotFoundHttpException();
      }
      $entity = $loaded;
    }

    $form_state->set('question_entity', $entity);

    // Initialise the options_rows list only on the very first form build.
    // Subsequent rebuilds (e.g. AJAX) must preserve whatever the user edited.
    if (!$form_state->has('options_rows')) {
      $rows = [];
      if ($entity !== NULL) {
        /** @var \Drupal\vs_core\Entity\VotingOption[] $options */
        $options = $this->entityTypeManager
          ->getStorage('voting_option')
          ->loadByProperties(['question_id' => $entity->id()]);

        // Sort options by weight before populating rows.
        usort($options, static fn($a, $b) => (int) $a->get('weight')->value <=> (int) $b->get('weight')->value);

        foreach ($options as $option) {
          $rows[] = [
            'option_id' => $option->id(),
            'label' => $option->get('label')->value,
            'description' => $option->get('description')->value ?? '',
            'weight' => (int) $option->get('weight')->value,
          ];
        }
      }
      $form_state->set('options_rows', $rows);
    }

    $form['title'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Title'),
      '#required' => TRUE,
      '#maxlength' => 255,
      '#default_value' => $entity ? $entity->get('title')->value : '',
    ];

    $form['description'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Description'),
      '#description' => $this->t('Optional text displayed below the question title to give voters additional context.'),
      '#required' => FALSE,
      '#default_value' => $entity ? $entity->get('description')->value : '',
    ];

    $form['status'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Active'),
      '#description' => $this->t('When checked, this question is visible to voters. Uncheck to hide it temporarily without deleting it.'),
      '#default_value' => $entity ? (bool) $entity->get('status')->value : TRUE,
    ];

    $form['show_results'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Show results to voters'),
      '#description' => $this->t('When checked, voters can see the current vote tally immediately after submitting their vote. When unchecked, results remain hidden until you enable this option.'),
      '#default_value' => $entity
        ? (bool) $entity->get('show_results')->value
        : FALSE,
    ];

    $closesAtDefault = NULL;
    if ($entity !== NULL && $entity->get('closes_at')->value !== NULL) {
      $closesAtDefault = DrupalDateTime::createFromTimestamp((int) $entity->get('closes_at')->value);
    }

    $form['closes_at'] = [
      '#type' => 'datetime',
      '#title' => $this->t('Closes at'),
      '#description' => $this->t('Leave empty for no expiry. When set, voters can no longer submit votes after this date and time.'),
      '#required' => FALSE,
      '#default_value' => $closesAtDefault,
    ];

    $form['options_section'] = $this->buildOptionsSection($form_state);

    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save'),
    ];

    return $form;
  }

  /**
   * Builds the options management fieldset with tabledrag and AJAX controls.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current form state, used to read options_rows.
   *
   * @return array<string, mixed>
   *   The options_section render array.
   */
  protected function buildOptionsSection(FormStateInterface $form_state): array {
    $optionsRows = $form_state->get('options_rows') ?? [];

    $section = [
      '#type' => 'fieldset',
      '#title' => $this->t('Options'),
      '#description' => $this->t('Manage the selectable answers for this question. Drag rows to reorder them.'),
      '#prefix' => '<div id="options-section-wrapper">',
      '#suffix' => '</div>',
    ];

    $section['options_table'] = [
      '#type' => 'table',
      '#header' => [
        $this->t('Label'),
        $this->t('Description'),
        $this->t('Weight'),
        $this->t('Actions'),
      ],
      '#tabledrag' => [
        [
          'action' => 'order',
          'relationship' => 'sibling',
          'group' => 'options-weight',
        ],
      ],
      '#empty' => $this->t('No options yet. Click "Add option" to add one.'),
      '#attached' => [
        'library' => ['core/drupal.tabledrag'],
      ],
    ];

    foreach ($optionsRows as $delta => $row) {
      $section['options_table'][$delta] = $this->buildOptionRow($delta, $row);
    }

    $section['add_option'] = [
      '#type' => 'submit',
      '#value' => $this->t('Add option'),
      '#submit' => ['::addOptionCallback'],
      '#ajax' => [
        'callback' => '::ajaxRefreshOptionsSection',
        'wrapper' => 'options-section-wrapper',
      ],
      '#limit_validation_errors' => [],
    ];

    return $section;
  }

  /**
   * Builds one row of the options table.
   *
   * @param int $delta
   *   The zero-based row index.
   * @param array<string, mixed> $row
   *   The row data: may contain option_id, label, description, weight.
   *
   * @return array<string, mixed>
   *   A render array for one table row.
   */
  protected function buildOptionRow(int $delta, array $row): array {
    $element = [
      '#attributes' => ['class' => ['draggable']],
    ];

    $element['label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Label'),
      '#title_display' => 'invisible',
      '#required' => FALSE,
      '#default_value' => $row['label'] ?? '',
      '#maxlength' => 255,
    ];

    $element['description'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Description'),
      '#title_display' => 'invisible',
      '#required' => FALSE,
      '#default_value' => $row['description'] ?? '',
      '#rows' => 2,
    ];

    $element['weight'] = [
      '#type' => 'weight',
      '#title' => $this->t('Weight'),
      '#title_display' => 'invisible',
      '#default_value' => $row['weight'] ?? 0,
      '#attributes' => ['class' => ['options-weight']],
    ];

    // Preserve the existing entity ID across rebuilds so submitForm() knows
    // which entity to update rather than creating a duplicate.
    if (!empty($row['option_id'])) {
      $element['option_id'] = [
        '#type' => 'hidden',
        '#value' => $row['option_id'],
      ];
    }

    $element['remove'] = [
      '#type' => 'submit',
      '#value' => $this->t('Remove'),
      '#name' => 'remove_option_' . $delta,
      '#submit' => ['::removeOptionCallback'],
      '#ajax' => [
        'callback' => '::ajaxRefreshOptionsSection',
        'wrapper' => 'options-section-wrapper',
      ],
      '#limit_validation_errors' => [],
      // Store the delta so removeOptionCallback knows which row to drop.
      '#delta' => $delta,
    ];

    return $element;
  }

  /**
   * AJAX submit handler: appends an empty row to options_rows and rebuilds.
   *
   * @param array<string, mixed> $form
   *   The form structure.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  public function addOptionCallback(array &$form, FormStateInterface $form_state): void {
    $rows = $form_state->get('options_rows') ?? [];
    $rows[] = ['label' => '', 'description' => '', 'weight' => 0];
    $form_state->set('options_rows', $rows);
    $form_state->setRebuild(TRUE);
  }

  /**
   * AJAX submit handler: removes the row at the clicked button's delta.
   *
   * If the option has existing votes, removal is blocked and an error messenger
   * message is added. The row stays in the form and the admin must delete the
   * votes first at /admin/content/voting-questions/{id}/votes.
   *
   * @param array<string, mixed> $form
   *   The form structure.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  public function removeOptionCallback(array &$form, FormStateInterface $form_state): void {
    $triggeringElement = $form_state->getTriggeringElement();
    $delta = $triggeringElement['#delta'];

    $rows = $form_state->get('options_rows') ?? [];

    if (!array_key_exists($delta, $rows)) {
      $form_state->setRebuild(TRUE);
      return;
    }

    $optionId = $rows[$delta]['option_id'] ?? NULL;
    if ($optionId !== NULL) {
      $voteCount = (int) $this->database
        ->select('voting_vote', 'v')
        ->condition('v.option_id', $optionId)
        ->countQuery()
        ->execute()
        ->fetchField();

      if ($voteCount > 0) {
        $this->messenger()->addError(
          $this->t('This option cannot be deleted because it has existing votes. Delete the votes first.')
        );
        $form_state->setRebuild(TRUE);
        return;
      }
    }

    array_splice($rows, $delta, 1);
    $form_state->set('options_rows', $rows);
    $form_state->setRebuild(TRUE);
  }

  /**
   * AJAX callback: returns the rebuilt options section wrapper.
   *
   * Returning only the options_section wrapper (not the full form) keeps the
   * AJAX response payload minimal.
   *
   * @param array<string, mixed> $form
   *   The rebuilt form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state after the submit handler ran.
   *
   * @return array<string, mixed>
   *   The options_section render array.
   */
  public function ajaxRefreshOptionsSection(array &$form, FormStateInterface $form_state): array {
    return $form['options_section'];
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $storage = $this->entityTypeManager->getStorage('voting_question');
    $entity = $form_state->get('question_entity');

    $closesAt = NULL;
    $closesAtValue = $form_state->getValue('closes_at');
    if ($closesAtValue instanceof DrupalDateTime) {
      $closesAt = $closesAtValue->getTimestamp();
    }

    $values = [
      'title' => $form_state->getValue('title'),
      'description' => $form_state->getValue('description'),
      'status' => (bool) $form_state->getValue('status'),
      'show_results' => (bool) $form_state->getValue('show_results'),
      'closes_at' => $closesAt,
    ];

    if (!$entity instanceof VotingQuestion) {
      /** @var \Drupal\vs_core\Entity\VotingQuestion $entity */
      $entity = $storage->create($values);
    }
    else {
      foreach ($values as $field => $value) {
        $entity->set($field, $value);
      }
    }

    $entity->save();

    $this->persistOptions($form_state, $entity);

    $this->messenger()->addStatus($this->t('Voting question saved.'));
    $form_state->setRedirect('vs_core.admin.questions_list');
  }

  /**
   * Persists the inline options from the form state.
   *
   * - Rows with an option_id update the matching entity.
   * - Rows without an option_id create a new entity.
   * - Rows that were removed via the AJAX handler no longer appear in
   *   options_rows and their entities are deleted here if they no longer
   *   correspond to any row.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The submitted form state.
   * @param \Drupal\vs_core\Entity\VotingQuestion $question
   *   The saved question entity.
   */
  protected function persistOptions(FormStateInterface $form_state, VotingQuestion $question): void {
    $optionStorage = $this->entityTypeManager->getStorage('voting_option');
    $optionRows = $form_state->get('options_rows') ?? [];
    // The fieldset uses #tree => FALSE (Drupal default), so submitted values
    // are NOT nested under 'options_section' — read from the top level.
    $rawTable = $form_state->getValue('options_table');
    // #type => 'table' has #input => TRUE, so FormBuilder sets the value to ''
    // (not NULL) when the table is absent from POST. The ?? [] fallback does
    // not trigger on '', so we guard explicitly with is_array().
    $tableRows = is_array($rawTable) ? $rawTable : [];

    // Merge weight and label from the submitted table values back into rows.
    foreach ($tableRows as $delta => $tableRow) {
      if (isset($optionRows[$delta])) {
        $optionRows[$delta]['label'] = $tableRow['label'] ?? $optionRows[$delta]['label'];
        $optionRows[$delta]['description'] = $tableRow['description'] ?? $optionRows[$delta]['description'] ?? '';
        $optionRows[$delta]['weight'] = (int) ($tableRow['weight'] ?? $optionRows[$delta]['weight']);
      }
    }

    // Collect the IDs of options that are still present in the form.
    $keptOptionIds = [];
    foreach ($optionRows as $row) {
      if (!empty($row['option_id'])) {
        $keptOptionIds[] = (int) $row['option_id'];
      }
    }

    // Delete options that existed before but were removed from the form.
    $existingOptions = $optionStorage->loadByProperties(['question_id' => $question->id()]);
    foreach ($existingOptions as $existing) {
      if (!in_array((int) $existing->id(), $keptOptionIds, TRUE)) {
        $existing->delete();
      }
    }

    // Create or update the remaining rows.
    foreach ($optionRows as $delta => $row) {
      $label = trim($row['label'] ?? '');
      if ($label === '') {
        continue;
      }

      if (!empty($row['option_id'])) {
        $option = $optionStorage->load($row['option_id']);
        if ($option instanceof VotingOption) {
          $option->set('label', $label);
          $option->set('description', $row['description'] ?? '');
          $option->set('weight', $row['weight'] ?? 0);
          $option->save();
        }
      }
      else {
        $option = $optionStorage->create([
          'label' => $label,
          'description' => $row['description'] ?? '',
          'question_id' => $question->id(),
          'weight' => $row['weight'] ?? 0,
        ]);
        $option->save();
      }
    }
  }

}
