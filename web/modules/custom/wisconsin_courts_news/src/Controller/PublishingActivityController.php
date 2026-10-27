<?php

namespace Drupal\wisconsin_courts_news\Controller;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Url;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Builds the publishing activity dashboard for News and Opinions.
 */
class PublishingActivityController extends ControllerBase {

  /**
   * Receives Drupal's clock and date-formatting services.
   */
  public function __construct(
    protected TimeInterface $time,
    protected DateFormatterInterface $dateFormatter,
  ) {}

  /**
   * Creates this controller using Drupal's service container.
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('datetime.time'),
      $container->get('date.formatter'),
    );
  }

  /**
   * Builds the dashboard using the selected reporting period.
   */
  public function overview(Request $request): array {
    $account = $this->currentUser();
    $storage = $this->entityTypeManager()->getStorage('node');
    $date_config = $this->config('system.date');

    // Use the site's timezone for calendar-day boundaries.
    // Publication dates are stored in UTC.
    $timezone = new \DateTimeZone(
      $date_config->get('timezone.default') ?: 'UTC'
    );

    $now = (new \DateTimeImmutable(
      '@' . $this->time->getRequestTime()
    ))->setTimezone($timezone);

    $today = $now->setTime(0, 0);

    // Reporting periods available in the dropdown.
    $range_options = [
      '7' => $this->t('Last 7 days'),
      '30' => $this->t('Last 30 days'),
      '90' => $this->t('Last 90 days'),
      '365' => $this->t('Last 365 days'),
      'year' => $this->t('This year'),
    ];

    // Read the selection from the URL, such as ?range=90.
    // Default to 30 days if the selection is missing or invalid.
    $range = $request->query->getString('range', '30');

    if (!isset($range_options[$range])) {
      $range = '30';
    }

    // Every available period ends today.
    if ($range === 'year') {
      $period_start = $today->setDate(
        (int) $today->format('Y'),
        1,
        1
      );
    }
    else {
      $days_back = (int) $range - 1;
      $period_start = $today->modify("-{$days_back} days");
    }

    // Count calendar days rather than assuming every day is 24 hours.
    $period_days = (int) $period_start->diff($today)->days + 1;

    // The weekly summary always covers the current week.
    $week_start = $today->modify('monday this week');

    // Older unpublished items have not been updated for 14 days.
    $older_cutoff = $now->modify('-14 days')->getTimestamp();

    $summary = [
      'published' => 0,
      'published_news' => 0,
      'published_opinions' => 0,
      'unpublished' => 0,
      'unpublished_news' => 0,
      'unpublished_opinions' => 0,
      'published_this_week' => 0,
      'older_unpublished' => 0,
      'latest_publication' => NULL,
    ];

    $older_drafts = [];
    $recent_publications = [];
    $latest_timestamp = NULL;

    // Divide the selected period into up to six date groups.
    // Short periods, such as January 1–3, use fewer groups.
    $chart = [];
    $bucket_count = min(6, $period_days);

    for ($i = 0; $i < $bucket_count; $i++) {
      $start_offset = (int) floor(
        $i * $period_days / $bucket_count
      );

      $end_offset = (int) floor(
        ($i + 1) * $period_days / $bucket_count
      );

      $start = $period_start->modify("+{$start_offset} days");
      $end = $period_start->modify("+{$end_offset} days");

      $chart[] = [
        'start' => $start->getTimestamp(),
        'end' => $end->getTimestamp(),
        'label' => $this->t('@start–@end', [
          '@start' => $this->formatDate(
            $start->getTimestamp(),
            $timezone,
            'M j'
          ),
          '@end' => $this->formatDate(
            $end->modify('-1 day')->getTimestamp(),
            $timezone,
            'M j'
          ),
        ]),
        'count' => 0,
        'percent' => 0,
      ];
    }

    // Preserve the selected range when returning from an editing form.
    $destination = Url::fromRoute(
      'wisconsin_courts_news.publishing_activity',
      [],
      ['query' => ['range' => $range]]
    )->toString();

    // Refresh cached data after five minutes or when content changes.
    // Different users and reporting periods get separate results.
    $cache = new CacheableMetadata();
    $cache->setCacheMaxAge(300);
    $cache->setCacheContexts([
      'url.query_args:range',
      'user',
      'user.permissions',
      'user.node_grants:update',
      'languages:language_interface',
    ]);
    $cache->setCacheTags(['node_list', 'user_list']);
    $cache->addCacheableDependency($date_config);

    // Read content in batches to limit how much is loaded at once.
    $offset = 0;
    $batch_size = 100;

    do {
      $ids = $storage->getQuery()
        ->accessCheck(TRUE)
        ->addMetaData('op', 'update')
        ->condition('type', ['news_story', 'opinion'], 'IN')
        ->sort('nid', 'ASC')
        ->range($offset, $batch_size)
        ->execute();

      /** @var \Drupal\node\NodeInterface $node */
      foreach ($storage->loadMultiple($ids) as $node) {
        // Include only content the signed-in user can edit.
        $access = $node->access('update', $account, TRUE);

        $cache->addCacheableDependency($node);
        $cache->addCacheableDependency($access);

        if (!$access->isAllowed()) {
          continue;
        }

        // Author names may change independently of the content.
        if ($owner = $node->getOwner()) {
          $cache->addCacheableDependency($owner);
        }

        $is_news = $node->bundle() === 'news_story';

        // Unpublished totals describe the current state.
        // They are not historical snapshots of the selected period.
        if (!$node->isPublished()) {
          $summary['unpublished']++;

          if ($is_news) {
            $summary['unpublished_news']++;
          }
          else {
            $summary['unpublished_opinions']++;
          }

          // Keep the three oldest unpublished items.
          if ($node->getChangedTime() <= $older_cutoff) {
            $summary['older_unpublished']++;

            $this->addToList(
              $older_drafts,
              $this->buildRow(
                $node,
                $node->getChangedTime(),
                $timezone,
                $destination
              ),
              FALSE,
              3
            );
          }

          continue;
        }

        // Publication statistics require a publication date.
        if (
          !$node->hasField('field_publication_date') ||
          $node->get('field_publication_date')->isEmpty()
        ) {
          continue;
        }

        $published = new \DateTimeImmutable(
          $node->get('field_publication_date')->value,
          new \DateTimeZone('UTC')
        );

        $timestamp = $published->getTimestamp();

        // Exclude dates that are still in the future.
        if ($timestamp > $now->getTimestamp()) {
          continue;
        }

        // This summary always shows the current week, regardless
        // of which reporting period the user selects.
        if ($timestamp >= $week_start->getTimestamp()) {
          $summary['published_this_week']++;
        }

        // Remaining publication statistics follow the selected period.
        if ($timestamp < $period_start->getTimestamp()) {
          continue;
        }

        $summary['published']++;

        if ($is_news) {
          $summary['published_news']++;
        }
        else {
          $summary['published_opinions']++;
        }

        // Find the latest publication within the selected period.
        if (
          $latest_timestamp === NULL ||
          $timestamp > $latest_timestamp
        ) {
          $latest_timestamp = $timestamp;
        }

        // Add the publication to the appropriate chart group.
        foreach ($chart as &$bucket) {
          if (
            $timestamp >= $bucket['start'] &&
            $timestamp < $bucket['end']
          ) {
            $bucket['count']++;
            break;
          }
        }
        unset($bucket);

        // Keep the five most recent publications in this period.
        $this->addToList(
          $recent_publications,
          $this->buildRow(
            $node,
            $timestamp,
            $timezone,
            $destination
          ),
          TRUE,
          5
        );
      }

      $offset += $batch_size;
    } while (count($ids) === $batch_size);

    $summary['latest_publication'] = $latest_timestamp !== NULL
      ? $this->formatDate($latest_timestamp, $timezone)
      : $this->t('None in this period');

    // Scale the tallest chart bar to 100%.
    // Using at least one prevents division by zero.
    $maximum = max(1, max(array_column($chart, 'count')));

    foreach ($chart as &$bucket) {
      $bucket['percent'] = round(
        ($bucket['count'] / $maximum) * 100,
        2
      );
    }
    unset($bucket);

    // Supply the data and filter settings to the Twig template.
    $build = [
      '#theme' => 'wisconsin_courts_publishing_activity',
      '#range' => $range,
      '#range_options' => $range_options,
      '#range_label' => $range_options[$range],
      '#filter_action' => Url::fromRoute(
        'wisconsin_courts_news.publishing_activity'
      )->toString(),
      '#summary' => $summary,
      '#chart' => $chart,
      '#older_drafts' => $older_drafts,
      '#recent_publications' => $recent_publications,
      '#period_label' => $this->t('@start–@end', [
        '@start' => $this->formatDate(
          $period_start->getTimestamp(),
          $timezone
        ),
        '@end' => $this->formatDate(
          $today->getTimestamp(),
          $timezone
        ),
      ]),
      '#timezone_label' => $timezone->getName(),
    ];

    $cache->applyTo($build);

    return $build;
  }

