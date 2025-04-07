<?php
/*
Plugin Name: Smart Categories Grid
Description: Responsive category grid with caching and advanced settings
Version: 1.2
Author: Your Name
Author URI: your-site.com
Text Domain: smart-cat-grid
*/

defined('ABSPATH') || exit;

class SmartCategoriesGrid {
    
    private $settings;
    private $cache_prefix = 'scg_cache_';
    
    public function __construct() {
        add_action('plugins_loaded', [$this, 'init']);
    }
    
    public function init() {
        $this->settings = get_option('scg_settings');
        
        add_shortcode('categories_grid', [$this, 'render_grid']);
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('admin_enqueue_scripts', [$this, 'admin_assets']);
        add_action('wp_enqueue_scripts', [$this, 'frontend_assets']);
        
        add_action('wp_ajax_scg_clear_cache', [$this, 'ajax_clear_cache']);
    }

    public function render_grid($atts) {
        $atts = shortcode_atts([
            'category_id' => $this->settings['default_category'] ?? 0,
            'force_update' => false
        ], $atts);
        
        $category_id = absint($atts['category_id']);
        if(!$category_id) return '';
        
        $cache_key = $this->cache_prefix . $category_id;
        $output = $atts['force_update'] ? false : get_transient($cache_key);
        
        if(false === $output) {
            $output = $this->generate_grid($category_id);
            $cache_time = $this->settings['cache_time'] ?? DAY_IN_SECONDS;
            set_transient($cache_key, $output, $cache_time);
        }
        
        return $output;
    }

    private function generate_grid($category_id) {
        $subcategories = get_terms([
            'taxonomy' => 'category',
            'parent' => $category_id,
            'hide_empty' => false,
            'orderby' => 'none'
        ]);
        
        if(empty($subcategories) || is_wp_error($subcategories)) return '';
        
        usort($subcategories, function($a, $b) {
            return strcasecmp($a->name, $b->name);
        });
        
        $grid_settings = [
            'columns' => $this->settings['columns'] ?? 6,
            'image_radius' => $this->settings['image_radius'] ?? 3,
            'hover_effect' => isset($this->settings['hover_effect'])
        ];
        
        ob_start(); ?>
        <div class="scg-grid" 
             style="--scg-columns: <?= esc_attr($grid_settings['columns']) ?>;
                   --scg-image-radius: <?= esc_attr($grid_settings['image_radius']) ?>px;">
            <?php foreach($subcategories as $cat) : 
                $image = $this->get_category_image($cat->term_id); ?>
                
                <div class="scg-col">
                    <div class="scg-card<?= $grid_settings['hover_effect'] ? ' has-hover' : '' ?>">
                        <a href="<?= esc_url(get_term_link($cat)) ?>" class="scg-image">
                            <img src="<?= esc_url($image) ?>" 
                                 alt="<?= esc_attr($cat->name) ?>" 
                                 width="120" 
                                 height="96"
                                 loading="lazy">
                        </a>
                        <h3 class="scg-title">
                            <a href="<?= esc_url(get_term_link($cat)) ?>">
                                <?= esc_html($cat->name) ?>
                            </a>
                        </h3>
                    </div>
                </div>
                
            <?php endforeach; ?>
        </div>
        <?php return ob_get_clean();
    }

    private function get_category_image($term_id) {
        $image_id = get_term_meta($term_id, 'logo', true);
        
        if($image_id && is_numeric($image_id)) {
            return wp_get_attachment_image_url($image_id, 'scg-thumb');
        }
        
        return $this->settings['default_image'] ?? plugins_url('assets/placeholder.png', __FILE__);
    }

    public function add_admin_menu() {
        add_options_page(
            'Categories Grid Settings',
            'Categories Grid',
            'manage_options',
            'scg-settings',
            [$this, 'settings_page']
        );
    }

    public function settings_page() { ?>
        <div class="wrap scg-settings-wrap">
            <h1><?php esc_html_e('Categories Grid Settings', 'smart-cat-grid') ?></h1>
            
            <form method="post" action="options.php">
                <?php 
                settings_fields('scg_settings_group');
                do_settings_sections('scg-settings');
                submit_button(__('Save Changes', 'smart-cat-grid')); 
                ?>
                
                <div id="scg-additional-buttons">
                    <button type="button" 
                            class="button button-danger" 
                            id="scg-clear-cache">
                        <?php esc_html_e('Clear Cache', 'smart-cat-grid') ?>
                    </button>
                </div>
            </form>
        </div>
    <?php }

    public function register_settings() {
        register_setting('scg_settings_group', 'scg_settings', [$this, 'validate_settings']);
        
        // General Settings
        add_settings_section(
            'scg_general_section',
            __('General Settings', 'smart-cat-grid'),
            null,
            'scg-settings'
        );
        
        add_settings_field(
            'default_category',
            __('Default Category', 'smart-cat-grid'),
            [$this, 'category_select_field'],
            'scg-settings',
            'scg_general_section'
        );
        
        add_settings_field(
            'cache_time',
            __('Cache Duration', 'smart-cat-grid'),
            [$this, 'cache_time_field'],
            'scg-settings',
            'scg_general_section'
        );
        
        // Display Settings
        add_settings_section(
            'scg_display_section',
            __('Display Settings', 'smart-cat-grid'),
            null,
            'scg-settings'
        );
        
        add_settings_field(
            'columns',
            __('Default Columns', 'smart-cat-grid'),
            [$this, 'columns_field'],
            'scg-settings',
            'scg_display_section'
        );
        
        add_settings_field(
            'image_radius',
            __('Image Border Radius', 'smart-cat-grid'),
            [$this, 'image_radius_field'],
            'scg-settings',
            'scg_display_section'
        );
        
        add_settings_field(
            'hover_effect',
            __('Hover Effect', 'smart-cat-grid'),
            [$this, 'hover_effect_field'],
            'scg-settings',
            'scg_display_section'
        );
        
        add_settings_field(
            'default_image',
            __('Default Image', 'smart-cat-grid'),
            [$this, 'default_image_field'],
            'scg-settings',
            'scg_display_section'
        );
    }

