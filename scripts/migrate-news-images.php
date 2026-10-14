
<?php

/**
 * Migrate News story images into Drupal Media.
 *
 * Run using:
 * ddev drush php:script scripts/migrate-news-images.php
 */

use Drupal\media\Entity\Media;

// Safety switch: preview changes without saving anything.
$apply = FALSE;

// Get Drupal's storage handlers for content and media.
$entity_manager = \Drupal::entityTypeManager();
$node_storage = $entity_manager->getStorage('node');
$media_storage = $entity_manager->getStorage('media');

// Map each old image field to its caption field.
$old_fields = [
  'field_hero_image' => 'field_hero_image_caption',
  'field_featured_image' => 'field_right_side_image_caption',
];

// Find all News stories.
$ids = $node_storage->getQuery()
  ->accessCheck(FALSE)
  ->condition('type', 'news_story')
  ->execute();

foreach ($node_storage->loadMultiple($ids) as $node) {

  // Confirm the new fields exist.
  if (!$node->hasField('field_featured_media') ||
      !$node->hasField('field_image_caption')) {
    throw new \RuntimeException('Missing migration fields.');
  }

  // Never overwrite an existing Featured image selection.
  if (!$node->get('field_featured_media')->isEmpty()) {
    echo "SKIP {$node->id()}: already migrated\n";
    continue;
  }

  // Determine which old image field contains an image.
  $matches = [];

  foreach ($old_fields as $image_field => $caption_field) {
    if (!$node->get($image_field)->isEmpty()) {
      $matches[] = [$image_field, $caption_field];
    }
  }

  if (count($matches) === 0) {
    echo "SKIP {$node->id()}: no image\n";
    continue;
  }

  // Avoid accidentally dropping an image.
  if (count($matches) > 1) {
    throw new \RuntimeException(
      "Story {$node->id()} has multiple images."
    );
  }

  [$image_field, $caption_field] = $matches[0];

  $image = $node->get($image_field)->first();
  $file = $image->entity;

  if (!$file) {
    throw new \RuntimeException(
      "Story {$node->id()} references a missing file entity."
    );
  }

  $path = \Drupal::service('file_system')
    ->realpath($file->getFileUri());

  if (!$path || !is_file($path)) {
    throw new \RuntimeException(
      "Story {$node->id()} has a missing image file."
    );
  }

  $alt = (string) ($image->alt ?? '');
  $caption = (string) ($node->get($caption_field)->value ?? '');

  // Prevent overwriting a previously entered caption.
  if (!$node->get('field_image_caption')->isEmpty() &&
      $node->get('field_image_caption')->value !== $caption) {
    throw new \RuntimeException(
      "Story {$node->id()} has conflicting captions."
    );
  }

  echo "Story {$node->id()}: {$file->getFilename()}\n";
  echo "  Source: $image_field\n";
  echo "  File ID: {$file->id()}\n";
  echo "  Alt: $alt\n";
  echo "  Caption: $caption\n";

  // Stop here during preview mode.
  if (!$apply) {
    continue;
  }

  // Look for an existing Image Media entity using this file
  // and the same alternative text.
  $media = NULL;

  $media_ids = $media_storage->getQuery()
    ->accessCheck(FALSE)
    ->condition('bundle', 'image')
    ->condition('field_media_image.target_id', $file->id())
    ->execute();

  foreach ($media_storage->loadMultiple($media_ids) as $candidate) {
    $candidate_alt = (string) (
      $candidate->get('field_media_image')->first()->alt ?? ''
    );

    if ($candidate_alt === $alt) {
      $media = $candidate;
      break;
    }
  }

  // Create Media only if no matching item exists.
  if (!$media) {
    $media = Media::create([
      'bundle' => 'image',
      'name' => $file->getFilename(),
      'status' => 1,
      'uid' => $node->getOwnerId(),
      'field_media_image' => [
        'target_id' => $file->id(),
        'alt' => $alt,
      ],
    ]);

    $media->save();
  }

  // Connect the News story to its Media entity.
  $node->set('field_featured_media', [
    'target_id' => $media->id(),
  ]);

  // Preserve the existing article-specific caption.
  if ($node->get('field_image_caption')->isEmpty() &&
      $caption !== '') {
    $node->set('field_image_caption', $caption);
  }

  $node->save();

  echo "  MIGRATED to Media {$media->id()}\n";
}

echo $apply
  ? "\nMigration finished.\n"
  : "\nPreview finished. No data changed.\n";
