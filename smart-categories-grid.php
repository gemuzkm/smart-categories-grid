<?php
/*
Plugin Name: Smart Categories Grid
Description: Responsive category grid with caching, advanced settings, category exclusion, optional image display, and category limit
Version: 1.9
Author: TM
Author URI: your-site.com
Text Domain: smart-cat-grid
*/

defined('ABSPATH') || exit;

class SmartCategoriesGrid {
    private const CACHE_PREFIX = 'scg_cache_';
    private const MIN_COLUMNS = 2;
    private const MAX_COLUMNS = 6;
    private const IMAGE_SIZE_NAME = 'scg-thumb'; // Custom image size name
    
    private array $settings;
    private static ?self $instance = null;
    
    public static function getInstance(): self {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('plugins_loaded', [$this, 'init']);
        // Add custom image size when theme is activated
        add_action('after_setup_theme', [$this, 'addImageSizes']);
    }
    
    public function init(): void {
        $this->loadSettings();
        $this->registerHooks();
    }
    
    /**
     * Adds custom image size for categories
     */
    public function addImageSizes(): void {
        // Add custom image size 120x96 pixels with cropping
        add_image_size(self::IMAGE_SIZE_NAME, 120, 96, true);
        
        // Add filter to display our size in media library
        add_filter('image_size_names_choose', [$this, 'addImageSizeNames']);
    }
    
    /**
     * Adds our size name to the list of available sizes
     */
    public function addImageSizeNames(array $sizes): array {
        return array_merge($sizes, [
            self::IMAGE_SIZE_NAME => __('Category Grid (120x96)', 'smart-cat-grid')
        ]);
    }
    
    private function loadSettings(): void {
        static $cached_settings = null;
        if (null === $cached_settings) {
            $cached_settings = get_option('scg_settings', []);
        }
        $this->settings = $cached_settings;
    }
    
    private function registerHooks(): void {
        add_shortcode('categories_grid', [$this, 'renderGrid']);
        add_action('admin_menu', [$this, 'addAdminMenu']);
        add_action('admin_init', [$this, 'registerSettings']);
        add_action('admin_enqueue_scripts', [$this, 'adminAssets']);
        add_action('wp_enqueue_scripts', [$this, 'frontendAssets']);
        add_action('wp_ajax_scg_clear_cache', [$this, 'ajaxClearCache']);
        
        // Clear cache when categories are modified
        add_action('created_category', [$this, 'clearAllCache']);
        add_action('edited_category', [$this, 'clearAllCache']);
        add_action('delete_category', [$this, 'clearAllCache']);
        
        // Add hook for thumbnail regeneration on plugin activation
        register_activation_hook(__FILE__, [$this, 'onActivation']);
    }
    
    /**
     * Executes on plugin activation
     */
    public function onActivation(): void {
        // Add image size
        $this->addImageSizes();
        
        // Optionally: add notice about thumbnail regeneration requirement
        add_option('scg_show_regenerate_notice', true);
    }
    
    public function renderGrid(array $atts): string {
        $atts = shortcode_atts([
            'category_id' => $this->settings['default_category'] ?? 0,
            'type' => 'subcategories',
            'exclude' => '',
            'show_images' => $this->settings['default_show_images'] ?? true,
            'limit' => $this->settings['default_limit'] ?? 0,
            'force_update' => false
        ], $atts);
        
        $type = $atts['type'];
        if (!in_array($type, ['subcategories', 'top-level'])) {
            return '';
        }
        
        if ($type === 'subcategories') {
            $parent = absint($atts['category_id']);
            if ($parent === 0) {
                return '';
            }
        } else {
            $parent = 0;
        }
        
        $exclude_ids = $this->parseExcludeIds($atts['exclude']);
        $show_images = filter_var($atts['show_images'], FILTER_VALIDATE_BOOLEAN);
        $limit = absint($atts['limit']);
        
        if ($atts['force_update']) {
            return $this->generateGrid($parent, $exclude_ids, $show_images, $limit);
        }
        
        return $this->getCachedGrid($parent, $exclude_ids, $show_images, $limit);
    }
    
