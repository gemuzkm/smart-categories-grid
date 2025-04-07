# Smart Categories Grid

**Smart Categories Grid** is a WordPress plugin that displays categories in a responsive grid layout with caching and advanced settings. It offers flexibility in customizing the appearance and performance, making it an ideal solution for sites with a large number of categories.

## Features

- **Responsive Grid**: Automatically adjusts to different screen sizes.
- **Caching**: Improves performance by caching category data.
- **Customizable Columns**: Choose the number of columns to display categories.
- **Hover Effects**: Option to enable or disable hover effects on categories.
- **Customizable Image Radius**: Adjust the border radius of category images.
- **Default Image Support**: Set a default image for categories without images.
- **Easy Integration**: Simple installation and configuration through the WordPress admin panel.

## Installation

1. **Download the plugin** from GitHub or install it via the WordPress admin panel.
2. **Activate the plugin** through the "Plugins" menu in WordPress.
3. **Configure the settings** under "Settings" -> "Categories Grid".
4. **Insert the shortcode** `[categories_grid]` on a page or post to display the category grid.

## Usage

The plugin provides a shortcode `[categories_grid]` that can be used to display the category grid. You can customize it with the following attributes:

- `category_id`: The ID of the parent category to display its subcategories. Defaults to the value set in the settings.
- `force_update`: Force cache update (true/false). Defaults to `false`.

**Example:**

```php
[categories_grid category_id="5" force_update="true"]
```
This will display the subcategories of the category with ID 5 and force a cache update.

## Settings
The plugin offers the following settings in the admin panel:

- **Default Category**: Select the default parent category to display its subcategories.
- **Cache Duration**: Set the caching time for data (1 hour, 12 hours, 1 day, 1 week, or no caching).
- **Default Columns**: Choose the number of columns in the grid (from 2 to 6).
- **Image Border Radius**: Set the border radius for category images (from 0 to 50 pixels).
- **Hover Effect**: Enable or disable hover effects on categories.
- **Default Image**: Set the URL of the default image for categories without images.

## Compatibility
The plugin is compatible with most WordPress themes and works with caching plugins like SQLite Object Cache, thanks to built-in support for cache clearing when settings are updated.

## License
This plugin is licensed under the [GNU General Public License v2.0](https://www.gnu.org/licenses/gpl-2.0.html).

## Contribution
If you would like to contribute to the development of the plugin, please create a pull request on GitHub. We welcome any improvements and bug fixes.

## Support
If you have any questions or issues with the plugin, please create an issue on [GitHub](https://github.com/gemuzkm/smart-categories-grid).
