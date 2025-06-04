# Smart Categories Grid

**Smart Categories Grid** is a WordPress plugin that displays categories in a responsive grid layout with caching, advanced settings, category exclusion, optional image display, and category limit capabilities. It offers flexibility in customizing the appearance and performance, making it an ideal solution for sites with a large number of categories.

## Features

- **Responsive Grid**: Automatically adjusts to different screen sizes.
- **Caching**: Improves performance by caching category data.
- **Customizable Columns**: Choose the number of columns to display categories.
- **Hover Effects**: Option to enable or disable hover effects on categories.
- **Customizable Image Radius**: Adjust the border radius of category images.
- **Default Image Support**: Set a default image for categories without images.
- **Display Options**: Choose to display subcategories or top-level categories.
- **Category Exclusion**: Exclude specific categories globally via settings or per shortcode.
- **Category Limit**: Limit the number of categories displayed, with a "View All" button if more categories are available.
- **Easy Integration**: Simple installation and configuration through the WordPress admin panel.

## Installation

1. **Download the plugin** from GitHub or install it via the WordPress admin panel.
2. **Activate the plugin** through the "Plugins" menu in WordPress.
3. **Configure the settings** under "Settings" -> "Categories Grid".
4. **Insert the shortcode** `[categories_grid]` on a page or post to display the category grid.

## Usage

The plugin provides a shortcode `[categories_grid]` that can be used to display the category grid. You can customize it with the following attributes:

- `category_id`: The ID of the parent category to display its subcategories (only used when `type="subcategories"`).
- `type`: The type of categories to display. Possible values are `'subcategories'` (default) or `'top-level'`.
- `exclude`: Comma-separated list of category IDs to exclude from the grid (e.g., `exclude="10,20"`).
- `show_images`: Whether to display category images (true/false). Defaults to the value set in the settings.
- `limit`: The maximum number of categories to display. If more categories are available, a "View All" button will be shown.
- `force_update`: Force cache update (true/false). Defaults to `false`.

**Examples:**

- Display subcategories of a specific category with a limit:
  ```php
  [categories_grid category_id="5" limit="10"]
  ```
  This will display up to 10 subcategories of the category with ID 5. If there are more than 10 subcategories, a "View All" button will be shown.

- Display top-level categories with no limit:
  ```php
  [categories_grid type="top-level" limit="0"]
  ```
  This will display all top-level categories without any limit.

- Exclude specific categories and hide images:
  ```php
  [categories_grid category_id="5" exclude="10,20" show_images="false"]
  ```
  This will display the subcategories of category 5, excluding categories with IDs 10 and 20, and without images.

- Force cache update:
  ```php
  [categories_grid category_id="5" force_update="true"]
  ```
  This will display the subcategories of category 5 and force a cache update.

## Settings

The plugin offers the following settings in the admin panel:

- **Default Category**: Select the default parent category to display its subcategories when using `[categories_grid]` without attributes.
- **Exclude Categories**: Enter a comma-separated list of category IDs to exclude from all grids (e.g., `10,20,30`).
- **Cache Duration**: Set the caching time for data (1 hour, 12 hours, 1 day, 1 week, or no caching).
- **Default Category Limit**: Set the default number of categories to display. 0 means no limit.
- **Default Columns**: Choose the number of columns in the grid (from 2 to 6).
- **Image Border Radius**: Set the border radius for category images (from 0 to 50 pixels).
- **Hover Effect**: Enable or disable hover effects on categories.
- **Default Image**: Set the URL of the default image for categories without images.
- **Show Images by Default**: If checked, images will be displayed in the grid unless overridden by the shortcode.

## Compatibility

The plugin is compatible with most WordPress themes and works with caching plugins like SQLite Object Cache, thanks to built-in support for cache clearing when settings are updated.

## License

This plugin is licensed under the [GNU General Public License v2.0](https://www.gnu.org/licenses/gpl-2.0.html).

## Contribution

If you would like to contribute to the development of the plugin, please create a pull request on GitHub. We welcome any improvements and bug fixes.

## Support

If you have any questions or issues with the plugin, please create an issue on [GitHub](https://github.com/gemuzkm/smart-categories-grid).