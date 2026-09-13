<?php

/**
 * @file
 * Read-only content/file inventory. Run with ddev drush php:script.
 *
 * Outputs counts and missing file IDs, not account details or content bodies.
 * A successful inventory does not replace a restore test.
 */

$manager = \Drupal::entityTypeManager();
$inventory = ['entity_counts' => [], 'node_bundles' => []];
foreach (['node', 'media', 'file', 'block_content', 'menu_link_content', 'taxonomy_term'] as $type) {
  $inventory['entity_counts'][$type] = (int) $manager->getStorage($type)
    ->getQuery()->accessCheck(FALSE)->count()->execute();
}
foreach ($manager->getStorage('node_type')->loadMultiple() as $type) {
  $inventory['node_bundles'][$type->id()] = (int) $manager->getStorage('node')
    ->getQuery()->accessCheck(FALSE)->condition('type', $type->id())->count()->execute();
}
$inventory['front_page'] = \Drupal::config('system.site')->get('page.front');
$inventory['config_sync'] = \Drupal\Core\Site\Settings::get('config_sync_directory');
$inventory['private_files_path'] = \Drupal\Core\Site\Settings::get('file_private_path', '');
$inventory['file_schemes'] = [];
$inventory['missing_local_file_ids'] = [];
foreach ($manager->getStorage('file')->loadMultiple() as $file) {
  $scheme = \Drupal\Core\StreamWrapper\StreamWrapperManager::getScheme($file->getFileUri());
  $inventory['file_schemes'][$scheme] = ($inventory['file_schemes'][$scheme] ?? 0) + 1;
  if (in_array($scheme, ['public', 'private'], TRUE) && !file_exists($file->getFileUri())) {
    $inventory['missing_local_file_ids'][] = $file->id();
  }
}
$home = $manager->getStorage('node')->load(1);
$inventory['homepage_exists'] = (bool) $home;
$inventory['homepage_layout_sections'] = $home && $home->hasField('layout_builder__layout')
  ? $home->get('layout_builder__layout')->count() : 0;
echo json_encode($inventory, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . PHP_EOL;
