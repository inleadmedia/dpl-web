<?php

/**
 * @file
 * Delete leftover newsletter_material paragraphs so cim can drop the type.
 */

$storage = \Drupal::entityTypeManager()->getStorage('paragraph');
$ids = $storage->getQuery()
  ->accessCheck(FALSE)
  ->condition('type', 'newsletter_material')
  ->execute();

print 'newsletter_material count=' . count($ids) . PHP_EOL;
if ($ids) {
  $storage->delete($storage->loadMultiple($ids));
  print "deleted\n";
}
