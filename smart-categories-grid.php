<?php
/*
Plugin Name: Smart Categories Grid
Description: Responsive category grid with caching, advanced settings, category exclusion, optional image display, and category limit
Version: 2.0.1
Author: TM
Author URI: your-site.com
Text Domain: smart-cat-grid
*/

defined('ABSPATH') || exit;

// register_activation_hook must be at top-level, before any plugins_loaded hooks.
register_activation_hook(__FILE__, ['SmartCategoriesGrid', 'onActivationStatic']);

class SmartCategoriesGrid {
    private const CACHE_PREFIX = 'scg_cache_';
    private const MIN_COLUMNS = 2;
    private const MAX_COLUMNS = 6;
    private const IMAGE_SIZE_NAME = 'scg-thumb';
    private const VERSION = '2.0.1';

    private array $settings;
    private static ?self $instance = null;
    private static bool $shortcode_used = false;

    public static function getInstance(): self {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('plugins_loaded', [$this, 'init']);
        add_action('after_setup_theme', [$this, 'addImageSizes']);
    }

    public function init(): void {
        $this->loadSettings();
        $this->registerHooks();
    }

    public function addImageSizes(): void {
        add_image_size(self::IMAGE_SIZE_NAME, 120, 96, true);
        add_filter('image_size_names_choose', [$this, 'addImageSizeNames']);
    }

    public function addImageSizeNames(array $sizes): array {
        return array_merge($sizes, [
            self::IMAGE_SIZE_NAME => __('Category Grid (120x96)', 'smart-cat-grid')
        ]);
    }

    private function loadSettings(): void {
        $this->settings = get_option('scg_settings', []);
    }

    private function registerHooks(): void {
        add_shortcode('categories_grid', [$this, 'renderGrid']);
        add_action('admin_menu', [$this, 'addAdminMenu']);
        add_action('admin_init', [$this, 'registerSettings']);
        add_action('admin_enqueue_scripts', [$this, 'adminAssets']);
        add_action('wp', [$this, 'preCheckShortcode']);
        add_action('wp_enqueue_scripts', [$this, 'frontendAssets']);
        add_action('wp_ajax_scg_clear_cache', [$this, 'ajaxClearCache']);

        add_action('created_category', [$this, 'clearAllCache']);
        add_action('edited_category', [$this, 'clearAllCache']);
        add_action('delete_category', [$this, 'clearAllCache']);
    }

