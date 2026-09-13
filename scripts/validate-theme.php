<?php

/**
 * @file
 * Read-only syntax/discovery audit. Run: ddev drush php:script scripts/validate-theme.php
 *
 * Requires an installed site because Twig extensions and UI Patterns are supplied
 * by Drupal. This checks parsing/discovery, not every possible prop combination.
 */

use Symfony\Component\Yaml\Yaml;
use Twig\Source;

$root = dirname(__DIR__);
$theme = $root . '/web/themes/custom/wisconsin_courts';
$twig = \Drupal::service('twig');
$counts = ['yaml' => 0, 'twig' => 0, 'components' => 0];
foreach ([$root . '/config/sync', $theme] as $directory) {
  $iterator = new RecursiveIteratorIterator(new RecursiveCallbackFilterIterator(
    new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
    static fn($file) => !in_array($file->getFilename(), ['node_modules', 'assets'], TRUE)
  ));
  foreach ($iterator as $file) {
    if (!$file->isFile()) {
      continue;
    }
    if ($file->getExtension() === 'yml') {
      Yaml::parseFile($file->getPathname());
      $counts['yaml']++;
    }
    if ($file->getExtension() === 'twig') {
      $twig->parse($twig->tokenize(new Source(
        file_get_contents($file->getPathname()),
        $file->getPathname()
      )));
      $counts['twig']++;
    }
  }
}
$manager = \Drupal::service('plugin.manager.sdc');
foreach (glob($theme . '/components/*/*.component.yml') as $file) {
  $id = 'wisconsin_courts:' . basename(dirname($file));
  if (!$manager->hasDefinition($id)) {
    throw new RuntimeException('Component not discovered: ' . $id);
  }
  $manager->createInstance($id);
  $counts['components']++;
}
echo json_encode($counts, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . PHP_EOL;
