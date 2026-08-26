<?php

declare(strict_types=1);

namespace Drupal\vs_core\Entity;

use Drupal\Core\Entity\Attribute\ContentEntityType;
use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Defines the voting_question entity type.
 */
#[ContentEntityType(
  id: 'voting_question',
  label: new TranslatableMarkup('Voting Question'),
  base_table: 'voting_question',
  entity_keys: [
    'id' => 'id',
    'uuid' => 'uuid',
    'label' => 'title',
    'uid' => 'uid',
    'status' => 'status',
  ],
)]
class VotingQuestion extends ContentEntityBase implements VotingQuestionInterface {

  /**
   * {@inheritdoc}
   */
  public function isOpen(): bool {
    if (!(bool) $this->get('status')->value) {
      return FALSE;
    }

    $closesAt = $this->get('closes_at')->value;
    if ($closesAt === NULL) {
      return TRUE;
    }

    return (int) $closesAt > \Drupal::time()->getRequestTime();
  }

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type): array {
    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['title'] = BaseFieldDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Title'))
      ->setRequired(TRUE)
      ->setSetting('max_length', 255);

    $fields['description'] = BaseFieldDefinition::create('text_long')
      ->setLabel(new TranslatableMarkup('Description'))
      ->setRequired(FALSE);

    $fields['status'] = BaseFieldDefinition::create('boolean')
      ->setLabel(new TranslatableMarkup('Status'))
      ->setRequired(TRUE)
      ->setDefaultValue(TRUE);

    $fields['show_results'] = BaseFieldDefinition::create('boolean')
      ->setLabel(new TranslatableMarkup('Show results'))
      ->setRequired(TRUE)
      ->setDefaultValue(FALSE);

    $fields['uid'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(new TranslatableMarkup('Author'))
      ->setRequired(TRUE)
      ->setSetting('target_type', 'user')
      ->setDefaultValueCallback('vs_core_voting_question_uid_default');

    // Timestamp storage is used (consistent with 'created'/'changed') to avoid
    // timezone conversion complexity and to allow simple integer comparisons in
    // entity queries for the "is question open?" check.
    $fields['closes_at'] = BaseFieldDefinition::create('timestamp')
      ->setLabel(new TranslatableMarkup('Closes at'))
      ->setRequired(FALSE);

    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(new TranslatableMarkup('Created'));

    $fields['changed'] = BaseFieldDefinition::create('changed')
      ->setLabel(new TranslatableMarkup('Changed'));

    return $fields;
  }

}
