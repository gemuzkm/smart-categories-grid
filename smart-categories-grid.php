<?php
/*
Plugin Name: Smart Categories Grid
Plugin URI: https://github.com/gemuzkm/smart-categories-grid
Description: Responsive category grid with caching, advanced settings, category exclusion, optional image display, and category limit
Version: 2.2.0
Author: TM
Author URI: https://github.com/gemuzkm
Text Domain: smart-cat-grid
Domain Path: /languages
Requires at least: 6.3
Tested up to: 7.1
Requires PHP: 7.4
License: GPL v2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Update URI: https://github.com/gemuzkm/smart-categories-grid
*/

defined('ABSPATH') || exit;

register_activation_hook(__FILE__, ['SmartCategoriesGrid', 'onActivationStatic']);

class SmartCategoriesGrid {
    private const CACHE_PREFIX       = 'scg_cache_';
    private const CACHE_GEN_OPTION   = 'scg_cache_gen';
    private const WIDGET_CACHE_KEY   = 'scg_widget_has_shortcode';
    private const MIN_COLUMNS        = 2;
    private const MAX_COLUMNS        = 6;
    private const IMAGE_SIZE_NAME    = 'scg-thumb';
    private const IMAGE_SIZE_2X_NAME = 'scg-thumb-2x';
    private const IMAGE_W            = 120;
    private const IMAGE_H            = 96;
    private const IMAGE_META_KEY     = 'logo';
    private const VERSION            = '2.2.0';

    private array $settings = [];
    private array $image_cache    = [];
    private array $asset_versions = [];
    private string $placeholder_url = '';
    private ?int $cache_gen = null;

    private static ?self $instance      = null;
    private static bool $shortcode_used = false;

    public static function getInstance(): self {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('plugins_loaded',    [$this, 'init']);
        add_action('init',              [$this, 'loadTextdomain']);
        add_action('after_setup_theme', [$this, 'addImageSizes']);
    }

    public function init(): void {
        $this->loadSettings();
        $this->computeAssetVersions();
        $this->computePlaceholderUrl();
        $this->registerHooks();
    }

    public function loadTextdomain(): void {
        load_plugin_textdomain('smart-cat-grid', false, dirname(plugin_basename(__FILE__)) . '/languages');
    }

    private function computeAssetVersions(): void {
        $base = plugin_dir_path(__FILE__) . 'assets/';
        foreach (['front.css', 'front.min.css', 'admin.css', 'admin.js'] as $file) {
            $path = $base . $file;
            $this->asset_versions[$file] = file_exists($path) ? (string) filemtime($path) : '';
        }
    }

    // Resolved once per request instead of a file_exists() per category.
    private function computePlaceholderUrl(): void {
        $url = '';
        if (file_exists(plugin_dir_path(__FILE__) . 'assets/placeholder.png')) {
            $url = plugins_url('assets/placeholder.png', __FILE__);
        }
        /**
         * Filter the fallback image URL used when a category has no image.
         *
         * @param string $url Placeholder image URL ('' disables the fallback).
         */
        $this->placeholder_url = (string) apply_filters('scg_placeholder_image', $url);
    }

    public function addImageSizes(): void {
        add_image_size(self::IMAGE_SIZE_NAME,    self::IMAGE_W,     self::IMAGE_H,     true);
        add_image_size(self::IMAGE_SIZE_2X_NAME, self::IMAGE_W * 2, self::IMAGE_H * 2, true);
        add_filter('image_size_names_choose', [$this, 'addImageSizeNames']);
    }

    public function addImageSizeNames(array $sizes): array {
        return array_merge($sizes, [
            self::IMAGE_SIZE_NAME => __('Category Grid (120x96)', 'smart-cat-grid'),
        ]);
    }

    private function loadSettings(): void {
        $settings       = get_option('scg_settings', []);
        $this->settings = is_array($settings) ? $settings : [];
    }

    private function registerHooks(): void {
        add_shortcode('categories_grid', [$this, 'renderGrid']);
        add_action('admin_menu',              [$this, 'addAdminMenu']);
        add_action('admin_init',              [$this, 'registerSettings']);
        add_action('admin_enqueue_scripts',   [$this, 'adminAssets']);
        add_action('wp',                      [$this, 'preCheckShortcode']);
        add_action('wp_enqueue_scripts',      [$this, 'frontendAssets']);
        add_action('wp_ajax_scg_clear_cache', [$this, 'ajaxClearCache']);

        // Category structure changes.
        add_action('created_category', [$this, 'clearAllCache']);
        add_action('edited_category',  [$this, 'clearAllCache']);
        add_action('delete_category',  [$this, 'clearAllCache']);

        // Category image (term meta "logo") changes without saving the term itself.
        add_action('added_term_meta',   [$this, 'onTermMetaChange'], 10, 3);
        add_action('updated_term_meta', [$this, 'onTermMetaChange'], 10, 3);
        add_action('deleted_term_meta', [$this, 'onTermMetaChange'], 10, 3);

        // Widget content changed: re-scan for the shortcode on next request.
        add_action('update_option_widget_text',  [$this, 'clearWidgetCache']);
        add_action('update_option_widget_block', [$this, 'clearWidgetCache']);
    }

