<?php
namespace SmartCategoriesGrid;

/**
 * Settings Management Class
 * 
 * Handles validation and storage of plugin settings
 * 
 * @package SmartCategoriesGrid
 * @since 1.0.0
 */
class Settings {
    /** @var string Option name in WordPress database */
    private const OPTION_NAME = 'scg_settings';
    
    /** @var int Minimum number of columns allowed */
    private const MIN_COLUMNS = 2;
    
    /** @var int Maximum number of columns allowed */
    private const MAX_COLUMNS = 6;
    
    /** @var int Maximum image radius in pixels */
    private const MAX_IMAGE_RADIUS = 50;
    
    /**
     * Default settings values
     * 
     * @var array<string, mixed>
     */
    private array $defaults = [
        'default_category' => 0,
        'cache_time' => DAY_IN_SECONDS,
        'columns' => 6,
        'image_radius' => 3,
        'hover_effect' => false,
        'default_image' => ''
    ];
    
    /**
     * Validates and sanitizes input settings
     * 
     * @param array $input Raw input settings
     * @return array Sanitized settings
     */
    public function validate(array $input): array {
        try {
            return [
                'default_category' => $this->validateCategory($input),
                'cache_time' => $this->validateCacheTime($input),
                'columns' => $this->validateColumns($input['columns'] ?? 6),
                'image_radius' => $this->validateImageRadius($input['image_radius'] ?? 3),
                'default_image' => $this->validateImageUrl($input),
                'hover_effect' => $this->validateHoverEffect($input)
            ];
        } catch (\Exception $e) {
            // Log error and return defaults if validation fails
            error_log('SCG Settings Validation Error: ' . $e->getMessage());
            return $this->defaults;
        }
    }
    
    /**
     * Validates category ID
     * 
     * @param array $input Input settings
     * @return int Valid category ID
     */
    private function validateCategory(array $input): int {
        $category_id = absint($input['default_category'] ?? 0);
        
        // Verify category exists if ID is provided
        if ($category_id > 0 && !term_exists($category_id, 'category')) {
            return 0;
        }
        
        return $category_id;
    }
    
    /**
     * Validates cache duration
     * 
     * @param array $input Input settings
     * @return int Cache duration in seconds
     */
    private function validateCacheTime(array $input): int {
        $cache_time = absint($input['cache_time'] ?? DAY_IN_SECONDS);
        
        // Validate against allowed cache durations
        $allowed_times = [3600, 43200, 86400, 604800, 0];
        
        return in_array($cache_time, $allowed_times) ? $cache_time : DAY_IN_SECONDS;
    }
    
    /**
     * Validates number of columns
     * 
     * @param int|string $value Number of columns
     * @return int Validated column count
     */
    private function validateColumns($value): int {
        $columns = absint($value);
        return min(max($columns, self::MIN_COLUMNS), self::MAX_COLUMNS);
    }
    
    /**
     * Validates image border radius
     * 
     * @param int|string $value Border radius value
     * @return int Validated radius in pixels
     */
    private function validateImageRadius($value): int {
        $radius = absint($value);
        return min(max($radius, 0), self::MAX_IMAGE_RADIUS);
    }
    
    /**
     * Validates image URL
     * 
     * @param array $input Input settings
     * @return string Sanitized URL or empty string
     */
    private function validateImageUrl(array $input): string {
        $url = trim($input['default_image'] ?? '');
        return esc_url_raw($url) ?: '';
    }
    
    /**
     * Validates hover effect setting
     * 
     * @param array $input Input settings
     * @return bool Whether hover effect is enabled
     */
    private function validateHoverEffect(array $input): bool {
        return !empty($input['hover_effect']);
    }
    
    /**
     * Gets default settings
     * 
     * @return array Default settings values
     */
    public function getDefaults(): array {
        return $this->defaults;
    }
}