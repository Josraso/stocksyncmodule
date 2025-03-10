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
    $stats = [
        'total' => 0,
        'pending' => 0,
        'processing' => 0,
        'completed' => 0,
        'failed' => 0,
        'skipped' => 0,
        'avg_completion_time' => 0
    ];
    
    try {
        // Determinar el nombre exacto de la tabla
        $table_name = _DB_PREFIX_ . 'stock_sync_queue';
        
        // Verificar si la tabla existe realmente
        $table_exists = Db::getInstance()->executeS("SHOW TABLES LIKE '" . $table_name . "'");
        if (empty($table_exists)) {
            // Buscar otras tablas similares
            $similar_tables = Db::getInstance()->executeS("SHOW TABLES LIKE '%" . 'stock_sync_queue' . "%'");
            if (!empty($similar_tables)) {
                // Usar la primera tabla encontrada
                $table_name = reset($similar_tables)[0];
            } else {
                return $stats; // No hay tablas similares
            }
        }
        
        // OPTIMIZACIÓN: Hacer una única consulta para todos los conteos
        $query = "SELECT 
                    COUNT(*) as total,
                    SUM(IF(status = 'pending', 1, 0)) as pending,
                    SUM(IF(status = 'processing', 1, 0)) as processing,
                    SUM(IF(status = 'completed', 1, 0)) as completed,
                    SUM(IF(status = 'failed', 1, 0)) as failed,
                    SUM(IF(status = 'skipped', 1, 0)) as skipped
                  FROM `" . $table_name . "`";
                  
        // Si se especifica un límite de horas, añadir la condición
        if ($hours > 0) {
            $date_limit = date('Y-m-d H:i:s', strtotime('-' . (int)$hours . ' hours'));
            $query .= " WHERE updated_at >= '" . pSQL($date_limit) . "'";
        }
        
        $result = Db::getInstance()->getRow($query);
        
        if ($result) {
            $stats['total'] = (int)$result['total'];
            $stats['pending'] = (int)$result['pending'];
            $stats['processing'] = (int)$result['processing'];
            $stats['completed'] = (int)$result['completed'];
            $stats['failed'] = (int)$result['failed'];
            $stats['skipped'] = (int)$result['skipped'];
        }
        
        // Calcular tiempo promedio de completado si hay suficientes elementos completados
        if ($stats['completed'] > 0) {
            // OPTIMIZACIÓN: Sólo calcular si es necesario y hay elementos completados
            $avg_time_query = "SELECT AVG(TIMESTAMPDIFF(SECOND, created_at, updated_at)) as avg_time 
                              FROM `" . $table_name . "` 
                              WHERE status = 'completed'";
                              
            if ($hours > 0) {
                $avg_time_query .= " AND updated_at >= '" . pSQL($date_limit) . "'";
            }
            
            $avg_time_result = Db::getInstance()->getValue($avg_time_query);
            if ($avg_time_result) {
                $stats['avg_completion_time'] = (float)$avg_time_result;
            }
        }
        
        return $stats;
    } catch (Exception $e) {
        error_log('Error en getStatistics de Queue: ' . $e->getMessage());
        return $stats;
    }
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
    
    // OPTIMIZACIÓN: Seleccionar solo los campos necesarios para mejorar rendimiento
    $query = new DbQuery();
    $query->select('q.id_queue, q.reference, q.old_quantity, q.new_quantity, q.operation_type, 
                    q.status, q.created_at, q.updated_at, q.source_store_id, q.target_store_id')
        ->from('stock_sync_queue', 'q')
        // OPTIMIZACIÓN: No hacer JOIN a menos que sea realmente necesario
        ->orderBy('q.id_queue DESC')
        ->limit((int) $limit);
    
    if ($status && self::columnExists('stock_sync_queue', 'status')) {
        if (strpos($status, ',') !== false) {
            // OPTIMIZACIÓN: Manejar múltiples estados de forma más eficiente
            $statuses = array_map('trim', explode(',', $status));
            $in_values = [];
            
            foreach ($statuses as $s) {
                $in_values[] = "'" . pSQL($s) . "'";
            }
            
            $query->where('q.status IN (' . implode(',', $in_values) . ')');
        } else {
            $query->where('q.status = "' . pSQL($status) . '"');
        }
    }
    
    // OPTIMIZACIÓN: Usar Limit directo en la consulta SQL
    $result = Db::getInstance()->executeS($query);
    
    // OPTIMIZACIÓN: Añadir nombres de tiendas solo si se necesitan y están disponibles
    if (!empty($result) && self::tableExists('stock_sync_stores')) {
        // Obtener todos los IDs de tiendas en una sola operación
        $store_ids = [];
        foreach ($result as $item) {
            $store_ids[] = (int)$item['source_store_id'];
            $store_ids[] = (int)$item['target_store_id'];
        }
        $store_ids = array_unique($store_ids);
        
        if (!empty($store_ids)) {
            // Obtener nombres de tiendas en una sola consulta
            $stores_query = new DbQuery();
            $stores_query->select('id_store, store_name')
                ->from('stock_sync_stores')
                ->where('id_store IN (' . implode(',', $store_ids) . ')');
            
            $stores = [];
            $stores_result = Db::getInstance()->executeS($stores_query);
            if (is_array($stores_result)) {
                foreach ($stores_result as $store) {
                    $stores[(int)$store['id_store']] = $store['store_name'];
                }
            }
            
            // Asignar nombres de tiendas a los resultados
            foreach ($result as &$item) {
                $item['source_store_name'] = isset($stores[(int)$item['source_store_id']]) ? 
                    $stores[(int)$item['source_store_id']] : 'Unknown Store #' . $item['source_store_id'];
                $item['target_store_name'] = isset($stores[(int)$item['target_store_id']]) ? 
                    $stores[(int)$item['target_store_id']] : 'Unknown Store #' . $item['target_store_id'];
            }
        }
    }
    
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