    public static function onActivationStatic(): void {
        add_image_size(self::IMAGE_SIZE_NAME,    self::IMAGE_W,     self::IMAGE_H,     true);
        add_image_size(self::IMAGE_SIZE_2X_NAME, self::IMAGE_W * 2, self::IMAGE_H * 2, true);
        add_option('scg_show_regenerate_notice', true);
        add_option(self::CACHE_GEN_OPTION, 1, '', true);
        delete_transient(self::WIDGET_CACHE_KEY);
    }

    public function onTermMetaChange($meta_id, $object_id, $meta_key): void {
        if ($meta_key === self::IMAGE_META_KEY) {
            $this->clearAllCache();
        }
    }

    public function clearWidgetCache(): void {
        delete_transient(self::WIDGET_CACHE_KEY);
    }

    public function renderGrid($atts): string {
        self::$shortcode_used = true;

        if (!wp_style_is('scg-front', 'enqueued') && !wp_style_is('scg-front', 'done')) {
            $this->enqueueStyles();
        }

        $atts = shortcode_atts([
            'category_id'  => '',
            'type'         => 'subcategories',
            'auto'         => 'false',
            'exclude'      => '',
            'show_images'  => '',
            'limit'        => '',
            'columns'      => '',
            'style'        => '',
            'hover_effect' => '',
            'image_radius' => '',
            'button_color' => '',
            'force_update' => 'false',
        ], is_array($atts) ? $atts : []);

        $auto_mode = filter_var($atts['auto'], FILTER_VALIDATE_BOOLEAN);

        if ($auto_mode) {
            $current_category = $this->getCurrentCategory();
            if ($current_category <= 0) return '';
            $parent = $current_category;
        } else {
            if ($atts['type'] === 'top-level') {
                $parent = 0;
            } else {
                $parent = !empty($atts['category_id'])
                    ? absint($atts['category_id'])
                    : (int) ($this->settings['default_category'] ?? 0);
                if ($parent === 0) return '';
            }
        }

        $exclude_ids  = $this->parseExcludeIds($atts['exclude']);
        $show_images  = $this->getShortcodeSetting('show_images',  $atts, !empty($this->settings['default_show_images']), 'bool');
        $limit        = $this->getShortcodeSetting('limit',        $atts, (int) ($this->settings['default_limit'] ?? 0), 'int');
        $columns      = $this->getShortcodeSetting('columns',      $atts, (int) ($this->settings['columns'] ?? self::MAX_COLUMNS), 'int');
        $style        = $this->getShortcodeSetting('style',        $atts, $this->settings['grid_style'] ?? 'classic');
        $hover_effect = $this->getShortcodeSetting('hover_effect', $atts, !empty($this->settings['hover_effect']), 'bool');
        $image_radius = $this->getShortcodeSetting('image_radius', $atts, (int) ($this->settings['image_radius'] ?? 3), 'int');
        $button_color = $this->getShortcodeSetting('button_color', $atts, $this->settings['button_color'] ?? '#b93434', 'color');

        $force_update = filter_var($atts['force_update'], FILTER_VALIDATE_BOOLEAN);
        $columns      = max(self::MIN_COLUMNS, min(self::MAX_COLUMNS, $columns));

        $grid_settings = [
            'columns'      => $columns,
            'image_radius' => $image_radius,
            'hover_effect' => $hover_effect,
            'style'        => $style,
            'button_color' => $button_color,
        ];

        if ($force_update) {
            return $this->generateGrid($parent, $exclude_ids, $show_images, $limit, $grid_settings)['html'];
        }

        return $this->getCachedGrid($parent, $exclude_ids, $show_images, $limit, $grid_settings);
    }

