<?php

declare(strict_types=1);

namespace Drupal\Tests\vs_core\Unit\EventSubscriber;

use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\vs_core\EventSubscriber\AnonymousRedirectSubscriber;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * @coversDefaultClass \Drupal\vs_core\EventSubscriber\AnonymousRedirectSubscriber
 * @group vs_core
 */
class AnonymousRedirectSubscriberTest extends UnitTestCase {

  /**
   * Builds a RequestEvent for a given path and request type.
   *
   * @param string $path
   *   The path info for the request.
   * @param int $requestType
   *   Either MAIN_REQUEST or SUB_REQUEST constant from HttpKernelInterface.
   *
   * @return \Symfony\Component\HttpKernel\Event\RequestEvent
   *   A fully configured request event.
   */
  private function buildEvent(string $path, int $requestType = HttpKernelInterface::MAIN_REQUEST): RequestEvent {
    $kernel = $this->createMock(HttpKernelInterface::class);
    $request = Request::create($path);
    return new RequestEvent($kernel, $request, $requestType);
  }

  /**
   * Builds an AnonymousRedirectSubscriber with the given anonymous state.
   *
   * @param bool $isAnonymous
   *   Whether the current user is anonymous.
   *
   * @return \Drupal\vs_core\EventSubscriber\AnonymousRedirectSubscriber
   *   The subscriber under test.
   */
  private function buildSubscriber(bool $isAnonymous): AnonymousRedirectSubscriber {
    $account = $this->createMock(AccountProxyInterface::class);
    $account->method('isAnonymous')->willReturn($isAnonymous);
    return new AnonymousRedirectSubscriber($account);
  }

  /**
   * @covers ::getSubscribedEvents
   */
  public function testGetSubscribedEventsRegistersOnKernelRequest(): void {
    $events = AnonymousRedirectSubscriber::getSubscribedEvents();

    $this->assertArrayHasKey(KernelEvents::REQUEST, $events);
    $this->assertSame(['onKernelRequest', 30], $events[KernelEvents::REQUEST]);
  }

  /**
   * @covers ::onKernelRequest
   */
  public function testAnonymousUserOnHomepageIsRedirected(): void {
    $subscriber = $this->buildSubscriber(TRUE);
    $event = $this->buildEvent('/');

    $subscriber->onKernelRequest($event);

    $this->assertTrue($event->hasResponse());
    $response = $event->getResponse();
    $this->assertInstanceOf(RedirectResponse::class, $response);
    $this->assertSame(302, $response->getStatusCode());
    $this->assertStringEndsWith('/user/login', $response->getTargetUrl());
  }

  /**
   * @covers ::onKernelRequest
   */
  public function testAnonymousUserOnLoginPageIsNotRedirected(): void {
    $subscriber = $this->buildSubscriber(TRUE);
    $event = $this->buildEvent('/user/login');

    $subscriber->onKernelRequest($event);

    $this->assertFalse($event->hasResponse());
  }

  /**
   * @covers ::onKernelRequest
   */
  public function testAnonymousUserOnApiPathIsNotRedirected(): void {
    $subscriber = $this->buildSubscriber(TRUE);
    $event = $this->buildEvent('/api/v1/questions');

    $subscriber->onKernelRequest($event);

    $this->assertFalse($event->hasResponse());
  }

  /**
   * @covers ::onKernelRequest
   */
  public function testAuthenticatedUserOnHomepageIsNotRedirected(): void {
    $subscriber = $this->buildSubscriber(FALSE);
    $event = $this->buildEvent('/');

    $subscriber->onKernelRequest($event);

    $this->assertFalse($event->hasResponse());
  }

  /**
   * @covers ::onKernelRequest
   */
  public function testAnonymousUserOnRegisterPageIsNotRedirected(): void {
    $subscriber = $this->buildSubscriber(TRUE);
    $event = $this->buildEvent('/user/register');

    $subscriber->onKernelRequest($event);

    $this->assertFalse($event->hasResponse());
  }

  /**
   * @covers ::onKernelRequest
   */
  public function testAnonymousUserOnPasswordPageIsNotRedirected(): void {
    $subscriber = $this->buildSubscriber(TRUE);
    $event = $this->buildEvent('/user/password');

    $subscriber->onKernelRequest($event);

    $this->assertFalse($event->hasResponse());
  }

  /**
   * @covers ::onKernelRequest
   */
  public function testSubRequestIsIgnored(): void {
    $subscriber = $this->buildSubscriber(TRUE);
    $event = $this->buildEvent('/', HttpKernelInterface::SUB_REQUEST);

    $subscriber->onKernelRequest($event);

    $this->assertFalse($event->hasResponse());
  }

  /**
   * @covers ::onKernelRequest
   */
  public function testAnonymousUserOnCronPathIsNotRedirected(): void {
    $subscriber = $this->buildSubscriber(TRUE);
    $event = $this->buildEvent('/cron/abc123');

    $subscriber->onKernelRequest($event);

    $this->assertFalse($event->hasResponse());
  }

}
