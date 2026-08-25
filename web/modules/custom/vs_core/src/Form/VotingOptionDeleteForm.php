<?php

declare(strict_types=1);

namespace Drupal\vs_core\Form;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\vs_core\Entity\VotingOption;
use Drupal\vs_core\Entity\VotingQuestion;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Delete confirmation form for a single voting_option entity.
 *
 * IDOR protection: validates that the option's question_id matches the {id}
 * route parameter before rendering the form.
 */
class VotingOptionDeleteForm extends ConfirmFormBase {

  /**
   * The voting_option entity being deleted.
   */
  private ?VotingOption $option = NULL;

  /**
   * The parent question entity ID.
   */
  private ?string $questionId = NULL;

  /**
   * Constructs a VotingOptionDeleteForm.
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
    return 'vs_core_voting_option_delete_form';
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion(): TranslatableMarkup {
    return $this->t('Are you sure you want to delete the option %label?', [
      '%label' => $this->option ? $this->option->label() : '',
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl(): Url {
    return Url::fromRoute('vs_core.admin.question_edit', ['id' => $this->questionId]);
  }

  /**
   * {@inheritdoc}
   *
   * @param array<string, mixed> $form
   *   The form structure.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   * @param string|null $id
   *   The parent question entity ID from the route.
   * @param string|null $oid
   *   The option entity ID from the route.
   *
   * @return array<string, mixed>
   *   The built form.
   */
  public function buildForm(
    array $form,
    FormStateInterface $form_state,
    ?string $id = NULL,
    ?string $oid = NULL,
  ): array {
    $this->questionId = $id;

    $question = $this->entityTypeManager->getStorage('voting_question')->load($id);
    if (!$question instanceof VotingQuestion) {
      throw new NotFoundHttpException();
    }

    $loadedOption = $this->entityTypeManager
      ->getStorage('voting_option')
      ->load($oid);
    if (!$loadedOption instanceof VotingOption) {
      throw new NotFoundHttpException();
    }

    // Prevent IDOR: option must belong to the question in the URL.
    if ((string) $loadedOption->get('question_id')->target_id !== (string) $question->id()) {
      throw new NotFoundHttpException();
    }

    $this->option = $loadedOption;

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    if ($this->option === NULL) {
      return;
    }

    $label = $this->option->label();
    $this->option->delete();

    $this->messenger()->addStatus(
      $this->t('Voting option %label has been deleted.', ['%label' => $label]),
    );
    $form_state->setRedirect('vs_core.admin.question_edit', ['id' => $this->questionId]);
  }

}