    private function getCurrentCategory(): int {
        static $cached_category = null;
        if ($cached_category !== null) return $cached_category;

        if (is_category()) {
            $c = get_queried_object();
            if ($c && isset($c->term_id)) return $cached_category = (int) $c->term_id;
        }

        $q = get_queried_object();
        if ($q && isset($q->taxonomy) && $q->taxonomy === 'category') {
            return $cached_category = (int) $q->term_id;
        }

        if (is_single()) {
            $post_id = get_the_ID();
            if ($post_id) {
                $primary = (int) get_post_meta($post_id, '_yoast_wpseo_primary_category', true);
                if (!$primary) $primary = (int) get_post_meta($post_id, 'rank_math_primary_category', true);
                if ($primary > 0) return $cached_category = $primary;

                $cats = get_the_category($post_id);
                if (!empty($cats)) {
                    return $cached_category = (int) $this->deepestCategory($cats)->term_id;
                }
            }
        }

        global $post;
        if (is_a($post, 'WP_Post')) {
            $cats = get_the_category($post->ID);
            if (!empty($cats)) {
                return $cached_category = (int) $this->deepestCategory($cats)->term_id;
            }
        }

        global $wp_query;
        if (!empty($wp_query->query_vars['cat'])) {
            $cat_id = absint($wp_query->query_vars['cat']);
            if ($cat_id > 0) return $cached_category = $cat_id;
        }
        if (!empty($wp_query->query_vars['category_name'])) {
            $c = get_category_by_slug($wp_query->query_vars['category_name']);
            if ($c && isset($c->term_id)) return $cached_category = (int) $c->term_id;
        }

        return $cached_category = 0;
    }

    /**
     * @param WP_Term[] $cats
     */
    private function deepestCategory(array $cats): WP_Term {
        $depths = [];
        foreach ($cats as $cat) {
            $depths[$cat->term_id] = count(get_ancestors($cat->term_id, 'category'));
        }
        usort($cats, function (WP_Term $a, WP_Term $b) use ($depths): int {
            return $depths[$b->term_id] - $depths[$a->term_id];
        });
        return $cats[0];
    }

    /**
     * Locale-aware, case-insensitive comparison. strcasecmp() only folds ASCII,
     * so Cyrillic/accented names would sort by byte value (all uppercase first).
     */
    private function compareNames(string $a, string $b): int {
        static $collator = null;
        if ($collator === null) {
            $collator = class_exists('Collator') ? new Collator(get_locale()) : false;
        }
        if ($collator instanceof Collator) {
            $result = $collator->compare($a, $b);
            if ($result !== false) return (int) $result;
        }
        if (function_exists('mb_strtolower')) {
            return strcmp(mb_strtolower($a, 'UTF-8'), mb_strtolower($b, 'UTF-8'));
        }
        return strcasecmp($a, $b);
    }

    private function getShortcodeSetting(string $key, array $atts, $default, string $type = 'string') {
        if (!isset($atts[$key]) || $atts[$key] === '') return $default;
        $value = $atts[$key];
        switch ($type) {
            case 'int':   return absint($value);
            case 'bool':  return filter_var($value, FILTER_VALIDATE_BOOLEAN);
            case 'color': return sanitize_hex_color($value) ?: $default;
            default:      return sanitize_text_field($value);
        }
    }

    private function parseExcludeIds(string $exclude): array {
        $local = [];
        if (!empty($exclude)) {
            $local = array_unique(array_map('absint', array_filter(explode(',', $exclude))));
        }
        $global = !empty($this->settings['exclude_categories'])
            ? array_map('absint', array_filter(explode(',', $this->settings['exclude_categories'])))
            : [];
        $merged = array_unique(array_filter(array_merge($local, $global)));
        sort($merged);
        return array_values($merged);
    }

    /**
     * Cache generation number. Bumping it invalidates every cached grid in O(1)
     * and works identically with the options table and persistent object caches.
     */
    private function getCacheGeneration(): int {
        if ($this->cache_gen === null) {
            $this->cache_gen = max(1, (int) get_option(self::CACHE_GEN_OPTION, 1));
        }
        return $this->cache_gen;
    }

    private function getCachedGrid(int $parent, array $exclude_ids, bool $show_images, int $limit, array $grid_settings): string {
        $key_parts = [
            $parent,
            implode(',', $exclude_ids) ?: '0',
            $show_images ? '1' : '0',
            $limit,
            $grid_settings['style'],
            $grid_settings['columns'],
            $grid_settings['image_radius'],
            $grid_settings['hover_effect'] ? '1' : '0',
            $grid_settings['button_color'],
            self::VERSION,
            $this->getCacheGeneration(),
        ];
        $cacheKey = self::CACHE_PREFIX . md5(implode('|', $key_parts));

        $cached = get_transient($cacheKey);
        if (is_array($cached) && isset($cached['html'])) {
            // Keep WordPress' per-page media counter accurate so lazy-loading
            // decisions for images rendered after this grid stay correct.
            if (!empty($cached['media']) && function_exists('wp_increase_content_media_count')) {
                wp_increase_content_media_count((int) $cached['media']);
            }
            return (string) $cached['html'];
        }

        $result    = $this->generateGrid($parent, $exclude_ids, $show_images, $limit, $grid_settings);
        $cacheTime = (int) ($this->settings['cache_time'] ?? DAY_IN_SECONDS);
        if ($cacheTime > 0) {
            set_transient($cacheKey, $result, $cacheTime);
        }
        return $result['html'];
    }

