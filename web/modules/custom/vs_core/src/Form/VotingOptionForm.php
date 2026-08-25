<?php

declare(strict_types=1);

namespace Drupal\vs_core\Form;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\file\FileInterface;
use Drupal\vs_core\Entity\VotingOption;
use Drupal\vs_core\Entity\VotingQuestion;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Provides create/edit form for voting_option entities.
 *
 * Both add and edit routes supply the parent question ID as {id}. The edit
 * route additionally supplies the option ID as {oid}. IDOR protection: the
 * form validates that the loaded option's question_id matches {id}.
 */
class VotingOptionForm extends FormBase {

  /**
   * Constructs a VotingOptionForm.
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
    return 'vs_core_voting_option_form';
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
   *   The option entity ID from the route, or NULL on the add route.
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
    $loadedQuestion = $this->entityTypeManager
      ->getStorage('voting_question')
      ->load($id);
    if (!$loadedQuestion instanceof VotingQuestion) {
      throw new NotFoundHttpException();
    }

    $entity = NULL;
    if ($oid !== NULL) {
      $loadedOption = $this->entityTypeManager
        ->getStorage('voting_option')
        ->load($oid);
      if (!$loadedOption instanceof VotingOption) {
        throw new NotFoundHttpException();
      }

      // Prevent IDOR: an option accessed via the wrong question's URL must 404.
      if ((string) $loadedOption->get('question_id')->target_id !== (string) $loadedQuestion->id()) {
        throw new NotFoundHttpException();
      }
      $entity = $loadedOption;
    }

    $form_state->set('option_entity', $entity);
    $form_state->set('question_id', $id);

    $form['label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Label'),
      '#description' => $this->t('The text displayed to voters as a selectable answer. Keep it short and clear.'),
      '#required' => TRUE,
      '#maxlength' => 255,
      '#default_value' => $entity ? $entity->get('label')->value : '',
    ];

    $form['description'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Description'),
      '#description' => $this->t('Optional additional text shown below the option label to give voters more context about this choice.'),
      '#required' => FALSE,
      '#default_value' => $entity ? $entity->get('description')->value : '',
    ];

    $existingFid = NULL;
    if ($entity !== NULL && !$entity->get('image')->isEmpty()) {
      $existingFid = $entity->get('image')->target_id;
    }

    $form['image'] = [
      '#type' => 'managed_file',
      '#title' => $this->t('Image'),
      '#description' => $this->t('Optional image displayed alongside this option. Accepted formats: PNG, GIF, JPG, JPEG, WebP.'),
      '#required' => FALSE,
      '#upload_location' => 'public://voting-options/',
      '#upload_validators' => [
        'FileExtension' => ['extensions' => 'png gif jpg jpeg webp'],
      ],
      '#default_value' => $existingFid ? [$existingFid] : [],
    ];

    $form['weight'] = [
      '#type' => 'number',
      '#title' => $this->t('Weight'),
      '#description' => $this->t('Controls the display order of options. Lower values appear first. Use 0 if order does not matter.'),
      '#required' => TRUE,
      '#default_value' => $entity ? (int) $entity->get('weight')->value : 0,
    ];

    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $storage = $this->entityTypeManager->getStorage('voting_option');
    $entity = $form_state->get('option_entity');
    $questionId = $form_state->get('question_id');

    $loadedQuestion = $this->entityTypeManager
      ->getStorage('voting_question')
      ->load($questionId);

    $fids = $form_state->getValue('image');
    $fid = !empty($fids) ? reset($fids) : NULL;

    if ($fid) {
      $file = $this->entityTypeManager->getStorage('file')->load($fid);
      if ($file instanceof FileInterface) {
        // Mark permanent before linking so file GC does not delete it.
        $file->setPermanent();
        $file->save();
      }
    }

    if (!$entity instanceof VotingOption) {
      $values = [
        'label' => $form_state->getValue('label'),
        'description' => $form_state->getValue('description'),
        'question_id' => $loadedQuestion,
        'weight' => (int) $form_state->getValue('weight'),
        'image' => $fid ? ['target_id' => $fid] : NULL,
      ];
      $entity = $storage->create($values);
    }
    else {
      $entity->set('label', $form_state->getValue('label'));
      $entity->set('description', $form_state->getValue('description'));
      $entity->set('weight', (int) $form_state->getValue('weight'));
      if ($fid) {
        $entity->set('image', ['target_id' => $fid]);
      }
    }

    $entity->save();

    $this->messenger()->addStatus($this->t('Voting option saved.'));
    $form_state->setRedirect('vs_core.admin.question_edit', ['id' => $questionId]);
  }

}
