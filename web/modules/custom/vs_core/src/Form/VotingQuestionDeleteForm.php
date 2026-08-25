<?php

declare(strict_types=1);

namespace Drupal\vs_core\Form;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\vs_core\Entity\VotingQuestion;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Delete confirmation form for a voting_question entity.
 *
 * Deletion is blocked when the question has any associated votes; in that case
 * the form renders an error message with a link to the vote management page
 * instead of the confirmation button.
 */
class VotingQuestionDeleteForm extends ConfirmFormBase {

  /**
   * The voting_question entity being deleted.
   */
  private ?VotingQuestion $question = NULL;

  /**
   * Constructs a VotingQuestionDeleteForm.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   */
  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('entity_type.manager'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'vs_core_voting_question_delete_form';
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion(): TranslatableMarkup {
    return $this->t('Are you sure you want to delete the question %title?', [
      '%title' => $this->question ? $this->question->get('title')->value : '',
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function getConfirmText(): TranslatableMarkup {
    return $this->t('Delete');
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl(): Url {
    return Url::fromRoute('vs_core.admin.questions_list');
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
    $loaded = $this->entityTypeManager->getStorage('voting_question')->load($id);
    if (!$loaded instanceof VotingQuestion) {
      throw new NotFoundHttpException();
    }
    $this->question = $loaded;

    $votes = $this->entityTypeManager
      ->getStorage('voting_vote')
      ->loadByProperties(['question_id' => $this->question->id()]);

    $voteCount = count($votes);

    if ($voteCount > 0) {
      $votesUrl = Url::fromRoute(
        'vs_core.admin.question_votes',
        ['id' => $this->question->id()],
      );
      $link = Link::fromTextAndUrl($this->t('Votes page'), $votesUrl);

      $form['message'] = [
        '#markup' => $this->t(
          'This question cannot be deleted because it has @count vote(s). Go to @link to delete all votes first.',
          ['@count' => $voteCount, '@link' => $link->toString()],
        ),
      ];

      return $form;
    }

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    if ($this->question === NULL) {
      return;
    }

    $optionStorage = $this->entityTypeManager->getStorage('voting_option');
    $options = $optionStorage->loadByProperties(['question_id' => $this->question->id()]);
    foreach ($options as $option) {
      $option->delete();
    }

    $title = $this->question->get('title')->value;
    $this->question->delete();

    $this->messenger()->addStatus(
      $this->t('Voting question %title has been deleted.', ['%title' => $title]),
    );
    $form_state->setRedirect('vs_core.admin.questions_list');
  }

}