    /**
     * @return array{html:string, media:int}
     */
    private function generateGrid(int $parent, array $exclude_ids, bool $show_images, int $limit, array $grid_settings): array {
        $display_images = ($grid_settings['style'] === 'text') ? false : $show_images;

        $args = [
            'taxonomy'               => 'category',
            'parent'                 => $parent,
            'hide_empty'             => false,
            'orderby'                => 'name',
            'order'                  => 'ASC',
            'hierarchical'           => false,
            // Prime term meta in ONE query only when we will actually read it.
            'update_term_meta_cache' => $display_images,
        ];
        if (!empty($exclude_ids)) {
            $args['exclude'] = $exclude_ids;
        }

        $categories = get_terms($args);
        if (empty($categories) || is_wp_error($categories)) {
            return ['html' => '', 'media' => 0];
        }

        usort($categories, function (WP_Term $a, WP_Term $b): int {
            return $this->compareNames($a->name, $b->name);
        });

        $total = count($categories);
        if ($limit > 0 && $total > $limit) {
            $categories = array_slice($categories, 0, $limit);
        }

        // Parent term is needed for hierarchical permalinks and the "View All" link.
        if ($parent > 0 && function_exists('_prime_term_caches')) {
            _prime_term_caches([$parent]);
        }

        // Collect attachment IDs and prime their post + meta caches in one query
        // instead of one query per image inside wp_get_attachment_image_url().
        if ($display_images) {
            $attachment_ids = [];
            foreach ($categories as $cat) {
                $image_id = get_term_meta($cat->term_id, self::IMAGE_META_KEY, true);
                if ($image_id && is_numeric($image_id)) {
                    $attachment_ids[] = (int) $image_id;
                }
            }
            if (!empty($attachment_ids) && function_exists('_prime_post_caches')) {
                _prime_post_caches(array_unique($attachment_ids), false, true);
            }
        }

        $hover_class  = $grid_settings['hover_effect'] ? ' has-hover' : '';
        $style_class  = ' scg-style-' . sanitize_html_class($grid_settings['style']);
        $columns      = absint($grid_settings['columns']);
        $image_radius = absint($grid_settings['image_radius']);
        $button_color = sanitize_hex_color($grid_settings['button_color']) ?: '#b93434';
        $media_count  = 0;

        ob_start();
        ?>
        <div class="scg-grid<?php echo esc_attr($hover_class . $style_class); ?>"
             style="--scg-columns: <?php echo esc_attr($columns); ?>;
                    --scg-image-radius: <?php echo esc_attr($image_radius); ?>px;
                    --scg-button-color: <?php echo esc_attr($button_color); ?>;">
            <?php foreach ($categories as $cat) :
                $term_link = get_term_link($cat);
                if (is_wp_error($term_link)) continue;
                $image = $display_images ? $this->getCategoryImage($cat->term_id) : null;
            ?>
                <div class="scg-col">
                    <div class="scg-card<?php echo esc_attr($hover_class); ?>">
                        <?php if ($image) :
                            $media_count++;
                            echo '<div class="scg-image">' . $this->buildImgTag($image, $cat->name) . '</div>';
                        endif; ?>
                        <div class="scg-title">
                            <a href="<?php echo esc_url($term_link); ?>"><?php echo esc_html($cat->name); ?></a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
            <?php if ($limit > 0 && $total > $limit) :
                $view_all_url = '';
                if ($parent > 0) {
                    $u = get_term_link($parent, 'category');
                    if (!is_wp_error($u)) $view_all_url = $u;
                } else {
                    $view_all_url = $this->settings['view_all_url'] ?? '';
                }
                if (!empty($view_all_url)) : ?>
                    <div class="scg-view-all">
                        <a href="<?php echo esc_url($view_all_url); ?>" class="scg-view-all-link">
                            <?php esc_html_e('View All', 'smart-cat-grid'); ?>
                        </a>
                    </div>
                <?php endif;
            endif; ?>
        </div>
        <?php
        return ['html' => (string) ob_get_clean(), 'media' => $media_count];
    }

