<?php
namespace SmartCategoriesGrid;

/**
 * Cache Management Class
 * 
 * Handles caching operations using WordPress transients
 * with prefix-based isolation and error handling
 * 
 * @package SmartCategoriesGrid
 * @since 1.0.0
 */
class Cache {
    /** @var string Prefix for all cache keys */
    private const CACHE_PREFIX = 'scg_cache_';
    
    /** @var int Default cache lifetime in seconds */
    private const DEFAULT_EXPIRATION = 86400; // 24 hours
    
    /**
     * Clears all plugin-specific cache entries
     * 
     * @return bool True on success, false on failure
     * @throws \RuntimeException When database query fails
     */
    public function clear(): bool {
        global $wpdb;
        
        try {
            $sql = $wpdb->prepare(
                "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
                $wpdb->esc_like('_transient_' . self::CACHE_PREFIX) . '%'
            );
            
            $result = $wpdb->query($sql);
            
            if ($result === false) {
                throw new \RuntimeException('Failed to clear cache: ' . $wpdb->last_error);
            }
            
            return true;
        } catch (\Exception $e) {
            error_log('SCG Cache Clear Error: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Sets a cache value with the plugin prefix
     * 
     * @param string $key Cache key
     * @param string $value Cache value
     * @param int $expiration Cache lifetime in seconds
     * @return bool True on success, false on failure
     */
    public function set(string $key, string $value, int $expiration = self::DEFAULT_EXPIRATION): bool {
        if (empty($key)) {
            error_log('SCG Cache Error: Empty cache key provided');
            return false;
        }
        
        return set_transient(
            self::CACHE_PREFIX . sanitize_key($key),
            $value,
            max(0, $expiration)
        );
    }
    
    /**
     * Gets a cached value by key
     * 
     * @param string $key Cache key
     * @return string|false Cached value or false if not found
     */
    public function get(string $key): string|false {
        if (empty($key)) {
            error_log('SCG Cache Error: Empty cache key provided');
            return false;
        }
        
        return get_transient(self::CACHE_PREFIX . sanitize_key($key));
    }
    
    /**
     * Checks if a cache key exists
     * 
     * @param string $key Cache key
     * @return bool True if cache exists, false otherwise
     */
    public function exists(string $key): bool {
        return false !== $this->get($key);
    }
}