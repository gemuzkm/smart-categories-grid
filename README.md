# Smart Categories Grid

**Smart Categories Grid** is a WordPress plugin that displays categories in a responsive grid layout with advanced caching, customizable settings, category exclusion, optional image display, and category limit capabilities. Optimized for performance and designed for sites with a large number of categories.

[![WordPress](https://img.shields.io/badge/WordPress-5.0%2B-blue.svg)](https://wordpress.org/)
[![PHP](https://img.shields.io/badge/PHP-7.4%2B-purple.svg)](https://php.net/)
[![License](https://img.shields.io/badge/License-GPL%20v2-green.svg)](https://www.gnu.org/licenses/gpl-2.0.html)

## ✨ Features

- **📱 Responsive Grid**: Automatically adjusts to different screen sizes (mobile, tablet, desktop)
- **⚡ Advanced Caching**: Intelligent caching system with configurable duration and automatic cache invalidation
- **🎨 Customizable Display**: 
  - Adjustable columns (2-6 columns)
  - Customizable image border radius
  - Optional hover effects
  - Custom button colors
- **🖼️ Image Support**: 
  - Custom image size (120x96px) with automatic cropping
  - Default image fallback
  - Lazy loading for better performance
- **🔧 Flexible Configuration**:
  - Display subcategories or top-level categories
  - Category exclusion (global and per-shortcode)
  - Category limit with "View All" button
  - Per-shortcode image display control
- **🚀 Performance Optimized**:
  - Static caching for settings and images
  - Conditional asset loading
  - Optimized database queries
  - Automatic cache clearing on category changes

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

The `[categories_grid]` shortcode supports the following attributes:

| Attribute | Type | Default | Description |
|-----------|------|---------|-------------|
| `category_id` | integer | Settings default | Parent category ID for subcategories |
| `type` | string | `subcategories` | Display type: `subcategories` or `top-level` |
| `exclude` | string | - | Comma-separated category IDs to exclude (e.g., `"10,20,30"`) |
| `show_images` | boolean | Settings default | Display category images (`true`/`false`) |
| `limit` | integer | Settings default | Maximum categories to display (0 = no limit) |
| `force_update` | boolean | `false` | Force cache refresh (`true`/`false`) |

### Examples

#### Display Subcategories with Limit

```php
[categories_grid category_id="5" limit="10"]
```

Displays up to 10 subcategories of category ID 5. Shows "View All" button if more categories exist.

#### Display Top-Level Categories

```php
[categories_grid type="top-level" limit="0"]
```

Displays all top-level categories without limit.

#### Exclude Categories and Hide Images

```php
[categories_grid category_id="5" exclude="10,20" show_images="false"]
```

Displays subcategories of category 5, excluding IDs 10 and 20, without images.

#### Force Cache Update

```php
[categories_grid category_id="5" force_update="true"]
```

Forces a cache refresh for the grid.

## ⚙️ Settings

Access plugin settings via **Settings → Categories Grid** in WordPress admin.

### General Settings

- **Default Category**: Default parent category for subcategories display
- **Exclude Categories**: Global category exclusion list (comma-separated IDs)
- **Cache Duration**: 
  - 1 Hour
  - 12 Hours
  - 1 Day (default)
  - 1 Week
  - No Caching
- **Default Category Limit**: Default number of categories to display (0 = unlimited)
- **View All URL**: URL for "View All" button on top-level categories

### Display Settings

- **Default Columns**: Grid columns (2-6, default: 6)
- **Image Border Radius**: Image corner radius (0-50px, default: 3px)
- **Hover Effect**: Enable/disable hover animations
- **Default Image**: Fallback image URL for categories without images
- **Show Images by Default**: Global image display toggle
- **Button Color**: "View All" button color (default: `#b93434`)

### Cache Management

- **Clear Cache**: Manual cache clearing button in settings
- **Auto-clear**: Cache automatically clears when:
  - Settings are saved
  - Categories are created/edited/deleted

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

#### Filters

- `scg_should_load_assets` - Control frontend asset loading

Example:
```php
add_filter('scg_should_load_assets', function($should_load) {
    // Custom logic to determine if assets should load
    return $should_load;
});
```

## 🔧 Technical Details

### Performance Optimizations

- **Static Caching**: Settings and images are cached statically to reduce database queries
- **Conditional Asset Loading**: CSS only loads when shortcode is present
- **Optimized Queries**: Efficient database queries with proper indexing
- **Lazy Loading**: Images use `loading="lazy"` and `decoding="async"` attributes
- **Cache Key Optimization**: Efficient cache key generation

### Image Handling

- Custom image size: `scg-thumb` (120x96px, hard crop)
- Automatic image size registration
- Fallback to default image if category image not found
- Image caching per request

### Cache System

- Uses WordPress transients API
- Automatic cache invalidation on category changes
- Configurable cache duration
- Efficient cache clearing (single query)

## 📁 File Structure

```
smart-categories-grid/
├── assets/
│   ├── admin.css          # Admin panel styles
│   ├── admin.js           # Admin panel JavaScript
│   └── front.css          # Frontend grid styles
├── languages/
│   └── sc-grid.pot        # Translation template
├── smart-categories-grid.php  # Main plugin file
└── README.md              # This file
```

## 🔒 Security

- All user inputs are sanitized and validated
- Proper escaping for all outputs
- Nonce verification for AJAX requests
- Capability checks for admin functions
- SQL injection prevention via prepared statements

## 🌍 Internationalization

The plugin is translation-ready and includes `.pot` file for translations. Text domain: `smart-cat-grid`

## ✅ Compatibility

- **WordPress**: 5.0+
- **PHP**: 7.4+
- **Themes**: Compatible with most WordPress themes
- **Caching Plugins**: Works with object cache plugins (Redis, Memcached, etc.)

## 🐛 Troubleshooting

### Images Not Displaying

1. Check if category has an image set in term meta with key `logo`
2. Regenerate thumbnails using "Regenerate Thumbnails" plugin
3. Verify default image URL in settings

### Cache Not Clearing

1. Use "Clear Cache" button in settings
2. Check if object cache plugin is interfering
3. Verify database permissions

### Grid Not Responsive

1. Clear browser cache
2. Verify CSS file is loading (check browser console)
3. Check for theme CSS conflicts

## 📝 Changelog

### Version 1.9
- Performance optimizations
- Improved caching system
- Enhanced security (escaping, sanitization)
- Code refactoring and optimization
- Removed unused files
- Updated documentation

## 🤝 Contributing

Contributions are welcome! Please follow these guidelines:

1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Test thoroughly
5. Submit a pull request

## 📄 License

This plugin is licensed under the [GNU General Public License v2.0](https://www.gnu.org/licenses/gpl-2.0.html).

## 💬 Support

- **GitHub Issues**: [Report bugs or request features](https://github.com/gemuzkm/smart-categories-grid/issues)
- **Documentation**: Check this README for usage examples

## 👤 Author

**TM**

- Website: [your-site.com](https://your-site.com)

---

⭐ If you find this plugin useful, please consider giving it a star on GitHub!