    /**
     * Build an <img> tag, letting core decide loading / fetchpriority / decoding
     * based on its per-page media counter (wp_get_loading_optimization_attributes).
     *
     * @param array{src:string, srcset:string} $image
     */
    private function buildImgTag(array $image, string $alt): string {
        $attr = [
            'src'    => $image['src'],
            'alt'    => $alt,
            'width'  => (string) self::IMAGE_W,
            'height' => (string) self::IMAGE_H,
        ];
        if (!empty($image['srcset'])) {
            $attr['srcset'] = $image['srcset'];
        }

        if (function_exists('wp_get_loading_optimization_attributes')) {
            $attr = array_merge($attr, wp_get_loading_optimization_attributes('img', $attr, 'scg_grid'));
        } else {
            $attr['loading']  = 'lazy';
            $attr['decoding'] = 'async';
        }
        if (empty($attr['decoding'])) {
            $attr['decoding'] = 'async';
        }

        $html = '<img';
        foreach ($attr as $name => $value) {
            if ($value === '' || $value === null || $value === false) continue;
            $escaped = ($name === 'src') ? esc_url($value) : esc_attr($value);
            $html   .= ' ' . $name . '="' . $escaped . '"';
        }
        return $html . '>';
    }

    /**
     * @return array{src:string, srcset:string}|null
     */
    private function getCategoryImage(int $term_id): ?array {
        if (array_key_exists($term_id, $this->image_cache)) {
            return $this->image_cache[$term_id];
        }

        $result   = null;
        $image_id = get_term_meta($term_id, self::IMAGE_META_KEY, true);

        if ($image_id && is_numeric($image_id)) {
            $image_id = (int) $image_id;
            $src      = wp_get_attachment_image_url($image_id, self::IMAGE_SIZE_NAME);
            if ($src) {
                $srcset = '';
                $src2x  = wp_get_attachment_image_src($image_id, self::IMAGE_SIZE_2X_NAME);
                // Only use the 2x candidate when a real cropped intermediate exists,
                // otherwise WordPress falls back to the full-size original.
                if (is_array($src2x) && !empty($src2x[3]) && (int) $src2x[1] === self::IMAGE_W * 2) {
                    $srcset = esc_url($src) . ' 1x, ' . esc_url($src2x[0]) . ' 2x';
                }
                $result = ['src' => $src, 'srcset' => $srcset];
            }
        }

        if ($result === null) {
            $default = $this->settings['default_image'] ?? '';
            if (!$default) {
                $default = $this->placeholder_url;
            }
            if ($default) {
                $result = ['src' => $default, 'srcset' => ''];
            }
        }

        return $this->image_cache[$term_id] = $result;
    }

    public function addAdminMenu(): void {
        add_options_page(
            __('Categories Grid Settings', 'smart-cat-grid'),
            __('Categories Grid', 'smart-cat-grid'),
            'manage_options',
            'scg-settings',
            [$this, 'settingsPage']
        );
    }

    public function settingsPage(): void { ?>
        <div class="wrap scg-settings-wrap">
            <h1><?php esc_html_e('Categories Grid Settings', 'smart-cat-grid'); ?></h1>

            <?php if (get_option('scg_show_regenerate_notice')) : ?>
                <div class="notice notice-info is-dismissible">
                    <p><?php esc_html_e('Plugin activated! For best image quality, please regenerate thumbnails using a plugin like "Regenerate Thumbnails".', 'smart-cat-grid'); ?></p>
                </div>
                <?php delete_option('scg_show_regenerate_notice'); ?>
            <?php endif; ?>

            <form method="post" action="options.php">
                <?php
                settings_fields('scg_settings_group');
                do_settings_sections('scg-settings');
                submit_button(__('Save Changes', 'smart-cat-grid'));
                ?>
            </form>

            <div class="scg-settings-section">
                <h3><?php esc_html_e('Image Size Information', 'smart-cat-grid'); ?></h3>
                <p><?php esc_html_e('This plugin registers two image sizes: 120x96 (1x) and 240x192 (2x for Retina displays). New uploads are resized automatically.', 'smart-cat-grid'); ?></p>
                <p><?php esc_html_e('For existing images, regenerate thumbnails using a plugin like "Regenerate Thumbnails".', 'smart-cat-grid'); ?></p>
            </div>

            <div class="scg-settings-section">
                <h3><?php esc_html_e('Usage', 'smart-cat-grid'); ?></h3>
                <p><?php esc_html_e('Shortcodes: [categories_grid type="top-level"], [categories_grid category_id="X"], [categories_grid auto="true" limit="200"]. Use exclude="X,Y", show_images="false", limit="N" as needed.', 'smart-cat-grid'); ?></p>
            </div>
        </div>
    <?php }

