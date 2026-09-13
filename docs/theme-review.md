# Review and remaining work

Reviewed custom Twig/YAML, page/component CSS and JS, Sass settings, Gulp/npm,
inherited theme libraries, exported configuration, and local content on
2026-09-13. The reference appearance was the running local site before edits.

## Changes

- Centralized 104 repeated color declarations and the 88rem container maximum.
- Shared Sass tokens supply USWDS brand settings and runtime custom properties.
- Extracted fixed alert inline styles into SDC CSS without changing structure.
- Repaired the watcher and added strict Sass validation before cleanup.
- Kept compiled USWDS CSS byte-identical; only variables output changes.
- Declared SDC dependencies, used Drupal header attachment, added Escape
  dismissal, and escaped quotes in client-rendered news attributes.
- Moved the initializer to the head; removed the fixed global library version.
- Fixed the invalid JS-style comment in UI Styles YAML.
- Aligned advertised Drupal compatibility with the installed parent theme.
- Added setup, maintenance, validation, content inventory, and transfer tools.

## Follow-up decisions

1. **Prototype content and destinations.** Many header/hero/task links point to
   `/` or the DDEV hostname; footer links use `#`. Search submits `search` with
   no configured backend. The alert refers to July 23, 2026; the footer description
   ends mid-sentence; some news stories are duplicates. Editors must approve
   final content and destinations. They were preserved for this design handoff.

2. **Accessibility.** Custom `wc-*` components are not official USWDS components
   just because they use utilities. News tabs lack the full keyboard/tabpanel
   relationship pattern. Header/search IDs are fixed; multiple instances need
   unique IDs. Audit screen readers, headings, contrast, keyboard, and no-JS
   behavior before production. This is not an accessibility certification.
   Consult the [official header guidance](https://designsystem.digital.gov/components/header/)
   when deciding whether to adopt upstream markup.

3. **Drupal rendering.** View Twig traverses `view.result` and entities directly.
   Review field access, cache tags/contexts, translations, paging, and file
   replacement invalidation before restricted content. Dates use America/Chicago.
   Original images are used rather than responsive image derivatives.

4. **Inactive variants.** Generic `hero` sets `background-image` directly from its
   URL prop, which may need CSS `url(...)` wrapping. Content-card images have
   decorative empty alt text. Their full variant matrix was not rendered.
   Do not remove stored prop names without migration.

5. **Toolchain.** CSS lint has a formatting/rule backlog; the compiler uses
   the deprecated Sass legacy API and older browser targets. Upgrade separately
   from approved design work. UI Patterns Settings is an alpha dependency.

6. **Reproduction.** A matching database and uploads are required; configuration
   import alone is insufficient. No content-seeding recipe exists. Fonts depend
   on Google. Subdirectory hosting, private file transfer, email delivery,
   production credentials and hosting were not configured or tested.

## Official references

- [USWDS settings](https://designsystem.digital.gov/documentation/settings/):
  configure modules before compilation.
- [USWDS design tokens](https://designsystem.digital.gov/design-tokens/):
  shared semantic design values.
- [USWDS customization](https://designsystem.digital.gov/documentation/getting-started/developers/phase-three-customize/):
  preserve project settings and avoid repeated initialization.
- [Drupal configuration management](https://www.drupal.org/docs/administering-a-drupal-site/configuration-management):
  export/import workflow for configuration.
