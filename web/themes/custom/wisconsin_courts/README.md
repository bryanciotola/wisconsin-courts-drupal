# Wisconsin Courts theme

Drupal Single Directory Components (SDC) and UI Patterns on UI Suite USWDS.
Preserve approved colors, type, dimensions, selector order, and breakpoints.

## Source map

| Path | Responsibility |
| --- | --- |
| `sass/base/_tokens.scss` | Shared approved brand/semantic colors and custom container width |
| `sass/base/uswds/_uswds-theme.scss` | USWDS font roles, brand settings, widescreen grid |
| `sass/base/styles.scss` | USWDS Sass entry point |
| `sass/base/variables.scss` | Expose shared tokens as global `--wc-*` custom properties |
| `sass/base/elements.scss` | Reserved element styles; currently empty |
| `assets/css/base/` | Generated CSS/maps; rebuild rather than editing |
| `assets/uswds/` | Copied locked USWDS assets; rebuild rather than editing |
| `css/` | Handwritten global page/layout CSS; no compile step |
| `components/NAME/` | Twig, prop/slot YAML, CSS/JS automatically loaded by SDC |
| `templates/layout/` | Parent page extensions; front page allows full-width sections |
| `templates/views/` | View entity adapters for news/opinion lists and component items |
| `templates/content/` | News story presentation and image positions |
| `wisconsin_courts.theme` | Theme paths and removal of parent header region's `usa-navbar` class |
| `wisconsin_courts.libraries.yml` | Global assets, ordering, head initializer |
| `wisconsin_courts.breakpoints.yml` | Drupal breakpoint declarations |

## Tokens and USWDS practices

Settings load before USWDS, following the
[official Sass configuration order](https://designsystem.digital.gov/documentation/settings/).
Use USWDS semantic tokens, functions, mixins, and utilities for new work where
they match the design.

Approved brand hex values intentionally remain exact in `_tokens.scss`.
Handwritten CSS consumes `var(--wc-color-primary)`, `var(--wc-color-heading)`,
`var(--wc-color-focus)`, etc., supplied by the global variables stylesheet.
Add shared values there rather than duplicating literals.

Keep these distinctions until a separate design review:

- Brand primary `#1e3a70`, accent `#2a5298`, and heading navy `#16385c`.
- Brand accent versus existing link hover `#005ea8`.
- Custom section width `88rem` versus USWDS `widescreen` at 1400px.
- Component-specific shades and fractional spacing.

Oswald and Carlito currently come from Google Fonts with system fallbacks.
Font roles and cap-height settings are preserved. Self-hosting requires font
and visual review. Review default USWDS font/image paths when introducing
unused upstream components; these custom font families do not use the bundled
USWDS font-face files.

[USWDS developer guidance](https://designsystem.digital.gov/documentation/developers/)
calls for npm-managed assets, custom Sass, a head initializer, and the full JS
bundle near the end of the body. Those are the boundaries here. Keep the parent
theme's Drupal integration CSS.

## Build and checks

Run here with Node 24 (`.nvmrc`), or prefix from the root with
`ddev exec --dir=/var/www/html/web/themes/custom/wisconsin_courts`.

```sh
npm ci
npm run build
npm run watch
```

| Command | Behavior |
| --- | --- |
| `build` | Copy pinned USWDS assets, build `js/`, validate and compile Sass |
| `build:css` | Validate entry points before replacing generated CSS |
| `build:js` | Minify `js/**/*.js` only; SDC JS is served directly |
| `build:all` | Build custom JS/Sass without copying upstream assets |
| `check:css` | Strict in-memory compilation; errors return nonzero |
| `watch` | Rebuild Sass/`js/` changes; SDC CSS/JS needs refresh, sometimes cache rebuild |
| `lint:js` | ESLint with classic-script Drupal and Node tooling settings |
| `lint:css` | Legacy Drupal/Sass style checks; documented formatting backlog |
| `build:uswds:update` | Re-copy/rebuild installed assets; does not change npm versions |
| `build:uswds:init` | Deliberately stops: initialization would overwrite theme settings |

The upstream compile helper swallows Sass errors, so `scripts/check-css.cjs`
validates before cleanup. Existing compiler browser targets stay unchanged for
output stability. Its legacy Sass API deprecation belongs in a separate upgrade.
Avoid bulk CSS auto-fixes during visual work.

Generated assets are tracked so a checkout can serve before building. Commit
source and built output together. After library/Twig edits: `ddev drush cr`.

## Component contracts

IDs and prop names are persisted in configuration and Layout Builder content.
Renaming them requires migrating stored references.

| Component | Responsibility / constraint |
| --- | --- |
| `site-header` | Utility/primary links; Drupal behavior, `core/once`, Escape dismissal |
| `site-alert` | Configured status strip; fixed styles in sibling CSS |
| `homepage-hero` | Hero/search/actions/audiences; no visible search placeholder by design |
| `latest-news` | Server-rendered initial list, JS filtering; `core/drupal` + `core/once` |
| `popular-tasks` | Descriptions supplied as renderable slots |
| `chief-justice-message` | Portrait variants, message slot, CTA |
| `supreme-court-live` | Status/image/calendar; no stream integration |
| `site-footer` | Configured logo/description; link lists still contain prototype `#` destinations |
| `content-card` | USWDS card/button classes and mapped image/variant props |
| `hero` | Upstream replacement; review background-image contract before new use |
| `homepage-updates` | Retained static alternative, not the active homepage news adapter |

`latest-news` retains legacy `all_news_*` props, but Twig/JS currently use
category-specific `/news` and `/opinions` links. These, the story back link, and
sprite paths assume this theme name and root hosting. Review subdirectory hosting
separately.

View templates traverse entities and use `file_url()`. Keep formatted body
content as a render array; do not introduce `|raw`. Future extraction should use
preprocess/render arrays with field access and cache metadata, plus image styles.