    public function registerSettings(): void {
        register_setting('scg_settings_group', 'scg_settings', [
            'type'              => 'array',
            'sanitize_callback' => [$this, 'validateSettings'],
            'default'           => [],
        ]);

        add_settings_section('scg_general_section',  __('General Settings', 'smart-cat-grid'),  '__return_null', 'scg-settings');
        add_settings_field('default_category',   __('Default Category', 'smart-cat-grid'),       [$this, 'categorySelectField'],    'scg-settings', 'scg_general_section');
        add_settings_field('exclude_categories', __('Exclude Categories', 'smart-cat-grid'),     [$this, 'excludeCategoriesField'], 'scg-settings', 'scg_general_section');
        add_settings_field('cache_time',         __('Cache Duration', 'smart-cat-grid'),         [$this, 'cacheTimeField'],         'scg-settings', 'scg_general_section');
        add_settings_field('default_limit',      __('Default Category Limit', 'smart-cat-grid'), [$this, 'defaultLimitField'],      'scg-settings', 'scg_general_section');
        add_settings_field('view_all_url',       __('View All URL', 'smart-cat-grid'),           [$this, 'viewAllUrlField'],        'scg-settings', 'scg_general_section');

        add_settings_section('scg_display_section', __('Display Settings', 'smart-cat-grid'), '__return_null', 'scg-settings');
        add_settings_field('columns',             __('Default Columns', 'smart-cat-grid'),        [$this, 'columnsField'],           'scg-settings', 'scg_display_section');
        add_settings_field('image_radius',        __('Image Border Radius', 'smart-cat-grid'),    [$this, 'imageRadiusField'],       'scg-settings', 'scg_display_section');
        add_settings_field('hover_effect',        __('Hover Effect', 'smart-cat-grid'),           [$this, 'hoverEffectField'],       'scg-settings', 'scg_display_section');
        add_settings_field('default_image',       __('Default Image', 'smart-cat-grid'),          [$this, 'defaultImageField'],      'scg-settings', 'scg_display_section');
        add_settings_field('default_show_images', __('Show Images by Default', 'smart-cat-grid'), [$this, 'defaultShowImagesField'], 'scg-settings', 'scg_display_section');
        add_settings_field('button_color',        __('Button Color', 'smart-cat-grid'),           [$this, 'buttonColorField'],       'scg-settings', 'scg_display_section');
        add_settings_field('grid_style',          __('Grid Style', 'smart-cat-grid'),             [$this, 'gridStyleField'],         'scg-settings', 'scg_display_section');
    }

    public function categorySelectField(): void {
        wp_dropdown_categories([
            'show_option_none'  => __('Select a category', 'smart-cat-grid'),
            'option_none_value' => 0,
            'name'              => 'scg_settings[default_category]',
            'selected'          => (int) ($this->settings['default_category'] ?? 0),
            'hierarchical'      => true,
            'hide_empty'        => false,
        ]);
    }

    public function excludeCategoriesField(): void {
        $value = $this->settings['exclude_categories'] ?? '';
        printf('<input type="text" name="scg_settings[exclude_categories]" value="%s" class="regular-text">', esc_attr($value));
        echo '<p class="description">' . esc_html__('Comma-separated category IDs to exclude (e.g., 10,20,30).', 'smart-cat-grid') . '</p>';
    }

    public function cacheTimeField(): void {
        $value   = (int) ($this->settings['cache_time'] ?? DAY_IN_SECONDS);
        $options = [
            HOUR_IN_SECONDS      => __('1 Hour', 'smart-cat-grid'),
            12 * HOUR_IN_SECONDS => __('12 Hours', 'smart-cat-grid'),
            DAY_IN_SECONDS       => __('1 Day', 'smart-cat-grid'),
            WEEK_IN_SECONDS      => __('1 Week', 'smart-cat-grid'),
            0                    => __('No Caching', 'smart-cat-grid'),
        ];
        echo '<select name="scg_settings[cache_time]">';
        foreach ($options as $k => $label) {
            printf('<option value="%d"%s>%s</option>', (int) $k, selected($value, $k, false), esc_html($label));
        }
        echo '</select> ';
        echo '<button type="button" id="scg-clear-cache" class="button">' . esc_html__('Clear Cache', 'smart-cat-grid') . '</button>';
    }

    public function columnsField(): void {
        $value = (int) ($this->settings['columns'] ?? self::MAX_COLUMNS);
        echo '<select name="scg_settings[columns]">';
        for ($i = self::MIN_COLUMNS; $i <= self::MAX_COLUMNS; $i++) {
            printf('<option value="%d"%s>%d %s</option>', $i, selected($value, $i, false), $i, esc_html__('Columns', 'smart-cat-grid'));
        }
        echo '</select>';
    }

    public function imageRadiusField(): void {
        $value = (int) ($this->settings['image_radius'] ?? 3);
        printf('<input type="number" name="scg_settings[image_radius]" min="0" max="50" value="%d"> px', $value);
    }

