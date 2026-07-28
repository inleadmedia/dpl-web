<?php

namespace Drupal\eonext_servicebanner\Plugin\rest\resource\v1;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Cache\CacheableResponse;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\eonext_servicebanner\ServiceBannerSettings;
use Drupal\rest\Plugin\ResourceBase;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Response;

/**
 * REST resource exposing the active service banner.
 *
 * @RestResource(
 *   id = "eonext_servicebanner:service_banner",
 *   label = @Translation("Service banner"),
 *   uri_paths = {
 *     "canonical" = "/api/v1/service-banner",
 *   }
 * )
 */
final class ServiceBannerResource extends ResourceBase {

  const CONFIG_ID = ServiceBannerSettings::CONFIG_ID;

  /**
   * Constructor.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    array $serializer_formats,
    LoggerInterface $logger,
    protected ConfigFactoryInterface $configFactory,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $serializer_formats, $logger);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->getParameter('serializer.formats'),
      $container->get('logger.factory')->get('rest'),
      $container->get('config.factory'),
    );
  }

  /**
   * GET /api/v1/service-banner.
   *
   * Returns the active service banner, or null data when disabled/expired.
   */
  public function get(): Response {
    $config = $this->configFactory->get(self::CONFIG_ID);

    $enabled = (bool) $config->get('enabled');
    $unpublish_on = $config->get('unpublish_on');

    // Auto-disable if the unpublish date has passed.
    if ($enabled && !empty($unpublish_on)) {
      $unpublish_timestamp = strtotime($unpublish_on);
      if ($unpublish_timestamp !== FALSE && $unpublish_timestamp <= time()) {
        $enabled = FALSE;
      }
    }

    $data = $enabled ? [
      'enabled' => TRUE,
      'title' => $config->get('title') ?: NULL,
      'body' => $config->get('body') ?: NULL,
      'url' => $config->get('url') ?: NULL,
      'url_text' => $config->get('url_text') ?: NULL,
      'unpublish_on' => $unpublish_on ?: NULL,
    ] : [
      'enabled' => FALSE,
    ];

    $response = new CacheableResponse(
      json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
      200,
      ['Content-Type' => 'application/json']
    );

    $cache = new CacheableMetadata();
    $cache->addCacheTags($config->getCacheTags());
    // No cache contexts — the banner is the same for all users.
    // Max-age 0 ensures the auto-unpublish date is always evaluated.
    if (!empty($unpublish_on)) {
      $cache->setCacheMaxAge(0);
    }
    $response->addCacheableDependency($cache);

    return $response;
  }

}
