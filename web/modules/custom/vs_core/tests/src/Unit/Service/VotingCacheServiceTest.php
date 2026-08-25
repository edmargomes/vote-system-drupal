<?php

declare(strict_types=1);

namespace Drupal\Tests\vs_core\Unit\Service;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\vs_core\Entity\VotingQuestionInterface;
use Drupal\vs_core\Service\VotingCacheService;

/**
 * @coversDefaultClass \Drupal\vs_core\Service\VotingCacheService
 * @group vs_core
 */
class VotingCacheServiceTest extends UnitTestCase {

  /**
   * Service under test.
   *
   * @var \Drupal\vs_core\Service\VotingCacheService
   */
  private VotingCacheService $service;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->service = new VotingCacheService();
  }

  /**
   * Builds a mock VotingQuestionInterface for the given entity ID.
   *
   * @param int $id
   *   The numeric entity ID.
   *
   * @return \Drupal\vs_core\Entity\VotingQuestionInterface
   *   A configured mock.
   */
  private function mockQuestion(int $id): VotingQuestionInterface {
    $entityType = $this->createMock(EntityTypeInterface::class);
    $entityType->method('getListCacheTags')
      ->willReturn(['voting_question_list']);

    $question = $this->createMock(VotingQuestionInterface::class);
    $question->method('getCacheTags')
      ->willReturn(["voting_question:$id"]);
    $question->method('getEntityType')
      ->willReturn($entityType);

    return $question;
  }

  /**
   * @covers ::forQuestionList
   */
  public function testForQuestionListWithEmptyListReturnsListTag(): void {
    $metadata = $this->service->forQuestionList([]);

    $this->assertInstanceOf(CacheableMetadata::class, $metadata);
    $this->assertContains('voting_question_list', $metadata->getCacheTags());
  }

  /**
   * @covers ::forQuestionList
   */
  public function testForQuestionListWithTwoQuestionsIncludesAllTags(): void {
    $questions = [
      $this->mockQuestion(3),
      $this->mockQuestion(7),
    ];

    $metadata = $this->service->forQuestionList($questions);
    $tags = $metadata->getCacheTags();

    $this->assertContains('voting_question_list', $tags);
    $this->assertContains('voting_question:3', $tags);
    $this->assertContains('voting_question:7', $tags);
  }

  /**
   * @covers ::forQuestionDetail
   */
  public function testForQuestionDetailIncludesListTagAndEntityTag(): void {
    $question = $this->mockQuestion(5);

    $metadata = $this->service->forQuestionDetail($question);
    $tags = $metadata->getCacheTags();

    $this->assertContains('voting_question_list', $tags);
    $this->assertContains('voting_question:5', $tags);
  }

  /**
   * @covers ::forAdminResults
   */
  public function testForAdminResultsIncludesEntityTagAndVoteListTagButNotQuestionListTag(): void {
    $question = $this->mockQuestion(8);

    $metadata = $this->service->forAdminResults($question);
    $tags = $metadata->getCacheTags();

    $this->assertContains('voting_question:8', $tags);
    $this->assertContains('voting_vote_list', $tags);
    $this->assertNotContains('voting_question_list', $tags);
  }

}
