# Wisconsin Courts Drupal / USWDS

This repository contains the Drupal configuration and custom Wisconsin Courts
theme used for the approved prototype.

## Handoff scope

The local reference site runs in DDEV with MariaDB 11.8. The work environment
will run Drupal on PostgreSQL 16. The goal is to get the checked-in code,
configuration, theme, and components running in the PostgreSQL environment. The
local database and uploaded files are not part of the GitHub handoff.

`config/sync/core.extension.yml` reflects the MariaDB installation: it enables
`mysql` and does not enable `pgsql`. Reconcile the database driver during the
PostgreSQL setup so the active PostgreSQL driver is retained when configuration
is installed or imported.

## Setup

Install the locked dependencies:

```sh
composer install
cd web/themes/custom/wisconsin_courts
npm ci
npm run build
```

The locks currently resolve Drupal 11.4.5, UI Suite USWDS 4.1.0, USWDS 3.14.0,
and UI Patterns Settings 3.0.0-alpha1. The theme build uses Node 24 (`.nvmrc`).

Configure Drupal for PostgreSQL and point Drupal's configuration sync directory
to the repository's `config/sync` directory before installing or importing
configuration.

In DDEV, the default `settings.ddev.php` points to
`sites/default/files/sync`. For the reference DDEV setup, this can be overridden
in `settings.php` with:

```php
$settings['config_sync_directory'] = '../config/sync';
```

Other environments can point the setting to the same checked-in `config/sync`
directory using their normal configuration.

After Drupal is installed and the checked-in configuration is imported, run:

```sh
drush cr
drush status
drush config:status
drush php:script scripts/validate-theme.php
```

The default theme should be `wisconsin_courts`; the admin theme is Claro. See
the [theme guide](web/themes/custom/wisconsin_courts/README.md) for build and
component details.

## Content boundary

Git includes content types and fields, Views, menu definitions, block placements,
theme settings, and all custom component source. It does not include Drupal
content entities or `web/sites/default/files`.

The site owner will recreate the content after the PostgreSQL site is running.
That includes the Home node and its per-node Layout Builder override, three Quick
Links, eight News Stories, three Opinions, media entities, and uploaded
images/PDFs. The News and Opinions Views are included but will be empty until
content is added. `system.site` currently points to `/node/1`; verify the front
page setting after creating the new Home page.

The header and footer block configuration contains media selections from the
local database and several prototype DDEV URLs. Upload and reselect those images,
then replace the prototype links during content rebuild. The current reference
content and homepage arrangement are recorded in
[`docs/content-rebuild.md`](docs/content-rebuild.md).

## Optional local MariaDB snapshot

The ignored `.handoff/` directory contains a MariaDB snapshot and uploads
archive made during verification. It is for local reference only, is not part of
the GitHub handoff, and is not intended for import into PostgreSQL.

Refresh it with `bash scripts/export-handoff.sh`. The resulting `site.sql.gz`
and `public-files.tar.gz` can be restored to a DDEV MariaDB checkout with
`ddev import-db` and `tar`. These files may contain accounts and site data, so
keep them outside Git.

## Maintenance

- Export structural Drupal changes to `config/sync` and review the YAML before
  committing. Content and uploads are managed separately.
- Keep component IDs and prop names stable; Layout Builder stores those names.
- Commit theme source and generated assets together after `npm run build`.
- Handle dependency upgrades separately and repeat the build and visual checks
  in [`docs/verification.md`](docs/verification.md).

Review [`docs/theme-review.md`](docs/theme-review.md) before production work.
