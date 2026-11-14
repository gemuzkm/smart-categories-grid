# Layout and performance verification

This document describes the changes prepared for 2.5.0. It is not a certification of WordPress, Redis or page-cache compatibility and does not claim a PageSpeed score or a live-site Core Web Vitals result.

## Stylesheets and rendering

- `assets/front.css` remains the single source. `.github/scripts/build-css.js` extracts shared rules and the rules of one style into five stylesheets; workflows minify them with clean-css.
- Front-end grids select `assets/front-<style>.min.css`. The full `front.min.css` remains the fallback if that file is absent. With `SCRIPT_DEBUG`, grids use the full source. The editor registers the full stylesheet so changing styles remains possible.
- The feature branch does not contain the generated per-style files. CI builds them for layout tests; Release builds them into the ZIP; Build assets commits them to main after the merge. Installing the unbuilt branch therefore uses the full-file fallback.
- Early grid detection requests the saved default style. A grid overriding that style can also request its own stylesheet, so a page can load more than one file. Multiple styles duplicate shared rules; no universal reduction in request count is claimed.
- Stylesheets opt into WordPress-managed inlining through path data. A grid rendered after the head retains the stylesheet-link fallback. Delivery in a category template, a block-editor preview and CSS-optimization plugins has not been verified on a live site.
- Image containers reserve their geometry before images load, including Card's fixed heights. Text style renders no images. No visitor-facing plugin JavaScript is enqueued.
- Hover effects require a fine pointer with hover support. They include shadow, background, border-color and color transitions, not only transform and opacity. Reduced-motion preferences disable transitions and animations. The 44px minimum link height applies under `pointer: coarse`, not on every device.

A development comparison using a simple local minifier produced 9,272 bytes for the full stylesheet and 4,127 bytes for Text. These are uncompressed development outputs, not measured clean-css release sizes, compressed transfer sizes or evidence of faster LCP. Measure the final package and deployed page before claiming a speed improvement.

## Cache behaviour and limitations

- Each grid configuration, plugin version and locale has a stable transient key. The generation token is stored in the entry, so invalidation does not create a new key for each edit. New configurations and plugin versions still create separate entries.
- The 2.5.0 implementation changes the in-request token immediately and batches persistence until shutdown. Attachment edits and deletions check whether the attachment is a default image or category logo before invalidating grids. This reduces write opportunities by design; database and Redis latency have not been benchmarked.
- `scg_cache_cleared` is an integration hook, not a built-in page-cache purge. Without an integration, existing cached pages can continue to contain older grid HTML. Do not flush the site's shared object cache.
- `force_update` bypasses grid caching only with `WP_DEBUG` enabled; setting cache duration to zero disables grid cache reads and writes independently.
- The release lookup has a separate site-transient cache: six hours for a usable release, one hour for a failed lookup. A cache-miss lookup can wait for its HTTP timeout; caching does not eliminate that first-request delay. Installation of an update has not been tested in WordPress.
- Uninstall targets this plugin's options and database grid-transient rows without a shared-cache flush. External-cache entries expire by TTL. Actual uninstall behaviour with Redis and multisite remains untested.

## Verification performed

The Chromium fixture covers five styles, four widths (320, 375, 768 and 1280px), two column settings (2 and 6), and two stylesheet modes: 80 cases. Source cases load the full source; minified cases load the per-style file rebuilt with clean-css. Touch contexts are emulated only at widths below 768px; wide touchscreens, hybrid devices and narrow mouse contexts are not covered.

The fixture checks geometry before and after delayed image loading, layout-shift entries, horizontal overflow, responsive columns, 44px link heights in touch contexts, touch hover and reduced-motion transition duration. Passing thresholds are geometry change below 0.5px, layout-shift sum below 0.001 and horizontal overflow at most 1px. These are synthetic thresholds, not measured live-site CLS.

On commit `66a9c4752f73767404033a2cc555daa015bf26bd`, all ten non-Lighthouse PR checks completed successfully: PHP syntax on 7.4 and 8.0-8.4, isolated renderer/cache fixtures on 7.4 and 8.3, version consistency, and layout. The layout run is https://github.com/gemuzkm/smart-categories-grid/actions/runs/43573441435 . This records that commit only; subsequent changes need their own checks. Lighthouse was skipped.

PHP fixtures replace WordPress functions with stubs. They do not verify actual database queries, persistent Redis behaviour, concurrent requests, shutdown ordering, block editor components, update installation or uninstall. Plugin Check has not been run. PHP autoformatting was removed by choice; this is not a claim of full coding-standard compliance.

## Checks before release

1. Build the installation ZIP, verify its root folder and version, and confirm all five generated stylesheets are included.
2. On staging, render the category-template shortcode on two different category archives in Text mode. Check correct subcategories, cold and warm caches, and invalidation after category changes with Redis enabled and disabled.
3. Test the actual page-cache plugin: purge affected archive pages through a targeted integration, then verify the new HTML. Test stylesheet delivery with the site's minification, deferred-CSS and unused-CSS settings.
4. Test editor search, the saved parent, colour selection/reset, settings saving, keyboard focus and mobile/desktop appearance. Test GitHub update installation and uninstall on a disposable site.
5. Measure repeated cold/warm page loads on a public representative URL. Lighthouse supplies laboratory LCP, CLS and TBT; TBT is not field INP. Use field data or RUM for INP. The whole theme, fonts, hosting and other plugins affect the results.

The optional Lighthouse workflow audits the deployed URL, not unpublished branch code. Do not include credentials or private tokens. Full-page/CDN caches serving older HTML may need a targeted purge after installation even though asset URLs include the plugin version and file modification time.
