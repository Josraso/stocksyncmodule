<?php
/**
 * Stock Sync Queue Class
 *
 * @author    Expert PrestaShop Developer
 * @copyright 2025
 * @license   Commercial
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

class StockSyncQueue
{
    // Cache para mejorar rendimiento
    private static $cache = [];
    
    /**
     * Add a new item to the synchronization queue
     *
     * @param int $id_product Product ID
     * @param int $id_product_attribute Product attribute ID (0 for products without combinations)
     * @param string $reference Product reference
     * @param float $old_quantity Old stock quantity
     * @param float $new_quantity New stock quantity
     * @param string $operation_type Type of operation (update, order, import)
     * @param int $source_store_id Source store ID
     * @param int $target_store_id Target store ID
     * @return int|bool Queue ID if successful, false otherwise
     */
    public static function add(
        $id_product,
        $id_product_attribute,
        $reference,
        $old_quantity,
        $new_quantity,
        $operation_type,
        $source_store_id,
        $target_store_id
    ) {
        // Check if there's already a pending item for this product/store combination
        $existing = self::getExistingQueueItem(
            $id_product,
            $id_product_attribute,
            $target_store_id
        );
        
        if ($existing) {
            // Update the existing queue item instead of creating a new one
            $result = Db::getInstance()->update(
                'stock_sync_queue',
                [
                    'old_quantity' => (float) $old_quantity,
                    'new_quantity' => (float) $new_quantity,
                    'operation_type' => pSQL($operation_type),
                    'status' => 'pending',
                    'error_message' => '',
                    'updated_at' => date('Y-m-d H:i:s')
                ],
                'id_queue = ' . (int) $existing['id_queue']
            );
            
            if ($result) {
                // Invalidar caché
                self::$cache = [];
                return (int) $existing['id_queue'];
            }
            
            return false;
        }
        
        // Insert a new queue item
        $result = Db::getInstance()->insert(
            'stock_sync_queue',
            [
                'id_product' => (int) $id_product,
                'id_product_attribute' => (int) $id_product_attribute,
                'reference' => pSQL($reference),
                'old_quantity' => (float) $old_quantity,
                'new_quantity' => (float) $new_quantity,
                'operation_type' => pSQL($operation_type),
                'status' => 'pending',
                'source_store_id' => (int) $source_store_id,
                'target_store_id' => (int) $target_store_id,
                'attempts' => 0,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ]
        );
        
        if ($result) {
            // Invalidar caché
            self::$cache = [];
            return (int) Db::getInstance()->Insert_ID();
        }
        
        return false;
    }
    
    /**
     * Get existing queue item for a product and target store
     *
     * @param int $id_product
     * @param int $id_product_attribute
     * @param int $target_store_id
     * @return array|bool
     */
    public static function getExistingQueueItem($id_product, $id_product_attribute, $target_store_id)
    {
        // Verificar primero si la tabla existe
        if (!self::tableExists('stock_sync_queue')) {
            return false;
        }
        
        $query = new DbQuery();
        $query->select('*')
            ->from('stock_sync_queue')
            ->where('id_product = ' . (int) $id_product)
            ->where('id_product_attribute = ' . (int) $id_product_attribute)
            ->where('target_store_id = ' . (int) $target_store_id)
            ->where("status IN ('pending', 'processing')");
        
        return Db::getInstance()->getRow($query);
    }
    
    /**
     * Get pending queue items
     *
     * @param int $limit Maximum number of items to return
     * @return array
     */
    public static function getPending($limit = 50)
    {
        // Verificar primero si la tabla existe
        if (!self::tableExists('stock_sync_queue')) {
            return [];
        }
        
        // Usar cache para mejorar rendimiento
        $cache_key = 'pending_' . $limit;
        if (isset(self::$cache[$cache_key]) && !empty(self::$cache[$cache_key])) {
            return self::$cache[$cache_key];
        }
        
        // Verificar si la columna status existe
        if (!self::columnExists('stock_sync_queue', 'status')) {
            return [];
        }
        
        $query = new DbQuery();
        $query->select('*')
            ->from('stock_sync_queue')
            ->where("status = 'pending'")
            ->orderBy('id_queue ASC')
            ->limit((int) $limit);
        
        $result = Db::getInstance()->executeS($query);
        self::$cache[$cache_key] = $result;
        
        return $result;
    }
    
    /**
     * Get queue items by reference and status
     *
     * @param string $reference Product reference
     * @param string $status Status or comma-separated list of statuses
     * @return array
     */
    public static function getByReference($reference, $status = 'pending')
    {
        // Verificar primero si la tabla existe
        if (!self::tableExists('stock_sync_queue')) {
            return [];
        }
        
        // Usar cache para mejorar rendimiento
        $cache_key = 'ref_' . $reference . '_' . $status;
        if (isset(self::$cache[$cache_key])) {
            return self::$cache[$cache_key];
        }
        
        // Verificar si la columna status existe
        if (!self::columnExists('stock_sync_queue', 'status')) {
            return [];
        }
        
        $statuses = array_map('trim', explode(',', $status));
        $statuses_sql = [];
        
        foreach ($statuses as $s) {
            $statuses_sql[] = "'" . pSQL($s) . "'";
        }
        
        $query = new DbQuery();
        $query->select('*')
            ->from('stock_sync_queue')
            ->where('reference = "' . pSQL($reference) . '"')
            ->where('status IN (' . implode(',', $statuses_sql) . ')')
            ->orderBy('id_queue ASC')
            ->limit(500); // Limitar para mejorar rendimiento
        
        $result = Db::getInstance()->executeS($query);
        self::$cache[$cache_key] = $result;
        
        return $result;
    }
    
    /**
     * Update queue item status
     *
     * @param int $id_queue Queue ID
     * @param string $status New status
     * @param string $error_message Error message (optional)
     * @return bool
     */
    public static function updateStatus($id_queue, $status, $error_message = '')
    {
        $data = [
            'status' => pSQL($status),
            'updated_at' => date('Y-m-d H:i:s')
        ];
        
        if ($error_message) {
            $data['error_message'] = pSQL($error_message);
        }
        
        $result = Db::getInstance()->update(
            'stock_sync_queue',
            $data,
            'id_queue = ' . (int) $id_queue
        );
        
        // Invalidar caché
        self::$cache = [];
        
        return $result;
    }
    
    /**
     * Increment attempt count
     *
     * @param int $id_queue Queue ID
     * @return bool
     */
    public static function incrementAttempt($id_queue)
    {
        $result = Db::getInstance()->execute(
            'UPDATE `' . _DB_PREFIX_ . 'stock_sync_queue` 
            SET `attempts` = `attempts` + 1,
                `updated_at` = "' . pSQL(date('Y-m-d H:i:s')) . '"
            WHERE `id_queue` = ' . (int) $id_queue
        );
        
        // Invalidar caché
        self::$cache = [];
        
        return $result;
    }
    
    /**
     * Delete old completed or failed queue items
     *
     * @param int $hours Keep items newer than this many hours
     * @return int Number of deleted items
     */
    public static function cleanOldItems($hours = 24)
    {
        // Verificar primero si la tabla existe
        if (!self::tableExists('stock_sync_queue')) {
            return 0;
        }
        
        // Verificar si la columna status existe
        if (!self::columnExists('stock_sync_queue', 'status')) {
            return 0;
        }
        
        $date = date('Y-m-d H:i:s', strtotime('-' . (int) $hours . ' hours'));
        
        $result = Db::getInstance()->execute(
            'DELETE FROM `' . _DB_PREFIX_ . 'stock_sync_queue` 
            WHERE `status` IN ("completed", "failed", "skipped")
            AND `updated_at` < "' . pSQL($date) . '"
            LIMIT 1000' // Limitar para evitar sobrecarga
        );
        
        // Invalidar caché
        self::$cache = [];
        
        return $result;
    }
    
    /**
     * Get queue statistics
     *
     * @param int $hours Statistics for the last X hours (0 for all time)
     * @return array
     */
    public static function getStatistics($hours = 0)
    {
        // Usar cache para mejorar rendimiento
        $cache_key = 'stats_' . $hours;
        if (isset(self::$cache[$cache_key])) {
            return self::$cache[$cache_key];
        }
        
        // Verificar primero si la tabla existe
        if (!self::tableExists('stock_sync_queue')) {
            $stats = [
                'total' => 0,
                'pending' => 0,
                'processing' => 0,
                'completed' => 0,
                'failed' => 0,
                'skipped' => 0,
                'avg_completion_time' => 0
            ];
            self::$cache[$cache_key] = $stats;
            return $stats;
        }
        
        // Verificar si la columna status existe
        if (!self::columnExists('stock_sync_queue', 'status')) {
            $stats = [
                'total' => 0,
                'pending' => 0,
                'processing' => 0,
                'completed' => 0,
                'failed' => 0,
                'skipped' => 0,
                'avg_completion_time' => 0
            ];
            self::$cache[$cache_key] = $stats;
            return $stats;
        }
        
        $where_clause = '';
        
        if ($hours > 0) {
            $date = date('Y-m-d H:i:s', strtotime('-' . (int) $hours . ' hours'));
            $where_clause = 'WHERE `created_at` >= "' . pSQL($date) . '" ';
        }
        
        $query = '
            SELECT 
                `status`,
                COUNT(*) as count,
                AVG(TIMESTAMPDIFF(SECOND, `created_at`, `updated_at`)) as avg_time
            FROM `' . _DB_PREFIX_ . 'stock_sync_queue` 
            ' . $where_clause . '
            GROUP BY `status`
            LIMIT 1000
        ';
        
        $results = Db::getInstance()->executeS($query);
        $stats = [
            'total' => 0,
            'pending' => 0,
            'processing' => 0,
            'completed' => 0,
            'failed' => 0,
            'skipped' => 0,
            'avg_completion_time' => 0
        ];
        
        if (is_array($results)) {
            foreach ($results as $row) {
                $stats[$row['status']] = (int) $row['count'];
                $stats['total'] += (int) $row['count'];
                
                if ($row['status'] == 'completed' && $row['avg_time']) {
                    $stats['avg_completion_time'] = round($row['avg_time'], 2);
                }
            }
        }
        
        self::$cache[$cache_key] = $stats;
        return $stats;
    }
    
    /**
     * Get recent queue items
     *
     * @param int $limit Maximum number of items to return
     * @param string $status Filter by status (empty for all)
     * @return array
     */
    public static function getRecent($limit = 20, $status = '')
    {
        // Usar cache para mejorar rendimiento
        $cache_key = 'recent_' . $limit . '_' . $status;
        if (isset(self::$cache[$cache_key])) {
            return self::$cache[$cache_key];
        }
        
        // Verificar primero si la tabla existe
        if (!self::tableExists('stock_sync_queue')) {
            return [];
        }
        
        $query = new DbQuery();
        $query->select('q.*, IFNULL(s1.store_name, "") as source_store_name, IFNULL(s2.store_name, "") as target_store_name')
            ->from('stock_sync_queue', 'q')
            ->leftJoin('stock_sync_stores', 's1', 's1.id_store = q.source_store_id')
            ->leftJoin('stock_sync_stores', 's2', 's2.id_store = q.target_store_id')
            ->orderBy('q.id_queue DESC')
            ->limit((int) $limit);
        
        if ($status && self::columnExists('stock_sync_queue', 'status')) {
            $query->where('q.status = "' . pSQL($status) . '"');
        }
        
        $result = Db::getInstance()->executeS($query);
        self::$cache[$cache_key] = $result;
        
        return $result;
    }
    
    /**
     * Retry failed items
     *
     * @param int $max_age Maximum age in hours for retry
     * @return int Number of items set for retry
     */
    public static function retryFailed($max_age = 24)
    {
        // Verificar primero si la tabla existe
        if (!self::tableExists('stock_sync_queue')) {
            return 0;
        }
        
        // Verificar si la columna status existe
        if (!self::columnExists('stock_sync_queue', 'status')) {
            return 0;
        }
        
        $date = date('Y-m-d H:i:s', strtotime('-' . (int) $max_age . ' hours'));
        
        $result = Db::getInstance()->execute(
            'UPDATE `' . _DB_PREFIX_ . 'stock_sync_queue` 
            SET `status` = "pending",
                `attempts` = 0,
                `error_message` = "Retry after failure",
                `updated_at` = "' . pSQL(date('Y-m-d H:i:s')) . '"
            WHERE `status` = "failed"
            AND `updated_at` >= "' . pSQL($date) . '"
            LIMIT 1000' // Limitar para evitar sobrecarga
        );
        
        // Invalidar caché
        self::$cache = [];
        
        return $result;
    }
    
    /**
     * Check if a table exists in the database
     *
     * @param string $table Table name without prefix
     * @return bool
     */
    private static function tableExists($table)
    {
        try {
            $result = Db::getInstance()->executeS('SHOW TABLES LIKE "' . _DB_PREFIX_ . $table . '"');
            return !empty($result);
        } catch (Exception $e) {
            // Registra el error en el archivo de registro
            error_log('Error al verificar existencia de tabla ' . $table . ': ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Check if a column exists in a table
     *
     * @param string $table Table name without prefix
     * @param string $column Column name
     * @return bool
     */
    private static function columnExists($table, $column)
    {
        try {
            $result = Db::getInstance()->executeS('SHOW COLUMNS FROM `' . _DB_PREFIX_ . $table . '` LIKE "' . $column . '"');
            return !empty($result);
        } catch (Exception $e) {
            // Registra el error en el archivo de registro
            error_log('Error al verificar existencia de columna ' . $column . ' en tabla ' . $table . ': ' . $e->getMessage());
            return false;
        }
    }
}