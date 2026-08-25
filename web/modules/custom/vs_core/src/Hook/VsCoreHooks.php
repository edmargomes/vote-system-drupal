<?php

declare(strict_types=1);

namespace Drupal\vs_core\Hook;

use Drupal\Core\Hook\Attribute\Hook;

/**
 * Hook implementations for the VS Core module.
 */
class VsCoreHooks {

  /**
   * Implements hook_theme().
   *
   * Registers the two Twig theme hooks used by the CMS voting interface.
   *
   * @return array<string, array<string, mixed>>
   *   An array of theme hook definitions keyed by hook name.
   */
  #[Hook('theme')]
  public function theme(): array {
    return [
      'vs_core_question_list' => [
        'variables' => [
          'voting_enabled' => TRUE,
          'is_anonymous' => FALSE,
          'questions' => [],
        ],
        'template' => 'vs-core-question-list',
      ],
      'vs_core_question_detail' => [
        'variables' => [
          'question_title' => '',
          'question_description' => NULL,
          'voting_enabled' => TRUE,
          'already_voted' => FALSE,
          'voted_option_label' => NULL,
          'show_results' => FALSE,
          'results' => NULL,
          'form' => [],
        ],
        'template' => 'vs-core-question-detail',
      ],
    ];
  }

}
