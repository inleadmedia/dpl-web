<?php

namespace Drupal\eonext_react;

use Drupal\Core\Asset\AssetQueryStringInterface;
use Drupal\Core\Asset\LibraryDiscoveryInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\File\FileExists;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\File\FileUrlGeneratorInterface;

/**
 * Stores the configured JavaScript as a file the page can load.
 *
 * The snippet has to be served as a regular asset rather than inlined: it is
 * meant for standalone React bundles, which need the webpack runtime of
 * dpl-react to be present before they execute. Inline scripts are rendered
 * ahead of the script tags, a file asset can be weighted in between them.
 */
class InjectedJavascript {

  public const CONFIG_ID = 'eonext_react.settings';

  public const LIBRARY = 'eonext_react/injected_javascript';

  public const HOOK_LIBRARY = 'eonext_react/hook_to_react';

  public const ORDER_BEFORE_MOUNT = 'before';

  public const ORDER_AFTER_MOUNT = 'after';

  private const DIRECTORY = 'public://eonext_react';

  private const FILENAME = 'injected.js';

  private const WEIGHT_BEFORE_MOUNT = -17;

  private const WEIGHT_AFTER_MOUNT = -15;

  public function __construct(
    protected readonly FileSystemInterface $fileSystem,
    protected readonly FileUrlGeneratorInterface $fileUrlGenerator,
    protected readonly LibraryDiscoveryInterface $libraryDiscovery,
    protected readonly AssetQueryStringInterface $assetQueryString,
    protected readonly ConfigFactoryInterface $configFactory,
  ) {}

  /**
   * Returns the URI of the generated file.
   */
  public function getUri(): string {
    return self::DIRECTORY . '/' . self::FILENAME;
  }

  /**
   * Returns whether any JavaScript is currently published.
   */
  public function exists(): bool {
    return file_exists($this->getUri());
  }

  /**
   * Returns the root relative path of the generated file, if it exists.
   */
  public function getPath(): ?string {
    if (!$this->exists()) {
      return NULL;
    }
    return $this->fileUrlGenerator->generateString($this->getUri());
  }

  /**
   * Returns whether hook-to-react.js should be loaded alongside the snippet.
   */
  public function usesHookToReact(): bool {
    $enabled = $this->configFactory->get(self::CONFIG_ID)->get('hook_to_react');
    return $enabled === NULL ? TRUE : (bool) $enabled;
  }

  /**
   * Returns whether the snippet runs before or after mount.js.
   */
  public function getOrder(): string {
    $order = $this->configFactory->get(self::CONFIG_ID)->get('order');
    return $order === self::ORDER_AFTER_MOUNT
      ? self::ORDER_AFTER_MOUNT
      : self::ORDER_BEFORE_MOUNT;
  }

  /**
   * Returns the asset weight that realises the configured order.
   */
  public function getWeight(): int {
    return $this->getOrder() === self::ORDER_AFTER_MOUNT
      ? self::WEIGHT_AFTER_MOUNT
      : self::WEIGHT_BEFORE_MOUNT;
  }

  /**
   * Returns the libraries the snippet has to be loaded after.
   */
  public function getLibraryDependencies(): array {
    return $this->usesHookToReact() ? [self::HOOK_LIBRARY] : ['dpl_react/base'];
  }

  /**
   * Writes the JavaScript to disk, or removes the file when empty.
   */
  public function save(string $javascript): void {
    $javascript = trim($javascript);

    if ($javascript === '') {
      if ($this->exists()) {
        $this->fileSystem->delete($this->getUri());
      }
    }
    else {
      $directory = self::DIRECTORY;
      $this->fileSystem->prepareDirectory(
        $directory,
        FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS
      );
      $this->fileSystem->saveData($javascript, $this->getUri(), FileExists::Replace);
    }

    // The library definition is built from the file, and browsers have to be
    // told the asset changed.
    $this->libraryDiscovery->clearCachedDefinitions();
    $this->assetQueryString->reset();
  }

}
