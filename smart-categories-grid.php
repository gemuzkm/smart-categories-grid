<?php
/*
Plugin Name: Smart Categories Grid
Description: Responsive category grid with caching and advanced settings
Version: 1.3
Author: TM
Author URI: your-site.com
Text Domain: smart-cat-grid
*/

defined('ABSPATH') || exit;

class SmartCategoriesGrid {
    private const CACHE_PREFIX = 'scg_cache_';
    private const MIN_COLUMNS = 2;
    private const MAX_COLUMNS = 6;
    
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
    }
    
    public function init(): void {
        $this->loadSettings();
        $this->registerHooks();
    }
    
    private function loadSettings(): void {
        $this->settings = get_option('scg_settings', []);
    }
    
    private function registerHooks(): void {
        add_shortcode('categories_grid', [$this, 'renderGrid']);
        add_action('admin_menu', [$this, 'addAdminMenu']);
        add_action('admin_init', [$this, 'registerSettings']);
        add_action('admin_enqueue_scripts', [$this, 'adminAssets']);
        add_action('wp_enqueue_scripts', [$this, 'frontendAssets']);
        add_action('wp_ajax_scg_clear_cache', [$this, 'ajaxClearCache']);
    }

    public function renderGrid(array $atts): string {
        $atts = shortcode_atts([
            'category_id' => $this->settings['default_category'] ?? 0,
            'force_update' => false
        ], $atts);
        
        $categoryId = absint($atts['category_id']);
        if (!$categoryId) {
            return '';
        }
        
        if ($atts['force_update']) {
            return $this->generateGrid($categoryId);
        }
        
        return $this->getCachedGrid($categoryId);
    }

    private function getCachedGrid(int $categoryId): string {
        $cacheKey = self::CACHE_PREFIX . $categoryId;
        $output = get_transient($cacheKey);
        
        if (false === $output) {
            $output = $this->generateGrid($categoryId);
            $cacheTime = $this->settings['cache_time'] ?? DAY_IN_SECONDS;
            set_transient($cacheKey, $output, $cacheTime);
        }
        
        return $output;
    }

    private function generateGrid(int $category_id): string {
        $subcategories = get_terms([
            'taxonomy' => 'category',
            'parent' => $category_id,
            'hide_empty' => false,
            'orderby' => 'none'
        ]);
        
        if (empty($subcategories) || is_wp_error($subcategories)) return '';
        
        usort($subcategories, function ($a, $b) {
            return strcasecmp($a->name, $b->name);
        });
        
        $grid_settings = [
            'columns' => $this->settings['columns'] ?? self::MAX_COLUMNS,
            'image_radius' => $this->settings['image_radius'] ?? 3,
            'hover_effect' => !empty($this->settings['hover_effect'])
        ];
        
        ob_start(); ?>
        <div class="scg-grid" 
             style="--scg-columns: <?= esc_attr($grid_settings['columns']); ?>;
                   --scg-image-radius: <?= esc_attr($grid_settings['image_radius']); ?>px;">
            <?php foreach ($subcategories as $cat) : 
                $image = $this->getCategoryImage($cat->term_id); ?>
                <div class="scg-col">
                    <div class="scg-card<?= $grid_settings['hover_effect'] ? ' has-hover' : ''; ?>">
                        <a href="<?= esc_url(get_term_link($cat)); ?>" class="scg-image">
                            <img src="<?= esc_url($image); ?>" 
                                 alt="<?= esc_attr($cat->name); ?>" 
                                 width="120" 
                                 height="96"
                                 loading="lazy">
                        </a>
                        <h3 class="scg-title">
                            <a href="<?= esc_url(get_term_link($cat)); ?>">
                                <?= esc_html($cat->name); ?>
                            </a>
                        </h3>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <?php return ob_get_clean();
    }

    private function getCategoryImage(int $term_id): string {
        $image_id = get_term_meta($term_id, 'logo', true);
        if ($image_id && is_numeric($image_id)) {
            return wp_get_attachment_image_url($image_id, 'scg-thumb');
        }
        return $this->settings['default_image'] ?? plugins_url('assets/placeholder.png', __FILE__);
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
            <form method="post" action="options.php">
                <?php 
                settings_fields('scg_settings_group');
                do_settings_sections('scg-settings');
                submit_button(__('Save Changes', 'smart-cat-grid')); 
                ?>
            </form>
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
            'cache_time',
            __('Cache Duration', 'smart-cat-grid'),
            [$this, 'cacheTimeField'],
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

    public function cacheTimeField(): void {
        $value = $this->settings['cache_time'] ?? DAY_IN_SECONDS; ?>
        <div class="scg-cache-controls">
            <select name="scg_settings[cache_time]">
                <option value="3600" <?php selected($value, 3600); ?>><?php _e('1 Hour', 'smart-cat-grid'); ?></option>
                <option value="43200" <?php selected($value, 43200); ?>><?php _e('12 Hours', 'smart-cat-grid'); ?></option>
                <option value="86400" <?php selected($value, 86400); ?>><?php _e('1 Day', 'smart-cat-grid'); ?></option>
                <option value="604800" <?php selected($value, 604800); ?>><?php _e('1 Week', 'smart-cat-grid'); ?></option>
                <option value="0" <?php selected($value, 0); ?>><?php _e('No Caching', 'smart-cat-grid'); ?></option>
            </select>
            <button type="button" class="button button-danger" id="scg-clear-cache">
                <?php esc_html_e('Clear Cache Now', 'smart-cat-grid'); ?>
            </button>
        </div>
    <?php }

    public function columnsField(): void {
        $value = $this->settings['columns'] ?? self::MAX_COLUMNS; ?>
        <select name="scg_settings[columns]">
            <?php for ($i = self::MIN_COLUMNS; $i <= self::MAX_COLUMNS; $i++) : ?>
                <option value="<?= $i; ?>" <?php selected($value, $i); ?>>
                    <?= $i; ?> <?php _e('Columns', 'smart-cat-grid'); ?>
                </option>
            <?php endfor; ?>
        </select>
    <?php }

    public function imageRadiusField(): void {
        $value = $this->settings['image_radius'] ?? 3; ?>
        <input type="number" 
               name="scg_settings[image_radius]" 
               min="0" 
               max="50" 
               value="<?= esc_attr($value); ?>"> px
    <?php }

    public function hoverEffectField(): void {
        $checked = isset($this->settings['hover_effect']) && 1 === $this->settings['hover_effect'] ? 'checked' : ''; ?>
        <label>
            <input type="checkbox" 
                   name="scg_settings[hover_effect]" 
                   value="1" 
                   <?= $checked; ?>> 
            <?php _e('Enable hover effects', 'smart-cat-grid'); ?>
        </label>
    <?php }

    public function defaultImageField(): void { ?>
        <input type="text" 
               name="scg_settings[default_image]" 
               value="<?= esc_url($this->settings['default_image'] ?? ''); ?>" 
               class="regular-text">
        <button type="button" class="button scg-upload-image">
            <?php _e('Upload Image', 'smart-cat-grid'); ?>
        </button>
    <?php }

    public function validateSettings(array $input): array {
        $output = [];
        
        $output['default_category'] = isset($input['default_category']) 
            ? absint($input['default_category']) 
            : 0;
        
        $output['cache_time'] = isset($input['cache_time'])
            ? absint($input['cache_time'])
            : DAY_IN_SECONDS;
        
        $output['columns'] = isset($input['columns']) && in_array($input['columns'], range(self::MIN_COLUMNS, self::MAX_COLUMNS)) 
            ? absint($input['columns']) 
            : self::MAX_COLUMNS;
        
        $output['image_radius'] = isset($input['image_radius'])
            ? max(0, min(50, absint($input['image_radius'])))
            : 3;
        
        $output['default_image'] = isset($input['default_image'])
            ? esc_url_raw($input['default_image'])
            : '';
        
        $output['hover_effect'] = isset($input['hover_effect']) ? 1 : 0;
        
        wp_cache_delete('scg_settings', 'options');
       
        $this->clearAllCache();
        
        return $output;
    }

    private function clearAllCache(): void {
        global $wpdb;
        
        $transients = $wpdb->get_col(
            "SELECT option_name FROM {$wpdb->options} 
            WHERE option_name LIKE '_transient_scg_cache_%'"
        );
        
        foreach ($transients as $transient) {
            $transient_name = str_replace('_transient_', '', $transient);
            delete_transient($transient_name);
        }
    }

    public function adminAssets(string $hook): void {
        if ('settings_page_scg-settings' !== $hook) return;
        
        wp_enqueue_media();
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
        wp_enqueue_style(
            'scg-front',
            plugins_url('assets/front.css', __FILE__),
            [],
            filemtime(plugin_dir_path(__FILE__) . 'assets/front.css')
        );
    }

    public function ajaxClearCache(): void {
        check_ajax_referer('scg-clear-cache', 'nonce');
        $this->clearAllCache();
        wp_send_json_success(['message' => __('Cache cleared successfully!', 'smart-cat-grid')]);
    }
}

SmartCategoriesGrid::getInstance();

function init_smart_categories_grid() {
    return SmartCategoriesGrid::getInstance();
}

add_action('plugins_loaded', 'init_smart_categories_grid');