#!/usr/bin/env bash
# Creates scripts/delete-kulturnat-events.php next to this installer. Run from cms/:
#   bash scripts/write-delete-kulturnat-events.sh
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
TARGET="$ROOT/scripts/delete-kulturnat-events.php"
mkdir -p "$ROOT/scripts"
cat > "$TARGET" <<'PHP'
<?php

/**
 * @file
 * Deletes all Kulturnat submissions and published events (CLI-safe).
 *
 * Usage (from cms/):
 *   ./vendor/bin/drush --uri=https://kulturnat-eonext.stg.inlead.dev php:script scripts/delete-kulturnat-events.php
 */

declare(strict_types=1);

use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpKernel\TerminableInterface;

const DELETE_KULTURNAT_EVENTS_MS_BUNDLES_CONFIG = 'eonext_mobilesearch.exportable_bundles';

final class DeleteKulturnatEventsCliHttpKernel implements HttpKernelInterface, TerminableInterface {

  public function handle(Request $request, int $type = self::MAIN_REQUEST, bool $catch = TRUE): Response {
    return new Response('');
  }

  public function terminate(Request $request, Response $response): void {}

}

final class DeleteKulturnatEventsNoopNextDispatcher implements \Drupal\Core\DestructableInterface {

  public function addEvent(mixed $event): void {}

  public function destruct(): void {}

}

function delete_kulturnat_events_isolate_cli(): void {
  \Drupal::getContainer()->set('http_kernel', new DeleteKulturnatEventsCliHttpKernel());

  if (\Drupal::hasService('next.entity_action_event_dispatcher')) {
    \Drupal::getContainer()->set(
      'next.entity_action_event_dispatcher',
      new DeleteKulturnatEventsNoopNextDispatcher(),
    );
  }

  $dispatcher = \Drupal::service('event_dispatcher');
  while ($dispatcher instanceof \Symfony\Component\EventDispatcher\Debug\TraceableEventDispatcher) {
    $reflection = new \ReflectionClass($dispatcher);
    $property = $reflection->getProperty('dispatcher');
    $property->setAccessible(TRUE);
    $dispatcher = $property->getValue($dispatcher);
  }

  if (!$dispatcher instanceof EventDispatcherInterface) {
    return;
  }

  foreach ([KernelEvents::REQUEST, KernelEvents::EXCEPTION, KernelEvents::RESPONSE] as $event_name) {
    $listeners = $dispatcher->getListeners($event_name);
    foreach ($listeners as $listener) {
      $priority = $dispatcher->getListenerPriority($event_name, $listener);
      if ($priority !== NULL) {
        $dispatcher->removeListener($event_name, $listener, $priority);
      }
    }
  }

  foreach (
    [
      'dpl_event.event_series_redirect',
      'dpl_event.event_instance_edit_redirect',
      'eonext.mobilesearch.entity_action_subscriber',
      'next.entity_action_event_revalidate_subscriber',
    ] as $service_id
  ) {
    if (\Drupal::hasService($service_id)) {
      delete_kulturnat_events_remove_subscriber(\Drupal::service($service_id));
    }
  }
}

function delete_kulturnat_events_remove_subscriber(object $subscriber): void {
  if (!$subscriber instanceof EventSubscriberInterface) {
    return;
  }
  $dispatcher = \Drupal::service('event_dispatcher');
  if ($dispatcher instanceof EventDispatcherInterface) {
    $dispatcher->removeSubscriber($subscriber);
  }
}

function delete_kulturnat_events_disable_mobilesearch_push(): ?array {
  if (!\Drupal::moduleHandler()->moduleExists('eonext_mobilesearch')) {
    return NULL;
  }

  $config = \Drupal::configFactory()->getEditable(DELETE_KULTURNAT_EVENTS_MS_BUNDLES_CONFIG);
  $backup = $config->getRawData();
  $changed = FALSE;
  foreach ($backup as $key => $value) {
    if ($value) {
      $config->set($key, FALSE);
      $changed = TRUE;
    }
  }
  if ($changed) {
    $config->save();
    return $backup;
  }

  return NULL;
}

function delete_kulturnat_events_restore_mobilesearch_config(?array $backup): void {
  if ($backup === NULL) {
    return;
  }
  $config = \Drupal::configFactory()->getEditable(DELETE_KULTURNAT_EVENTS_MS_BUNDLES_CONFIG);
  foreach ($backup as $key => $value) {
    $config->set($key, $value);
  }
  $config->save();
}

function delete_kulturnat_events_purge_type(string $type): void {
  delete_kulturnat_events_isolate_cli();
  $storage = \Drupal::entityTypeManager()->getStorage($type);
  $ids = $storage->getQuery()->accessCheck(FALSE)->execute();
  $ok = 0;
  $fail = 0;

  foreach ($ids as $id) {
    $entity = $storage->load($id);
    if ($entity === NULL) {
      continue;
    }
    try {
      delete_kulturnat_events_isolate_cli();
      ob_start();
      $entity->delete();
      ob_end_clean();
      $ok++;
    }
    catch (\Throwable $e) {
      if (ob_get_level() > 0) {
        ob_end_clean();
      }
      $fail++;
      echo $type . ' ' . $id . ' FAILED: ' . $e->getMessage() . PHP_EOL;
    }
  }

  echo $type . ': deleted ' . $ok . ', failed ' . $fail . PHP_EOL;
}