    private function parseExcludeIds(string $exclude): array {
        $exclude_ids = [];
        if (!empty($exclude)) {
            $ids = array_map('absint', array_filter(explode(',', $exclude)));
            $exclude_ids = array_unique($ids);
        }
        
        $global_excludes = !empty($this->settings['exclude_categories']) ? array_map('absint', array_filter(explode(',', $this->settings['exclude_categories']))) : [];
        return array_unique(array_merge($exclude_ids, $global_excludes));
    }
    
    private function getCachedGrid(int $parent, array $exclude_ids, bool $show_images, int $limit): string {
        // Optimized cache key generation
        $style = $this->settings['grid_style'] ?? 'classic';
        $exclude_str = empty($exclude_ids) ? '0' : implode(',', $exclude_ids);
        $cacheKey = self::CACHE_PREFIX . $parent . '_' . md5($exclude_str) . '_' . ($show_images ? '1' : '0') . '_' . $limit . '_' . $style;
        $output = get_transient($cacheKey);
        
        if (false === $output) {
            $output = $this->generateGrid($parent, $exclude_ids, $show_images, $limit);
            $cacheTime = $this->settings['cache_time'] ?? DAY_IN_SECONDS;
            if ($cacheTime > 0) {
                set_transient($cacheKey, $output, $cacheTime);
            }
        }
        
        return $output;
    }
    
    private function generateGrid(int $parent, array $exclude_ids, bool $show_images, int $limit): string {
        // Optimized query with proper parameters
        $args = [
            'taxonomy' => 'category',
            'parent' => $parent,
            'hide_empty' => false,
            'orderby' => 'name',
            'order' => 'ASC',
            'fields' => 'all'
        ];
        
        if (!empty($exclude_ids)) {
            $args['exclude'] = $exclude_ids;
        }
        
        // Get all categories to count total (needed for "View All" button)
        $all_categories = get_terms($args);
        
        if (empty($all_categories) || is_wp_error($all_categories)) {
            return '';
        }
        
        // Additional sorting for accuracy
        usort($all_categories, function ($a, $b) {
            return strcasecmp($a->name, $b->name);
        });
        
        $total_categories = count($all_categories);
        
        // Apply limit if needed
        $categories = $all_categories;
        if ($limit > 0 && $total_categories > $limit) {
            $categories = array_slice($all_categories, 0, $limit);
        }
        
        $grid_settings = [
            'columns' => $this->settings['columns'] ?? self::MAX_COLUMNS,
            'image_radius' => $this->settings['image_radius'] ?? 3,
            'hover_effect' => !empty($this->settings['hover_effect']),
            'style' => $this->settings['grid_style'] ?? 'classic'
        ];
        
        ob_start(); 
        $hover_class = $grid_settings['hover_effect'] ? ' has-hover' : '';
        $style_class = ' scg-style-' . esc_attr($grid_settings['style']);
        $columns = absint($grid_settings['columns']);
        $image_radius = absint($grid_settings['image_radius']);
        $button_color = sanitize_hex_color($this->settings['button_color'] ?? '#b93434');
        
        // For text style, always hide images
        $display_images = ($grid_settings['style'] === 'text') ? false : $show_images;
        ?>
        <div class="scg-grid<?php echo esc_attr($hover_class . $style_class); ?>" 
             style="--scg-columns: <?php echo esc_attr($columns); ?>;
                    --scg-image-radius: <?php echo esc_attr($image_radius); ?>px;
                    --scg-button-color: <?php echo esc_attr($button_color); ?>;">
            <?php foreach ($categories as $cat) : 
                $term_link = get_term_link($cat);
                if (is_wp_error($term_link)) {
                    continue;
                }
                $image = $this->getCategoryImage($cat->term_id); ?>
                <div class="scg-col">
                    <div class="scg-card<?php echo esc_attr($hover_class); ?>">
                        <?php if ($display_images) : ?>
                            <div class="scg-image">
                                <img src="<?php echo esc_url($image); ?>" 
                                     alt="<?php echo esc_attr($cat->name); ?>" 
                                     width="120" 
                                     height="96"
                                     loading="lazy"
                                     decoding="async">
                            </div>
                        <?php endif; ?>
                        <div class="scg-title">
                            <a href="<?php echo esc_url($term_link); ?>"><?php echo esc_html($cat->name); ?></a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
            <?php if ($limit > 0 && $total_categories > $limit) : 
                if ($parent > 0) {
                    $view_all_url = get_term_link($parent);
                    if (is_wp_error($view_all_url)) {
                        $view_all_url = '';
                    }
                } else {
                    $view_all_url = $this->settings['view_all_url'] ?? '';
                }
                if (!empty($view_all_url)) : ?>
                    <div class="scg-view-all">
                        <a href="<?php echo esc_url($view_all_url); ?>" class="scg-view-all-link">
                            <?php esc_html_e('View All', 'smart-cat-grid'); ?>
                        </a>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
        <?php return ob_get_clean();
    }
    