  /**
   * Prepares a content item for a dashboard list.
   */
  protected function buildRow(
    NodeInterface $node,
    int $timestamp,
    \DateTimeZone $timezone,
    string $destination
  ): array {
    return [
      'id' => (int) $node->id(),
      'timestamp' => $timestamp,
      'title' => $node->label(),
      'type' => $node->bundle() === 'news_story'
        ? $this->t('News story')
        : $this->t('Opinion'),
      'author' => $node->getOwner()?->getDisplayName()
        ?? $this->t('Unknown'),
      'date' => $this->formatDate($timestamp, $timezone),
      'edit_url' => $node->toUrl('edit-form', [
        'query' => ['destination' => $destination],
      ])->toString(),
    ];
  }

  /**
   * Keeps a short list sorted by date.
   *
   * Publications use newest first.
   * Older unpublished items use oldest first.
   */
  protected function addToList(
    array &$rows,
    array $row,
    bool $newest_first,
    int $limit
  ): void {
    $rows[] = $row;

    usort($rows, static function (
      array $a,
      array $b
    ) use ($newest_first): int {
      $comparison = $newest_first
        ? $b['timestamp'] <=> $a['timestamp']
        : $a['timestamp'] <=> $b['timestamp'];

      // Keep items with identical dates in a consistent order.
      return $comparison ?: ($b['id'] <=> $a['id']);
    });

    $rows = array_slice($rows, 0, $limit);
  }

  /**
   * Formats a date using the site's timezone.
   */
  protected function formatDate(
    int $timestamp,
    \DateTimeZone $timezone,
    string $format = 'M j, Y'
  ): string {
    return $this->dateFormatter->format(
      $timestamp,
      'custom',
      $format,
      $timezone->getName()
    );
  }

}