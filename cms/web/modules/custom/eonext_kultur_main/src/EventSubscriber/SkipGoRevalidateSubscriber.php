<?php

declare(strict_types=1);

namespace Drupal\eonext_kultur_main\EventSubscriber;

use Drupal\next\Event\EntityActionEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Keeps Kulturnat content changes off the GO front-end cache.
 *
 * GO node types still point at the "go" next site. Deleting one calls
 * /cache/revalidate on that host, and a 502 from it is logged as an error
 * on the delete request. Kulturnat does not serve that front end.
 */
final class SkipGoRevalidateSubscriber implements EventSubscriberInterface {

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    if (!class_exists(\Drupal\next\Event\EntityEvents::class)) {
      return [];
    }

    return [
      \Drupal\next\Event\EntityEvents::ENTITY_ACTION => ['onAction', 100],
    ];
  }

  /**
   * Drops the GO site before Next pings it.
   */
  public function onAction(EntityActionEvent $event): void {
    $sites = array_filter(
      $event->getSites(),
      static fn ($site): bool => $site->id() !== 'go',
    );
    $event->setSites(array_values($sites));
  }

}
