<?php

namespace Drupal\wisconsin_courts_news\EventSubscriber;

use Drupal\Core\Session\AccountProxyInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Sends PIO users from the general content page to their dashboard.
 */
class PioContentRedirectSubscriber implements EventSubscriberInterface {

  /**
   * The currently signed-in user.
   */
  protected AccountProxyInterface $currentUser;

  /**
   * Receives the current-user service from Drupal.
   */
  public function __construct(AccountProxyInterface $current_user) {
    $this->currentUser = $current_user;
  }

  /**
   * Checks page requests and redirects PIO users when appropriate.
   */
  public function redirectPioContent(RequestEvent $event): void {
    // Only act on the main browser request, not internal subrequests.
    if (!$event->isMainRequest()) {
      return;
    }

    $request = $event->getRequest();

    // Only redirect normal page visits to the general Content page.
    if (
      !in_array($request->getMethod(), ['GET', 'HEAD'], TRUE) ||
      $request->attributes->get('_route') !== 'system.admin_content'
    ) {
      return;
    }

    $roles = $this->currentUser->getRoles();

    // Leave administrators on the regular Content page, even if they
    // also have the PIO role. User 1 is Drupal's special admin account.
    if (
      (int) $this->currentUser->id() === 1 ||
      in_array('administrator', $roles, TRUE)
    ) {
      return;
    }

    // Leave users without the PIO role on the regular Content page.
    if (!in_array('pio', $roles, TRUE)) {
      return;
    }

    // Send the PIO to their dashboard. The base path also supports sites
    // installed in a subdirectory.
    $dashboard_path = $request->getBasePath() . '/admin/content/pio';
    $response = new RedirectResponse($dashboard_path);

    // This redirect depends on the current user, so it must not be
    // stored as a shared response for other users.
    $response->headers->set('Cache-Control', 'private, no-store');

    $event->setResponse($response);
  }

  /**
   * Tells Drupal which event this class listens for.
   */
  public static function getSubscribedEvents(): array {
    // Priority 0 runs after Drupal has identified the requested route.
    return [
      KernelEvents::REQUEST => ['redirectPioContent', 0],
    ];
  }

}