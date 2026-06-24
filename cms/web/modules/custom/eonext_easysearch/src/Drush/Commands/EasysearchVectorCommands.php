<?php

declare(strict_types=1);

namespace Drupal\eonext_easysearch\Drush\Commands;

use Drupal\eonext_easysearch\Vector\MaterialVectorIndexer;
use Drupal\eonext_easysearch\Vector\MaterialVectorSnapshot;
use Drupal\eonext_easysearch\Vector\QdrantClient;
use Drupal\eonext_easysearch\Vector\SemanticSearch;
use Drupal\eonext_easysearch\Vector\VectorSearchSettings;
use Drush\Attributes\Argument;
use Drush\Attributes\Command;
use Drush\Attributes\Option;
use Drush\Attributes\Usage;
use Drush\Commands\DrushCommands;

/**
 * Drush commands for semantic (vector) material search.
 */
final class EasysearchVectorCommands extends DrushCommands {

  /**
   * Constructs the FBI vector commands.
   */
  public function __construct(
    private readonly MaterialVectorIndexer $indexer,
    private readonly SemanticSearch $semanticSearch,
    private readonly QdrantClient $qdrant,
    private readonly VectorSearchSettings $settings,
  ) {
    parent::__construct();
  }

  /**
   * Show active vector search configuration (env-driven).
   */
  #[Command(name: 'eonext-easysearch:vector-config')]
  #[Usage(name: 'drush eonext-easysearch:vector-config', description: 'Print Qdrant + embedding backend settings.')]
  public function config(): void {
    $summary = $this->settings->summary();
    $this->io()->title('Vector search configuration');

    $rows = [];
    foreach ($summary as $key => $value) {
      if ($key === 'qdrant_cloud') {
        $value = $value ? 'yes (API key set)' : 'no (local or no key)';
      }
      $rows[] = [$key, is_bool($value) ? ($value ? 'true' : 'false') : (string) $value];
    }

    $this->io()->table(['Setting', 'Value'], $rows);

    if ($this->settings->usesQdrantInference() && !$this->settings->usesQdrantCloud()) {
      $this->io()->warning('EONEXT_EASYSEARCH_EMBEDDING=qdrant_inference requires Qdrant Cloud (QDRANT_API_KEY).');
    }

    $count = $this->qdrant->count(MaterialVectorIndexer::COLLECTION);
    $this->io()->writeln(sprintf('Collection "%s": %d point(s).', MaterialVectorIndexer::COLLECTION, $count));
  }

  /**
   * Build the vector index from FBI works.
   *
   * @param string $cql
   *   CQL selecting works to index.
   * @param array $options
   *   Command options.
   */
  #[Command(name: 'eonext-easysearch:vector-index')]
  #[Argument(name: 'cql', description: 'CQL selecting works to index.')]
  #[Option(name: 'max', description: 'Maximum number of works to index.')]
  #[Option(name: 'batch', description: 'Batch size for fetching and embedding.')]
  #[Usage(name: 'drush eonext-easysearch:vector-index \'term.type="bog"\' --max=300', description: 'Index up to 300 books.')]
  public function index(string $cql, array $options = [
    'max' => 200,
    'batch' => 50,
  ]): void {
    $this->printBackendHint();
    $this->io()->title(sprintf('Indexing works matching: %s', $cql));

    $stats = $this->indexer->index(
      $cql,
      max(1, (int) $options['max']),
      max(1, (int) $options['batch']),
    );

    $this->io()->success(sprintf(
      'Indexed %d work(s) into "%s" (FBI hitcount: %d, collection now holds %d).',
      $stats['indexed'],
      MaterialVectorIndexer::COLLECTION,
      $stats['hitcount'],
      $this->qdrant->count(MaterialVectorIndexer::COLLECTION),
    ));
  }

  /**
   * Run a semantic search over the vector index.
   *
   * @param string $query
   *   The natural-language query.
   * @param array $options
   *   Command options.
   */
  #[Command(name: 'eonext-easysearch:vector-search')]
  #[Argument(name: 'query', description: 'The natural-language query.')]
  #[Option(name: 'limit', description: 'Maximum number of results.')]
  #[Usage(name: 'drush eonext-easysearch:vector-search "stories about a warming planet"', description: 'Semantic search.')]
  public function search(string $query, array $options = ['limit' => 10]): void {
    $this->io()->title(sprintf('Semantic search — "%s"', $query));

    $results = $this->semanticSearch->search($query, max(1, (int) $options['limit']));

    if ($results === []) {
      $hint = $this->settings->usesQdrantInference()
        ? 'No results (is the index built and is Qdrant Cloud Inference enabled?).'
        : 'No results (is the index built and are TEI/Qdrant services up?).';
      $this->io()->warning($hint);
      return;
    }

    $rows = [];
    foreach ($results as $i => $work) {
      $rows[] = [
        $i + 1,
        sprintf('%.3f', $work['score']),
        $work['title'] !== '' ? $work['title'] : '(no title)',
        $work['creator'],
        $work['materialTypeGeneral'],
        implode(', ', $work['languages']),
        $work['workId'],
      ];
    }

    $this->io()->table(['#', 'Score', 'Title', 'Creator', 'Type', 'Language', 'Work ID'], $rows);
  }

