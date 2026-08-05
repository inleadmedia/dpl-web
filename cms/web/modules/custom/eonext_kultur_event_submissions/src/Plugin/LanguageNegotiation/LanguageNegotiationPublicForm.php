<?php

declare(strict_types=1);

namespace Drupal\eonext_kultur_event_submissions\Plugin\LanguageNegotiation;

use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\language\Attribute\LanguageNegotiation;
use Drupal\language\LanguageNegotiationMethodBase;
use Symfony\Component\HttpFoundation\Request;

/**
 * Forces English on the public Kulturnat event submission form.
 */
#[LanguageNegotiation(
  id: LanguageNegotiationPublicForm::METHOD_ID,
  name: new TranslatableMarkup('Kulturnat event submission form'),
  types: [
    LanguageInterface::TYPE_INTERFACE,
    LanguageInterface::TYPE_CONTENT,
  ],
  weight: -20,
  description: new TranslatableMarkup('Uses English on the public event submission form.'),
)]
final class LanguageNegotiationPublicForm extends LanguageNegotiationMethodBase {

  /**
   * The language negotiation method ID.
   */
  public const METHOD_ID = 'language-eonext-kultur-event-submission-form';

  /**
   * {@inheritdoc}
   */
  public function getLangcode(?Request $request = NULL): ?string {
    if ($request === NULL) {
      return NULL;
    }

    $path = rtrim($request->getPathInfo(), '/');
    if ($path !== '/kultur/register-event' && !str_ends_with($path, '/kultur/register-event')) {
      return NULL;
    }

    return 'en';
  }

}
