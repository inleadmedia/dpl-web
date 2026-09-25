<?php

declare(strict_types=1);

namespace Drupal\eonext_kultur_events\Plugin\Linkit\Matcher;

use Drupal\linkit\MatcherBase;
use Drupal\linkit\Suggestion\DescriptionSuggestion;
use Drupal\linkit\Suggestion\SuggestionCollection;

/**
 * Suggests fixed kultur site pages such as the events overview.
 *
 * @Matcher(
 *   id = "kultur_site_page",
 *   label = @Translation("Kultur site pages"),
 * )
 */
final class KulturSitePageMatcher extends MatcherBase {

  /**
   * {@inheritdoc}
   */
  public function execute($string): SuggestionCollection {
    $suggestions = new SuggestionCollection();
    $needle = mb_strtolower(trim((string) $string));

    if ($needle === '') {
      return $suggestions;
    }

    foreach ($this->sitePages() as $page) {
      if (!$this->matchesQuery($needle, $page['keywords'])) {
        continue;
      }

      $suggestion = new DescriptionSuggestion();
      $suggestion->setLabel($page['label'])
        ->setPath($page['path'])
        ->setGroup($this->t('Kultur site pages'))
        ->setDescription($page['description']);
      $suggestions->addSuggestion($suggestion);
    }

    return $suggestions;
  }

  /**
   * @return list<array{label: string, description: string, path: string, keywords: list<string>}>
   *   Known kultur listing pages for banner links.
   */
  private function sitePages(): array {
    $pages = [
      [
        'label' => (string) $this->t('Kalender (/arrangementer)'),
        'description' => (string) $this->t('Events overview page'),
        'path' => '/arrangementer',
        'keywords' => ['arrangement', 'arrangementer', 'kalender', 'event', 'events'],
      ],
    ];

    \Drupal::moduleHandler()->alter('kultur_site_pages', $pages);

    return $pages;
  }

  /**
   * @param list<string> $keywords
   */
  private function matchesQuery(string $needle, array $keywords): bool {
    foreach ($keywords as $keyword) {
      if ($needle === $keyword || str_contains($keyword, $needle) || str_contains($needle, $keyword)) {
        return TRUE;
      }
    }

    return FALSE;
  }

}
