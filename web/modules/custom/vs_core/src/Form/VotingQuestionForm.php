<?php

declare(strict_types=1);

namespace Drupal\vs_core\Form;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\vs_core\Entity\VotingQuestion;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Provides create/edit form for voting_question entities.
 */
class VotingQuestionForm extends FormBase {

  /**
   * Constructs a VotingQuestionForm.
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
    $storage = $this->entityTypeManager->getStorage('voting_question');
    $entity = $form_state->get('question_entity');

    $values = [
      'title' => $form_state->getValue('title'),
      'description' => $form_state->getValue('description'),
      'status' => (bool) $form_state->getValue('status'),
      'show_results' => (bool) $form_state->getValue('show_results'),
    ];

    if (!$entity instanceof VotingQuestion) {
      $entity = $storage->create($values);
    }
    else {
      foreach ($values as $field => $value) {
        $entity->set($field, $value);
      }
    }

    $entity->save();

    $this->messenger()->addStatus($this->t('Voting question saved.'));
    $form_state->setRedirect('vs_core.admin.questions_list');
  }

}