    public static function onActivationStatic(): void {
        add_image_size(self::IMAGE_SIZE_NAME, 120, 96, true);
        add_option('scg_show_regenerate_notice', true);
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
            'force_update' => 'false'
        ], is_array($atts) ? $atts : []);

        $auto_mode = filter_var($atts['auto'], FILTER_VALIDATE_BOOLEAN);

        if ($auto_mode) {
            $current_category = $this->getCurrentCategory();
            if ($current_category <= 0) {
                return '';
            }
            $parent = $current_category;
        } else {
            if ($atts['type'] === 'top-level') {
                $parent = 0;
            } else {
                $parent = !empty($atts['category_id'])
                    ? absint($atts['category_id'])
                    : (int) ($this->settings['default_category'] ?? 0);

                if ($parent === 0) {
                    return '';
                }
            }
        }

        $exclude_ids  = $this->parseExcludeIds($atts['exclude']);
        $show_images  = $this->getShortcodeSetting('show_images', $atts, !empty($this->settings['default_show_images']), 'bool');
        $limit        = $this->getShortcodeSetting('limit', $atts, (int) ($this->settings['default_limit'] ?? 0), 'int');
        $columns      = $this->getShortcodeSetting('columns', $atts, (int) ($this->settings['columns'] ?? self::MAX_COLUMNS), 'int');
        $style        = $this->getShortcodeSetting('style', $atts, $this->settings['grid_style'] ?? 'classic');
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
            return $this->generateGrid($parent, $exclude_ids, $show_images, $limit, $grid_settings);
        }

        return $this->getCachedGrid($parent, $exclude_ids, $show_images, $limit, $grid_settings);
    }

    private function getCurrentCategory(): int {
        static $cached_category = null;
        if ($cached_category !== null) {
            return $cached_category;
        }

        if (is_category()) {
            $c = get_queried_object();
            if ($c && isset($c->term_id)) {
                return $cached_category = (int) $c->term_id;
            }
        }

        $q = get_queried_object();
        if ($q && isset($q->taxonomy) && $q->taxonomy === 'category') {
            return $cached_category = (int) $q->term_id;
        }

        if (is_single()) {
            $post_id = get_the_ID();
            if ($post_id) {
                $primary = (int) get_post_meta($post_id, '_yoast_wpseo_primary_category', true);
                if (!$primary) {
                    $primary = (int) get_post_meta($post_id, 'rank_math_primary_category', true);
                }
                if ($primary > 0) {
                    return $cached_category = $primary;
                }
                $cats = get_the_category($post_id);
                if (!empty($cats)) {
                    usort($cats, function ($a, $b) {
                        return count(get_ancestors($b->term_id, 'category'))
                             - count(get_ancestors($a->term_id, 'category'));
                    });
                    return $cached_category = (int) $cats[0]->term_id;
                }
            }
        }

        global $post;
        if (is_a($post, 'WP_Post')) {
            $cats = get_the_category($post->ID);
            if (!empty($cats)) {
                usort($cats, function ($a, $b) {
                    return count(get_ancestors($b->term_id, 'category'))
                         - count(get_ancestors($a->term_id, 'category'));
                });
                return $cached_category = (int) $cats[0]->term_id;
            }
        }

        global $wp_query;
        if (!empty($wp_query->query_vars['cat'])) {
            $cat_id = absint($wp_query->query_vars['cat']);
            if ($cat_id > 0) {
                return $cached_category = $cat_id;
            }
        }
        if (!empty($wp_query->query_vars['category_name'])) {
            $c = get_category_by_slug($wp_query->query_vars['category_name']);
            if ($c && isset($c->term_id)) {
                return $cached_category = (int) $c->term_id;
            }
        }

        return $cached_category = 0;
    }

    private function getShortcodeSetting(string $key, array $atts, $default, string $type = 'string') {
        if (!isset($atts[$key]) || $atts[$key] === '') {
            return $default;
        }
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
        $merged = array_unique(array_merge($local, $global));
        sort($merged);
        return $merged;
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
        ];
        $cacheKey = self::CACHE_PREFIX . md5(implode('|', $key_parts));

        $output = get_transient($cacheKey);
        if (false === $output) {
            $output    = $this->generateGrid($parent, $exclude_ids, $show_images, $limit, $grid_settings);
            $cacheTime = (int) ($this->settings['cache_time'] ?? DAY_IN_SECONDS);
            if ($cacheTime > 0) {
                set_transient($cacheKey, $output, $cacheTime);
            }
        }
        return $output;
    }

    private function generateGrid(int $parent, array $exclude_ids, bool $show_images, int $limit, array $grid_settings): string {
        $args = [
            'taxonomy'               => 'category',
            'parent'                 => $parent,
            'hide_empty'             => false,
            // Ask DB to sort by name, but we CANNOT rely on it:
            // when 'exclude' is used, WordPress wraps the query in a subquery
            // that discards ORDER BY in MySQL 5.7+ / MariaDB 10.3+ strict mode.
            // We enforce the order in PHP below via usort().
            'orderby'                => 'name',
            'order'                  => 'ASC',
            'hierarchical'           => false,
            'update_term_meta_cache' => false,
        ];
        if (!empty($exclude_ids)) {
            $args['exclude'] = $exclude_ids;
        }

        $categories = get_terms($args);
        if (empty($categories) || is_wp_error($categories)) {
            return '';
        }

        // Guaranteed PHP-level sort: strcasecmp handles Unicode/multibyte names
        // correctly (locale-aware, case-insensitive). This covers the case where
        // DB ORDER BY is ignored due to subquery wrapping with 'exclude'.
        usort($categories, function (WP_Term $a, WP_Term $b): int {
            return strcasecmp($a->name, $b->name);
        });

        $total = count($categories);
        if ($limit > 0 && $total > $limit) {
            $categories = array_slice($categories, 0, $limit);
        }

        // For text style skip image queries entirely
        $display_images = ($grid_settings['style'] === 'text') ? false : $show_images;

        ob_start();
        $hover_class  = $grid_settings['hover_effect'] ? ' has-hover' : '';
        $style_class  = ' scg-style-' . sanitize_html_class($grid_settings['style']);
        $columns      = absint($grid_settings['columns']);
        $image_radius = absint($grid_settings['image_radius']);
        $button_color = sanitize_hex_color($grid_settings['button_color']) ?: '#b93434';
        ?>
        <div class="scg-grid<?php echo esc_attr($hover_class . $style_class); ?>"
             style="--scg-columns: <?php echo esc_attr($columns); ?>;
                    --scg-image-radius: <?php echo esc_attr($image_radius); ?>px;
                    --scg-button-color: <?php echo esc_attr($button_color); ?>;">
            <?php foreach ($categories as $cat) :
                $term_link = get_term_link($cat);
                if (is_wp_error($term_link)) continue;
                $image = $display_images ? $this->getCategoryImage($cat->term_id) : '';
            ?>
                <div class="scg-col">
                    <div class="scg-card<?php echo esc_attr($hover_class); ?>">
                        <?php if ($display_images && $image) : ?>
                            <div class="scg-image">
                                <img src="<?php echo esc_url($image); ?>"
                                     alt="<?php echo esc_attr($cat->name); ?>"
                                     width="120" height="96"
                                     loading="lazy" decoding="async">
                            </div>
                        <?php endif; ?>
                        <div class="scg-title">
                            <a href="<?php echo esc_url($term_link); ?>"><?php echo esc_html($cat->name); ?></a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
            <?php if ($limit > 0 && $total > $limit) :
                $view_all_url = '';
                if ($parent > 0) {
                    $u = get_term_link($parent);
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
        return ob_get_clean();
    }

    private function getCategoryImage(int $term_id): string {
        static $image_cache = [];
        if (isset($image_cache[$term_id])) {
            return $image_cache[$term_id];
        }

        $image_id = get_term_meta($term_id, 'logo', true);
        if ($image_id && is_numeric($image_id)) {
            $url = wp_get_attachment_image_url((int) $image_id, self::IMAGE_SIZE_NAME);
            if ($url) {
                return $image_cache[$term_id] = $url;
            }
        }

        $default = $this->settings['default_image'] ?? '';
        if (!$default) {
            $placeholder = plugin_dir_path(__FILE__) . 'assets/placeholder.png';
            if (file_exists($placeholder)) {
                $default = plugins_url('assets/placeholder.png', __FILE__);
            }
        }
        return $image_cache[$term_id] = $default;
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
                    <p>
                        <?php esc_html_e('Plugin activated! For best image quality, please regenerate thumbnails using a plugin like "Regenerate Thumbnails".', 'smart-cat-grid'); ?>
                    </p>
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
                <p><?php esc_html_e('This plugin uses a custom image size of 120x96 pixels. New uploads are resized automatically.', 'smart-cat-grid'); ?></p>
                <p><?php esc_html_e('For existing images, regenerate thumbnails using a plugin like "Regenerate Thumbnails".', 'smart-cat-grid'); ?></p>
            </div>

            <div class="scg-settings-section">
                <h3><?php esc_html_e('Usage', 'smart-cat-grid'); ?></h3>
                <p><?php esc_html_e('Shortcodes: [categories_grid type="top-level"], [categories_grid category_id="X"], [categories_grid auto="true" limit="200"]. Use exclude="X,Y", show_images="false", limit="N" as needed.', 'smart-cat-grid'); ?></p>
            </div>
        </div>
    <?php }

    public function registerSettings(): void {
        register_setting('scg_settings_group', 'scg_settings', [$this, 'validateSettings']);

        add_settings_section('scg_general_section', __('General Settings', 'smart-cat-grid'), null, 'scg-settings');
        add_settings_field('default_category',   __('Default Category', 'smart-cat-grid'),        [$this, 'categorySelectField'],    'scg-settings', 'scg_general_section');
        add_settings_field('exclude_categories', __('Exclude Categories', 'smart-cat-grid'),      [$this, 'excludeCategoriesField'], 'scg-settings', 'scg_general_section');
        add_settings_field('cache_time',         __('Cache Duration', 'smart-cat-grid'),          [$this, 'cacheTimeField'],         'scg-settings', 'scg_general_section');
        add_settings_field('default_limit',      __('Default Category Limit', 'smart-cat-grid'),  [$this, 'defaultLimitField'],      'scg-settings', 'scg_general_section');
        add_settings_field('view_all_url',       __('View All URL', 'smart-cat-grid'),            [$this, 'viewAllUrlField'],        'scg-settings', 'scg_general_section');

        add_settings_section('scg_display_section', __('Display Settings', 'smart-cat-grid'), null, 'scg-settings');
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
        ]);
    }

    public function excludeCategoriesField(): void {
        $value = $this->settings['exclude_categories'] ?? '';
        printf(
            '<input type="text" name="scg_settings[exclude_categories]" value="%s" class="regular-text">',
            esc_attr($value)
        );
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
        $checked = !empty($this->settings['hover_effect']) ? 'checked' : '';
        printf(
            '<label><input type="checkbox" name="scg_settings[hover_effect]" value="1" %s> %s</label>',
            $checked,
            esc_html__('Enable hover effects', 'smart-cat-grid')
        );
    }

    public function defaultImageField(): void {
        $value = $this->settings['default_image'] ?? '';
        printf(
            '<input type="url" name="scg_settings[default_image]" value="%s" class="regular-text"> ',
            esc_url($value)
        );
        echo '<button type="button" class="button scg-upload-image">' . esc_html__('Select Image', 'smart-cat-grid') . '</button>';
    }

    public function defaultShowImagesField(): void {
        $checked = !empty($this->settings['default_show_images']) ? 'checked' : '';
        printf(
            '<label><input type="checkbox" name="scg_settings[default_show_images]" value="1" %s> %s</label>',
            $checked,
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
        printf(
            '<input type="url" name="scg_settings[view_all_url]" value="%s" class="regular-text">',
            esc_url($value)
        );
        echo '<p class="description">' . esc_html__('URL for the "View All" button for top-level categories. Leave empty to hide.', 'smart-cat-grid') . '</p>';
    }

    public function buttonColorField(): void {
        $value = $this->settings['button_color'] ?? '#b93434';
        printf(
            '<input type="text" name="scg_settings[button_color]" value="%s" class="scg-color-picker">',
            esc_attr($value)
        );
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

        $output['default_category']    = absint($input['default_category'] ?? 0);

        $exclude = trim(sanitize_text_field($input['exclude_categories'] ?? ''));
        $ids     = $exclude ? array_unique(array_map('absint', array_filter(explode(',', $exclude), 'is_numeric'))) : [];
        $output['exclude_categories']  = implode(',', $ids);

        $output['cache_time']          = absint($input['cache_time'] ?? DAY_IN_SECONDS);

        $columns = absint($input['columns'] ?? self::MAX_COLUMNS);
        $output['columns']             = max(self::MIN_COLUMNS, min(self::MAX_COLUMNS, $columns));

        $output['image_radius']        = max(0, min(50, absint($input['image_radius'] ?? 3)));
        $output['default_image']       = esc_url_raw($input['default_image'] ?? '');
        $output['hover_effect']        = !empty($input['hover_effect']) ? 1 : 0;
        $output['default_show_images'] = !empty($input['default_show_images']) ? 1 : 0;
        $output['default_limit']       = max(0, absint($input['default_limit'] ?? 0));
        $output['view_all_url']        = esc_url_raw($input['view_all_url'] ?? '');
        $output['button_color']        = sanitize_hex_color($input['button_color'] ?? '#b93434') ?: '#b93434';

        $valid_styles    = ['classic', 'modern', 'minimal', 'card', 'text'];
        $style           = $input['grid_style'] ?? 'classic';
        $output['grid_style'] = in_array($style, $valid_styles, true) ? $style : 'classic';

        $this->settings = $output;
        wp_cache_delete('scg_settings', 'options');
        wp_cache_delete('alloptions', 'options');
        $this->clearAllCache();

        return $output;
    }

    public function clearAllCache(): void {
        global $wpdb;
        $pattern   = $wpdb->esc_like('_transient_' . self::CACHE_PREFIX) . '%';
        $pattern_t = $wpdb->esc_like('_transient_timeout_' . self::CACHE_PREFIX) . '%';
        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
            $pattern,
            $pattern_t
        ));
        wp_cache_delete('alloptions', 'options');
    }

    public function adminAssets(string $hook): void {
        if ('settings_page_scg-settings' !== $hook) return;

        wp_enqueue_media();
        wp_enqueue_style('wp-color-picker');
        wp_enqueue_script('wp-color-picker');
        wp_add_inline_script('wp-color-picker',
            'jQuery(function($){ $(".scg-color-picker").wpColorPicker(); });'
        );

        $css_path = plugin_dir_path(__FILE__) . 'assets/admin.css';
        $js_path  = plugin_dir_path(__FILE__) . 'assets/admin.js';

        wp_enqueue_style('scg-admin', plugins_url('assets/admin.css', __FILE__), [],
            file_exists($css_path) ? filemtime($css_path) : self::VERSION);

        wp_enqueue_script('scg-admin', plugins_url('assets/admin.js', __FILE__),
            ['jquery', 'wp-i18n'],
            file_exists($js_path) ? filemtime($js_path) : self::VERSION,
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

    public function preCheckShortcode(): void {
        if (self::$shortcode_used) return;

        global $post;
        $found = false;

        if (is_a($post, 'WP_Post') && has_shortcode($post->post_content, 'categories_grid')) {
            $found = true;
        }

        if (!$found) {
            $widget_text = get_option('widget_text');
            if (is_array($widget_text)) {
                foreach ($widget_text as $w) {
                    if (isset($w['text']) && has_shortcode($w['text'], 'categories_grid')) {
                        $found = true;
                        break;
                    }
                }
            }
        }

        if (!$found) {
            $block_widgets = get_option('widget_block');
            if (is_array($block_widgets)) {
                foreach ($block_widgets as $w) {
                    if (isset($w['content']) && strpos($w['content'], 'categories_grid') !== false) {
                        $found = true;
                        break;
                    }
                }
            }
        }

        if (!$found && is_a($post, 'WP_Post')) {
            $el = get_post_meta($post->ID, '_elementor_data', true);
            if (is_string($el) && strpos($el, 'categories_grid') !== false) {
                $found = true;
            }
        }

        if (!$found && is_a($post, 'WP_Post') && has_blocks($post->post_content)) {
            if (strpos($post->post_content, 'categories_grid') !== false) {
                $found = true;
            }
        }

        $found = apply_filters('scg_has_shortcode', $found);
        if ($found) self::$shortcode_used = true;
    }

    public function frontendAssets(): void {
        if (self::$shortcode_used) {
            $this->enqueueStyles();
        }
    }

    private function enqueueStyles(): void {
        static $done = false;
        if ($done) return;

        $css_path = plugin_dir_path(__FILE__) . 'assets/front.css';
        wp_enqueue_style(
            'scg-front',
            plugins_url('assets/front.css', __FILE__),
            [],
            file_exists($css_path) ? filemtime($css_path) : self::VERSION
        );
        $done = true;
    }

    public function ajaxClearCache(): void {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permission denied.', 'smart-cat-grid')], 403);
        }
        check_ajax_referer('scg-clear-cache', 'nonce');
        $this->clearAllCache();
        wp_send_json_success(['message' => __('Cache cleared successfully!', 'smart-cat-grid')]);
    }
}

SmartCategoriesGrid::getInstance();
