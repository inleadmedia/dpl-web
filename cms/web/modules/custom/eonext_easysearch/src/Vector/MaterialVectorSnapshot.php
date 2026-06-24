<?php

declare(strict_types=1);

namespace Drupal\eonext_easysearch\Vector;

/**
 * Reads and writes normalized vector index snapshots as JSON on disk.
 *
 * Snapshots store FBI-fetched work text + Qdrant payload so you can re-embed
 * and upsert without calling FBI again (e.g. after changing TEI model).
 */
final class MaterialVectorSnapshot {

  public const FORMAT_VERSION = 1;

  /**
   * Default snapshot path relative to the repository root.
   */
  public const DEFAULT_FILENAME = 'fbi-materials.json';

  /**
   * Returns the repository root (parent of the cms/ directory).
   */
  public static function projectRoot(): string {
    return dirname(DRUPAL_ROOT, 2);
  }

  /**
   * Returns the default snapshot directory ({project}/data).
   */
  public static function defaultDirectory(): string {
    return self::projectRoot() . '/data';
  }

  /**
   * Returns the default snapshot file path.
   */
  public static function defaultFilePath(): string {
    return self::defaultDirectory() . '/' . self::DEFAULT_FILENAME;
  }

  /**
   * Resolves a user path or returns the default snapshot path.
   */
  public static function resolvePath(?string $path): string {
    if ($path === NULL || trim($path) === '') {
      return self::defaultFilePath();
    }

    $path = trim($path);
    if ($path[0] !== '/') {
      return self::projectRoot() . '/' . ltrim($path, '/');
    }

    return $path;
  }

  /**
   * Ensures the snapshot directory exists.
   */
  public static function ensureDirectory(string $file_path): void {
    $directory = dirname($file_path);
    if (!is_dir($directory)) {
      mkdir($directory, 0775, TRUE);
    }
  }

  /**
   * Writes a snapshot to disk.
   *
   * @param string $file_path
   *   Absolute path to the JSON file.
   * @param array $metadata
   *   Snapshot metadata (cql, hitcount, branch_ids, etc.).
   * @param array[] $works
   *   Normalised works (workId, text, payload).
   */
  public static function write(string $file_path, array $metadata, array $works): void {
    self::ensureDirectory($file_path);

    $payload = array_merge([
      'format_version' => self::FORMAT_VERSION,
      'exported_at' => gmdate('c'),
      'work_count' => count($works),
      'works' => $works,
    ], $metadata);

    $json = json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
    if (file_put_contents($file_path, $json) === FALSE) {
      throw new \RuntimeException(sprintf('Could not write snapshot to %s.', $file_path));
    }
  }

  /**
   * Reads a snapshot from disk.
   *
   * @param string $file_path
   *   Absolute path to the JSON file.
   *
   * @return array
   *   Decoded snapshot with a 'works' list.
   */
  public static function read(string $file_path): array {
    if (!is_readable($file_path)) {
      throw new \RuntimeException(sprintf('Snapshot file not readable: %s', $file_path));
    }

    $payload = json_decode(
      (string) file_get_contents($file_path),
      TRUE,
      flags: JSON_THROW_ON_ERROR,
    );

    if (!is_array($payload) || !isset($payload['works']) || !is_array($payload['works'])) {
      throw new \RuntimeException('Invalid snapshot format: missing works array.');
    }

    $version = (int) ($payload['format_version'] ?? 0);
    if ($version !== self::FORMAT_VERSION) {
      throw new \RuntimeException(sprintf(
        'Unsupported snapshot format version %d (expected %d).',
        $version,
        self::FORMAT_VERSION,
      ));
    }

    return $payload;
  }

}
