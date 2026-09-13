# Handoff verification — 2026-09-13

All work is local on `codex/developer-handoff`. Starting `main` was clean; no
pre-existing uncommitted changes needed preservation. Nothing was pushed or
deployed. DDEV was started to inspect the existing local site.

## Results

| Check | Result |
| --- | --- |
| Composer manifest / platform requirements | Passed; PHP 8.4.22 |
| Existing theme build | Passed |
| Clean npm installation in `.handoff/clean-theme` | Passed, 688 locked packages |
| Clean-install build | Passed; generated CSS matches working theme byte-for-byte |
| USWDS `assets/css/base/styles.css` vs starting Git | Byte-identical |
| Invalid Sass in isolated copy | Nonzero exit as intended; existing generated CSS retained |
| Watch in isolated copy | Initial build passed; Sass edit triggered a second build |
| JavaScript lint | Passed |
| PHP syntax: theme and new scripts | Passed |
| Drupal YAML/Twig/SDC audit | 266 YAML files, 17 Twig templates, 11 components parsed/discovered |
| Active configuration vs `config/sync` | No differences |
| Content inventory | 12 nodes, 7 media, 21 managed files; none missing |
| Archive checksums / database gzip | Passed |
| Public archive structure | 34 relative entries; no absolute/parent-traversal paths |
| `git diff --check` | Passed |

The build emits upstream USWDS notifications and a Sass legacy API deprecation.
Clean npm installation warned that two optional dependency install scripts
(`@parcel/watcher`, `unrs-resolver`) lacked npm approval; build/watch still passed.
No dependency versions were upgraded.

## Visual and browser checks

The running site before editing was the baseline; no separate approved design
image was provided.

Desktop homepage, `/news`, `/opinions`, and `/node/9` matched their baseline element
positions and sampled computed styles. The 390×844 mobile homepage matched too.
Element counts were 374, 234, 167, 162, and 374 respectively: **zero differences**.

Compared geometry, color/background, font family/size/line height, display,
padding, margin, border/radius, maximum width, gap, and outline. This is not an
exhaustive pixel diff or cross-browser test. Screenshots were also inspected,
including the full homepage and a mobile news story.

Mobile smoke checks on News, Opinions, and the news story found no horizontal
overflow or broken images. The homepage had no broken image elements. Menu
open/close, Escape dismissal, and News/Opinions filtering worked. No console
errors were observed. The initializer is declared in a head library, with the
full USWDS bundle in the footer library.

## Failed or unverified

- **CSS lint remains failing:** 1,057 violations across the expanded Sass and
  handwritten CSS scope. Main categories: blank lines (534), Prettier formatting
  (213), property order (145), plus quotes, indentation and color notation.
  No bulk reformat was applied; builds and appearance checks passed separately.
- A fresh Composer install plus complete database/files restore on another
  environment has not been executed. Integrity checks are not a restore test.
  The recipient must follow the README restoration sequence.
- Managed uploads exist, but external resources, destination links, search,
  media contents, and editorial accuracy are not fully validated.
- Full SDC variants, Layout Builder AJAX lifecycle, field access/cache behavior,
  all image variants, no-JS behavior, screen readers, and other browser engines
  remain unverified.
- Production hosting, email, credentials, and private-file restoration were
  outside this local handoff.

## Local transfer

Prepared `.handoff/transfer-20260913T214442Z/`:

- `site.sql.gz`: matching database, approximately 1.2 MB.
- `public-files.tar.gz`: original uploads, approximately 14 MB.
- `inventory.json`: counts and missing-file audit.
- `SHA256SUMS`: integrity checks.

The export omits regenerable CSS/JS/PHP caches, image derivatives, and obsolete
`files/sync` configuration. It includes unmanaged original uploads too.
No private files were configured in this site's inventory.

Artifacts and test logs are ignored by Git. Transfer the archive directory
privately along with the repository/branch. Refresh it with
`bash scripts/export-handoff.sh` if content or uploads change.
