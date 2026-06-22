<?php

declare(strict_types=1);

namespace Drupal\eonext_token_override\Lms;

/**
 * Builds authorization URLs for Cicero LMS OAuth proxy endpoints.
 *
 * LMS expects redirect_uri to contain the OpenID callback URL plus state,
 * prompt, and agency as a single, fully URL-encoded query parameter value.
 */
final class LmsAuthorizationUrlBuilder {

  /**
   * Builds an LMS authorization URL from standard OAuth query parameters.
   *
   * @param string $authorizationEndpoint
   *   The LMS authorization endpoint URL.
   * @param array<string, scalar|null> $oauthQuery
   *   OAuth query parameters from OpenID Connect, including redirect_uri,
   *   state, prompt, and agency.
   */
  public static function build(string $authorizationEndpoint, array $oauthQuery): string {
    $callbackUrl = (string) ($oauthQuery['redirect_uri'] ?? '');
    $embedded = array_filter([
      'state' => $oauthQuery['state'] ?? NULL,
      'prompt' => $oauthQuery['prompt'] ?? NULL,
      'agency' => $oauthQuery['agency'] ?? NULL,
    ], static fn ($value) => $value !== NULL && $value !== '');

    $innerRedirectUri = $callbackUrl . '?' . http_build_query($embedded, '', '&', PHP_QUERY_RFC3986);

    $params = [
      'client_id' => (string) ($oauthQuery['client_id'] ?? ''),
      'response_type' => (string) ($oauthQuery['response_type'] ?? ''),
      'scope' => (string) ($oauthQuery['scope'] ?? ''),
      'redirect_uri' => $innerRedirectUri,
    ];

    return self::appendQuery($authorizationEndpoint, self::encodeQuery($params));
  }

  /**
   * @param array<string, scalar|null> $params
   */
  private static function encodeQuery(array $params): string {
    $parts = [];
    foreach ($params as $key => $value) {
      $parts[] = rawurlencode($key) . '=' . rawurlencode((string) $value);
    }

    return implode('&', $parts);
  }

  private static function appendQuery(string $url, string $query): string {
    if ($query === '') {
      return $url;
    }

    $separator = str_contains($url, '?') ? '&' : '?';

    return $url . $separator . $query;
  }

}
