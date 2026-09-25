<?php

/**
 * @file
 * Creates sample branch nodes for local /foreninger testing.
 *
 * Usage: ddev drush php:script scripts/create-kultur-branches.php
 */

declare(strict_types=1);

use Drupal\node\Entity\Node;

$branches = [
  ['title' => 'Korsør Teaterforening', 'street' => 'Nygade 12', 'lat' => '55.3298', 'lon' => '11.1412', 'media' => 1],
  ['title' => 'Korsør Musikforening', 'street' => 'Algade 5', 'lat' => '55.3310', 'lon' => '11.1380', 'media' => 2],
  ['title' => 'Korsør Jazzklub', 'street' => 'Torvet 3', 'lat' => '55.3290', 'lon' => '11.1355', 'media' => 3],
  ['title' => 'Skælskør Kulturforening', 'street' => 'Algade 18', 'lat' => '55.2506', 'lon' => '11.2935', 'media' => 4],
  ['title' => 'Slagelse Kunstforum', 'street' => 'Korsgade 7', 'lat' => '55.4028', 'lon' => '11.3546', 'media' => 5],
  ['title' => 'Korsør Filmklub', 'street' => 'Skolegade 8', 'lat' => '55.3390', 'lon' => '11.1320', 'media' => 6],
  ['title' => 'Havnens Kulturforening', 'street' => 'Havnevej 2', 'lat' => '55.3335', 'lon' => '11.1270', 'media' => 7],
  ['title' => 'Vestsjællands Korforening', 'street' => 'Kirkegade 4', 'lat' => '55.3285', 'lon' => '11.1440', 'media' => 9],
  ['title' => 'Korsør Ungekultur', 'street' => 'Skolegade 14', 'lat' => '55.3402', 'lon' => '11.1345', 'media' => 10],
];

$uuid = \Drupal::service('uuid');
$storage = \Drupal::entityTypeManager()->getStorage('node');
$created = [];

foreach ($branches as $item) {
  $existing = $storage->loadByProperties([
    'type' => 'branch',
    'title' => $item['title'],
  ]);
  if ($existing !== []) {
    $node = reset($existing);
    $created[] = $node->id() . ' (existing): ' . $item['title'];
    continue;
  }

  $postal = str_contains($item['title'], 'Skælskør') ? '4230' : (str_contains($item['title'], 'Slagelse') ? '4200' : '4220');
  $locality = str_contains($item['title'], 'Skælskør') ? 'Skælskør' : (str_contains($item['title'], 'Slagelse') ? 'Slagelse' : 'Korsør');
  $user_input = $item['street'] . ', ' . $postal . ' ' . $locality;

  $node = Node::create([
    'type' => 'branch',
    'langcode' => 'da',
    'uid' => 1,
    'status' => 1,
    'title' => $item['title'],
    'field_promoted_on_lists' => 1,
    'field_main_media' => ['target_id' => (int) $item['media']],
    'field_address' => [
      'country_code' => 'DK',
      'locality' => $locality,
      'postal_code' => $postal,
      'address_line1' => $item['street'],
    ],
    'field_address_gsearch' => [
      'id' => $uuid->generate(),
      'user_input' => $user_input,
      'value' => $user_input,
      'address' => $item['street'],
      'postal_code' => $postal,
      'postal_name' => $locality,
      'country_code' => 'DK',
      'latitude' => $item['lat'],
      'longitude' => $item['lon'],
    ],
  ]);
  $node->save();
  $created[] = $node->id() . ': ' . $item['title'];
}

print implode("\n", $created) . "\n";
