<?php

declare(strict_types=1);

namespace Drupal\vs_core\EventSubscriber;

use Drupal\Component\Uuid\Php as UuidGenerator;
use Drupal\vs_core\Service\VotingLogger;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Injects and propagates the X-Correlation-ID header on every API request.
 *
 * Only acts on requests to paths under /api/v1/.
 */
class VotingRequestSubscriber implements EventSubscriberInterface {

  /**
   * The Correlation ID for the current request lifecycle.
   */
  private string $correlationId = '';

  /**
   * Constructs a VotingRequestSubscriber.
   *
   * @param \Drupal\vs_core\Service\VotingLogger $logger
   *   The voting logger service.
   */
  public function __construct(
    private readonly VotingLogger $logger,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      KernelEvents::REQUEST => ['onRequest', 20],
      KernelEvents::RESPONSE => ['onResponse', 0],
    ];
  }

  /**
   * Reads or generates the Correlation ID from the incoming request.
   *
   * @param \Symfony\Component\HttpKernel\Event\RequestEvent $event
   *   The kernel request event.
   */
  public function onRequest(RequestEvent $event): void {
    if (!$event->isMainRequest()) {
      return;
    }

    $request = $event->getRequest();

    if (!str_starts_with($request->getPathInfo(), '/api/v1/')) {
      return;
    }

    $existing = $request->headers->get('X-Correlation-ID', '');
    $this->correlationId = $existing ?: (new UuidGenerator())->generate();
  }

  /**
   * Injects security and tracing headers into the API response.
   *
   * @param \Symfony\Component\HttpKernel\Event\ResponseEvent $event
   *   The kernel response event.
   */
  public function onResponse(ResponseEvent $event): void {
    if (!$event->isMainRequest()) {
      return;
    }

    $request = $event->getRequest();

    if (!str_starts_with($request->getPathInfo(), '/api/v1/')) {
      return;
    }

    $response = $event->getResponse();
    $response->headers->set('X-Correlation-ID', $this->correlationId);
    $response->headers->set('X-Content-Type-Options', 'nosniff');
    $response->headers->set('X-Frame-Options', 'DENY');
  }

}