  /**
   * Export FBI works to a JSON snapshot (no embedding).
   *
   * @param string $cql
   *   CQL selecting works to export.
   * @param array $options
   *   Command options.
   */
  #[Command(name: 'eonext-easysearch:vector-export')]
  #[Argument(name: 'cql', description: 'CQL selecting works to export.')]
  #[Option(name: 'max', description: 'Maximum number of works to export.')]
  #[Option(name: 'batch', description: 'FBI fetch batch size.')]
  #[Option(name: 'file', description: 'Snapshot path relative to project root or absolute.')]
  #[Usage(name: "drush eonext-easysearch:vector-export '*' --max=1000 --batch=50", description: 'Export 1000 works to data/fbi-materials.json.')]
  public function export(string $cql, array $options = [
    'max' => 200,
    'batch' => 50,
    'file' => NULL,
  ]): void {
    $file = MaterialVectorSnapshot::resolvePath($options['file'] ?? NULL);
    $this->io()->title(sprintf('Exporting works matching: %s', $cql));
    $this->io()->writeln(sprintf('Snapshot file: %s', $file));

    $stats = $this->indexer->exportToFile(
      $cql,
      max(1, (int) $options['max']),
      max(1, (int) $options['batch']),
      $file,
    );

    $this->io()->success(sprintf(
      'Exported %d work(s) to %s (FBI hitcount: %d). Run eonext-easysearch:vector-import to embed into Qdrant.',
      $stats['exported'],
      $stats['file'],
      $stats['hitcount'],
    ));
  }

  /**
   * Import a JSON snapshot into Qdrant (embed + upsert, no FBI).
   *
   * @param array $options
   *   Command options.
   */
  #[Command(name: 'eonext-easysearch:vector-import')]
  #[Option(name: 'file', description: 'Snapshot path relative to project root or absolute.')]
  #[Option(name: 'batch', description: 'Upsert batch size (TEI max 16; inference up to 50).')]
  #[Usage(name: 'drush eonext-easysearch:vector-import', description: 'Re-index from data/fbi-materials.json.')]
  #[Usage(name: 'drush eonext-easysearch:vector-import --file=data/my-export.json --batch=16', description: 'Import a custom snapshot.')]
  public function import(array $options = [
    'file' => NULL,
    'batch' => 16,
  ]): void {
    $file = MaterialVectorSnapshot::resolvePath($options['file'] ?? NULL);
    $max_batch = $this->settings->usesQdrantInference() ? 50 : 16;
    $batch = max(1, (int) $options['batch']);
    if ($batch > $max_batch) {
      $this->io()->warning(sprintf('Batch capped at %d for current embedding backend.', $max_batch));
      $batch = $max_batch;
    }

    $this->printBackendHint();
    $this->io()->title('Importing vector snapshot into Qdrant');
    $this->io()->writeln(sprintf('Snapshot file: %s', $file));

    $stats = $this->indexer->indexFromFile($file, $batch);

    $this->io()->success(sprintf(
      'Indexed %d work(s) from %s into "%s" (collection now holds %d).',
      $stats['indexed'],
      $stats['file'],
      MaterialVectorIndexer::COLLECTION,
      $this->qdrant->count(MaterialVectorIndexer::COLLECTION),
    ));
  }

  /**
   * Prints a one-line hint about the active backend.
   */
  private function printBackendHint(): void {
    $embedding = $this->settings->usesQdrantInference()
      ? 'Qdrant Cloud Inference (' . $this->settings->inferenceModel() . ')'
      : 'local TEI (' . $this->settings->teiUrl() . ')';
    $qdrant = $this->settings->usesQdrantCloud()
      ? 'Qdrant Cloud'
      : 'local Qdrant (' . $this->settings->qdrantUrl() . ')';

    $this->io()->writeln(sprintf('Backend: %s → %s', $embedding, $qdrant));
  }

}