    public function hoverEffectField(): void {
        printf(
            '<label><input type="checkbox" name="scg_settings[hover_effect]" value="1"%s> %s</label>',
            checked(!empty($this->settings['hover_effect']), true, false),
            esc_html__('Enable hover effects', 'smart-cat-grid')
        );
    }

    public function defaultImageField(): void {
        $value = $this->settings['default_image'] ?? '';
        printf('<input type="url" name="scg_settings[default_image]" value="%s" class="regular-text"> ', esc_url($value));
        echo '<button type="button" class="button scg-upload-image">' . esc_html__('Select Image', 'smart-cat-grid') . '</button>';
    }

    public function defaultShowImagesField(): void {
        printf(
            '<label><input type="checkbox" name="scg_settings[default_show_images]" value="1"%s> %s</label>',
            checked(!empty($this->settings['default_show_images']), true, false),
            esc_html__('Show images by default', 'smart-cat-grid')
        );
        echo '<p class="description">' . esc_html__('If checked, images will be shown unless overridden by the shortcode.', 'smart-cat-grid') . '</p>';
    }

    public function defaultLimitField(): void {
        $value = (int) ($this->settings['default_limit'] ?? 0);
        printf('<input type="number" name="scg_settings[default_limit]" min="0" value="%d">', $value);
        echo '<p class="description">' . esc_html__('Default number of categories to display. 0 = no limit.', 'smart-cat-grid') . '</p>';
    }

    public function viewAllUrlField(): void {
        $value = $this->settings['view_all_url'] ?? '';
        printf('<input type="url" name="scg_settings[view_all_url]" value="%s" class="regular-text">', esc_url($value));
        echo '<p class="description">' . esc_html__('URL for the "View All" button for top-level categories. Leave empty to hide.', 'smart-cat-grid') . '</p>';
    }

    public function buttonColorField(): void {
        $value = $this->settings['button_color'] ?? '#b93434';
        printf('<input type="text" name="scg_settings[button_color]" value="%s" class="scg-color-picker">', esc_attr($value));
        echo '<p class="description">' . esc_html__('Color for the "View All" button.', 'smart-cat-grid') . '</p>';
    }

    public function gridStyleField(): void {
        $value  = $this->settings['grid_style'] ?? 'classic';
        $styles = [
            'classic' => __('Classic', 'smart-cat-grid'),
            'modern'  => __('Modern', 'smart-cat-grid'),
            'minimal' => __('Minimal', 'smart-cat-grid'),
            'card'    => __('Card', 'smart-cat-grid'),
            'text'    => __('Text Only', 'smart-cat-grid'),
        ];
        echo '<select name="scg_settings[grid_style]">';
        foreach ($styles as $k => $label) {
            printf('<option value="%s"%s>%s</option>', esc_attr($k), selected($value, $k, false), esc_html($label));
        }
        echo '</select>';
        echo '<p class="description">' . esc_html__('Visual style for the category grid.', 'smart-cat-grid') . '</p>';
    }

    public function validateSettings($input): array {
        $input  = is_array($input) ? $input : [];
        $output = [];

        $output['default_category'] = absint($input['default_category'] ?? 0);

        $exclude = trim(sanitize_text_field($input['exclude_categories'] ?? ''));
        $ids     = $exclude ? array_unique(array_filter(array_map('absint', explode(',', $exclude)))) : [];
        $output['exclude_categories'] = implode(',', $ids);

        $output['cache_time'] = absint($input['cache_time'] ?? DAY_IN_SECONDS);

        $columns = absint($input['columns'] ?? self::MAX_COLUMNS);
        $output['columns'] = max(self::MIN_COLUMNS, min(self::MAX_COLUMNS, $columns));

        $output['image_radius']        = max(0, min(50, absint($input['image_radius'] ?? 3)));
        $output['default_image']       = esc_url_raw($input['default_image'] ?? '');
        $output['hover_effect']        = !empty($input['hover_effect']) ? 1 : 0;
        $output['default_show_images'] = !empty($input['default_show_images']) ? 1 : 0;
        $output['default_limit']       = max(0, absint($input['default_limit'] ?? 0));
        $output['view_all_url']        = esc_url_raw($input['view_all_url'] ?? '');
        $output['button_color']        = sanitize_hex_color($input['button_color'] ?? '#b93434') ?: '#b93434';

        $valid_styles = ['classic', 'modern', 'minimal', 'card', 'text'];
        $style        = $input['grid_style'] ?? 'classic';
        $output['grid_style'] = in_array($style, $valid_styles, true) ? $style : 'classic';

        $this->settings = $output;
        $this->clearWidgetCache();
        $this->clearAllCache();

        return $output;
    }

