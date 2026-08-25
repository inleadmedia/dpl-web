<?php

/**
 * @file
 * Deploy hooks for eonext_data_fixes.
 */

declare(strict_types=1);

use Drupal\node\NodeInterface;
use Drupal\paragraphs\Entity\Paragraph;

/**
 * Walk up the paragraph parent chain and return the host node.
 */
function _eonext_data_fixes_find_parent_node(Paragraph $paragraph): ?NodeInterface {
  $entity = $paragraph;
  for ($i = 0; $i < 10; $i++) {
    $parent = $entity->getParentEntity();
    if ($parent === NULL) {
      return NULL;
    }
    if ($parent instanceof NodeInterface) {
      return $parent;
    }
    if ($parent instanceof Paragraph) {
      $entity = $parent;
      continue;
    }
    return NULL;
  }
  return NULL;
}

/**
 * Return the absolute URL for a node, or a fallback string.
 */
function _eonext_data_fixes_node_url(NodeInterface $node): string {
  try {
    return $node->toUrl('canonical', ['absolute' => TRUE])->toString();
  }
  catch (\Exception) {
    return '(URL unavailable)';
  }
}

/**
 * Fix material_grid_automatic paragraphs where field_material_amount is NULL.
 *
 * When the field is NULL, PHP casts it to 0, which causes the React app to
 * send limit=0 to the FBI GraphQL API, returning "Internal server error".
 * Sets the missing value to the field's configured default of 8.
 *
 * Logs a single watchdog notice listing every changed paragraph with its
 * node ID and URL for reporting to content owners.
 */
function eonext_data_fixes_deploy_fix_missing_material_amount(): string {
  $storage    = \Drupal::entityTypeManager()->getStorage('paragraph');
  $batch_size = 50;

  $pids = array_values(
    $storage->getQuery()
      ->condition('type', 'material_grid_automatic')
      ->notExists('field_material_amount')
      ->accessCheck(FALSE)
      ->execute()
  );

  $changes = [];

  foreach (array_chunk($pids, $batch_size) as $chunk) {
    /** @var \Drupal\paragraphs\Entity\Paragraph[] $paragraphs */
    $paragraphs = $storage->loadMultiple($chunk);

    foreach ($paragraphs as $paragraph) {
      $node = _eonext_data_fixes_find_parent_node($paragraph);
      $nid  = $node ? (int) $node->id() : NULL;
      $url  = $node ? _eonext_data_fixes_node_url($node) : '(no parent node)';

      $changes[] = implode(' | ', array_filter([
        "pid={$paragraph->id()}",
        $nid ? "nid={$nid}" : NULL,
        $url,
        'field_material_amount: NULL → 8',
      ]));

      $paragraph->set('field_material_amount', 8);
      $paragraph->save();
    }

    // Free memory between chunks.
    $storage->resetCache($chunk);
  }

  $count = count($changes);

  if ($count > 0) {
    \Drupal::logger('eonext_data_fixes')->notice(
      "fix_missing_material_amount: updated @count paragraphs.\n@log",
      ['@count' => $count, '@log' => implode("\n", $changes)]
    );
  }

  return "Fixed field_material_amount on {$count} paragraphs.";
}

/**
 * Move firstaccessiondate conditions from the CQL string to filter fields.
 *
 * The FBI GraphQL API does not support firstaccessiondate as a CQL index.
 * It must be passed as the firstAccessionDate filter parameter instead.
 * Extracts the condition from field_cql_search_value and moves it to
 * first_accession_date_operator / first_accession_date_value. Only touches
 * paragraphs where the filter fields are not yet set.
 *
 * Logs a single watchdog notice listing every changed paragraph with its
 * node ID, URL, and the before/after CQL for reporting to content owners.
 */