    /**
     * Gets category image using custom size
     */
    private function getCategoryImage(int $term_id): string {
        // Cache result to avoid repeated queries
        static $image_cache = [];
        
        if (isset($image_cache[$term_id])) {
            return $image_cache[$term_id];
        }
        
        $image_id = get_term_meta($term_id, 'logo', true);
        
        if ($image_id && is_numeric($image_id)) {
            // Use our custom image size
            $image_url = wp_get_attachment_image_url((int)$image_id, self::IMAGE_SIZE_NAME);
            if ($image_url) {
                $image_cache[$term_id] = $image_url;
                return $image_url;
            }
        }
        
        // Return default image
        $default_image = $this->settings['default_image'] ?? plugins_url('assets/placeholder.png', __FILE__);
        $image_cache[$term_id] = $default_image;
        return $default_image;
    }
    
    public function addAdminMenu(): void {
        add_options_page(
            'Categories Grid Settings',
            'Categories Grid',
            'manage_options',
            'scg-settings',
            [$this, 'settingsPage']
        );
    }
    
    public function settingsPage(): void { ?>
        <div class="wrap scg-settings-wrap">
            <h1><?php esc_html_e('Categories Grid Settings', 'smart-cat-grid'); ?></h1>
            
            <?php if (get_option('scg_show_regenerate_notice')) : ?>
                <div class="notice notice-info">
                    <p>
                        <?php esc_html_e('Plugin activated! For best image quality, please regenerate thumbnails using a plugin like "Regenerate Thumbnails" or "Force Regenerate Thumbnails".', 'smart-cat-grid'); ?>
                        <a href="#" onclick="this.parentNode.parentNode.style.display='none'; return false;"><?php esc_html_e('Dismiss', 'smart-cat-grid'); ?></a>
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
                <p><?php esc_html_e('This plugin uses a custom image size of 120x96 pixels. When you upload new images, they will automatically be resized to this dimension.', 'smart-cat-grid'); ?></p>
                <p><?php esc_html_e('For existing images, you may need to regenerate thumbnails using a plugin like "Regenerate Thumbnails".', 'smart-cat-grid'); ?></p>
            </div>
            
            <div class="scg-settings-section">
                <h3><?php esc_html_e('Usage', 'smart-cat-grid'); ?></h3>
                <p><?php esc_html_e('Use shortcode [categories_grid type="top-level"] to display top-level categories, or [categories_grid category_id="X"] for subcategories. Use exclude="X,Y" to exclude specific categories. Use show_images="false" to hide images. Use limit="N" to limit the number of categories.', 'smart-cat-grid'); ?></p>
            </div>
        </div>
    <?php }

