<?php

declare(strict_types=1);

namespace Drupal\vs_core\EventSubscriber;

use Drupal\Core\Session\AccountProxyInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Redirects unauthenticated visitors to the login page.
 *
 * Fires on kernel.request with priority 30, after Drupal's RouterListener
 * (priority 32) so that path info is fully resolved, and before
 * VotingRequestSubscriber (priority 20) to avoid unnecessary correlation-ID
 * work for requests that will be redirected.
 *
 * Paths starting with any of the excluded prefixes are never redirected so
 * that machine clients, Drupal system paths, and the login flow itself remain
 * accessible to anonymous users.
 */
class AnonymousRedirectSubscriber implements EventSubscriberInterface {

  /**
   * Path prefixes that must never trigger a redirect.
   *
   * Checked with str_starts_with — O(1) per prefix, no router coupling.
   *
   * @var string[]
   */
  private const EXCLUDED_PREFIXES = [
    '/user/',
    '/api/',
    '/cron/',
    '/update.php',
    '/system/',
    '/admin/',
  ];

  /**
   * Constructs an AnonymousRedirectSubscriber.
   *
   * @param \Drupal\Core\Session\AccountProxyInterface $currentUser
   *   The current user proxy service.
   */
  public function __construct(
    private readonly AccountProxyInterface $currentUser,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      KernelEvents::REQUEST => ['onKernelRequest', 30],
    ];
  }

  /**
   * Redirects anonymous users to the login page when the path is not excluded.
   *
   * @param \Symfony\Component\HttpKernel\Event\RequestEvent $event
   *   The kernel request event.
   */
  public function onKernelRequest(RequestEvent $event): void {
    if (!$event->isMainRequest()) {
      return;
    }

    if (!$this->currentUser->isAnonymous()) {
      return;
    }

    $path = $event->getRequest()->getPathInfo();

    foreach (self::EXCLUDED_PREFIXES as $prefix) {
      if (str_starts_with($path, $prefix)) {
        return;
      }
    }

    $event->setResponse(new RedirectResponse('/user/login', 302));
  }

}
