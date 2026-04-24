<?php

namespace Drupal\eonext_translation\EventSubscriber;

use Drupal\graphql\Event\OperationEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Event subscriber to clean language prefixes from GraphQL responses.
 */
class GraphQLResponseSubscriber implements EventSubscriberInterface {

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    return [
      OperationEvent::GRAPHQL_OPERATION_AFTER => 'onGraphQlOperationAfter',
    ];
  }

  /**
   * Clean language prefixes from GraphQL response data.
   *
   * @param \Drupal\graphql\Event\OperationEvent $event
   *   The GraphQL operation event.
   */
  public function onGraphQlOperationAfter(OperationEvent $event) {
    $result = $event->getResult();

    if ($result && isset($result->data)) {
      $this->cleanLanguagePrefixes($result->data);
    }
  }

  /**
   * Recursively clean language prefixes from data.
   *
   * @param mixed $data
   *   The data to clean.
   */
  protected function cleanLanguagePrefixes(&$data) {
    if (is_array($data)) {
      foreach ($data as $key => &$value) {
        if (($key === 'url' || $key === 'path') && is_string($value)) {
          // Clean the URL and path fields specifically.
          $value = $this->removeLanguagePrefix($value);
        }
        elseif (is_array($value) || is_object($value)) {
          // Recursively process nested data.
          $this->cleanLanguagePrefixes($value);
        }
      }
    }
    elseif (is_object($data)) {
      foreach (get_object_vars($data) as $key => &$value) {
        if (($key === 'url' || $key === 'path') && is_string($value)) {
          // Clean the URL and path fields specifically.
          $value = $this->removeLanguagePrefix($value);
        }
        elseif (is_array($value) || is_object($value)) {
          // Recursively process nested data.
          $this->cleanLanguagePrefixes($value);
        }
      }
    }
  }

    /**
   * Remove language prefix from a URL.
   *
   * @param string $url
   *   The URL to clean.
   *
   * @return string
   *   The cleaned URL.
   */
  protected function removeLanguagePrefix(string $url): string {
    // Remove language prefix if present (e.g., /en/, /da/, /de/, /kl/).
    // This works for both full URLs and relative paths.
    return preg_replace('/\/[a-z]{2}\//', '/', $url);
  }

}
