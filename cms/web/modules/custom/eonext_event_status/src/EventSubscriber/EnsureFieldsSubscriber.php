<?php

declare(strict_types=1);

namespace Drupal\eonext_event_status\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Installs ribbon fields when an event form is opened and config is missing.
 */
class EnsureFieldsSubscriber implements EventSubscriberInterface {

  /**
   * Event routes where ribbon fields must exist before the form is built.
   */
  private const EVENT_FORM_ROUTES = [
    'entity.eventseries.edit_form',
    'entity.eventseries.add_form',
    'entity.eventinstance.edit_form',
    'entity.eventinstance.add_form',
  ];

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      KernelEvents::REQUEST => ['onRequest', 30],
    ];
  }

  /**
   * Ensures ribbon field config exists before event edit forms are built.
   */
  public function onRequest(RequestEvent $event): void {
    if (!$event->isMainRequest()) {
      return;
    }

    $route = (string) $event->getRequest()->attributes->get('_route');
    if (!in_array($route, self::EVENT_FORM_ROUTES, TRUE)) {
      return;
    }

    eonext_event_status_ensure_fields_installed();
  }

}