    public function registerSettings(): void {
        register_setting('scg_settings_group', 'scg_settings', [$this, 'validateSettings']);
        
        add_settings_section(
            'scg_general_section',
            __('General Settings', 'smart-cat-grid'),
            null,
            'scg-settings'
        );
        
        add_settings_field(
            'default_category',
            __('Default Category', 'smart-cat-grid'),
            [$this, 'categorySelectField'],
            'scg-settings',
            'scg_general_section'
        );
        
        add_settings_field(
            'exclude_categories',
            __('Exclude Categories', 'smart-cat-grid'),
            [$this, 'excludeCategoriesField'],
            'scg-settings',
            'scg_general_section'
        );
        
        add_settings_field(
            'cache_time',
            __('Cache Duration', 'smart-cat-grid'),
            [$this, 'cacheTimeField'],
            'scg-settings',
            'scg_general_section'
        );
        
        add_settings_field(
            'default_limit',
            __('Default Category Limit', 'smart-cat-grid'),
            [$this, 'defaultLimitField'],
            'scg-settings',
            'scg_general_section'
        );
        
        add_settings_field(
            'view_all_url',
            __('View All URL', 'smart-cat-grid'),
            [$this, 'viewAllUrlField'],
            'scg-settings',
            'scg_general_section'
        );
        
        add_settings_section(
            'scg_display_section',
            __('Display Settings', 'smart-cat-grid'),
            null,
            'scg-settings'
        );
        
        add_settings_field(
            'columns',
            __('Default Columns', 'smart-cat-grid'),
            [$this, 'columnsField'],
            'scg-settings',
            'scg_display_section'
        );
        
        add_settings_field(
            'image_radius',
            __('Image Border Radius', 'smart-cat-grid'),
            [$this, 'imageRadiusField'],
            'scg-settings',
            'scg_display_section'
        );
        
        add_settings_field(
            'hover_effect',
            __('Hover Effect', 'smart-cat-grid'),
            [$this, 'hoverEffectField'],
            'scg-settings',
            'scg_display_section'
        );
        
        add_settings_field(
            'default_image',
            __('Default Image', 'smart-cat-grid'),
            [$this, 'defaultImageField'],
            'scg-settings',
            'scg_display_section'
        );
        
        add_settings_field(
            'default_show_images',
            __('Show Images by Default', 'smart-cat-grid'),
            [$this, 'defaultShowImagesField'],
            'scg-settings',
            'scg_display_section'
        );
        
        add_settings_field(
            'button_color',
            __('Button Color', 'smart-cat-grid'),
            [$this, 'buttonColorField'],
            'scg-settings',
            'scg_display_section'
        );
        
        add_settings_field(
            'grid_style',
            __('Grid Style', 'smart-cat-grid'),
            [$this, 'gridStyleField'],
            'scg-settings',
            'scg_display_section'
        );
    }
    
    public function categorySelectField(): void {
        wp_dropdown_categories([
            'show_option_none' => __('Select a category', 'smart-cat-grid'),
            'option_none_value' => 0,
            'name' => 'scg_settings[default_category]',
            'selected' => $this->settings['default_category'] ?? 0,
            'hierarchical' => true
        ]);
    }
    
    public function excludeCategoriesField(): void {
        $value = !empty($this->settings['exclude_categories']) ? esc_attr($this->settings['exclude_categories']) : '';
        ?>
        <input type="text" 
               name="scg_settings[exclude_categories]" 
               value="<?= $value; ?>" 
               class="regular-text">
        <p class="description"><?php esc_html_e('Enter a comma-separated list of category IDs to exclude from the grid (e.g., 10,20,30).', 'smart-cat-grid'); ?></p>
        <?php
    }
    
