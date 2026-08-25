<?php

declare(strict_types=1);

namespace Drupal\vs_core\Service;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\vs_core\Entity\VotingQuestionInterface;

/**
 * Builds CacheableMetadata objects for voting API responses.
 *
 * Encapsulates cache-tag assembly so controllers remain free of tag-string
 * knowledge. All tag strings are derived from entity API calls to stay
 * resilient to entity type ID changes.
 */
class VotingCacheService {

  /**
   * Builds CacheableMetadata for the question-list response.
   *
   * Includes the list cache tag plus one entity tag per question so that
   * invalidation fires whenever any listed question is saved or deleted.
   *
   * @param \Drupal\vs_core\Entity\VotingQuestionInterface[] $questions
   *   The questions present in the response.
   *
   * @return \Drupal\Core\Cache\CacheableMetadata
   *   Metadata with question-list and per-question cache tags.
   */
  public function forQuestionList(array $questions): CacheableMetadata {
    $metadata = new CacheableMetadata();

    if (!empty($questions)) {
      // Derive the list tag from the entity type to avoid hardcoding.
      $listTags = reset($questions)->getEntityType()->getListCacheTags();
      $metadata->addCacheTags($listTags);

      foreach ($questions as $question) {
        $metadata->addCacheTags($question->getCacheTags());
      }
    }
    else {
      // No entity instance available, but the list tag must still be set so
      // that saving a new question invalidates this empty-list response.
      $metadata->addCacheTags(['voting_question_list']);
    }

    return $metadata;
  }

  /**
   * Builds CacheableMetadata for a single-question detail response.
   *
   * Tags: voting_question_list + voting_question:{id}.
   *
   * @param \Drupal\vs_core\Entity\VotingQuestionInterface $question
   *   The question returned in the response.
   *
   * @return \Drupal\Core\Cache\CacheableMetadata
   *   Metadata with question-list and entity cache tags.
   */
  public function forQuestionDetail(VotingQuestionInterface $question): CacheableMetadata {
    $metadata = new CacheableMetadata();
    $metadata->addCacheTags($question->getEntityType()->getListCacheTags());
    $metadata->addCacheTags($question->getCacheTags());
    return $metadata;
  }

  /**
   * Builds CacheableMetadata for the admin results response.
   *
   * Tags: voting_question:{id} + voting_vote_list.
   * voting_question_list is intentionally omitted: this endpoint is not a
   * question list — it tracks result data that changes when votes are cast.
   *
   * @param \Drupal\vs_core\Entity\VotingQuestionInterface $question
   *   The question whose results are returned.
   *
   * @return \Drupal\Core\Cache\CacheableMetadata
   *   Metadata with the question entity tag and vote-list tag.
   */
  public function forAdminResults(VotingQuestionInterface $question): CacheableMetadata {
    $metadata = new CacheableMetadata();
    $metadata->addCacheTags($question->getCacheTags());
    // Drupal-standard list tag for voting_vote — no entity instance available.
    $metadata->addCacheTags(['voting_vote_list']);
    return $metadata;
  }

}
