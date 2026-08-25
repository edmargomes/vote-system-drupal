<?php

declare(strict_types=1);

namespace Drupal\vs_core\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Provides the admin settings form for the VS Core voting module.
 */
class VotingSettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames(): array {
    return ['vs_core.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'vs_core_voting_settings';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $config = $this->config('vs_core.settings');

    $form['voting_enabled'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Voting enabled'),
      '#description' => $this->t('When unchecked, all voting endpoints return 403.'),
      '#default_value' => (bool) $config->get('voting_enabled'),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $this->config('vs_core.settings')
      ->set('voting_enabled', (bool) $form_state->getValue('voting_enabled'))
      ->save();

    parent::submitForm($form, $form_state);
  }

}
