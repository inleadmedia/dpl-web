<?php

declare(strict_types=1);

namespace Drupal\dpl_fbi\Drush\Commands;

use Drupal\dpl_fbi\FreeTextSearchClient;
use Drush\Attributes\Argument;
use Drush\Attributes\Command;
use Drush\Attributes\Option;
use Drush\Attributes\Usage;
use Drush\Commands\DrushCommands;

/**
 * Drush commands for evaluating FBI search relevance.
 *
 * Lets you compare FBI's free-text search and semantic "mood" search from the
 * CLI, to judge whether a local vector/AI index is actually needed.
 */
final class FbiSearchCommands extends DrushCommands {

  /**
   * Constructs the FBI search commands.
   */
  public function __construct(
    private readonly FreeTextSearchClient $searchClient,
  ) {
    parent::__construct();
  }

  /**
   * Search FBI materials and print ranked results.
   *
   * @param string $query
   *   The natural-language / keyword query.
   * @param array<string,mixed> $options
   *   Command options.
   */
  #[Command(name: 'dpl-fbi:search')]
  #[Argument(name: 'query', description: 'The natural-language / keyword query.')]
  #[Option(name: 'limit', description: 'Maximum number of results per strategy.')]
  #[Option(name: 'mood', description: 'Also run the semantic moodSearch for comparison.')]
  #[Usage(name: 'drush dpl-fbi:search "books about climate change for kids"', description: 'Free-text search scoped to holdings.')]
  #[Usage(name: 'drush dpl-fbi:search "a cozy mystery in a snowy village" --mood', description: 'Compare free-text and semantic search.')]
  public function search(string $query, array $options = [
    'limit' => 10,
    'mood' => FALSE,
  ]): void {
    $limit = max(1, (int) $options['limit']);

    $this->printResults(
      sprintf('Free-text search (holdings-scoped) — "%s"', $query),
      $this->searchClient->search($query, $limit),
    );

    if (!empty($options['mood'])) {
      $this->printResults(
        sprintf('Semantic moodSearch (fiction, not holdings-scoped) — "%s"', $query),
        $this->searchClient->moodSearch($query, $limit),
      );
    }
  }

  /**
   * Prints a labelled, numbered list of work results.
   *
   * @param string $heading
   *   The section heading.
   * @param array<int,array{workId:string,title:string,creator:string}> $results
   *   The works to print.
   */
  private function printResults(string $heading, array $results): void {
    $this->io()->title($heading);

    if ($results === []) {
      $this->io()->warning('No results (or FBI request failed — check the dpl_fbi log).');
      return;
    }

    $rows = [];
    foreach ($results as $i => $work) {
      $rows[] = [
        $i + 1,
        $work['title'] !== '' ? $work['title'] : '(no title)',
        $work['creator'],
        $work['workId'],
      ];
    }

    $this->io()->table(['#', 'Title', 'Creator', 'Work ID'], $rows);
  }

}
