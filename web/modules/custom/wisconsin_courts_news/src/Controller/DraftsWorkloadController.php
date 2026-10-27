<?php

namespace Drupal\wisconsin_courts_news\Controller;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Pager\PagerManagerInterface;
use Drupal\Core\Url;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Builds the Drafts and workload dashboard.
 */
class DraftsWorkloadController extends ControllerBase {

  /**
   * Receives Drupal's clock, date formatter, and pager manager.
   */
  public function __construct(
    protected TimeInterface $time,
    protected DateFormatterInterface $dateFormatter,
    protected PagerManagerInterface $pagerManager,
  ) {}

  /**
   * Creates this controller using Drupal's service container.
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('datetime.time'),
      $container->get('date.formatter'),
      $container->get('pager.manager'),
    );
  }

  /**
   * Builds the dashboard using the selected filters.
   */
  public function overview(Request $request): array {
    $account = $this->currentUser();
    $storage = $this->entityTypeManager()->getStorage('node');
    $date_config = $this->config('system.date');

    $timezone = new \DateTimeZone(
      $date_config->get('timezone.default') ?: 'UTC'
    );

    $now = (new \DateTimeImmutable(
      '@' . $this->time->getRequestTime()
    ))->setTimezone($timezone);

    // Compare last-updated times against this threshold.
    $older_cutoff = $now->modify('-14 days')->getTimestamp();

    $type_options = [
      'all' => $this->t('All types'),
      'news_story' => $this->t('News stories'),
      'opinion' => $this->t('Opinions'),
    ];

    // Read and validate the content-type selection.
    $type = $request->query->getString('type', 'all');

    if (!isset($type_options[$type])) {
      $type = 'all';
    }

    // "all" is separate from numeric user IDs, including user ID 0.
    $author = $request->query->getString('author', 'all');

    if ($author !== 'all') {
      $author = ctype_digit($author)
        ? (string) (int) $author
        : 'all';
    }

    // This checkbox narrows the unpublished-content table.
    $older_only = $request->query->getBoolean('older', FALSE);

    // The Last updated column can switch between newest and oldest.
    $sort = $request->query->getString('sort', 'newest');

    if (!in_array($sort, ['newest', 'oldest'], TRUE)) {
      $sort = 'newest';
    }

    $summary = [
      'unpublished_news' => 0,
      'unpublished_opinions' => 0,
      'unpublished_total' => 0,
      'older' => 0,
    ];

    $all_authors = [];
    $drafts_by_author = [];
    $draft_rows = [];
    $recently_updated = [];

    // Cache separately for users, permissions, and filter selections.
    // Content changes invalidate the data before this time expires.
    $cache = new CacheableMetadata();
    $cache->setCacheMaxAge(300);
    $cache->setCacheContexts([
      'url.query_args',
      'url.path',
      'user',
      'user.permissions',
      'user.node_grants:update',
      'languages:language_interface',
    ]);
    $cache->setCacheTags(['node_list', 'user_list']);
    $cache->addCacheableDependency($date_config);

    // Read records in batches, ordered by their last-updated time.
    $offset = 0;
    $batch_size = 100;

    do {
      $ids = $storage->getQuery()
        ->accessCheck(TRUE)
        ->addMetaData('op', 'update')
        ->condition('type', ['news_story', 'opinion'], 'IN')
        ->sort('changed', 'DESC')
        ->sort('nid', 'DESC')
        ->range($offset, $batch_size)
        ->execute();

      /** @var \Drupal\node\NodeInterface $node */
      foreach ($storage->loadMultiple($ids) as $node) {
        // Check editing access to each individual item.
        $access = $node->access('update', $account, TRUE);

        $cache->addCacheableDependency($node);
        $cache->addCacheableDependency($access);

        if (!$access->isAllowed()) {
          continue;
        }

        $owner = $node->getOwner();
        $owner_id = (string) ($node->getOwnerId() ?? 0);
        $owner_name = $owner?->getDisplayName()
          ?? $this->t('Unknown');

        if ($owner) {
          $cache->addCacheableDependency($owner);
        }

        // Build the Author dropdown from editable content before
        // applying the selected Type and Author filters.
        $all_authors[$owner_id] = $owner_name;

        if ($type !== 'all' && $node->bundle() !== $type) {
          continue;
        }

        if ($author !== 'all' && $owner_id !== $author) {
          continue;
        }

        $is_older = $node->getChangedTime() <= $older_cutoff;

        $row = $this->buildRow(
          $node,
          $timezone,
          $owner_name,
          $is_older
        );

        // The query is newest first. Keep the first three matching
        // items, including both published and unpublished content.
        if (count($recently_updated) < 3) {
          $recently_updated[] = $row;
        }

        // The workload table and author totals cover unpublished
        // items only.
        if ($node->isPublished()) {
          continue;
        }

        $summary['unpublished_total']++;

        if ($node->bundle() === 'news_story') {
          $summary['unpublished_news']++;
        }
        else {
          $summary['unpublished_opinions']++;
        }

        if ($is_older) {
          $summary['older']++;
        }

        // Group unpublished items by their content author.
        if (!isset($drafts_by_author[$owner_id])) {
          $drafts_by_author[$owner_id] = [
            'name' => $owner_name,
            'count' => 0,
          ];
        }

        $drafts_by_author[$owner_id]['count']++;

        // Apply the older-items checkbox only to the main table.
        // The summary cards retain the Type/Author workload totals.
        if ($older_only && !$is_older) {
          continue;
        }

        $draft_rows[] = $row;
      }

      $offset += $batch_size;
    } while (count($ids) === $batch_size);

    // Sort the Author dropdown alphabetically.
    uasort(
      $all_authors,
      static fn($a, $b): int => strnatcasecmp(
        (string) $a,
        (string) $b
      )
    );

    // Preserve numeric user IDs as dropdown keys.
    $author_options = [
      'all' => $this->t('All authors'),
    ] + $all_authors;

    // Keep an unmatched author selection visible in an empty result.
    if (
      $author !== 'all' &&
      !array_key_exists($author, $author_options)
    ) {
      $author_options[$author] = $this->t('Selected author');
    }

    // Put authors with the largest unpublished workload first.
    uasort(
      $drafts_by_author,
      static function (array $a, array $b): int {
        return ($b['count'] <=> $a['count'])
          ?: strnatcasecmp(
            (string) $a['name'],
            (string) $b['name']
          );
      }
    );

    // Apply the selected Last updated sort to the main table.
    $direction = $sort === 'oldest' ? 1 : -1;

    usort(
      $draft_rows,
      static function (
        array $a,
        array $b
      ) use ($direction): int {
        return (
          ($a['changed'] <=> $b['changed']) * $direction
        ) ?: ($b['id'] <=> $a['id']);
      }
    );

    // Paginate after access checks and filters have been applied.
    $total = count($draft_rows);
    $per_page = 10;
    $pager = $this->pagerManager->createPager($total, $per_page);

    $rows = array_slice(
      $draft_rows,
      $pager->getCurrentPage() * $per_page,
      $per_page
    );

    // Preserve these filters in sorting and pagination links.
    $query = [
      'type' => $type,
      'author' => $author,
      'older' => $older_only ? 1 : 0,
      'sort' => $sort,
    ];

    $route = 'wisconsin_courts_news.drafts_workload';

    // Editing an item should return the user to the same filtered
    // table and page after saving.
    $destination = Url::fromRoute(
      $route,
      [],
      [
        'query' => array_merge($query, [
          'page' => $pager->getCurrentPage(),
        ]),
      ]
    )->toString();

    $this->addEditLinks($rows, $destination);
    $this->addEditLinks($recently_updated, $destination);

    $build = [
      '#theme' => 'wisconsin_courts_drafts_workload',

      '#filters' => [
        'type' => $type,
        'author' => $author,
        'older' => $older_only,
        'sort' => $sort,
        'type_options' => $type_options,
        'author_options' => $author_options,
      ],

      '#summary' => $summary,
      '#rows' => $rows,
      '#authors' => array_values($drafts_by_author),
      '#recent' => $recently_updated,
      '#total' => $total,

      '#sort_direction' => $sort === 'oldest'
        ? 'ascending'
        : 'descending',

      '#urls' => [
        'filter' => Url::fromRoute($route)->toString(),

        'reset' => Url::fromRoute($route)->toString(),

        // Remove the older-items restriction while keeping the
        // selected Type, Author, and sorting.
        'all' => Url::fromRoute(
          $route,
          [],
          [
            'query' => array_merge($query, [
              'older' => 0,
            ]),
          ]
        )->toString(),

        // The older-items summary card can open that filtered list.
        'older' => Url::fromRoute(
          $route,
          [],
          [
            'query' => array_merge($query, [
              'older' => 1,
            ]),
          ]
        )->toString(),

        // Clicking Last updated reverses the table's date order.
        'sort' => Url::fromRoute(
          $route,
          [],
          [
            'query' => array_merge($query, [
              'sort' => $sort === 'newest' ? 'oldest' : 'newest',
            ]),
          ]
        )->toString(),
      ],

      '#pager' => [
        '#type' => 'pager',
        '#element' => 0,
        '#parameters' => $query,
      ],

      '#timezone_label' => $timezone->getName(),
    ];

    $cache->applyTo($build);

    return $build;
  }

  /**
   * Prepares an item for the table or Recently updated list.
   */
  protected function buildRow(
    NodeInterface $node,
    \DateTimeZone $timezone,
    $author_name,
    bool $is_older
  ): array {
    return [
      'id' => (int) $node->id(),
      'title' => $node->label(),
      'bundle' => $node->bundle(),
      'type' => $node->bundle() === 'news_story'
        ? $this->t('News')
        : $this->t('Opinion'),
      'author' => $author_name,
      'changed' => $node->getChangedTime(),
      'updated' => $this->dateFormatter->format(
        $node->getChangedTime(),
        'custom',
        'M j, Y',
        $timezone->getName()
      ),
      'is_older' => $is_older,
      'published' => $node->isPublished(),
    ];
  }

  /**
   * Adds Edit URLs that preserve the current dashboard selections.
   */
  protected function addEditLinks(
    array &$rows,
    string $destination
  ): void {
    foreach ($rows as &$row) {
      $row['edit_url'] = Url::fromRoute(
        'entity.node.edit_form',
        ['node' => $row['id']],
        ['query' => ['destination' => $destination]]
      )->toString();
    }
    unset($row);
  }

}