function delete_kulturnat_events_force_remove_eventseries(int $id): void {
  $connection = \Drupal::database();
  $definition = \Drupal::entityTypeManager()->getDefinition('eventseries');
  $paragraph_storage = \Drupal::entityTypeManager()->getStorage('paragraph');
  $instance_storage = \Drupal::entityTypeManager()->getStorage('eventinstance');

  $paragraph_table = 'eventseries__field_event_paragraphs';
  if ($connection->schema()->tableExists($paragraph_table)) {
    $paragraph_ids = $connection->select($paragraph_table, 't')
      ->fields('t', ['field_event_paragraphs_target_id'])
      ->condition('entity_id', $id)
      ->execute()
      ->fetchCol();
    foreach (array_filter($paragraph_ids) as $paragraph_id) {
      $paragraph = $paragraph_storage->load($paragraph_id);
      if ($paragraph !== NULL) {
        ob_start();
        try {
          $paragraph->delete();
        }
        catch (\Throwable) {
        }
        ob_end_clean();
      }
    }
  }

  if ($connection->schema()->tableExists('eventinstance_field_data')) {
    $instance_ids = $connection->select('eventinstance_field_data', 'i')
      ->fields('i', ['id'])
      ->condition('eventseries_id', $id)
      ->execute()
      ->fetchCol();
    foreach ($instance_ids as $instance_id) {
      $instance = $instance_storage->load($instance_id);
      if ($instance !== NULL) {
        ob_start();
        try {
          $instance->delete();
        }
        catch (\Throwable) {
          delete_kulturnat_events_force_remove_eventinstance((int) $instance_id);
        }
        ob_end_clean();
      }
    }
  }

  foreach ($connection->schema()->findTables('eventseries__') as $table) {
    $connection->delete($table)->condition('entity_id', $id)->execute();
  }

  foreach (
    [
      $definition->getRevisionDataTable(),
      $definition->getDataTable(),
      $definition->getRevisionTable(),
      $definition->getBaseTable(),
    ] as $table
  ) {
    if (is_string($table) && $table !== '' && $connection->schema()->tableExists($table)) {
      $connection->delete($table)->condition('id', $id)->execute();
    }
  }

  \Drupal::entityTypeManager()->getStorage('eventseries')->resetCache([$id]);
}

function delete_kulturnat_events_force_remove_eventinstance(int $id): void {
  $connection = \Drupal::database();
  $definition = \Drupal::entityTypeManager()->getDefinition('eventinstance');

  foreach ($connection->schema()->findTables('eventinstance__') as $table) {
    $connection->delete($table)->condition('entity_id', $id)->execute();
  }

  foreach (
    [
      $definition->getRevisionDataTable(),
      $definition->getDataTable(),
      $definition->getRevisionTable(),
      $definition->getBaseTable(),
    ] as $table
  ) {
    if (is_string($table) && $table !== '' && $connection->schema()->tableExists($table)) {
      $connection->delete($table)->condition('id', $id)->execute();
    }
  }

  \Drupal::entityTypeManager()->getStorage('eventinstance')->resetCache([$id]);
}

function delete_kulturnat_events_purge_eventseries(): void {
  delete_kulturnat_events_isolate_cli();
  $storage = \Drupal::entityTypeManager()->getStorage('eventseries');
  $ids = $storage->getQuery()->accessCheck(FALSE)->execute();
  $ok = 0;
  $fail = 0;

  foreach ($ids as $id) {
    $id = (int) $id;
    try {
      delete_kulturnat_events_force_remove_eventseries($id);
      $storage->resetCache([$id]);
      if ($storage->load($id) === NULL) {
        $ok++;
      }
      else {
        $fail++;
        echo 'eventseries ' . $id . ' still present after force remove.' . PHP_EOL;
      }
    }
    catch (\Throwable $e) {
      $fail++;
      echo 'eventseries ' . $id . ' FAILED: ' . $e->getMessage() . PHP_EOL;
    }
  }

  echo 'eventseries: deleted ' . $ok . ', failed ' . $fail . PHP_EOL;
}

delete_kulturnat_events_isolate_cli();
$mobilesearch_config_backup = delete_kulturnat_events_disable_mobilesearch_push();
echo "CLI isolation enabled (noop HTTP kernel, Next queue, kernel listeners stripped).\n";

foreach (['eventseries', 'eventinstance', 'kultur_event_submission'] as $type) {
  echo 'before ' . $type . ': ' . \Drupal::entityTypeManager()->getStorage($type)->getQuery()->accessCheck(FALSE)->count()->execute() . PHP_EOL;
}

delete_kulturnat_events_purge_type('eventinstance');
delete_kulturnat_events_purge_type('kultur_event_submission');
delete_kulturnat_events_purge_eventseries();

delete_kulturnat_events_isolate_cli();
delete_kulturnat_events_restore_mobilesearch_config($mobilesearch_config_backup);

echo '--- after ---' . PHP_EOL;
foreach (['eventseries', 'eventinstance', 'kultur_event_submission'] as $type) {
  echo $type . ': ' . \Drupal::entityTypeManager()->getStorage($type)->getQuery()->accessCheck(FALSE)->count()->execute() . PHP_EOL;
}

echo "Done. Run: drush cr && drush search-api:reset-tracker events && drush search-api:index events\n";
PHP
chmod 644 "$TARGET"
echo "Created $TARGET"
php -l "$TARGET"
