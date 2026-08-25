<?php

declare(strict_types=1);

namespace Drupal\Tests\vs_core\Kernel;

use Drupal\Core\Cache\CacheableResponseInterface;
use Drupal\KernelTests\KernelTestBase;
use Drupal\vs_core\Controller\Api\AdminResultsController;
use Drupal\vs_core\Controller\Api\QuestionApiController;
use Drupal\vs_core\Service\VotingCacheService;
use Symfony\Component\HttpFoundation\Request;

/**
 * Verifies that GET controllers return CacheableJsonResponse with correct tags.
 *
 * Controllers are instantiated directly — the full HTTP kernel is bypassed to
 * keep the test fast and to allow direct inspection of CacheableMetadata.
 *
 * @group vs_core
 */
class CacheHeadersKernelTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'vs_core',
    'user',
    'system',
    'file',
    'image',
    'text',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('voting_question');
    $this->installEntitySchema('voting_option');
    $this->installEntitySchema('voting_vote');
    $this->installEntitySchema('user');
    $this->installConfig(['vs_core']);
  }

  /**
   * Builds a QuestionApiController wired to the real container services.
   *
   * @return \Drupal\vs_core\Controller\Api\QuestionApiController
   *   A controller ready to handle requests.
   */
  private function buildQuestionApiController(): QuestionApiController {
    /** @var \Drupal\vs_core\Service\VotingService $votingService */
    $votingService = $this->container->get('vs_core.voting');

    /** @var \Drupal\vs_core\Service\QuestionService $questionService */
    $questionService = $this->container->get('vs_core.question');

    /** @var \Drupal\Core\File\FileUrlGenerator $fileUrlGenerator */
    $fileUrlGenerator = $this->container->get('file_url_generator');

    $cacheService = new VotingCacheService();

    return new QuestionApiController(
      $votingService,
      $questionService,
      $fileUrlGenerator,
      $cacheService,
    );
  }

  /**
   * Builds an AdminResultsController wired to the real container services.
   *
   * @return \Drupal\vs_core\Controller\Api\AdminResultsController
   *   A controller ready to handle requests.
   */
  private function buildAdminResultsController(): AdminResultsController {
    /** @var \Drupal\vs_core\Service\QuestionService $questionService */
    $questionService = $this->container->get('vs_core.question');

    /** @var \Drupal\vs_core\Service\ResultService $resultService */
    $resultService = $this->container->get('vs_core.result');

    $cacheService = new VotingCacheService();

    return new AdminResultsController(
      $questionService,
      $resultService,
      $cacheService,
    );
  }

  /**
   * QuestionApiController::list() 200 response carries voting_question_list.
   */
  public function testQuestionListResponseCarriesListCacheTag(): void {
    $questionStorage = $this->container->get('entity_type.manager')
      ->getStorage('voting_question');
    $questionStorage->create(['title' => 'Cache tag test?', 'status' => TRUE])->save();

    $controller = $this->buildQuestionApiController();
    $response = $controller->list(Request::create('/api/v1/questions'));

    $this->assertInstanceOf(CacheableResponseInterface::class, $response);
    $tags = $response->getCacheableMetadata()->getCacheTags();
    $this->assertContains('voting_question_list', $tags);
  }

  /**
   * QuestionApiController::detail() 200 response carries the entity cache tag.
   */
  public function testQuestionDetailResponseCarriesEntityCacheTag(): void {
    $questionStorage = $this->container->get('entity_type.manager')
      ->getStorage('voting_question');

    /** @var \Drupal\vs_core\Entity\VotingQuestionInterface $question */
    $question = $questionStorage->create(['title' => 'Detail cache?', 'status' => TRUE]);
    $question->save();

    $controller = $this->buildQuestionApiController();
    $url = '/api/v1/questions/' . $question->uuid();
    $response = $controller->detail($question->uuid(), Request::create($url));

    $this->assertInstanceOf(CacheableResponseInterface::class, $response);
    $tags = $response->getCacheableMetadata()->getCacheTags();
    $entityTag = 'voting_question:' . $question->id();
    $this->assertContains($entityTag, $tags);
  }

  /**
   * AdminResultsController::results() carries entity + vote-list, not list tag.
   */
  public function testAdminResultsResponseCarriesEntityAndVoteListTags(): void {
    $questionStorage = $this->container->get('entity_type.manager')
      ->getStorage('voting_question');

    /** @var \Drupal\vs_core\Entity\VotingQuestionInterface $question */
    $question = $questionStorage->create(['title' => 'Results cache?', 'status' => TRUE]);
    $question->save();

    $controller = $this->buildAdminResultsController();
    $response = $controller->results($question->uuid(), Request::create('/api/v1/admin/questions/' . $question->uuid() . '/results'));

    $this->assertInstanceOf(CacheableResponseInterface::class, $response);
    $tags = $response->getCacheableMetadata()->getCacheTags();
    $entityTag = 'voting_question:' . $question->id();
    $this->assertContains($entityTag, $tags);
    $this->assertContains('voting_vote_list', $tags);
    $this->assertNotContains('voting_question_list', $tags);
  }

}
