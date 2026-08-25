<?php

declare(strict_types=1);

namespace Drupal\vs_core\Form;

use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\vs_core\Entity\VotingQuestion;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Lists all votes for a question and allows administrators to bulk-delete them.
 *
 * The form is the sole path for removing votes so that the question delete
 * guard in VotingQuestionDeleteForm can remain a hard stop.
 */
class VotingBulkDeleteForm extends FormBase {

  /**
   * Constructs a VotingBulkDeleteForm.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\Core\Datetime\DateFormatterInterface $dateFormatter
   *   The date formatter service.
   */
  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly DateFormatterInterface $dateFormatter,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('date.formatter'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'vs_core_voting_bulk_delete_form';
  }

  /**
   * {@inheritdoc}
   *
   * @param array<string, mixed> $form
   *   The form structure.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   * @param string|null $id
   *   The question entity ID from the route.
   *
   * @return array<string, mixed>
   *   The built form.
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?string $id = NULL): array {
    $loadedQuestion = $this->entityTypeManager
      ->getStorage('voting_question')
      ->load($id);
    if (!$loadedQuestion instanceof VotingQuestion) {
      throw new NotFoundHttpException();
    }

    $form_state->set('question_id', $id);

    /** @var \Drupal\vs_core\Entity\VotingVote[] $votes */
    $votes = $this->entityTypeManager
      ->getStorage('voting_vote')
      ->loadByProperties(['question_id' => $id]);

    if (empty($votes)) {
      $form['empty'] = [
        '#markup' => $this->t('No votes found for this question.'),
      ];

      return $form;
    }

    $options = [];
    foreach ($votes as $vote) {
      /** @var \Drupal\user\UserInterface|null $userEntity */
      $userEntity = $vote->get('uid')->entity;
      /** @var \Drupal\Core\Entity\EntityInterface|null $optionEntity */
      $optionEntity = $vote->get('option_id')->entity;

      $username = $userEntity ? $userEntity->getDisplayName() : (string) $this->t('Unknown');
      $optionLabel = $optionEntity ? $optionEntity->label() : (string) $this->t('Unknown');
      $source = $vote->get('source')->value;
      $created = $this->dateFormatter->format((int) $vote->get('created')->value, 'short');

      $options[$vote->id()] = $this->t('@user — @option (@source) on @date', [
        '@user' => $username,
        '@option' => $optionLabel,
        '@source' => $source,
        '@date' => $created,
      ]);
    }

    $form['votes'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Votes'),
      '#options' => $options,
    ];

    $header = [
      $this->t('Select'),
      $this->t('User'),
      $this->t('Option'),
      $this->t('Source'),
      $this->t('Created'),
    ];
    $rows = [];
    foreach ($votes as $vote) {
      /** @var \Drupal\user\UserInterface|null $userEntity */
      $userEntity = $vote->get('uid')->entity;
      /** @var \Drupal\Core\Entity\EntityInterface|null $optionEntity */
      $optionEntity = $vote->get('option_id')->entity;

      $username = $userEntity ? $userEntity->getDisplayName() : (string) $this->t('Unknown');
      $optionLabel = $optionEntity ? $optionEntity->label() : (string) $this->t('Unknown');

      $rows[] = [
        [
          'data' => [
            '#type' => 'checkbox',
            '#name' => 'votes[' . $vote->id() . ']',
            '#return_value' => $vote->id(),
          ],
        ],
        $username,
        $optionLabel,
        $vote->get('source')->value,
        $this->dateFormatter->format((int) $vote->get('created')->value, 'short'),
      ];
    }

    // The checkboxes element drives form submission; the table is for display.
    $form['votes']['#access'] = FALSE;

    $form['table'] = [
      '#type' => 'table',
      '#header' => $header,
      '#rows' => $rows,
    ];

    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Delete selected votes'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $selected = array_filter((array) $form_state->getValue('votes'));
    $questionId = $form_state->get('question_id');

    if (empty($selected)) {
      $this->messenger()->addWarning($this->t('No votes selected.'));
      $form_state->setRedirect('vs_core.admin.question_votes', ['id' => $questionId]);
      return;
    }

    $voteStorage = $this->entityTypeManager->getStorage('voting_vote');
    $votes = $voteStorage->loadMultiple(array_keys($selected));
    foreach ($votes as $vote) {
      $vote->delete();
    }

    $count = count($votes);
    $this->messenger()->addStatus($this->t('@count vote(s) deleted.', ['@count' => $count]));
    $form_state->setRedirect('vs_core.admin.question_votes', ['id' => $questionId]);
  }

}