    public function category_select_field() {
        wp_dropdown_categories([
            'show_option_none' => __('Select a category', 'smart-cat-grid'),
            'option_none_value' => 0,
            'name' => 'scg_settings[default_category]',
            'selected' => $this->settings['default_category'] ?? 0,
            'hierarchical' => true
        ]);
    }

    public function cache_time_field() {
        $value = $this->settings['cache_time'] ?? DAY_IN_SECONDS; ?>
        <select name="scg_settings[cache_time]">
            <option value="3600" <?php selected($value, 3600) ?>><?php _e('1 Hour', 'smart-cat-grid') ?></option>
            <option value="43200" <?php selected($value, 43200) ?>><?php _e('12 Hours', 'smart-cat-grid') ?></option>
            <option value="86400" <?php selected($value, 86400) ?>><?php _e('1 Day', 'smart-cat-grid') ?></option>
            <option value="604800" <?php selected($value, 604800) ?>><?php _e('1 Week', 'smart-cat-grid') ?></option>
            <option value="0" <?php selected($value, 0) ?>><?php _e('No Caching', 'smart-cat-grid') ?></option>
        </select>
    <?php }

    public function columns_field() {
        $value = $this->settings['columns'] ?? 6; ?>
        <select name="scg_settings[columns]">
            <option value="2" <?php selected($value, 2) ?>>2 <?php _e('Columns', 'smart-cat-grid') ?></option>
            <option value="3" <?php selected($value, 3) ?>>3 <?php _e('Columns', 'smart-cat-grid') ?></option>
            <option value="4" <?php selected($value, 4) ?>>4 <?php _e('Columns', 'smart-cat-grid') ?></option>
            <option value="6" <?php selected($value, 6) ?>>6 <?php _e('Columns', 'smart-cat-grid') ?></option>
        </select>
    <?php }

    public function image_radius_field() {
        $value = $this->settings['image_radius'] ?? 3; ?>
        <input type="number" 
               name="scg_settings[image_radius]" 
               min="0" 
               max="50" 
               value="<?= esc_attr($value) ?>"> px
    <?php }

    public function hover_effect_field() {
        $checked = isset($this->settings['hover_effect']) && 1 === $this->settings['hover_effect'] ? 'checked' : ''; ?>
        <label>
            <input type="checkbox" 
               name="scg_settings[hover_effect]" 
               value="1" 
               <?= $checked ?>> 
            <?php _e('Enable hover effects', 'smart-cat-grid') ?>
        </label>
    <?php }

    public function default_image_field() { ?>
        <input type="text" 
               name="scg_settings[default_image]" 
               value="<?= esc_url($this->settings['default_image'] ?? '') ?>" 
               class="regular-text">
        <button type="button" class="button scg-upload-image">
            <?php _e('Upload Image', 'smart-cat-grid') ?>
        </button>
    <?php }

    public function validate_settings($input) {
        $output = [];
        
        // Validate numbers
        $output['default_category'] = isset($input['default_category']) 
            ? absint($input['default_category']) 
            : 0;
        
        $output['cache_time'] = isset($input['cache_time'])
            ? absint($input['cache_time'])
            : DAY_IN_SECONDS;
        
        $output['columns'] = isset($input['columns']) && in_array($input['columns'], [2,3,4,6]) 
            ? $input['columns'] 
            : 6;
        
        $output['image_radius'] = isset($input['image_radius'])
            ? max(0, min(50, absint($input['image_radius'])))
            : 3;
        
        // Validate URLs
        $output['default_image'] = isset($input['default_image'])
            ? esc_url_raw($input['default_image'])
            : '';
        
        // Checkboxes
        $output['hover_effect'] = isset($input['hover_effect']) ? 1 : 0;
        
        return $output;
    }

    public function admin_assets($hook) {
        if('settings_page_scg-settings' !== $hook) return;
        
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

    public function frontend_assets() {
        wp_enqueue_style(
            'scg-front',
            plugins_url('assets/front.css', __FILE__),
            [],
            filemtime(plugin_dir_path(__FILE__) . 'assets/front.css')
        );
    }

    public function ajax_clear_cache() {
        check_ajax_referer('scg-clear-cache', 'nonce');
        
        global $wpdb;
        $deleted = $wpdb->query(
            "DELETE FROM {$wpdb->options} 
             WHERE option_name LIKE '_transient_{$this->cache_prefix}%'"
        );
        
        if(false !== $deleted) {
            wp_send_json_success(['message' => __('Cache cleared successfully!', 'smart-cat-grid')]);
        }
        
        wp_send_json_error(['message' => __('Error clearing cache', 'smart-cat-grid')]);
    }
}

new SmartCategoriesGrid();