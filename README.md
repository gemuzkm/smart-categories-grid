# Smart Categories Grid

**Smart Categories Grid** is a WordPress plugin that displays categories in a responsive grid layout with advanced caching, customizable settings, category exclusion, optional image display, and category limit capabilities. Available as a **Gutenberg block** and a **shortcode**. Optimized for performance and designed for sites with a large number of categories.

[![WordPress](https://img.shields.io/badge/WordPress-6.3%2B-blue.svg)](https://wordpress.org/)
[![Tested up to](https://img.shields.io/badge/Tested%20up%20to-7.1-brightgreen.svg)](https://wordpress.org/)
[![PHP](https://img.shields.io/badge/PHP-7.4%2B-purple.svg)](https://php.net/)
[![License](https://img.shields.io/badge/License-GPL%20v2-green.svg)](https://www.gnu.org/licenses/gpl-2.0.html)

## ✨ Features

- **🧩 Block + Shortcode**: native "Categories Grid" block with live preview and sidebar controls, plus the classic `[categories_grid]` shortcode — both share one render path and one cache
- **📱 Responsive Grid**: Automatically adjusts to different screen sizes (mobile, tablet, desktop)
- **⚡ Advanced Caching**: Generation-based cache invalidation that works with the options table **and** persistent object caches (Redis, Memcached)
- **🎯 Auto Category Detection**: Automatically detect current category and display its direct subcategories
- **🎨 Customizable Display**:
  - Adjustable columns (2-6 columns)
  - Customizable image border radius
  - Optional hover effects
  - Custom button colors
  - 5 beautiful styles: Classic, Modern, Minimal, Card, Text Only
- **🖼️ Image Support**:
  - Custom image sizes: 120x96 (1x) and 240x192 (2x, Retina) served via `srcset`
  - Default image fallback (`assets/placeholder.png`, overridable via filter)
  - Native lazy loading / `fetchpriority` decided by WordPress core per page
- **🔧 Flexible Configuration**:
  - Display subcategories or top-level categories
  - **Auto mode**: Automatically detect current category
  - **Direct children only**: Shows only 1 level of subcategories (no deep nesting)
  - Category exclusion (global and per-block/shortcode)
  - Category limit with "View All" button
  - Per-block/shortcode settings override all defaults
- **🚀 Performance Optimized**:
  - Term meta and attachment caches primed in a single query each (no N+1)
  - Instance-level image cache shared across multiple grids on the same page
  - Asset file versions computed once at init (no repeated disk I/O)
  - Minified frontend CSS (`front.min.css`, falls back to `front.css` when `SCRIPT_DEBUG` is on)
  - Conditional asset loading (block styles are loaded by WordPress only when the block is present)
  - Widget shortcode detection cached via transient and invalidated when widgets change
  - Locale-aware category sorting (ICU `Collator` when available)

## 📦 Installation

### Manual Installation

1. Download the latest `smart-categories-grid-X.Y.Z.zip` from [Releases](https://github.com/gemuzkm/smart-categories-grid/releases)
2. Go to **Plugins → Add New → Upload Plugin**, choose the ZIP and click **Install Now**
3. Activate the plugin
4. Navigate to **Settings → Categories Grid** to configure defaults

## 🚀 Quick Start

### Block editor

Insert the **Categories Grid** block (category *Widgets*, or type `/categories`). Pick the source in the sidebar — auto-detect, subcategories of a chosen parent, or top-level categories — and optionally override style, columns, images, hover effect, image radius and button color. Everything left on *Default (from settings)* inherits the values from **Settings → Categories Grid**.

### Shortcode

```
[categories_grid]
```

For more control, use attributes:

```
[categories_grid category_id="5" limit="10" show_images="true"]
```

## 📖 Usage

### Block attributes vs shortcode attributes

| Block control | Shortcode attribute | Type | Default | Description |
|---------------|---------------------|------|---------|-------------|
| Auto-detect current category | `auto` | boolean | `false` | Show direct subcategories of the category being viewed |
| Display → Subcategories / Top-level | `type` | string | `subcategories` | `subcategories` or `top-level` |
| Parent category | `category_id` | integer | Settings default | Parent category ID (ignored if `auto="true"`) |
| Exclude category IDs | `exclude` | string | - | Comma-separated IDs, e.g. `"10,20,30"` |
| Images | `show_images` | boolean | Settings default | `true` / `false` |
| Limit | `limit` | integer | Settings default | Max categories (0 = no limit) |
| Columns | `columns` | integer | Settings default | 2–6 |
| Style | `style` | string | Settings default | `classic`, `modern`, `minimal`, `card`, `text` |
| Hover effect | `hover_effect` | boolean | Settings default | `true` / `false` |
| Image border radius | `image_radius` | integer | Settings default | 0–50 px |
| Button color | `button_color` | string | Settings default | Hex color for "View All" |
| — | `force_update` | boolean | `false` | Bypass cache for this render (shortcode only) |

### Shortcode examples

```php
[categories_grid auto="true"]
[categories_grid category_id="5" limit="10"]
[categories_grid auto="true" style="modern" columns="4" show_images="true"]
[categories_grid type="top-level" limit="0"]
[categories_grid category_id="5" exclude="10,20" show_images="false"]
[categories_grid auto="true" style="card" columns="3" limit="6" hover_effect="true" button_color="#ff6b6b"]
```

### Auto Mode Details

When auto mode is on (block toggle or `auto="true"`):

- **Automatic Detection** from category archive pages, single posts (Yoast / Rank Math primary category, otherwise the deepest assigned category) or the current post in the loop.
- **Direct Subcategories Only**: only 1 level of subcategories is shown.
- **No Subcategories**: the block/shortcode renders nothing.
- **Editor preview**: not available for auto mode (the editor has no "current category"); a placeholder is shown instead and the grid renders on the front end.

## ⚙️ Settings

Access plugin settings via **Settings → Categories Grid** in WordPress admin.

### General Settings

- **Default Category**: Default parent category for subcategories display
- **Exclude Categories**: Global category exclusion list (comma-separated IDs)
- **Cache Duration**: 1 Hour / 12 Hours / 1 Day (default) / 1 Week / No Caching
- **Default Category Limit**: Default number of categories to display (0 = unlimited)
- **View All URL**: URL for "View All" button on top-level categories

### Display Settings

- **Default Columns**: Grid columns (2-6, default: 6)
- **Image Border Radius**: Image corner radius (0-50px, default: 3px)
- **Hover Effect**: Enable/disable hover animations
- **Default Image**: Fallback image URL for categories without images
- **Show Images by Default**: Global image display toggle
- **Button Color**: "View All" button color (default: `#b93434`)
- **Grid Style**: Classic / Modern / Minimal / Card / Text Only

### Cache Management

- **Clear Cache**: Manual cache clearing button in settings
- **Auto-clear**: Cache automatically clears when:
  - Settings are saved
  - Categories are created/edited/deleted
  - A category image (term meta `logo`) is added, changed or removed

## 🎨 Customization

### CSS Customization

The plugin uses CSS custom properties for easy theming:

```css
.scg-grid {
    --scg-columns: 6;
    --scg-image-radius: 3px;
    --scg-button-color: #b93434;
}
```

The block additionally supports `alignwide` / `alignfull` and margin controls via block supports.

### Hooks and Filters

| Filter | Arguments | Description |
|--------|-----------|-------------|
| `scg_has_shortcode` | `bool $found` | Override shortcode detection (useful for page builders) |
| `scg_placeholder_image` | `string $url` | Replace or disable (`''`) the fallback image |

Core filters also apply: `wp_lazy_loading_enabled` and `wp_get_loading_optimization_attributes` receive the context `scg_grid`.

Example:
```php
add_filter('scg_has_shortcode', function ($found) {
    return $found || is_page('my-category-page');
});
```

## 🔧 Technical Details

### Block implementation

- Dynamic block `smart-cat-grid/categories-grid` (`apiVersion` 3, works inside the iframed editor of WordPress 7.x)
- No build step: `block/index.js` uses `wp.element.createElement` and WordPress globals (`wp.blocks`, `wp.blockEditor`, `wp.components`, `wp.coreData`, `wp.serverSideRender`)
- `render_callback` maps block attributes onto the shortcode attributes, so caching, sorting and image handling are identical for both entry points
- Stylesheet handle `scg-front` is registered once and referenced from `block.json`; the shortcode enqueues the same handle, so CSS is never loaded twice

### Performance Optimizations

- **Generation-based cache**: Every cache key includes `scg_cache_gen`; clearing the cache just increments that number (O(1), no `LIKE … DELETE` on `wp_options`, works with Redis/Memcached)
- **No N+1 queries**: `update_term_meta_cache` is enabled only when images are shown, and all attachment posts/meta are primed via `_prime_post_caches()` before rendering
- **Core-driven image loading**: `<img>` attributes come from `wp_get_loading_optimization_attributes()`, so `loading`, `fetchpriority` and `decoding` respect WordPress' per-page media counter. On cache hits the counter is replayed with `wp_increase_content_media_count()`
- **Retina `srcset`**: a 2x candidate is added only when a real 240x192 intermediate exists (never the full-size original)
- **Conditional Asset Loading**: CSS loads only when the block/shortcode is present; the minified file is used unless `SCRIPT_DEBUG` is enabled
- **CLS Prevention**: `width`/`height` attributes plus `aspect-ratio` reserve space before images load

### Image Handling

- Image sizes: `scg-thumb` (120x96) and `scg-thumb-2x` (240x192), both hard-cropped
- Category image is read from term meta key `logo` (attachment ID)
- Fallback order: category image → **Default Image** setting → `assets/placeholder.png` → no image

### Cache System

- WordPress Transients API with a generation number in the key
- Automatic invalidation on category and category-image changes
- Configurable duration; `0` disables caching
- `uninstall.php` removes all options and leftover transients

## 📁 File Structure

```
smart-categories-grid/
├── .github/workflows/
│   ├── ci.yml                 # php -l matrix + consistency checks
│   └── release.yml            # ZIP build + GitHub Release on tag
├── assets/
│   ├── admin.css              # Admin panel styles
│   ├── admin.js               # Admin panel JavaScript
│   ├── front.css              # Frontend grid styles (source)
│   ├── front.min.css          # Frontend grid styles (minified, used in production)
│   └── placeholder.png        # Default category image placeholder
├── block/
│   ├── block.json             # Block metadata (attributes, supports, asset handles)
│   └── index.js               # Block editor UI (no build step)
├── languages/
│   └── smart-cat-grid.pot     # Translation template (PHP + JS + block.json strings)
├── smart-categories-grid.php  # Main plugin file
├── uninstall.php              # Cleanup on plugin deletion
└── README.md                  # This file
```

## 🔒 Security

- All user inputs are sanitized and validated
- Proper escaping for all outputs
- Nonce verification and capability checks for AJAX requests
- `Update URI` header prevents accidental updates from an unrelated wordpress.org plugin with the same slug

## 🌍 Internationalization

Translation-ready; text domain `smart-cat-grid`. PHP translations are loaded from `/languages` on `init`; block editor strings use `wp_set_script_translations()` (JSON files generated with `wp i18n make-json`). The template `languages/smart-cat-grid.pot` covers PHP, JS and `block.json` strings.

## ✅ Compatibility

- **WordPress**: 6.3+
- **Tested up to**: 7.1
- **PHP**: 7.4+ (ICU `intl` extension recommended for locale-aware sorting)
- **Themes**: Compatible with most WordPress themes, including block themes
- **Caching**: Works with page caches and persistent object caches (Redis, Memcached)

## 🐛 Troubleshooting

### Images Not Displaying

1. Check if the category has an attachment ID in term meta `logo`
2. Regenerate thumbnails (both `scg-thumb` and `scg-thumb-2x`) using "Regenerate Thumbnails"
3. Verify the default image URL in settings or that `assets/placeholder.png` exists

### Block preview is empty

1. Auto mode never previews in the editor — this is expected
2. For other modes check that the chosen parent category has direct subcategories
3. Open the browser console: a REST error from `/wp/v2/block-renderer/` usually points to a PHP notice in a theme or another plugin

### Cache Not Clearing

1. Use the "Clear Cache" button in settings
2. Make sure the `scg_cache_gen` option is writable (it is incremented on every clear)

### Grid Not Responsive

1. Clear browser / page cache
2. Verify `front.min.css` is loading (check browser console)
3. Check for theme CSS conflicts

## 🚢 Releasing

1. Bump `Version:` in the plugin header, `const VERSION` in the class and `version` in `block/block.json`
2. Add a `### Version X.Y.Z` section to the changelog below
3. Push a tag `vX.Y.Z` — the Release workflow lints the code, verifies the versions match, builds the ZIP and publishes a GitHub Release with these notes

## 📝 Changelog

### Version 2.3.0
- **New**: Gutenberg block `smart-cat-grid/categories-grid` with live `ServerSideRender` preview, hierarchical parent-category picker and all display overrides in the sidebar; supports wide/full alignment and margin controls
- **New**: shared stylesheet handle `scg-front` registered on `init` and referenced from `block.json` — block and shortcode never load CSS twice
- **New**: `languages/smart-cat-grid.pot` regenerated (84 strings: PHP, JS and `block.json`); `wp_set_script_translations()` for the editor script
- **Improved**: `admin.js` — color picker initialised from the script (inline script removed), `wp.media` guard, `aria-busy` + `wp.a11y.speak()` for the Clear Cache action, `ajax_url` passed via `wp_localize_script`
- **Improved**: `style` attribute validated against the allowed list and `image_radius` capped at 50 before reaching the cache key
- **Improved**: shortcode detection also recognises the block in post content and block widgets
- **Chore**: old `languages/sc-grid.pot` removed; README restructured around block + shortcode

### Version 2.2.0
- **Fixed**: cache was never invalidated on sites with a persistent object cache (Redis/Memcached) — replaced `LIKE … DELETE` with generation-based keys
- **Fixed**: changing a category image (term meta `logo`) did not clear the cache — added `added/updated/deleted_term_meta` hooks
- **Fixed**: widget shortcode detection transient was not reset when widgets changed — hooked `update_option_widget_text/_block`
- **Fixed**: non-ASCII (e.g. Cyrillic) category names were sorted by byte value — now uses ICU `Collator` with `mb_strtolower` fallback
- **Fixed**: translations could not load (`load_plugin_textdomain` was missing)
- **Perf**: term meta primed in one query; attachment posts/meta primed with `_prime_post_caches()`
- **Perf**: image `loading`/`fetchpriority`/`decoding` from `wp_get_loading_optimization_attributes()`; media counter replayed on cache hits
- **Perf**: removed `will-change: transform`; shipped `front.min.css`; placeholder URL resolved once per request
- **New**: 2x Retina image size `scg-thumb-2x` with `srcset`; `scg_placeholder_image` filter; `uninstall.php`
- **Chore**: `Tested up to: 7.1`, `Requires at least: 6.3`, `Update URI`, `Domain Path`; modern `register_setting()` args

### Version 2.1.0
- WordPress 7.0 compatibility headers
- LCP / CLS / INP fixes (eager first image, `aspect-ratio`, explicit transitions)
- Asset versions computed once; instance-level image cache; widget transient cache

### Version 2.0.1
- Performance optimizations, improved caching, enhanced security, code refactoring

### Version 1.9
- Initial stable release

## 🤝 Contributing

1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Test thoroughly (CI runs `php -l` on PHP 7.4–8.4 and consistency checks)
5. Submit a pull request

## 📄 License

This plugin is licensed under the [GNU General Public License v2.0](https://www.gnu.org/licenses/gpl-2.0.html).

## 💬 Support

- **GitHub Issues**: [Report bugs or request features](https://github.com/gemuzkm/smart-categories-grid/issues)

## 👤 Author

**TM** — [github.com/gemuzkm](https://github.com/gemuzkm)

---

⭐ If you find this plugin useful, please consider giving it a star on GitHub!
