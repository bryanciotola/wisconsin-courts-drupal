# Wisconsin Courts Drupal / USWDS

Developer handoff for the approved site. Use the **database + uploads restore**
below; a Git checkout alone does not contain the homepage content.

## Runtime

Use DDEV from the repository root, in WSL/Linux on Windows. Docker and DDEV
must already be installed. Tracked `.ddev/config.yaml` selects PHP 8.4,
MariaDB 11.8, Node 24, Nginx, and document root `web/`.

The locks resolve Drupal **11.4.5**, UI Suite USWDS **4.1.0**, USWDS **3.14.0**,
and UI Patterns Settings **3.0.0-alpha1**. Use `composer install` and `npm ci`;
do not update dependencies during restoration.

## Restore the approved site

Use a new checkout / empty local environment. A database import replaces the
destination database; back up any existing destination first.

1. Check out `codex/developer-handoff` or the delivered commit. An offline Git
   bundle is also provided at `.handoff/drupal-uswds.bundle`. Clone it with
   `git clone --branch codex/developer-handoff /path/to/drupal-uswds.bundle drupal-uswds`.
2. Obtain the matching transfer directory containing `site.sql.gz`,
   `public-files.tar.gz`, `inventory.json`, and `SHA256SUMS`. Put it under the
   ignored `.handoff/` folder. These files are intentionally outside Git.
3. From the transfer directory, run `sha256sum -c SHA256SUMS`.
4. From the repository root:

   ```sh
   ddev start
   ddev composer install
   ```

5. DDEV creates local database settings in `web/sites/default/settings.php` and
   `settings.ddev.php`. Ensure this assignment is in the ignored `settings.php`,
   after the DDEV settings include:

   ```php
   $settings['config_sync_directory'] = '../config/sync';
   ```

6. Restore the supplied snapshot, substituting its directory name:

   ```sh
   ddev import-db --file=.handoff/TRANSFER_DIRECTORY/site.sql.gz
   mkdir -p web/sites/default/files
   tar -xzf .handoff/TRANSFER_DIRECTORY/public-files.tar.gz -C web/sites/default/files
   ```

   The archive contains the **contents** of `sites/default/files`, without an
   extra `files/` directory. Image derivatives and caches are regenerated.
   Do not run `site:install` on this restored database.

7. Install and build the theme:

   ```sh
   ddev exec --dir=/var/www/html/web/themes/custom/wisconsin_courts npm ci
   ddev exec --dir=/var/www/html/web/themes/custom/wisconsin_courts npm run build
   ddev drush cr
   ddev drush status
   ddev drush config:status
   ddev drush php:script scripts/validate-theme.php
   ddev drush php:script scripts/handoff-inventory.php
   ```

8. Open `https://drupal-uswds.ddev.site`, `/news`, `/opinions`, and a news story.
   Use `ddev drush uli` for a local login; do not share the one-time login URL.
   The supplied database matches exported configuration. If a later snapshot
   differs, inspect `config:status` before deciding to run `ddev drush config:import`.

A clean Composer install/database restore on another machine remains a required
recipient check. This audit includes a clean npm install/build, not a full
second-machine restore. See [verification](docs/verification.md).

## What the developer needs

| Layer | Location / transfer requirement |
| --- | --- |
| Theme source and built assets | `web/themes/custom/wisconsin_courts/` |
| Exact dependency resolutions | Root Composer lock and theme npm lock |
| Drupal configuration | `config/sync/`: fields, content types, Views, roles, blocks, theme settings |
| Local infrastructure | `.ddev/config.yaml` |
| Content and actual homepage layout | Database; node 1 has 3 Layout Builder sections |
| Uploaded images, logos, PDFs | Public-files archive; Git ignores `web/sites/*/files/` |
| Local credentials / hash salt | Generated local settings; do not commit production secrets |
| Private files | None configured locally; check inventory for other environments |

The site has 12 nodes (1 basic page, 8 news stories, 3 opinions), 7 media entities,
21 managed public files, and 3 content menu links. All managed files existed in
the audit. Header/footer block configuration references media IDs **2** and **7**;
copying images without the matching database is insufficient. There is no
content-seeding recipe in the repository.

`web/sites/default/files/sync` is an obsolete local export. The authoritative
tracked configuration is `config/sync`, outside the web root.

## Daily maintenance

- Theme changes: use the [theme guide](web/themes/custom/wisconsin_courts/README.md).
- Site-building changes: `ddev drush config:export`, review YAML, and commit
  intended changes. Export does not include content or uploads.
- Homepage content/layout: edit node 1 in Layout Builder. Refresh the database
  and file transfer after content changes.
- Content types, fields, Views, and component prop machine names are contracts.
  Review stored mappings before renaming/removing them.
- Upgrades: use a separate branch, update locks deliberately, build, run required
  database updates, rebuild caches, and repeat visual checks.
- Never edit Composer-managed core/contrib files or `node_modules`.

Create a fresh local transfer:

```sh
bash scripts/export-handoff.sh
```

This writes a timestamped directory under `.handoff/` and never sends data.
Database archives can contain accounts and site data; transfer through the
team's approved private channel. Do not force-add `.handoff` to Git.

See [review and follow-up work](docs/theme-review.md) before production.
No push or deployment is part of this handoff.
[Drupal configuration guidance](https://www.drupal.org/docs/administering-a-drupal-site/configuration-management)
describes the configuration workflow; content/files still need separate transfer.
