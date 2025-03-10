<?php
/**
 * Stock Sync Tools Class - OPTIMIZED VERSION
 *
 * @author    Expert PrestaShop Developer
 * @copyright 2025
 * @license   Commercial
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

class StockSyncTools
{
    // Cache para mejorar rendimiento
    private static $tokenCache = [];
    private static $productCache = [];
    
    /**
     * Generate a secure token
     *
     * @param int $length Token length
     * @return string
     */
    public static function generateSecureToken($length = 64)
    {
        if (function_exists('random_bytes')) {
            return bin2hex(random_bytes($length / 2));
        }
        
        if (function_exists('openssl_random_pseudo_bytes')) {
            return bin2hex(openssl_random_pseudo_bytes($length / 2));
        }
        
        // Fallback to less secure method
        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $token = '';
        
        for ($i = 0; $i < $length; $i++) {
            $token .= $characters[mt_rand(0, strlen($characters) - 1)];
        }
        
        return $token;
    }
    
    /**
     * Generate a token for API request
     *
     * @param string $api_key API key
     * @return string
     */
    public static function generateTokenForRequest($api_key)
    {
        // SOLUCIÓN DEFINITIVA: Devolver siempre directamente la API key como token
        return $api_key;
    }
    
    /**
     * Validate token
     *
     * @param string $token Token to validate
     * @param string $api_key API key (if not provided, check against all active stores)
     * @return bool
     */
    public static function validateToken($token, $api_key = null)
    {
        // SOLUCIÓN MEJORADA: Verificación directa contra API keys
        if (empty($token)) {
            return false;
        }
        
        // Primera comprobación: ¿El token coincide exactamente con alguna API key?
        $stores = StockSyncStore::getActiveStores();
        foreach ($stores as $store) {
            if ($token === $store['api_key']) {
                return true;
            }
        }
        
        // Segunda comprobación: ¿El api_key proporcionado coincide con el token?
        if ($api_key !== null && $token === $api_key) {
            return true;
        }
        
        // Tercera comprobación: Verificar formato de token compuesto (legado)
        if (strpos($token, '.') !== false) {
            $parts = explode('.', $token);
            
            if (count($parts) != 2) {
                return false;
            }
            
            $hash = $parts[0];
            $timestamp = (int) $parts[1];
            $expiry = (int) Configuration::get('STOCK_SYNC_TOKEN_EXPIRY', 86400); 
            
            // Comprobar si el token ha caducado (7 días)
            if (time() - $timestamp > $expiry * 7) {
                return false;
            }
            
            $salt = Configuration::get('STOCK_SYNC_TOKEN_SALT', '');
            
            if (!$salt) {
                $salt = self::generateSecureToken(16);
                Configuration::updateValue('STOCK_SYNC_TOKEN_SALT', $salt);
            }
            
            // Verificaciones contra API keys específicas o todas las tiendas
            if ($api_key) {
                $expected_hash = hash('sha256', $api_key . $salt . $timestamp);
                return $hash === $expected_hash;
            } else {
                foreach ($stores as $store) {
                    $expected_hash = hash('sha256', $store['api_key'] . $salt . $timestamp);
                    if ($hash === $expected_hash) {
                        return true;
                    }
                }
            }
        }
        
        // DEPURACIÓN: Registrar el token fallido si está en modo debug
        if (Configuration::get('STOCK_SYNC_DEBUG_MODE')) {
            $log_file = _PS_MODULE_DIR_ . 'stocksyncmodule/logs/token_debug.log';
            file_put_contents(
                $log_file, 
                date('Y-m-d H:i:s') . ' - Validation failed for token: ' . $token . "\n", 
                FILE_APPEND
            );
        }
        
        return false;
    }
    
    /**
     * Get old product quantity from cache
     *
     * @param int $id_product Product ID
     * @return float|null
     */
    public static function getOldProductQuantity($id_product)
    {
        $cache_id = 'StockSync_product_' . (int) $id_product;
        
        // Use internal cache instead of PrestaShop Cache for better performance
        if (isset(self::$productCache[$cache_id])) {
            return (float) self::$productCache[$cache_id];
        }
        
        // If not in cache, store current quantity
        $quantity = StockAvailable::getQuantityAvailableByProduct($id_product);
        self::$productCache[$cache_id] = $quantity;
        
        return null;
    }
    
    /**
     * Get old combination quantity from cache
     *
     * @param int $id_product Product ID
     * @param int $id_product_attribute Product attribute ID
     * @return float|null
     */
    public static function getOldCombinationQuantity($id_product, $id_product_attribute)
    {
        $cache_id = 'StockSync_combination_' . (int) $id_product . '_' . (int) $id_product_attribute;
        
        // Use internal cache instead of PrestaShop Cache for better performance
        if (isset(self::$productCache[$cache_id])) {
            return (float) self::$productCache[$cache_id];
        }
        
        // If not in cache, store current quantity
        $quantity = StockAvailable::getQuantityAvailableByProduct($id_product, $id_product_attribute);
        self::$productCache[$cache_id] = $quantity;
        
        return null;
    }
    
    /**
     * Format date for display
     *
     * @param string $date Date string
     * @param bool $with_time Include time
     * @return string
     */
    public static function formatDate($date, $with_time = true)
    {
        if (!$date) {
            return '-';
        }
        
        $format = $with_time ? 'Y-m-d H:i:s' : 'Y-m-d';
        return date($format, strtotime($date));
    }
    
    /**
     * Format time elapsed
     *
     * @param string $date Date string
     * @return string
     */
    public static function timeElapsed($date)
    {
        if (!$date) {
            return '-';
        }
        
        $timestamp = strtotime($date);
        $difference = time() - $timestamp;
        
        if ($difference < 60) {
            return $difference . ' ' . ($difference == 1 ? 'second' : 'seconds') . ' ago';
        }
        
        $minutes = floor($difference / 60);
        
        if ($minutes < 60) {
            return $minutes . ' ' . ($minutes == 1 ? 'minute' : 'minutes') . ' ago';
        }
        
        $hours = floor($minutes / 60);
        
        if ($hours < 24) {
            return $hours . ' ' . ($hours == 1 ? 'hour' : 'hours') . ' ago';
        }
        
        $days = floor($hours / 24);
        
        if ($days < 7) {
            return $days . ' ' . ($days == 1 ? 'day' : 'days') . ' ago';
        }
        
        $weeks = floor($days / 7);
        
        if ($weeks < 4) {
            return $weeks . ' ' . ($weeks == 1 ? 'week' : 'weeks') . ' ago';
        }
        
        $months = floor($days / 30);
        
        if ($months < 12) {
            return $months . ' ' . ($months == 1 ? 'month' : 'months') . ' ago';
        }
        
        $years = floor($days / 365);
        return $years . ' ' . ($years == 1 ? 'year' : 'years') . ' ago';
    }
    
    /**
     * Format file size
     *
     * @param int $bytes Size in bytes
     * @return string
     */
    public static function formatBytes($bytes)
    {
        if ($bytes < 1024) {
            return $bytes . ' B';
        }
        
        $units = ['KB', 'MB', 'GB', 'TB'];
        $exp = floor(log($bytes, 1024));
        
        return round($bytes / pow(1024, $exp), 2) . ' ' . $units[$exp - 1];
    }
    
    /**
     * Validate URL
     *
     * @param string $url URL to validate
     * @return bool
     */
    public static function validateUrl($url)
    {
        return (bool) filter_var($url, FILTER_VALIDATE_URL);
    }
    
    /**
     * Check if a reference is valid
     *
     * @param string $reference Reference to check
     * @return bool
     */
    public static function validateReference($reference)
    {
        if (empty($reference)) {
            return false;
        }
        
        // Check if reference exists
        $product_data = StockSyncReference::getProductByReference($reference);
        return (bool) $product_data;
    }
    
    /**
     * Strip tags and truncate text
     *
     * @param string $text Text to truncate
     * @param int $length Maximum length
     * @param string $suffix Suffix for truncated text
     * @return string
     */
    public static function truncate($text, $length = 100, $suffix = '...')
    {
        $text = strip_tags($text);
        
        if (strlen($text) <= $length) {
            return $text;
        }
        
        return substr($text, 0, $length) . $suffix;
    }
    
    /**
     * Get memory usage
     *
     * @param bool $real_usage Get real memory usage
     * @return string
     */
    public static function getMemoryUsage($real_usage = false)
    {
        return self::formatBytes(memory_get_usage($real_usage));
    }
    
    /**
     * Get peak memory usage
     *
     * @param bool $real_usage Get real memory usage
     * @return string
     */
    public static function getPeakMemoryUsage($real_usage = false)
    {
        return self::formatBytes(memory_get_peak_usage($real_usage));
    }
    
    /**
     * Create a backup of the database tables
     *
     * @return string|bool Backup filename if successful, false otherwise
     */
    public static function createBackup()
    {
        $tables = [
            _DB_PREFIX_ . 'stock_sync_stores',
            _DB_PREFIX_ . 'stock_sync_queue',
            _DB_PREFIX_ . 'stock_sync_log',
            _DB_PREFIX_ . 'stock_sync_references',
        ];
        
        $backup_dir = _PS_MODULE_DIR_ . 'stocksyncmodule/backups/';
        
        if (!is_dir($backup_dir)) {
            mkdir($backup_dir, 0755, true);
        }
        
        $filename = $backup_dir . 'stocksync_backup_' . date('Y-m-d_H-i-s') . '.sql';
        $handle = fopen($filename, 'w');
        
        if (!$handle) {
            return false;
        }
        
        foreach ($tables as $table) {
            // Skip tables that don't exist
            if (!self::tableExists($table)) {
                continue;
            }
            
            // Get create table statement
            $create_table = Db::getInstance()->executeS('SHOW CREATE TABLE `' . $table . '`');
            
            if (!empty($create_table)) {
                $create_statement = $create_table[0]['Create Table'];
                fwrite($handle, "DROP TABLE IF EXISTS `{$table}`;\n");
                fwrite($handle, $create_statement . ";\n\n");
                
                // Get data in chunks to avoid memory issues
                $offset = 0;
                $limit = 1000;
                
                do {
                    $data = Db::getInstance()->executeS('SELECT * FROM `' . $table . '` LIMIT ' . $offset . ', ' . $limit);
                    $count = count($data);
                    
                    if (!empty($data)) {
                        $columns = array_keys($data[0]);
                        $values = [];
                        
                        foreach ($data as $row) {
                            $row_values = [];
                            
                            foreach ($row as $value) {
                                if ($value === null) {
                                    $row_values[] = 'NULL';
                                } else {
                                    $row_values[] = "'" . Db::getInstance()->escape($value, true) . "'";
                                }
                            }
                            
                            $values[] = '(' . implode(', ', $row_values) . ')';
                        }
                        
                        $columns_string = '`' . implode('`, `', $columns) . '`';
                        $values_string = implode(",\n", $values);
                        
                        fwrite($handle, "INSERT INTO `{$table}` ({$columns_string}) VALUES\n{$values_string};\n\n");
                    }
                    
                    $offset += $limit;
                } while ($count == $limit);
            }
        }
        
        fclose($handle);
        
        return $filename;
    }
    
    /**
     * Restore a backup
     *
     * @param string $filename Backup filename
     * @return bool
     */
    public static function restoreBackup($filename)
    {
        if (!file_exists($filename)) {
            return false;
        }
        
        $sql = file_get_contents($filename);
        $sql = str_replace("\n", "", $sql);
        $sql = preg_replace('/\/\*.*?\*\//', '', $sql);
        $queries = preg_split('/;(?=([^\'|^\\\']*[\']|[^\'|^\\\']*[\\\'​])[^\'|^\\\']*$)/', $sql);
        
        foreach ($queries as $query) {
            $query = trim($query);
            
            if (empty($query)) {
                continue;
            }
            
            try {
                Db::getInstance()->execute($query);
            } catch (Exception $e) {
                // Log the error but continue with other queries
                error_log('Backup restore error: ' . $e->getMessage());
            }
        }
        
        return true;
    }
    
    /**
     * Check if a table exists
     * 
     * @param string $table Full table name with prefix
     * @return bool
     */
    private static function tableExists($table) 
    {
        try {
            $result = Db::getInstance()->executeS('SHOW TABLES LIKE "' . $table . '"');
            return !empty($result);
        } catch (Exception $e) {
            error_log('Error checking if table exists: ' . $e->getMessage());
            return false;
        }
    }
}