function eonext_data_fixes_deploy_fix_firstaccessiondate_in_cql(): string {
  $storage    = \Drupal::entityTypeManager()->getStorage('paragraph');
  $batch_size = 50;

  // Target any paragraph where firstaccessiondate is still in the CQL string.
  // If it's still there the paragraph is broken regardless of whether the
  // dedicated operator/value fields are set, empty, or set to the wrong value.
  $pids = array_values(
    $storage->getQuery()
      ->condition('field_cql_search.value', '%firstaccessiondate%', 'LIKE')
      ->accessCheck(FALSE)
      ->execute()
  );

  $changes = [];
  $skipped = [];

  $value_pat       = '(?:NOW\s*[-+]\s*\d+\s*(?:DAYS?|MONTHS?)|\d{4}-\d{2}-\d{2})';
  $op_pat          = '>=?|<=?|=';
  $extract_re      = '/\bfirstaccessiondate\s*(' . $op_pat . ')\s*\'?(' . $value_pat . ')\'?/i';
  $cond_re         = 'firstaccessiondate\s*(?:' . $op_pat . ')\s*\'?(?:' . $value_pat . ')\'?';
  $remove_patterns = [
    '/^\(?\s*' . $cond_re . '\s*\)?\s*(?:AND\s+|and\s+)/i',
    '/\s+(?:AND\s+|and\s+)' . $cond_re . '(?:\s+(?:AND\s+|and\s+))/i',
    '/\s*(?:AND\s+|and\s+)' . $cond_re . '\s*$/i',
    '/\s*' . $cond_re . '\s*/i',
  ];

  foreach (array_chunk($pids, $batch_size) as $chunk) {
    /** @var \Drupal\paragraphs\Entity\Paragraph[] $paragraphs */
    $paragraphs = $storage->loadMultiple($chunk);

    foreach ($paragraphs as $paragraph) {
      if (!$paragraph->hasField('field_cql_search')) {
        $skipped[] = "pid={$paragraph->id()} (no field_cql_search)";
        continue;
      }

      $cql_values = $paragraph->get('field_cql_search')->getValue();
      $cql = $cql_values[0]['value'] ?? '';

      if (empty($cql) || !preg_match($extract_re, $cql, $matches)) {
        $skipped[] = "pid={$paragraph->id()} (no extractable firstaccessiondate)";
        continue;
      }

      $raw_operator  = $matches[1];
      $raw_value     = trim($matches[2]);
      $operator_char = match(TRUE) {
        in_array($raw_operator, ['>=', '>']) => '>',
        in_array($raw_operator, ['<=', '<']) => '<',
        default => '=',
      };

      $new_cql = $cql;
      foreach ($remove_patterns as $pattern) {
        $result = preg_replace($pattern, ' ', $new_cql, 1);
        if ($result !== $new_cql) {
          $new_cql = $result;
          break;
        }
      }
      $new_cql = trim($new_cql);

      if (empty($new_cql)) {
        $skipped[] = "pid={$paragraph->id()} (removal produced empty CQL — fix manually)";
        continue;
      }

      $node  = _eonext_data_fixes_find_parent_node($paragraph);
      $nid   = $node ? (int) $node->id() : NULL;
      $url   = $node ? _eonext_data_fixes_node_url($node) : '(no parent node)';
      $short = mb_strlen($cql) > 100 ? mb_substr($cql, 0, 100) . '…' : $cql;

      $changes[] = implode(' | ', array_filter([
        "pid={$paragraph->id()}",
        $nid ? "nid={$nid}" : NULL,
        $url,
        "cql \"{$short}\" → operator={$operator_char} value={$raw_value}",
      ]));

      $cql_values[0]['value']                         = $new_cql;
      $cql_values[0]['first_accession_date_operator'] = $operator_char;
      $cql_values[0]['first_accession_date_value']    = $raw_value;

      $paragraph->set('field_cql_search', $cql_values);
      $paragraph->save();
    }

    // Free memory between chunks.
    $storage->resetCache($chunk);
  }

  $fixed         = count($changes);
  $skipped_count = count($skipped);

  if ($fixed > 0 || $skipped_count > 0) {
    \Drupal::logger('eonext_data_fixes')->notice(
      "fix_firstaccessiondate_in_cql: fixed @fixed paragraphs, skipped @skipped.\n\nCHANGED:\n@changed\n\nSKIPPED:\n@skipped_log",
      [
        '@fixed'       => $fixed,
        '@skipped'     => $skipped_count,
        '@changed'     => $fixed ? implode("\n", $changes) : '(none)',
        '@skipped_log' => $skipped_count ? implode("\n", $skipped) : '(none)',
      ]
    );
  }

  return "Moved firstaccessiondate to filter fields for {$fixed} paragraphs, skipped {$skipped_count}.";
}
