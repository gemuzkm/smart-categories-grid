<?php
/*
Plugin Name: Smart Categories Grid
Plugin URI: https://github.com/gemuzkm/smart-categories-grid
Description: Responsive category navigation for WordPress. Includes a block and shortcode, server-side caching, category images, display controls and five visual styles.
Version: 2.4.1
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
    private const VERSION = '2.4.1';
    private const BLOCK = 'smart-cat-grid/categories-grid';
    private const FLAG = 'scg_widget_flag';
    private array $settings = [];
    private array $images = [];
    private ?string $placeholder = null;
    private static ?self $instance = null;

    public static function getInstance(): self {
        if (self::$instance === null) self::$instance = new self();
        return self::$instance;
    }
    private function __construct() {
        add_action('plugins_loaded', [$this, 'init']);
        add_action('init', [$this, 'registerAssets']);
        add_action('init', [$this, 'registerBlock'], 11);
        add_action('after_setup_theme', [$this, 'addImageSizes']);
    }
    public function init(): void {
        $settings = get_option('scg_settings', []);
        $this->settings = is_array($settings) ? $settings : [];
        add_shortcode('categories_grid', [$this, 'renderGrid']);
        add_action('wp_enqueue_scripts', [$this, 'frontendAssets']);
        add_action('admin_menu', [$this, 'addAdminMenu']);
        add_action('admin_init', [$this, 'registerSettings']);
        add_action('admin_enqueue_scripts', [$this, 'adminAssets']);
        add_action('wp_ajax_scg_clear_cache', [$this, 'ajaxClearCache']);
        // Runs after updated counts have been stored, including deferred counts.
        add_action('clean_term_cache', [$this, 'onTermCacheClean'], 10, 2);
        foreach (['created_category','edited_category','delete_category'] as $hook) {
            add_action($hook, [$this, 'clearAllCache']);
        }
        foreach (['added_term_meta','updated_term_meta','deleted_term_meta'] as $hook) {
            add_action($hook, [$this, 'onTermMetaChange'], 10, 3);
        }
        foreach (['add_option_widget_text','update_option_widget_text','delete_option_widget_text',
            'add_option_widget_block','update_option_widget_block','delete_option_widget_block',
            'update_option_sidebars_widgets','switch_theme'] as $hook) {
            add_action($hook, [$this, 'clearWidgetCache']);
        }
        add_action('edit_attachment', [$this, 'clearAllCache']);
        add_action('delete_attachment', [$this, 'clearAllCache']);
    }
    public function addImageSizes(): void {
        add_image_size('scg-thumb', 120, 96, true);
        add_image_size('scg-thumb-2x', 240, 192, true);
    }
    public static function onActivationStatic(): void {
        add_image_size('scg-thumb', 120, 96, true);
        add_image_size('scg-thumb-2x', 240, 192, true);
        add_option('scg_show_regenerate_notice', true);
        delete_option(self::FLAG);
        delete_transient('scg_widget_has_shortcode');
    }
    private function styleFile(): string {
        return defined('SCRIPT_DEBUG') && SCRIPT_DEBUG ? 'assets/front.css' : 'assets/front.min.css';
    }
    // The file time changes the asset URL whenever a file is replaced, even if the version number is not.
    private function assetVersion(string $file): string {
        $path = plugin_dir_path(__FILE__) . $file;
        return is_file($path) ? self::VERSION . '.' . filemtime($path) : self::VERSION;
    }
    public function registerAssets(): void {
        load_plugin_textdomain('smart-cat-grid', false, dirname(plugin_basename(__FILE__)) . '/languages');
        wp_register_style('scg-front', plugins_url($this->styleFile(), __FILE__), [], $this->assetVersion($this->styleFile()));
        if (apply_filters('scg_inline_styles', true)) {
            wp_style_add_data('scg-front', 'path', plugin_dir_path(__FILE__) . $this->styleFile());
        }
        if (is_admin()) {
            wp_register_script('scg-block-editor', plugins_url('block/index.js', __FILE__),
                ['wp-blocks','wp-element','wp-block-editor','wp-components','wp-i18n','wp-core-data','wp-server-side-render'], $this->assetVersion('block/index.js'), true);
            wp_set_script_translations('scg-block-editor', 'smart-cat-grid', plugin_dir_path(__FILE__) . 'languages');
        }
    }
    public function registerBlock(): void {
        register_block_type(plugin_dir_path(__FILE__) . 'block', ['render_callback' => [$this, 'renderBlock']]);
    }
    public function renderBlock(array $a, string $content = '', $block = null): string {
        if (!empty($a['auto']) && defined('REST_REQUEST') && REST_REQUEST) {
            return '<p>' . esc_html__('Auto mode: subcategories of the current category will be rendered on the front end.', 'smart-cat-grid') . '</p>';
        }
        $atts = [];
        foreach (['categoryId'=>'category_id','showImages'=>'show_images','hoverEffect'=>'hover_effect',
            'imageRadius'=>'image_radius','buttonColor'=>'button_color','showViewAll'=>'show_view_all',
            'hideEmpty'=>'hide_empty','auto'=>'auto','type'=>'type','exclude'=>'exclude',
            'limit'=>'limit','columns'=>'columns','style'=>'style'] as $from=>$to) {
            if (!isset($a[$from])) continue;
            // 0 is the block's "Default (from settings)" choice, not an explicit parent.
            if ($from === 'categoryId' && !absint($a[$from])) continue;
            $atts[$to] = is_bool($a[$from]) ? ($a[$from] ? 'true' : 'false') : $a[$from];
        }
        $html = $this->renderGrid($atts);
        return $html === '' ? '' : '<div ' . get_block_wrapper_attributes() . '>' . $html . '</div>';
    }
    private function hasGrid(string $text): bool {
        return has_shortcode($text, 'categories_grid') || strpos($text, self::BLOCK) !== false;
    }
    public function clearWidgetCache(): void { delete_option(self::FLAG); }
    private function widgetsHaveGrid(): bool {
        $flag = get_option(self::FLAG, '');
        if ($flag === '0' || $flag === '1') return $flag === '1';
        $found = false;
        // Scan only widgets assigned to active sidebars, not wp_inactive_widgets.
        $sidebars = wp_get_sidebars_widgets();
        $text = get_option('widget_text', []);
        $blocks = get_option('widget_block', []);
        foreach ((array) $sidebars as $sidebar=>$widgets) {
            if ($sidebar === 'wp_inactive_widgets') continue;
            foreach ((array) $widgets as $id) {
                if (preg_match('/^(text|block)-(\d+)$/', $id, $m)) {
                    $item = $m[1] === 'text' ? ($text[(int)$m[2]] ?? []) : ($blocks[(int)$m[2]] ?? []);
                    if ($this->hasGrid((string)($item['text'] ?? $item['content'] ?? ''))) $found = true;
                }
            }
        }
        update_option(self::FLAG, $found ? '1' : '0', true);
        return $found;
    }
    public function frontendAssets(): void {
        global $post;
        $found = $post instanceof WP_Post && $this->hasGrid($post->post_content);
        if (!$found && $post instanceof WP_Post) {
            $el = get_post_meta($post->ID, '_elementor_data', true);
            $found = is_string($el) && strpos($el, 'categories_grid') !== false;
        }
        if (!$found) $found = $this->widgetsHaveGrid();
        if (apply_filters('scg_has_shortcode', $found)) wp_enqueue_style('scg-front');
    }
    private function styles(): string {
        if (wp_style_is('scg-front', 'done')) return '';
        if (did_action('wp_head') && !doing_action('wp_head') && apply_filters('scg_print_late_styles', true)) {
            // Do not mark as done: this render may be discarded by an excerpt/SEO filter.
            return '<link rel="stylesheet" href="' . esc_url(add_query_arg('ver', $this->assetVersion($this->styleFile()), plugins_url($this->styleFile(), __FILE__))) . '" media="all">';
        }
        wp_enqueue_style('scg-front');
        return '';
    }
    public function onTermCacheClean($ids, $taxonomy): void {
        if ($taxonomy === 'category') $this->clearAllCache();
    }
    public function onTermMetaChange($id, $term, $key): void {
        if ($key === 'logo') $this->clearAllCache();
    }
    public function clearAllCache(): void {
        // Unique tokens avoid lost-increment races between simultaneous edits.
        update_option('scg_cache_gen', wp_generate_uuid4(), true);
        $this->images = [];
    }
    private function currentCategory(): int {
        if (is_category()) return absint(get_queried_object_id());
        $id = is_single() ? get_queried_object_id() : get_the_ID();
        if (!$id) return 0;
        $cats = get_the_category($id);
        if (empty($cats) || is_wp_error($cats)) return 0;
        $assigned = wp_list_pluck($cats, 'term_id');
        foreach (['_yoast_wpseo_primary_category','rank_math_primary_category'] as $key) {
            $primary = absint(get_post_meta($id, $key, true));
            if (in_array($primary, array_map('intval', $assigned), true)) return $primary;
        }
        $best = 0; $depth = -1;
        foreach ($cats as $cat) {
            $d = count(get_ancestors($cat->term_id, 'category'));
            if ($d > $depth || ($d === $depth && $cat->term_id < $best)) { $best = (int)$cat->term_id; $depth = $d; }
        }
        return $best;
    }
    private function setting(array $a, string $key, $default) {
        return isset($a[$key]) && is_scalar($a[$key]) && $a[$key] !== '' ? $a[$key] : $default;
    }
    private function ids(string $value): array {
        $ids = array_values(array_unique(array_filter(array_map('absint', explode(',', $value)))));
        sort($ids, SORT_NUMERIC);
        return $ids;
    }
    public function renderGrid($atts): string {
        $a = is_array($atts) ? $atts : [];
        $s = $this->settings;
        $parent = filter_var($this->setting($a, 'auto', false), FILTER_VALIDATE_BOOLEAN)
            ? $this->currentCategory()
            : absint($this->setting($a, 'category_id', $s['default_category'] ?? 0));
        $top = $this->setting($a, 'type', 'subcategories') === 'top-level'
            && !filter_var($this->setting($a, 'auto', false), FILTER_VALIDATE_BOOLEAN);
        if ($top) $parent = 0;
        elseif (!$parent) return '';
        $style = (string)$this->setting($a, 'style', $s['grid_style'] ?? 'classic');
        if (!in_array($style, ['classic','modern','minimal','card','text'], true)) $style = 'classic';
        $v = [
            'parent'=>$parent,
            'exclude'=>$this->ids((string)$this->setting($a, 'exclude', '') . ',' . ($s['exclude_categories'] ?? '')),
            'style'=>$style,
            'columns'=>max(2, min(6, absint($this->setting($a, 'columns', $s['columns'] ?? 6)))),
            'radius'=>min(50, absint($this->setting($a, 'image_radius', $s['image_radius'] ?? 3))),
            'color'=>sanitize_hex_color($this->setting($a, 'button_color', $s['button_color'] ?? '#b93434')) ?: '#b93434',
            'hover'=>filter_var($this->setting($a, 'hover_effect', !empty($s['hover_effect'])), FILTER_VALIDATE_BOOLEAN),
            'images'=>$style !== 'text' && filter_var($this->setting($a, 'show_images', !empty($s['default_show_images'])), FILTER_VALIDATE_BOOLEAN),
            'limit'=>absint($this->setting($a, 'limit', $s['default_limit'] ?? 0)),
            'all'=>filter_var($this->setting($a, 'show_view_all', true), FILTER_VALIDATE_BOOLEAN),
            'hide'=>filter_var($this->setting($a, 'hide_empty', false), FILTER_VALIDATE_BOOLEAN),
        ];
        $ttl = min(WEEK_IN_SECONDS, absint($s['cache_time'] ?? DAY_IN_SECONDS));
        $bypass = !$ttl || filter_var($this->setting($a, 'force_update', false), FILTER_VALIDATE_BOOLEAN);
        // One stable key per configuration: the generation token is stored inside the entry,
        // so an invalidation overwrites the entry instead of leaving an orphaned transient.
        $gen = (string)get_option('scg_cache_gen', '1');
        $key = 'scg_cache_' . md5(wp_json_encode([$v, self::VERSION, get_locale()]));
        $cached = $bypass ? false : get_transient($key);
        $html = is_array($cached) && isset($cached['html'], $cached['gen']) && $cached['gen'] === $gen ? (string)$cached['html'] : null;
        if ($html === null) {
            $html = $this->generate($v);
            if (!$bypass) set_transient($key, ['html'=>$html, 'gen'=>$gen], $ttl);
        }
        return $html === '' ? '' : $this->styles() . $html;
    }
    private function generate(array $v): string {
        // Core's hierarchical query keeps empty parents with populated descendants;
        // parent still restricts the results to direct children. No custom tree scan.
        $cats = get_terms(['taxonomy'=>'category','parent'=>$v['parent'],'exclude'=>$v['exclude'],
            'hide_empty'=>$v['hide'],'hierarchical'=>$v['hide'],'orderby'=>'name','order'=>'ASC',
            'update_term_meta_cache'=>false]);
        if (is_wp_error($cats) || !$cats) return '';
        $collator = class_exists('Collator') ? Collator::create(get_locale()) : null;
        if ($collator) $collator->setStrength(Collator::SECONDARY);
        usort($cats, static function($a, $b) use ($collator): int {
            $r = $collator ? $collator->compare($a->name, $b->name) : false;
            if ($r === false) {
                $r = function_exists('mb_strtolower') ? strcmp(mb_strtolower($a->name, 'UTF-8'), mb_strtolower($b->name, 'UTF-8')) : strcasecmp($a->name, $b->name);
            }
            return $r ?: ($a->term_id <=> $b->term_id);
        });
        $total = count($cats);
        if ($v['limit']) $cats = array_slice($cats, 0, $v['limit']);
        if ($v['images']) {
            update_termmeta_cache(wp_list_pluck($cats, 'term_id'));
            $ids = [absint($this->settings['default_image_id'] ?? 0)];
            foreach ($cats as $cat) $ids[] = absint(get_term_meta($cat->term_id, 'logo', true));
            _prime_post_caches(array_values(array_unique(array_filter($ids))), false, true);
        }
        $hover = $v['hover'] ? ' has-hover' : '';
        $html = '<div class="scg-grid scg-style-' . esc_attr($v['style']) . esc_attr($hover) . '" style="--scg-columns:' . esc_attr($v['columns']) . ';--scg-image-radius:' . esc_attr($v['radius']) . 'px;--scg-button-color:' . esc_attr($v['color']) . ';">';
        $index = 0;
        foreach ($cats as $cat) {
            $link = get_term_link($cat);
            if (is_wp_error($link)) continue;
            $html .= '<div class="scg-col"><div class="scg-card' . esc_attr($hover) . '">';
            $image = $v['images'] ? $this->categoryImage((int)$cat->term_id) : null;
            if ($image) {
                $lazy = wp_lazy_loading_enabled('img', 'scg_grid') && $index >= $v['columns'];
                $html .= '<a class="scg-image" href="' . esc_url($link) . '" tabindex="-1" aria-hidden="true" style="text-decoration:none;border:0;box-shadow:none">';
                $html .= '<img src="' . esc_url($image['src']) . '" alt="' . esc_attr($cat->name) . '" width="120" height="96" decoding="async"';
                if ($image['srcset']) $html .= ' srcset="' . esc_attr($image['srcset']) . '"';
                if ($lazy) $html .= ' loading="lazy"';
                $html .= '></a>';
                $index++;
            }
            $html .= '<div class="scg-title"><a href="' . esc_url($link) . '">' . esc_html($cat->name) . '</a></div></div></div>';
        }
        if ($v['all'] && $v['limit'] && $total > $v['limit']) {
            $link = $v['parent'] ? get_term_link($v['parent'], 'category') : ($this->settings['view_all_url'] ?? '');
            if (!is_wp_error($link) && $link) $html .= '<div class="scg-view-all"><a class="scg-view-all-link" href="' . esc_url($link) . '">' . esc_html__('View All', 'smart-cat-grid') . '</a></div>';
        }
        return $html . '</div>';
    }
    private function attachment(int $id): ?array {
        if (!$id) return null;
        $src = wp_get_attachment_image_url($id, 'scg-thumb');
        if (!$src) return null;
        $hi = wp_get_attachment_image_src($id, 'scg-thumb-2x');
        $set = is_array($hi) && !empty($hi[3]) && (int)$hi[1] === 240 && (int)$hi[2] === 192
            ? esc_url($src) . ' 1x, ' . esc_url($hi[0]) . ' 2x' : '';
        return ['src'=>$src, 'srcset'=>$set];
    }
    private function categoryImage(int $id): ?array {
        if (array_key_exists($id, $this->images)) return $this->images[$id];
        $image = $this->attachment(absint(get_term_meta($id, 'logo', true)));
        if (!$image) $image = $this->attachment(absint($this->settings['default_image_id'] ?? 0));
        if (!$image) {
            $url = (string)($this->settings['default_image'] ?? '');
            if (!$url) {
                if ($this->placeholder === null) $this->placeholder = (string)apply_filters('scg_placeholder_image', plugins_url('assets/placeholder.png', __FILE__));
                $url = $this->placeholder;
            }
            if ($url) $image = ['src'=>$url, 'srcset'=>''];
        }
        return $this->images[$id] = $image;
    }
    public function addAdminMenu(): void {
        add_options_page(__('Categories Grid Settings', 'smart-cat-grid'), __('Categories Grid', 'smart-cat-grid'), 'manage_options', 'scg-settings', [$this, 'settingsPage']);
    }
    public function registerSettings(): void {
        register_setting('scg_settings_group', 'scg_settings', ['type'=>'array','default'=>[], 'sanitize_callback'=>[$this,'validateSettings']]);
    }
    public function validateSettings($input): array {
        $a = is_array($input) ? array_filter($input, 'is_scalar') : [];
        $out = [];
        foreach (['default_category','default_limit','image_radius','columns','cache_time'] as $key) $out[$key] = absint($a[$key] ?? 0);
        $out['columns'] = max(2, min(6, $out['columns'] ?: 6));
        $out['image_radius'] = min(50, $out['image_radius']);
        $out['cache_time'] = min(WEEK_IN_SECONDS, $out['cache_time']);
        $out['exclude_categories'] = implode(',', $this->ids((string)($a['exclude_categories'] ?? '')));
        foreach (['default_image','view_all_url'] as $key) $out[$key] = esc_url_raw($a[$key] ?? '');
        $out['default_image_id'] = $out['default_image'] ? absint(attachment_url_to_postid($out['default_image'])) : 0;
        foreach (['hover_effect','default_show_images'] as $key) $out[$key] = empty($a[$key]) ? 0 : 1;
        $out['button_color'] = sanitize_hex_color($a['button_color'] ?? '') ?: '#b93434';
        $out['grid_style'] = in_array($a['grid_style'] ?? '', ['classic','modern','minimal','card','text'], true) ? $a['grid_style'] : 'classic';
        $this->settings = $out;
        $this->clearAllCache();
        $this->clearWidgetCache();
        return $out;
    }
    public function settingsPage(): void {
        if (!current_user_can('manage_options')) return;
        echo '<div class="wrap scg-settings-wrap"><h1>' . esc_html__('Categories Grid Settings', 'smart-cat-grid') . '</h1>';
        if (get_option('scg_show_regenerate_notice')) {
            delete_option('scg_show_regenerate_notice');
            echo '<div class="notice notice-info is-dismissible"><p>' . esc_html__('Plugin activated! For best image quality, please regenerate thumbnails using a plugin like "Regenerate Thumbnails".', 'smart-cat-grid') . '</p></div>';
        }
        echo '<form method="post" action="options.php">';
        settings_fields('scg_settings_group');
        $sections = [
            __('General Settings', 'smart-cat-grid') => [
                'default_category'=>__('Default Category', 'smart-cat-grid'),
                'exclude_categories'=>__('Exclude Categories', 'smart-cat-grid'),
                'cache_time'=>__('Cache Duration', 'smart-cat-grid'),
                'default_limit'=>__('Default Category Limit', 'smart-cat-grid'),
                'view_all_url'=>__('View All URL', 'smart-cat-grid')],
            __('Display Settings', 'smart-cat-grid') => [
                'columns'=>__('Default Columns', 'smart-cat-grid'),
                'image_radius'=>__('Image Border Radius', 'smart-cat-grid'),
                'hover_effect'=>__('Hover Effect', 'smart-cat-grid'),
                'default_image'=>__('Default Image', 'smart-cat-grid'),
                'default_show_images'=>__('Show Images by Default', 'smart-cat-grid'),
                'button_color'=>__('Button Color', 'smart-cat-grid'),
                'grid_style'=>__('Grid Style', 'smart-cat-grid')]];
        $help = [
            'exclude_categories'=>__('Comma-separated category IDs to exclude (e.g., 10,20,30).', 'smart-cat-grid'),
            'default_limit'=>__('Default number of categories to display. 0 = no limit.', 'smart-cat-grid'),
            'view_all_url'=>__('URL for the "View All" button for top-level categories. Leave empty to hide.', 'smart-cat-grid'),
            'default_show_images'=>__('If checked, images will be shown unless overridden by the shortcode.', 'smart-cat-grid'),
            'button_color'=>__('Color for the "View All" button.', 'smart-cat-grid'),
            'grid_style'=>__('Visual style for the category grid.', 'smart-cat-grid')];
        $checkboxes = [
            'hover_effect'=>__('Enable hover effects', 'smart-cat-grid'),
            'default_show_images'=>__('Show images by default', 'smart-cat-grid')];
        $defaults = ['columns'=>6,'image_radius'=>3,'cache_time'=>DAY_IN_SECONDS,'button_color'=>'#b93434','grid_style'=>'classic'];
        foreach ($sections as $title=>$fields) {
            echo '<h2>' . esc_html($title) . '</h2><table class="form-table" role="presentation">';
            foreach ($fields as $key=>$label) {
                $value = $this->settings[$key] ?? $defaults[$key] ?? '';
                $name = 'scg_settings[' . $key . ']';
                echo '<tr><th scope="row"><label for="scg-' . esc_attr($key) . '">' . esc_html($label) . '</label></th><td>';
                if ($key === 'default_category') {
                    wp_dropdown_categories(['id'=>'scg-default_category','name'=>$name,'selected'=>$value,'hierarchical'=>true,'hide_empty'=>false,'show_option_none'=>__('Select a category', 'smart-cat-grid'),'option_none_value'=>0]);
                } elseif (isset($checkboxes[$key])) {
                    echo '<label><input id="scg-' . esc_attr($key) . '" type="checkbox" name="' . esc_attr($name) . '" value="1" ' . checked(!empty($value), true, false) . '> ' . esc_html($checkboxes[$key]) . '</label>';
                } elseif ($key === 'grid_style' || $key === 'cache_time' || $key === 'columns') {
                    $options = $key === 'grid_style' ? ['classic'=>__('Classic', 'smart-cat-grid'),'modern'=>__('Modern', 'smart-cat-grid'),'minimal'=>__('Minimal', 'smart-cat-grid'),'card'=>__('Card', 'smart-cat-grid'),'text'=>__('Text Only', 'smart-cat-grid')]
                        : ($key === 'columns' ? [2=>2,3=>3,4=>4,5=>5,6=>6] : [0=>__('No Caching', 'smart-cat-grid'),3600=>__('1 Hour', 'smart-cat-grid'),43200=>__('12 Hours', 'smart-cat-grid'),86400=>__('1 Day', 'smart-cat-grid'),604800=>__('1 Week', 'smart-cat-grid')]);
                    echo '<select id="scg-' . esc_attr($key) . '" name="' . esc_attr($name) . '">';
                    foreach ($options as $v=>$text) echo '<option value="' . esc_attr($v) . '" ' . selected($value, $v, false) . '>' . esc_html($text) . '</option>';
                    echo '</select>';
                    if ($key === 'cache_time') echo ' <button type="button" id="scg-clear-cache" class="button">' . esc_html__('Clear Cache', 'smart-cat-grid') . '</button>';
                } else {
                    $type = in_array($key, ['view_all_url','default_image'], true) ? 'url' : (in_array($key, ['image_radius','default_limit'], true) ? 'number' : 'text');
                    echo '<input id="scg-' . esc_attr($key) . '" type="' . esc_attr($type) . '" min="0" ' . ($key === 'image_radius' ? 'max="50" ' : '') . 'name="' . esc_attr($name) . '" value="' . esc_attr($value) . '" class="' . ($key === 'button_color' ? 'scg-color-picker' : 'regular-text') . '">';
                    if ($key === 'default_image') echo ' <button type="button" class="button scg-upload-image">' . esc_html__('Select Image', 'smart-cat-grid') . '</button>';
                }
                if (isset($help[$key])) echo '<p class="description">' . esc_html($help[$key]) . '</p>';
                echo '</td></tr>';
            }
            echo '</table>';
        }
        submit_button();
        echo '</form>';
        echo '<h2>' . esc_html__('Image Size Information', 'smart-cat-grid') . '</h2>';
        echo '<p>' . esc_html__('This plugin registers two image sizes: 120x96 (1x) and 240x192 (2x for Retina displays). New uploads are resized automatically.', 'smart-cat-grid') . '</p>';
        echo '<p>' . esc_html__('For existing images, regenerate thumbnails using a plugin like "Regenerate Thumbnails".', 'smart-cat-grid') . '</p>';
        echo '<h2>' . esc_html__('Usage', 'smart-cat-grid') . '</h2>';
        echo '<p>' . esc_html__('Block editor: add the "Categories Grid" block and configure it in the sidebar.', 'smart-cat-grid') . '</p>';
        echo '<p>' . esc_html__('Shortcodes: [categories_grid type="top-level"], [categories_grid category_id="X"], [categories_grid auto="true" limit="200"]. Use exclude="X,Y", show_images="false", limit="N", hide_empty="true", show_view_all="false" as needed.', 'smart-cat-grid') . '</p></div>';
    }
    public function adminAssets(string $hook): void {
        if ($hook !== 'settings_page_scg-settings') return;
        wp_enqueue_media();
        wp_enqueue_style('wp-color-picker');
        wp_enqueue_style('scg-admin', plugins_url('assets/admin.css', __FILE__), [], $this->assetVersion('assets/admin.css'));
        wp_enqueue_script('scg-admin', plugins_url('assets/admin.js', __FILE__), ['jquery','wp-color-picker','wp-a11y'], $this->assetVersion('assets/admin.js'), true);
        wp_localize_script('scg-admin','scg_admin',['nonce'=>wp_create_nonce('scg-clear-cache'),'ajax_url'=>admin_url('admin-ajax.php'),'i18n'=>[
            'clear_confirm'=>__('Are you sure?', 'smart-cat-grid'),'clearing'=>__('Clearing...', 'smart-cat-grid'),
            'clear_cache'=>__('Clear Cache', 'smart-cat-grid'),'upload_title'=>__('Select Image', 'smart-cat-grid'),
            'use_image'=>__('Use This Image', 'smart-cat-grid'),'clear_failed'=>__('Failed to clear cache', 'smart-cat-grid')]]);
    }
    public function ajaxClearCache(): void {
        if (!current_user_can('manage_options')) wp_send_json_error(['message'=>__('Permission denied.', 'smart-cat-grid')],403);
        check_ajax_referer('scg-clear-cache','nonce');
        $this->clearAllCache(); $this->clearWidgetCache();
        wp_send_json_success(['message'=>__('Cache cleared successfully!', 'smart-cat-grid')]);
    }
}
SmartCategoriesGrid::getInstance();
