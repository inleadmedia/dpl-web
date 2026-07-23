<?php

namespace Drupal\eonext_eresource\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\Attribute\FieldFormatter;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\link\Plugin\Field\FieldFormatter\LinkSeparateFormatter;

/**
 * Plugin implementation of the 'link' formatter.
 */
#[FieldFormatter(
  id: 'eresource_link',
  label: new TranslatableMarkup('Eresource Link Formatter'),
  field_types: [
    'link',
  ],
)]
class EonextResourceLinkFormatter extends LinkSeparateFormatter {

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $element = [];
    $entity = $items->getEntity();
    $settings = $this->getSettings();

    foreach ($items as $delta => $item) {
      // By default use the full URL as the link text.
      $url = $this->buildUrl($item);
      $link_title = $url->toString();

      // If the link text field value is available, use it for the text.
      if (empty($settings['url_only']) && !empty($item->title)) {
        // Unsanitized token replacement here because the entire link title
        // gets auto-escaped during link generation in
        // \Drupal\Core\Utility\LinkGenerator::generate().
        $link_title = \Drupal::token()->replace($item->title, [$entity->getEntityTypeId() => $entity], ['clear' => TRUE]);
      }

      // The link_separate formatter has two titles; the link text (as in the
      // field values) and the URL itself. If there is no link text value,
      // $link_title defaults to the URL, so it needs to be unset.
      // The URL version may need to be trimmed as well.
      if (empty($item->title)) {
        $link_title = NULL;
      }

      $element[$delta] = [
        '#theme' => 'eresource_link',
        '#title' => $link_title,
        '#url_string' => $url->getUri(),
      ];

    }

    return $element;
  }

}
