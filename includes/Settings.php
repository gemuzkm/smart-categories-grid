<?php
namespace SmartCategoriesGrid;

class Settings {
    private const OPTION_NAME = 'scg_settings';
    private array $defaults = [
        'default_category' => 0,
        'cache_time' => DAY_IN_SECONDS,
        'columns' => 6,
        'image_radius' => 3,
        'hover_effect' => false,
        'default_image' => ''
    ];
    
    public function validate(array $input): array {
        $output = [];
        
        $output['default_category'] = absint($input['default_category'] ?? 0);
        $output['cache_time'] = absint($input['cache_time'] ?? DAY_IN_SECONDS);
        $output['columns'] = $this->validateColumns($input['columns'] ?? 6);
        $output['image_radius'] = $this->validateImageRadius($input['image_radius'] ?? 3);
        $output['default_image'] = esc_url_raw($input['default_image'] ?? '');
        $output['hover_effect'] = !empty($input['hover_effect']);
        
        return $output;
    }
    
    private function validateColumns($value): int {
        $value = absint($value);
        return min(max($value, 2), 6);
    }
    
    private function validateImageRadius($value): int {
        $value = absint($value);
        return min(max($value, 0), 50);
    }
}