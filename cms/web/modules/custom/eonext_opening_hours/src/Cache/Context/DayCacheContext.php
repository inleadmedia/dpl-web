<?php

namespace Drupal\eonext_opening_hours\Cache\Context;

use Drupal\Core\Cache\Context\CacheContextInterface;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Cache\CacheableMetadata;

/**
 * Defines a cache context based on the current date (YYYY-MM-DD).
 */
class DayCacheContext implements CacheContextInterface {

  /**
   * The time object.
   *
   * @var \Drupal\Component\Datetime\TimeInterface
   */
  protected TimeInterface $time;

  /**
   * Constructs the context object.
   *
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time object.
   */
  public function __construct(TimeInterface $time) {
    $this->time = $time;
  }

  /**
   * Returns the current date as the cache context value.
   */
  public function getContext() {
    return date('Y-m-d', $this->time->getCurrentTime());
  }

  /**
   * {@inheritdoc}
   */
  public static function getLabel() {
    return t('Cache varies by day');
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheableMetadata() {
    return new CacheableMetadata();
  }

}
