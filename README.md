# Smart Categories Grid

Responsive category navigation for WordPress, available as a dynamic block and the `[categories_grid]` shortcode. Both interfaces share a server-side renderer and cache.

## Requirements

WordPress 6.3+, PHP 7.4+. Compatibility metadata remains at WordPress 7.1; no new platform certification is claimed. The optional `intl` extension enables locale-aware sorting.

## Features

- Five styles: Classic, Modern, Minimal, Card and Text Only.
- Responsive columns, category selection, automatic context detection and exclusions.
- Optional category images, default images and high-density thumbnail candidates.
- Optional category limits, View All navigation and empty-archive filtering.
- Transient-based HTML caching with one stable entry per grid configuration.
- No plugin JavaScript enqueued for visitor-facing grids.

## Installation and usage

Install the release ZIP through Plugins → Add New → Upload Plugin. Configure Settings → Categories Grid, then insert the Categories Grid block or use:

```text
[categories_grid category_id="5" limit="12" show_images="true"]
[categories_grid type="top-level" hide_empty="true"]
[categories_grid auto="true" columns="4" style="modern"]
```

From 2.5.0 the Plugins screen offers updates published as GitHub releases. This requires the plugin to be installed in the `smart-categories-grid` folder; the release lookup is cached for six hours.

Attributes: `category_id`, `type` (`subcategories` or `top-level`), `auto`, `exclude`, `hide_empty`, `limit`, `show_view_all`, `show_images`, `columns`, `style`, `hover_effect`, `image_radius`, `button_color`, `force_update`.

Columns are clamped to 2–6, radius to 0–50 px. A zero limit means unlimited. `hide_empty` defaults to false and retains parents with populated descendants. `show_view_all` defaults to true; its button requires a truncated list and links to the parent archive or the saved top-level View All URL. `force_update` is a debugging aid: while `WP_DEBUG` is enabled it bypasses the cache for one render without replacing the cached entry; otherwise it is ignored.

Blank block controls inherit saved settings. Auto mode uses archive context or the assigned post category; the editor displays a placeholder. Only direct subcategories are displayed.

Category images use an attachment ID stored in term meta `logo`. Fallback order: category attachment, saved default attachment, default URL, bundled placeholder. Save settings once to resolve an existing default URL to a local attachment. External URLs remain supported. Regenerate `scg-thumb` (120×96) and `scg-thumb-2x` (240×192) for existing images.

## Performance and integration

Term metadata and attachments for displayed categories are primed in batches. The widget-detection flag is autoloaded. Asset URLs include the file modification time, which costs one file-stat call per request for the front-end stylesheet. These changes reduce overhead but do not guarantee zero cost or a specific query count.

Images reserve dimensions. First-row images are not explicitly marked lazy; later images use native lazy loading. WordPress and optimization plugins may further process images. The stylesheet is requested only by pages that render a grid. Each grid loads a stylesheet with the shared rules and its own style; the full stylesheet serves the block editor and is the fallback when a per-style file is missing. CSS is eligible for WordPress-managed inlining; late renders retain the stylesheet-link fallback. Themes, CSP and delayed-CSS tools may affect the result. Verify CLS, LCP and visual appearance on representative site pages.

Filters: `scg_has_shortcode`, `scg_placeholder_image`, `scg_inline_styles`, `scg_print_late_styles`. The core `wp_lazy_loading_enabled` filter is consulted with context `scg_grid`; clear grid caches after changing rendering filters. The action `scg_cache_cleared` fires once per request after grid caches were invalidated; use it to purge a page cache.

Existing card wrappers and class names are retained. Images are clickable; the title remains the accessible keyboard link. DOM simplification and a redesign of all styles are deferred.

## Development and verification

Edit `assets/front.css`, not the minified files. The Build assets workflow rebuilds and commits `front.min.css` and one `front-<style>.min.css` per style, produced by `.github/scripts/build-css.js`. Release packaging rebuilds them again. Enable SCRIPT_DEBUG in development. Asset URLs change automatically when a file is replaced.

The PHP header, class constant and block metadata versions must match. Release notes use the matching changelog heading. Create a `vX.Y.Z` tag only after CI and site acceptance checks pass.

The regression workflow runs PHP syntax checks and isolated renderer/cache fixtures on PHP 7.4 and 8.3, plus JavaScript syntax checks. These are not WordPress integration tests, browser screenshots or Lighthouse measurements.

## Removal

Uninstall removes plugin options and the cached grid rows in the options table, including on multisite, without flushing the shared object cache. Persistent-cache entries that cannot be enumerated expire by TTL (at most one week for new entries).

## Changelog

### Version 2.5.0
- Offer updates from GitHub releases in the Plugins screen (the plugin folder must be `smart-categories-grid`).
- Load a stylesheet with the shared rules and the grid's own style instead of all five styles; the full stylesheet remains the fallback.
- Store the cache generation once per request; attachment changes invalidate grids only for category images and the default image. New action `scg_cache_cleared`.
- Block editor: search parent categories on demand instead of loading all of them; standard colour palette for the button colour.
- Honour `force_update` only while `WP_DEBUG` is enabled.
- Uninstall removes cached grid rows from the options table even when a persistent object cache is active.

### Version 2.4.1
- Fix a block with parent "Default (from settings)" rendering nothing; it inherits the default category again.
- Restore the 2.3.2 look of the five styles and keep reserved image space. Touch screens get 44 px link targets; hover effects apply to pointer devices only.
- Restore image alt text, block field hints, the empty-result placeholder, and the settings page sections, descriptions and usage help.
- Use one stable cache entry per grid configuration; invalidation overwrites it instead of adding a transient.
- Add the file modification time to asset URLs.
- Register the stylesheet as a block editor style; front-end pages request it only when a grid is rendered.

### Version 2.4.0
- Added `hide_empty` / `hideEmpty` using WordPress hierarchical term queries.
- Invalidate grids after category-count updates; use unique cache-generation tokens.
- Made images clickable while retaining accessible title links.
- Use an autoloaded widget flag and ignore inactive widgets.
- Remove startup asset-stat calls; register editor scripts only in administration.
- Enable optional WordPress-managed CSS inlining and thumbnail-sized default images.
- Bypass cache reads and writes when caching is disabled.
- Remove global cache flushing from uninstall; add multisite cleanup.
- Add isolated regression tests and refresh translations and professional descriptions.

### Version 2.3.2
- Remove direct media-counter manipulation; add deterministic loading attributes, a late CSS fallback and automatic CSS minification.

### Version 2.3.1
- Add per-grid View All control.

### Version 2.3.0
- Add a dynamic editor block sharing the shortcode renderer.

### Version 2.2.0
- Improve caching, metadata priming and high-density thumbnails.

## License and support

GPL v2 or later. Author: TM. Report reproducible issues through GitHub Issues with WordPress/PHP versions, theme and grid configuration.
