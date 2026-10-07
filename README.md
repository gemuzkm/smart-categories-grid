# Smart Categories Grid

**Smart Categories Grid** is a WordPress plugin that displays categories in a responsive grid layout with advanced caching, customizable settings, category exclusion, optional image display, and category limit capabilities. Optimized for performance and designed for sites with a large number of categories.

[![WordPress](https://img.shields.io/badge/WordPress-6.3%2B-blue.svg)](https://wordpress.org/)
[![Tested up to](https://img.shields.io/badge/Tested%20up%20to-7.1-brightgreen.svg)](https://wordpress.org/)
[![PHP](https://img.shields.io/badge/PHP-7.4%2B-purple.svg)](https://php.net/)
[![License](https://img.shields.io/badge/License-GPL%20v2-green.svg)](https://www.gnu.org/licenses/gpl-2.0.html)

## ✨ Features

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
  - Category exclusion (global and per-shortcode)
  - Category limit with "View All" button
  - Per-shortcode image display control
  - **Per-shortcode settings**: Each shortcode can override all default settings
- **🚀 Performance Optimized**:
  - Term meta and attachment caches primed in a single query each (no N+1)
  - Instance-level image cache shared across multiple shortcodes on the same page
  - Asset file versions computed once at init (no repeated disk I/O)
  - Minified frontend CSS (`front.min.css`, falls back to `front.css` when `SCRIPT_DEBUG` is on)
  - Conditional asset loading
  - Widget shortcode detection cached via transient and invalidated when widgets change
  - Locale-aware category sorting (ICU `Collator` when available)

## 📦 Installation

### Manual Installation

1. Download the plugin from GitHub
2. Upload the `smart-categories-grid` folder to `/wp-content/plugins/` directory
3. Activate the plugin through the "Plugins" menu in WordPress
4. Navigate to **Settings → Categories Grid** to configure

### Via WordPress Admin

1. Go to **Plugins → Add New**
2. Click **Upload Plugin**
3. Choose the plugin zip file
4. Click **Install Now** and then **Activate**

## 🚀 Quick Start

After activation, simply add the shortcode to any page or post:

```
[categories_grid]
```

For more control, use attributes:

```
[categories_grid category_id="5" limit="10" show_images="true"]
```

## 📖 Usage

### Shortcode Attributes

The `[categories_grid]` shortcode supports the following attributes. **All attributes override default settings** when specified:

| Attribute | Type | Default | Description |
|-----------|------|---------|-------------|
| `auto` | boolean | `false` | Automatically detect current category and show its direct subcategories |
| `category_id` | integer | Settings default | Parent category ID for subcategories (ignored if `auto="true"`) |
| `type` | string | `subcategories` | Display type: `subcategories` or `top-level` |
| `exclude` | string | - | Comma-separated category IDs to exclude (e.g., `"10,20,30"`) |
| `show_images` | boolean | Settings default | Display category images (`true`/`false`) |
| `limit` | integer | Settings default | Maximum categories to display (0 = no limit) |
| `columns` | integer | Settings default | Number of columns (2-6) |
| `style` | string | Settings default | Grid style: `classic`, `modern`, `minimal`, `card`, `text` |
| `hover_effect` | boolean | Settings default | Enable hover effects (`true`/`false`) |
| `image_radius` | integer | Settings default | Image border radius in pixels (0-50) |
| `button_color` | string | Settings default | "View All" button color (hex code) |
| `force_update` | boolean | `false` | Bypass cache for this render (`true`/`false`) |

### Examples

#### Auto-Detect Current Category (Recommended)

```php
[categories_grid auto="true"]
```

Automatically detects the current category (from category archive page or single post) and displays **only direct subcategories** (1 level deep). If no subcategories exist, nothing is displayed. Perfect for category archive pages!

#### Display Subcategories with Limit

```php
[categories_grid category_id="5" limit="10"]
```

Displays up to 10 subcategories of category ID 5. Shows "View All" button if more categories exist.

#### Auto-Detect with Custom Settings

```php
[categories_grid auto="true" style="modern" columns="4" show_images="true"]
```

#### Display Top-Level Categories

```php
[categories_grid type="top-level" limit="0"]
```

#### Exclude Categories and Hide Images

```php
[categories_grid category_id="5" exclude="10,20" show_images="false"]
```

#### Fully Customized Grid

```php
[categories_grid auto="true" style="card" columns="3" limit="6" hover_effect="true" button_color="#ff6b6b"]
```

### Auto Mode Details

When using `auto="true"`:

- **Automatic Detection**: The plugin determines the current category from:
  - Category archive pages (queried object)
  - Single post pages (Yoast / Rank Math primary category, otherwise the deepest assigned category)
  - Current post in the loop
- **Direct Subcategories Only**: Shows **only 1 level** of subcategories (direct children).
- **No Subcategories**: If the current category has no direct subcategories, the shortcode returns empty.

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

### Performance Optimizations

- **Generation-based cache**: Every cache key includes `scg_cache_gen`; clearing the cache just increments that number (O(1), no `LIKE … DELETE` on `wp_options`, works with Redis/Memcached)
- **No N+1 queries**: `update_term_meta_cache` is enabled only when images are shown, and all attachment posts/meta are primed via `_prime_post_caches()` before rendering
- **Core-driven image loading**: `<img>` attributes come from `wp_get_loading_optimization_attributes()`, so `loading`, `fetchpriority` and `decoding` respect WordPress' per-page media counter. On cache hits the counter is replayed with `wp_increase_content_media_count()`
- **Retina `srcset`**: a 2x candidate is added only when a real 240x192 intermediate exists (never the full-size original)
- **Conditional Asset Loading**: CSS loads only when the shortcode is present; the minified file is used unless `SCRIPT_DEBUG` is enabled
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
├── assets/
│   ├── admin.css          # Admin panel styles
│   ├── admin.js           # Admin panel JavaScript
│   ├── front.css          # Frontend grid styles (source)
│   ├── front.min.css      # Frontend grid styles (minified, used in production)
│   └── placeholder.png    # Default category image placeholder
├── languages/
│   └── sc-grid.pot        # Translation template
├── smart-categories-grid.php  # Main plugin file
├── uninstall.php          # Cleanup on plugin deletion
└── README.md              # This file
```

## 🔒 Security

- All user inputs are sanitized and validated
- Proper escaping for all outputs
- Nonce verification and capability checks for AJAX requests
- `Update URI` header prevents accidental updates from an unrelated wordpress.org plugin with the same slug

## 🌍 Internationalization

Translation-ready; text domain `smart-cat-grid`, translations are loaded from `/languages` on `init`.

## ✅ Compatibility

- **WordPress**: 6.3+
- **Tested up to**: 7.1
- **PHP**: 7.4+ (ICU `intl` extension recommended for locale-aware sorting)
- **Themes**: Compatible with most WordPress themes
- **Caching**: Works with page caches and persistent object caches (Redis, Memcached)

## 🐛 Troubleshooting

### Images Not Displaying

1. Check if the category has an attachment ID in term meta `logo`
2. Regenerate thumbnails (both `scg-thumb` and `scg-thumb-2x`) using "Regenerate Thumbnails"
3. Verify the default image URL in settings or that `assets/placeholder.png` exists

### Cache Not Clearing

1. Use the "Clear Cache" button in settings
2. Make sure the `scg_cache_gen` option is writable (it is incremented on every clear)

### Grid Not Responsive

1. Clear browser / page cache
2. Verify `front.min.css` is loading (check browser console)
3. Check for theme CSS conflicts

## 📝 Changelog

### Version 2.2.0
- **Fixed**: cache was never invalidated on sites with a persistent object cache (Redis/Memcached) — replaced `LIKE … DELETE` with generation-based keys
- **Fixed**: changing a category image (term meta `logo`) did not clear the cache — added `added/updated/deleted_term_meta` hooks
- **Fixed**: widget shortcode detection transient was not reset when widgets changed — hooked `update_option_widget_text/_block`
- **Fixed**: non-ASCII (e.g. Cyrillic) category names were sorted by byte value — now uses ICU `Collator` with `mb_strtolower` fallback
- **Fixed**: translations could not load (`load_plugin_textdomain` was missing)
- **Perf**: term meta primed in one query (was N queries with `update_term_meta_cache => false`)
- **Perf**: attachment posts/meta primed with `_prime_post_caches()` (was N queries)
- **Perf**: image `loading`/`fetchpriority`/`decoding` now come from `wp_get_loading_optimization_attributes()`; media counter replayed on cache hits
- **Perf**: removed `will-change: transform` (created one compositor layer per card on large grids)
- **Perf**: shipped `front.min.css`; placeholder URL resolved once per request
- **New**: 2x Retina image size `scg-thumb-2x` with `srcset`
- **New**: `scg_placeholder_image` filter, `uninstall.php`
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
4. Test thoroughly
5. Submit a pull request

## 📄 License

This plugin is licensed under the [GNU General Public License v2.0](https://www.gnu.org/licenses/gpl-2.0.html).

## 💬 Support

- **GitHub Issues**: [Report bugs or request features](https://github.com/gemuzkm/smart-categories-grid/issues)

## 👤 Author

**TM** — [github.com/gemuzkm](https://github.com/gemuzkm)

---

⭐ If you find this plugin useful, please consider giving it a star on GitHub!
