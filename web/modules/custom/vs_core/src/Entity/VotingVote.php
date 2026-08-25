<?php

declare(strict_types=1);

namespace Drupal\vs_core\Entity;

use Drupal\Core\Entity\Attribute\ContentEntityType;
use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Defines the voting_vote entity type.
 */
#[ContentEntityType(
  id: 'voting_vote',
  label: new TranslatableMarkup('Voting Vote'),
  base_table: 'voting_vote',
  entity_keys: [
    'id' => 'id',
    'uuid' => 'uuid',
  ],
)]
class VotingVote extends ContentEntityBase implements VotingVoteInterface {

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type): array {
    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['question_id'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(new TranslatableMarkup('Question'))
      ->setRequired(TRUE)
      ->setSetting('target_type', 'voting_question');

    $fields['option_id'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(new TranslatableMarkup('Option'))
      ->setRequired(TRUE)
      ->setSetting('target_type', 'voting_option');

    $fields['uid'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(new TranslatableMarkup('User'))
      ->setRequired(TRUE)
      ->setSetting('target_type', 'user');

    $fields['source'] = BaseFieldDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Source'))
      ->setRequired(TRUE)
      ->setSetting('max_length', 32)
      ->setDefaultValue('api');

    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(new TranslatableMarkup('Created'));

    return $fields;
  }

}
