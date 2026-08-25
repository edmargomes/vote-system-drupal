<?php

declare(strict_types=1);

namespace Drupal\vs_core\Entity;

use Drupal\Core\Entity\Attribute\ContentEntityType;
use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Defines the voting_option entity type.
 *
 * The label entity key maps to the field named 'label' so that tests can
 * create options via create(['label' => 'Blue']). The API controller
 * serialises this field under the JSON key 'title' to satisfy the contract.
 */
#[ContentEntityType(
  id: 'voting_option',
  label: new TranslatableMarkup('Voting Option'),
  base_table: 'voting_option',
  entity_keys: [
    'id' => 'id',
    'uuid' => 'uuid',
    'label' => 'label',
  ],
)]
class VotingOption extends ContentEntityBase implements VotingOptionInterface {

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type): array {
    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['question_id'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(new TranslatableMarkup('Question'))
      ->setRequired(TRUE)
      ->setSetting('target_type', 'voting_question');

    $fields['label'] = BaseFieldDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Label'))
      ->setRequired(TRUE)
      ->setSetting('max_length', 255);

    $fields['description'] = BaseFieldDefinition::create('text_long')
      ->setLabel(new TranslatableMarkup('Description'))
      ->setRequired(FALSE);

    $fields['image'] = BaseFieldDefinition::create('image')
      ->setLabel(new TranslatableMarkup('Image'))
      ->setRequired(FALSE)
      ->setSetting('target_type', 'file');

    $fields['weight'] = BaseFieldDefinition::create('integer')
      ->setLabel(new TranslatableMarkup('Weight'))
      ->setRequired(TRUE)
      ->setDefaultValue(0);

    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(new TranslatableMarkup('Created'));

    return $fields;
  }

}