    public function cacheTimeField(): void {
        $value = $this->settings['cache_time'] ?? DAY_IN_SECONDS; ?>
        <select name="scg_settings[cache_time]">
            <option value="<?= HOUR_IN_SECONDS; ?>" <?= selected($value, HOUR_IN_SECONDS, false); ?>><?php _e('1 Hour', 'smart-cat-grid'); ?></option>
            <option value="<?= 12 * HOUR_IN_SECONDS; ?>" <?= selected($value, 12 * HOUR_IN_SECONDS, false); ?>><?php _e('12 Hours', 'smart-cat-grid'); ?></option>
            <option value="<?= DAY_IN_SECONDS; ?>" <?= selected($value, DAY_IN_SECONDS, false); ?>><?php _e('1 Day', 'smart-cat-grid'); ?></option>
            <option value="<?= WEEK_IN_SECONDS; ?>" <?= selected($value, WEEK_IN_SECONDS, false); ?>><?php _e('1 Week', 'smart-cat-grid'); ?></option>
            <option value="0" <?= selected($value, 0, false); ?>><?php _e('No Caching', 'smart-cat-grid'); ?></option>
        </select>
        <button type="button" id="scg-clear-cache" class="button"><?php _e('Clear Cache', 'smart-cat-grid'); ?></button>
        <?php
    }
    
    public function columnsField(): void {
        $value = $this->settings['columns'] ?? self::MAX_COLUMNS; ?>
        <select name="scg_settings[columns]">
            <?php for ($i = self::MIN_COLUMNS; $i <= self::MAX_COLUMNS; $i++) : ?>
                <option value="<?= $i; ?>" <?= selected($value, $i, false); ?>>
                    <?= $i; ?> <?php _e('Columns', 'smart-cat-grid'); ?>
                </option>
            <?php endfor; ?>
        </select>
        <?php
    }
    
    public function imageRadiusField(): void {
        $value = $this->settings['image_radius'] ?? 3; ?>
        <input type="number" 
               name="scg_settings[image_radius]" 
               min="0" 
               max="50" 
               value="<?= esc_attr($value); ?>"> px
        <?php
    }
    
    public function hoverEffectField(): void {
        $checked = isset($this->settings['hover_effect']) && 1 === $this->settings['hover_effect'] ? 'checked' : ''; ?>
        <label>
            <input type="checkbox" 
                   name="scg_settings[hover_effect]" 
                   value="1" 
                   <?= $checked; ?>> 
            <?php _e('Enable hover effects', 'smart-cat-grid'); ?>
        </label>
        <?php
    }
    
    public function defaultImageField(): void { ?>
        <input type="url" 
               name="scg_settings[default_image]" 
               value="<?= esc_url($this->settings['default_image'] ?? ''); ?>" 
               class="regular-text">
        <button type="button" class="button scg-upload-image"><?php _e('Select Image', 'smart-cat-grid'); ?></button>
        <?php
    }
    
    public function defaultShowImagesField(): void {
        $checked = isset($this->settings['default_show_images']) && 1 === $this->settings['default_show_images'] ? 'checked' : '';
        ?>
        <label>
            <input type="checkbox" 
                   name="scg_settings[default_show_images]" 
                   value="1" 
                   <?= $checked; ?>> 
            <?php _e('Show images by default', 'smart-cat-grid'); ?>
        </label>
        <p class="description"><?php esc_html_e('If checked, images will be displayed in the grid unless overridden by the shortcode.', 'smart-cat-grid'); ?></p>
        <?php
    }
    
    public function defaultLimitField(): void {
        $value = $this->settings['default_limit'] ?? 0;
        ?>
        <input type="number" 
               name="scg_settings[default_limit]" 
               min="0" 
               value="<?= esc_attr($value); ?>">
        <p class="description"><?php esc_html_e('Set the default number of categories to display. 0 means no limit.', 'smart-cat-grid'); ?></p>
        <?php
    }
    
