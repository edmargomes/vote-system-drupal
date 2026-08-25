<?php

declare(strict_types=1);

namespace Drupal\vs_core\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\vs_core\Entity\VotingOptionInterface;
use Drupal\vs_core\Entity\VotingQuestionInterface;
use Drupal\vs_core\Exception\DuplicateVoteException;
use Drupal\vs_core\Service\QuestionService;
use Drupal\vs_core\Service\VotingService;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides the CMS voting form for casting a vote on a single question.
 *
 * Business logic guards (voting enabled, already voted) are handled in
 * VotingCmsController::detail(); this form only handles option selection,
 * validation, and submission.
 */
class VotingForm extends FormBase {

  /**
   * Constructs a VotingForm.
   *
   * @param \Drupal\vs_core\Service\VotingService $votingService
   *   The voting service.
   * @param \Drupal\vs_core\Service\QuestionService $questionService
   *   The question service.
   */
  public function __construct(
    private readonly VotingService $votingService,
    private readonly QuestionService $questionService,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('vs_core.voting'),
      $container->get('vs_core.question'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'vs_core_voting_form';
  }

  /**
   * {@inheritdoc}
   *
   * @param array<string, mixed> $form
   *   The form structure.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current form state.
   * @param \Drupal\vs_core\Entity\VotingQuestionInterface|null $question
   *   The question entity passed from the controller.
   *
   * @return array<string, mixed>
   *   The form render array.
   */
  public function buildForm(
    array $form,
    FormStateInterface $form_state,
    ?VotingQuestionInterface $question = NULL,
  ): array {
    // Store for use in submitForm() which cannot receive the question directly.
    $form_state->set('question', $question);

    if ($question === NULL) {
      return $form;
    }

    $options = $this->questionService->getOptions($question);
    $radioOptions = [];
    foreach ($options as $option) {
      $radioOptions[$option->uuid()] = $option->label();
    }

    $form['option_uuid'] = [
      '#type' => 'radios',
      '#title' => $this->t('Select an option'),
      '#options' => $radioOptions,
      '#required' => TRUE,
    ];

    $form['actions'] = [
      '#type' => 'actions',
    ];

    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Vote'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    $optionUuid = $form_state->getValue('option_uuid');

    if ($optionUuid === NULL || $optionUuid === '') {
      return;
    }

    $option = $this->questionService->findOptionByUuid($optionUuid);

    // Defensive check: the #options constraint already prevents invalid UUIDs
    // for form submissions, but direct POST manipulation bypasses that.
    if (!$option instanceof VotingOptionInterface) {
      $form_state->setErrorByName('option_uuid', $this->t('The selected option is not valid.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    /** @var \Drupal\vs_core\Entity\VotingQuestionInterface|null $question */
    $question = $form_state->get('question');

    if (!$question instanceof VotingQuestionInterface) {
      return;
    }

    $optionUuid = $form_state->getValue('option_uuid');
    $option = $this->questionService->findOptionByUuid($optionUuid);

    if (!$option instanceof VotingOptionInterface) {
      return;
    }

    try {
      $this->votingService->registerVote(
        $this->currentUser(),
        $question,
        $option,
        'cms',
      );
      $this->messenger()->addStatus($this->t('Your vote has been registered.'));
    }
    catch (DuplicateVoteException) {
      // This guard handles the race-condition window between the controller's
      // hasVoted() check and the actual insert — the DB constraint is the
      // source of truth.
      $this->messenger()->addWarning(
        $this->t('You have already voted on this question.'),
      );
    }

    $form_state->setRedirectUrl(
      Url::fromRoute('vs_core.cms.question_detail', ['uuid' => $question->uuid()]),
    );
  }

}
