<?php

declare(strict_types=1);

namespace Drupal\eonext_kultur_locale;

use Drupal\filter\Entity\FilterFormat;
use Drupal\node\NodeInterface;

/**
 * Builds template variables for lokale node displays.
 */
final class LokaleViewBuilder {

  /**
   * Builds the lokale prop bag for row and full view modes.
   *
   * @return array<string, mixed>
   *   Template variables.
   */
  public static function build(NodeInterface $node): array {
    $file_url = \Drupal::service('file_url_generator');

    $images = [];
    if ($node->hasField('field_images') && !$node->get('field_images')->isEmpty()) {
      foreach ($node->get('field_images') as $item) {
        if ($item->entity) {
          $images[] = [
            'src' => $file_url->generateString($item->entity->getFileUri()),
            'alt' => $item->alt ?: $node->label(),
          ];
        }
      }
    }

    $size = ($node->hasField('field_size_m2') && !$node->get('field_size_m2')->isEmpty())
      ? (int) $node->get('field_size_m2')->value : NULL;
    $capacity = ($node->hasField('field_capacity') && !$node->get('field_capacity')->isEmpty())
      ? (int) $node->get('field_capacity')->value : NULL;
    $capacity_parts = [];
    if ($size) {
      $capacity_parts[] = $size . ' m²';
    }
    if ($capacity) {
      $capacity_parts[] = t('Max @n personer', ['@n' => $capacity]);
    }
    $capacity_text = implode(' · ', $capacity_parts);

    $description = '';
    if ($node->hasField('field_description') && !$node->get('field_description')->isEmpty()) {
      $item = $node->get('field_description')->first();
      $description = check_markup($item->value, self::resolveTextFormat($item->format ?? ''));
    }

    $link = static function (string $field) use ($node): array {
      if ($node->hasField($field) && !$node->get($field)->isEmpty()) {
        $item = $node->get($field)->first();
        return ['url' => $item->getUrl()->toString(), 'title' => $item->title ?? ''];
      }
      return ['url' => '', 'title' => ''];
    };

    $booking = $link('field_booking_url');
    $terms = $link('field_booking_terms');
    $contact = $link('field_contact');

    $terms_content = '';
    if ($node->hasField('field_booking_betingelser') && !$node->get('field_booking_betingelser')->isEmpty()) {
      $item = $node->get('field_booking_betingelser')->first();
      $terms_content = check_markup($item->value, self::resolveTextFormat($item->format ?? ''));
    }

    $dialog_available = \Drupal::service('eonext_kultur_locale.dialog_availability')->isAvailable();
    $theme = \Drupal::theme()->getActiveTheme();
    $icon_path = '/' . $theme->getPath() . '/assets/dpl-design-system/icons/collection/CloseLarge.svg';

    return [
      'node_id' => $node->id(),
      'url' => $node->toUrl()->toString(),
      'name' => $node->label(),
      'images' => $images,
      'capacity_text' => $capacity_text,
      'description' => $description,
      'booking_mode' => ($node->hasField('field_booking_mode') && !$node->get('field_booking_mode')->isEmpty())
        ? $node->get('field_booking_mode')->value : 'book',
      'booking_url' => $booking['url'],
      'terms_url' => $terms['url'],
      'terms_content' => $terms_content,
      'use_terms_modal' => $terms_content !== '' && $dialog_available,
      'dialog_close_icon' => $icon_path,
      'contact_url' => $contact['url'],
      'contact_text' => $contact['title'] ?: (string) t('Mere info om lån og kontakt'),
    ];
  }

  /**
   * Resolves a safe text format ID.
   */
  private static function resolveTextFormat(string $format): string {
    if ($format !== '' && FilterFormat::load($format)) {
      return $format;
    }

    return filter_fallback_format();
  }

}
