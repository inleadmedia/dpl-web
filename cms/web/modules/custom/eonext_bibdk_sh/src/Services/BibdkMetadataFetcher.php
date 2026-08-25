<?php

namespace Drupal\eonext_bibdk_sh\Services;

use Drupal\Core\File\FileExists;
use Drupal\Core\File\FileSystem;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

class BibdkMetadataFetcher {

  public const BIBDK_FILE_URL = 'https://raw.githubusercontent.com/DBCDK/bibdk_subject_hierarchy/master/data/emnehierarki_full.xml';

  public const BIBDK_FILE_PATH = 'public://bibdk_subject_hierarchy/';

  public const BIBDK_FILE_NAME = 'emnehierarki_full.xml';

  public function __construct(
    protected Client $httpClient,
    protected FileSystem $fileSystem,
    protected LoggerChannelFactoryInterface $loggerChannelFactory
  ) {}

  public function getBibdkMetadata(string $url = self::BIBDK_FILE_URL): void {
    try {
      $response = $this->httpClient->get(self::BIBDK_FILE_URL);
    }
    catch (RequestException $e) {
      $this->loggerChannelFactory->get('logger.eonext_bibdk_sh')->error($e->getMessage());

      return;
    }

    $contents = $response->getBody()->getContents();
    if (!$this->isXml($contents)) {
      $this->loggerChannelFactory->get('eonext_bibdk_sh')->error(
        sprintf('Failed to read bibdk subject hierarchy file from: %s', $url)
      );

      return;
    }

    $dir = self::BIBDK_FILE_PATH;
    $this->fileSystem->prepareDirectory($dir, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS);
    $this->fileSystem->saveData($contents, self::BIBDK_FILE_PATH . self::BIBDK_FILE_NAME, FileExists::Replace);
  }

  public function bibdkHierarchyFileExists(): bool {
    return is_readable(self::BIBDK_FILE_PATH . self::BIBDK_FILE_NAME);
  }

  protected function isXml(string $input): bool {
    $xml = simplexml_load_string($input);

    return $xml !== FALSE;
  }

}