    public function viewAllUrlField(): void {
        $value = $this->settings['view_all_url'] ?? '';
        ?>
        <input type="url" 
               name="scg_settings[view_all_url]" 
               value="<?= esc_attr($value); ?>" 
               class="regular-text">
        <p class="description"><?php esc_html_e('Enter the URL for the "View All" button for top-level categories (e.g., blog page). Leave empty to hide the button.', 'smart-cat-grid'); ?></p>
        <?php
    }
    
    public function buttonColorField(): void {
        $value = $this->settings['button_color'] ?? '#b93434';
        ?>
        <input type="text" 
               name="scg_settings[button_color]" 
               value="<?= esc_attr($value); ?>" 
               class="scg-color-picker">
        <p class="description"><?php esc_html_e('Select the color for the "View All" button.', 'smart-cat-grid'); ?></p>
        <?php 
    }
    
    public function gridStyleField(): void {
        $value = $this->settings['grid_style'] ?? 'classic';
        $styles = [
            'classic' => __('Classic', 'smart-cat-grid'),
            'modern' => __('Modern', 'smart-cat-grid'),
            'minimal' => __('Minimal', 'smart-cat-grid'),
            'card' => __('Card', 'smart-cat-grid'),
            'text' => __('Text Only', 'smart-cat-grid')
        ];
        ?>
        <select name="scg_settings[grid_style]">
            <?php foreach ($styles as $key => $label) : ?>
                <option value="<?= esc_attr($key); ?>" <?= selected($value, $key, false); ?>>
                    <?= esc_html($label); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <p class="description"><?php esc_html_e('Choose the visual style for the category grid.', 'smart-cat-grid'); ?></p>
        <?php
    }
    
    public function validateSettings(array $input): array {
        $output = [];
        
        // Validate default_category
        $output['default_category'] = isset($input['default_category']) 
            ? absint($input['default_category']) 
            : 0;
        
        // Validate exclude_categories
        $exclude = isset($input['exclude_categories']) ? trim(sanitize_text_field($input['exclude_categories'])) : '';
        if (!empty($exclude)) {
            $ids = array_map('absint', array_filter(explode(',', $exclude), 'is_numeric'));
            $output['exclude_categories'] = implode(',', array_unique($ids));
        } else {
            $output['exclude_categories'] = '';
        }
        
        // Validate cache_time
        $output['cache_time'] = isset($input['cache_time'])
            ? absint($input['cache_time'])
            : DAY_IN_SECONDS;
        
        // Validate columns
        $columns = isset($input['columns']) ? absint($input['columns']) : self::MAX_COLUMNS;
        $output['columns'] = ($columns >= self::MIN_COLUMNS && $columns <= self::MAX_COLUMNS) 
            ? $columns 
            : self::MAX_COLUMNS;
        
        // Validate image_radius
        $output['image_radius'] = isset($input['image_radius'])
            ? max(0, min(50, absint($input['image_radius'])))
            : 3;
        
        // Validate default_image
        $output['default_image'] = isset($input['default_image'])
            ? esc_url_raw($input['default_image'])
            : '';
        
        // Validate hover_effect
        $output['hover_effect'] = isset($input['hover_effect']) ? 1 : 0;
        
        // Validate default_show_images
        $output['default_show_images'] = isset($input['default_show_images']) ? 1 : 0;
        
        // Validate default_limit
        $output['default_limit'] = isset($input['default_limit']) ? max(0, absint($input['default_limit'])) : 0;
        
        // Validate view_all_url
        $output['view_all_url'] = isset($input['view_all_url']) ? esc_url_raw($input['view_all_url']) : '';
        
        // Validate button_color
        $output['button_color'] = isset($input['button_color']) 
            ? sanitize_hex_color($input['button_color']) 
            : '#b93434';
        
        // Validate grid_style
        $valid_styles = ['classic', 'modern', 'minimal', 'card', 'text'];
        $output['grid_style'] = isset($input['grid_style']) && in_array($input['grid_style'], $valid_styles, true)
            ? sanitize_text_field($input['grid_style'])
            : 'classic';
        
        // Clear options cache
        wp_cache_delete('scg_settings', 'options');
        
        // Clear grid cache
        $this->clearAllCache();
        
        return $output;
    }
    
