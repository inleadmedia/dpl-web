<?php

declare(strict_types=1);

namespace Drupal\eonext_event_engine\EventSubscriber;

use Drupal\Core\Config\ConfigEvents;
use Drupal\Core\Config\FileStorage;
use Drupal\Core\Config\StorageInterface;
use Drupal\Core\Config\StorageTransformEvent;
use Drupal\Core\Extension\ExtensionPathResolver;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Overlays this module's configuration onto the DPL CMS import storage.
 */
class OverlayConfigEventSubscriber implements EventSubscriberInterface {

  public function __construct(protected ExtensionPathResolver $pathResolver) {}

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      ConfigEvents::STORAGE_TRANSFORM_IMPORT => ['onImportTransform'],
    ];
  }

  /**
   * Adds our configuration to the storage being imported.
   */
  public function onImportTransform(StorageTransformEvent $event): void {
    $path = $this->pathResolver->getPath('module', 'eonext_event_engine') . '/config/sync';
    self::overlayConfig($event->getStorage(), new FileStorage($path));
  }

  /**
   * Writes every object in the overlay into the target storage.
   */
  public static function overlayConfig(StorageInterface $storage, StorageInterface $overlay): void {
    $collection_names = array_merge(
      [StorageInterface::DEFAULT_COLLECTION],
      $overlay->getAllCollectionNames(),
    );

    foreach ($collection_names as $collection_name) {
      $storage_collection = $storage->createCollection($collection_name);
      $overlay_collection = $overlay->createCollection($collection_name);

      foreach ($overlay_collection->listAll() as $name) {
        $data = $overlay_collection->read($name);
        if ($data) {
          $storage_collection->write($name, $data);
        }
      }
    }
  }

}