    /**
     * Invalidate all cached grids by bumping the cache generation.
     * No LIKE-DELETE on wp_options, and it works with Redis/Memcached where
     * transients never touch the database.
     */
    public function clearAllCache(): void {
        $gen = $this->getCacheGeneration() + 1;
        update_option(self::CACHE_GEN_OPTION, $gen, true);
        $this->cache_gen   = $gen;
        $this->image_cache = [];
    }

    public function adminAssets(string $hook): void {
        if ('settings_page_scg-settings' !== $hook) return;

        wp_enqueue_media();
        wp_enqueue_style('wp-color-picker');
        wp_enqueue_script('wp-color-picker');
        wp_add_inline_script('wp-color-picker', 'jQuery(function($){ $(".scg-color-picker").wpColorPicker(); });');

        wp_enqueue_style('scg-admin',
            plugins_url('assets/admin.css', __FILE__), [],
            $this->assetVersion('admin.css'));

        wp_enqueue_script('scg-admin',
            plugins_url('assets/admin.js', __FILE__),
            ['jquery', 'wp-i18n'],
            $this->assetVersion('admin.js'),
            true);

        wp_localize_script('scg-admin', 'scg_admin', [
            'nonce' => wp_create_nonce('scg-clear-cache'),
            'i18n'  => [
                'clear_confirm'  => __('Are you sure?', 'smart-cat-grid'),
                'clearing'       => __('Clearing...', 'smart-cat-grid'),
                'clear_cache'    => __('Clear Cache', 'smart-cat-grid'),
                'upload_title'   => __('Select Image', 'smart-cat-grid'),
                'use_image'      => __('Use This Image', 'smart-cat-grid'),
                'settings_saved' => __('Settings saved successfully!', 'smart-cat-grid'),
                'clear_failed'   => __('Failed to clear cache', 'smart-cat-grid'),
            ],
        ]);
    }

    private function assetVersion(string $file): string {
        return $this->asset_versions[$file] ?: self::VERSION;
    }

    public function preCheckShortcode(): void {
        if (self::$shortcode_used) return;

        global $post;
        $found = false;

        if (is_a($post, 'WP_Post') && has_shortcode($post->post_content, 'categories_grid')) {
            $found = true;
        }

        if (!$found && is_a($post, 'WP_Post') && has_blocks($post->post_content)
            && strpos($post->post_content, 'categories_grid') !== false) {
            $found = true;
        }

        if (!$found && is_a($post, 'WP_Post')) {
            $el = get_post_meta($post->ID, '_elementor_data', true);
            if (is_string($el) && strpos($el, 'categories_grid') !== false) {
                $found = true;
            }
        }

        if (!$found) {
            $widget_cached = get_transient(self::WIDGET_CACHE_KEY);
            if ($widget_cached === false) {
                $widget_has = $this->widgetHasShortcode();
                set_transient(self::WIDGET_CACHE_KEY, $widget_has ? '1' : '0', WEEK_IN_SECONDS);
                $found = $widget_has;
            } elseif ($widget_cached === '1') {
                $found = true;
            }
        }

        $found = apply_filters('scg_has_shortcode', $found);
        if ($found) self::$shortcode_used = true;
    }

    private function widgetHasShortcode(): bool {
        $widget_text = get_option('widget_text');
        if (is_array($widget_text)) {
            foreach ($widget_text as $w) {
                if (is_array($w) && isset($w['text']) && has_shortcode($w['text'], 'categories_grid')) return true;
            }
        }
        $block_widgets = get_option('widget_block');
        if (is_array($block_widgets)) {
            foreach ($block_widgets as $w) {
                if (is_array($w) && isset($w['content']) && strpos($w['content'], 'categories_grid') !== false) return true;
            }
        }
        return false;
    }

    public function frontendAssets(): void {
        if (self::$shortcode_used) $this->enqueueStyles();
    }

    private function enqueueStyles(): void {
        static $done = false;
        if ($done) return;

        $use_min = !(defined('SCRIPT_DEBUG') && SCRIPT_DEBUG) && $this->asset_versions['front.min.css'] !== '';
        $file    = $use_min ? 'front.min.css' : 'front.css';

        wp_enqueue_style(
            'scg-front',
            plugins_url('assets/' . $file, __FILE__),
            [],
            $this->assetVersion($file)
        );
        $done = true;
    }

    public function ajaxClearCache(): void {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permission denied.', 'smart-cat-grid')], 403);
        }
        check_ajax_referer('scg-clear-cache', 'nonce');
        $this->clearAllCache();
        $this->clearWidgetCache();
        wp_send_json_success(['message' => __('Cache cleared successfully!', 'smart-cat-grid')]);
    }
}

SmartCategoriesGrid::getInstance();