    public function clearAllCache(): void {
        global $wpdb;
        
        // Optimized query to delete all plugin transients
        $pattern = $wpdb->esc_like('_transient_' . self::CACHE_PREFIX) . '%';
        $transient_timeout_pattern = $wpdb->esc_like('_transient_timeout_' . self::CACHE_PREFIX) . '%';
        
        // Delete transients and their timeouts in one query
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->options} 
                 WHERE option_name LIKE %s OR option_name LIKE %s",
                $pattern,
                $transient_timeout_pattern
            )
        );
        
        // Clear WordPress options cache
        wp_cache_delete('alloptions', 'options');
    }
    
    public function adminAssets(string $hook): void {
        if ('settings_page_scg-settings' !== $hook) return;
        
        wp_enqueue_media();
        wp_enqueue_style('wp-color-picker');
        wp_enqueue_script('wp-color-picker');
        wp_add_inline_script('wp-color-picker', '
            jQuery(document).ready(function($){
                $(".scg-color-picker").wpColorPicker();
            });
        ');
        wp_enqueue_style(
            'scg-admin',
            plugins_url('assets/admin.css', __FILE__),
            [],
            filemtime(plugin_dir_path(__FILE__) . 'assets/admin.css')
        );
        
        wp_enqueue_script(
            'scg-admin',
            plugins_url('assets/admin.js', __FILE__),
            ['jquery', 'wp-i18n'],
            filemtime(plugin_dir_path(__FILE__) . 'assets/admin.js'),
            true
        );
        
        wp_localize_script('scg-admin', 'scg_admin', [
            'nonce' => wp_create_nonce('scg-clear-cache'),
            'i18n' => [
                'clear_confirm' => __('Are you sure?', 'smart-cat-grid'),
                'clearing' => __('Clearing...', 'smart-cat-grid'),
                'clear_cache' => __('Clear Cache', 'smart-cat-grid'),
                'upload_title' => __('Select Image', 'smart-cat-grid'),
                'use_image' => __('Use This Image', 'smart-cat-grid'),
                'settings_saved' => __('Settings saved successfully!', 'smart-cat-grid'),
                'clear_failed' => __('Failed to clear cache', 'smart-cat-grid')
            ]
        ]);
    }
    
    public function frontendAssets(): void {
        // Conditionally load styles - check for shortcode presence
        global $post;
        $should_load = false;
        
        // Check main post content
        if (is_a($post, 'WP_Post') && has_shortcode($post->post_content, 'categories_grid')) {
            $should_load = true;
        }
        
        // Check text widgets
        if (!$should_load) {
            $widget_text = get_option('widget_text');
            if (is_array($widget_text)) {
                foreach ($widget_text as $widget) {
                    if (isset($widget['text']) && has_shortcode($widget['text'], 'categories_grid')) {
                        $should_load = true;
                        break;
                    }
                }
            }
        }
        
        // If not found, load always (for compatibility with widgets and other places)
        // Can be further optimized by adding a flag via filter
        $should_load = apply_filters('scg_should_load_assets', $should_load);
        
        if ($should_load) {
            $css_path = plugin_dir_path(__FILE__) . 'assets/front.css';
            $css_version = file_exists($css_path) ? filemtime($css_path) : '1.0';
            
            wp_enqueue_style(
                'scg-front',
                plugins_url('assets/front.css', __FILE__),
                [],
                $css_version
            );
        }
    }
    
    public function ajaxClearCache(): void {
        check_ajax_referer('scg-clear-cache', 'nonce');
        $this->clearAllCache();
        wp_send_json_success(['message' => __('Cache cleared successfully!', 'smart-cat-grid')]);
    }
}

SmartCategoriesGrid::getInstance();
