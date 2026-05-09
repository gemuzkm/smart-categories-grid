# Changelog

## [2.0] - 2026-05-09

### Fixed — Critical
- `register_activation_hook` moved to top-level file scope; was previously inside `registerHooks()` called from `plugins_loaded` and therefore never fired — custom image size `scg-thumb` was never registered on activation
- Removed `static $cached_settings` from `loadSettings()`; prevented settings from updating within the same request after save. WordPress's built-in `get_option()` caching is sufficient
- Cache key in `getCachedGrid()` now includes all rendering parameters: `hover_effect`, `image_radius`, `button_color`, and plugin version — previously two shortcodes with identical parent/style/columns but different colors returned the same cached HTML
- Added `current_user_can('manage_options')` check to `ajaxClearCache()` — any authenticated user could previously clear the cache
- Added graceful fallback in `getCategoryImage()`: if `placeholder.png` doesn't exist and no default image is configured, returns an empty string so no broken `<img>` is rendered. Also added `assets/placeholder.png` (120×96)

### Fixed — Security
- All admin settings fields now use `esc_attr()`, `esc_html()`, `esc_url()` — previously used raw `<?=` output
- Fixed `wp_cache_delete()` call in `validateSettings()`: now properly clears `alloptions` group

### Fixed — Logic
- `getCurrentCategory()` in `auto` mode now prefers Yoast SEO / RankMath primary category meta; falls back to the deepest category by `get_ancestors()` depth for deterministic results on multi-category posts
- Responsive CSS breakpoints replaced `!important` overrides with CSS `min()` function: `repeat(min(var(--scg-columns), 2), 1fr)` on mobile, `min(var(--scg-columns), 3)` on tablet — user-configured column count is now respected
- `getCategoryImage()` is no longer called for `text` style — eliminates unnecessary database queries
- Removed duplicate `usort()` after `get_terms()` — `get_terms()` already returns results sorted by name

### Fixed — JavaScript (admin.js)
- `handleMediaUpload`: creates a new `wp.media` frame on each click instead of reusing one — reusing caused the `select` callback to always write to the first button's sibling input due to closure capture
- Removed `$(document).ajaxComplete()` → `bindEvents()` loop; event delegation via `$(document).on()` is sufficient and doesn't interfere with other plugins

### Fixed — Compatibility
- `preCheckShortcode()` now checks `widget_block` option for Block Widgets introduced in WordPress 5.8 — styles were not enqueued when the shortcode was placed in a block widget
