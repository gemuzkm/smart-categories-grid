<?php
namespace SmartCategoriesGrid;

class Cache {
    private const CACHE_PREFIX = 'scg_cache_';
    
    public function clear(): bool {
        global $wpdb;
        
        $sql = $wpdb->prepare(
            "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
            $wpdb->esc_like('_transient_' . self::CACHE_PREFIX) . '%'
        );
        
        return (bool) $wpdb->query($sql);
    }
    
    public function set(string $key, string $value, int $expiration): bool {
        return set_transient(self::CACHE_PREFIX . $key, $value, $expiration);
    }
    
    public function get(string $key): string|false {
        return get_transient(self::CACHE_PREFIX . $key);
    }
}