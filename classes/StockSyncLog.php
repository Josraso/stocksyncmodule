<?php
/**
 * Stock Sync Log Class
 *
 * @author    Expert PrestaShop Developer
 * @copyright 2025
 * @license   Commercial
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

class StockSyncLog
{
    // Cache para mejorar rendimiento
    private static $cache = [];
    
    /**
     * Add a new log entry
     *
     * @param string $message Log message
     * @param string $level Log level (info, warning, error, conflict)
     * @param int $id_queue Related queue ID (optional)
     * @return int|bool Log ID if successful, false otherwise
     */
    public static function add($message, $level = 'info', $id_queue = null)
    {
        // Verificar primero si la tabla existe
        if (!self::tableExists('stock_sync_log')) {
            return false;
        }
        
        // Limitar el número de logs para evitar sobrecarga
        self::limitLogEntries(10000);
        
        $result = Db::getInstance()->insert(
            'stock_sync_log',
            [
                'id_queue' => $id_queue ? (int) $id_queue : null,
                'message' => pSQL($message),
                'level' => pSQL($level),
                'created_at' => date('Y-m-d H:i:s')
            ]
        );
        
        // Invalidar caché
        self::$cache = [];
        
        if ($result) {
            return (int) Db::getInstance()->Insert_ID();
        }
        
        return false;
    }
    
    /**
     * Limitar el número de entradas de log
     * 
     * @param int $max_entries Número máximo de entradas a mantener
     * @return int Número de entradas eliminadas
     */
    private static function limitLogEntries($max_entries = 10000)
    {
        try {
            // Contar el total de entradas
            $count_query = new DbQuery();
            $count_query->select('COUNT(*)')
                ->from('stock_sync_log');
            
            $total = (int)Db::getInstance()->getValue($count_query);
            
            // Si superamos el máximo, eliminar las más antiguas
            if ($total > $max_entries) {
                $to_delete = $total - $max_entries;
                
                // Obtener el ID más alto que debemos eliminar
                $id_query = new DbQuery();
                $id_query->select('id_log')
                    ->from('stock_sync_log')
                    ->orderBy('id_log ASC')
                    ->limit(1, $to_delete - 1);
                
                $max_id = (int)Db::getInstance()->getValue($id_query);
                
                // Eliminar todas las entradas hasta ese ID
                return Db::getInstance()->execute(
                    'DELETE FROM `' . _DB_PREFIX_ . 'stock_sync_log` 
                    WHERE `id_log` <= ' . $max_id
                );
            }
            
            return 0;
        } catch (Exception $e) {
            return 0;
        }
    }
    
   /**
 * Get recent log entries
 *
 * @param int $limit Maximum number of entries to return
 * @param string $level Filter by log level (empty for all)
 * @return array
 */
public static function getRecent($limit = 100, $level = '')
{
    // Usar cache para mejorar rendimiento
    $cache_key = 'recent_' . $limit . '_' . $level;
    if (isset(self::$cache[$cache_key])) {
        return self::$cache[$cache_key];
    }
    
    // Verificar primero si la tabla existe
    if (!self::tableExists('stock_sync_log')) {
        return [];
    }
    
    // OPTIMIZACIÓN: Consulta optimizada con índices adecuados
    $query = new DbQuery();
    $query->select('l.id_log, l.message, l.level, l.created_at, IFNULL(q.reference, "") as reference')
        ->from('stock_sync_log', 'l')
        // OPTIMIZACIÓN: Usar LEFT JOIN solo si se necesita la referencia real
        ->leftJoin('stock_sync_queue', 'q', 'q.id_queue = l.id_queue')
        ->orderBy('l.id_log DESC')
        ->limit((int) $limit);
    
    if ($level && self::columnExists('stock_sync_log', 'level')) {
        // OPTIMIZACIÓN: Preparar la condición WHERE de forma más eficiente
        if (strpos($level, ',') !== false) {
            $levels = array_map('trim', explode(',', $level));
            $in_values = [];
            
            foreach ($levels as $l) {
                $in_values[] = "'" . pSQL($l) . "'";
            }
            
            $query->where('l.level IN (' . implode(',', $in_values) . ')');
        } else {
            $query->where('l.level = "' . pSQL($level) . '"');
        }
    }
    
    // OPTIMIZACIÓN: Usar SQL_CALC_FOUND_ROWS para obtener el total simultáneamente si se necesita
    $sql = str_replace('SELECT ', 'SELECT SQL_CALC_FOUND_ROWS ', $query->build());
    $result = Db::getInstance()->executeS($sql);
    
    self::$cache[$cache_key] = $result;
    
    return $result;
}
    
    /**
     * Get logs related to a specific queue item
     *
     * @param int $id_queue Queue ID
     * @return array
     */
    public static function getByQueue($id_queue)
    {
        // Usar cache para mejorar rendimiento
        $cache_key = 'queue_' . $id_queue;
        if (isset(self::$cache[$cache_key])) {
            return self::$cache[$cache_key];
        }
        
        // Verificar primero si la tabla existe
        if (!self::tableExists('stock_sync_log')) {
            return [];
        }
        
        $query = new DbQuery();
        $query->select('*')
            ->from('stock_sync_log')
            ->where('id_queue = ' . (int) $id_queue)
            ->orderBy('id_log DESC')
            ->limit(500); // Limitar para mejorar rendimiento
        
        $result = Db::getInstance()->executeS($query);
        self::$cache[$cache_key] = $result;
        
        return $result;
    }
    
    /**
     * Get logs related to a specific reference
     *
     * @param string $reference Product reference
     * @param int $limit Maximum number of entries to return
     * @return array
     */
    public static function getByReference($reference, $limit = 50)
    {
        // Usar cache para mejorar rendimiento
        $cache_key = 'ref_' . $reference . '_' . $limit;
        if (isset(self::$cache[$cache_key])) {
            return self::$cache[$cache_key];
        }
        
        // Verificar primero si la tabla existe
        if (!self::tableExists('stock_sync_log')) {
            return [];
        }
        
        // Verificar si la tabla de colas existe
        if (!self::tableExists('stock_sync_queue')) {
            return [];
        }
        
        // Verificar si la columna reference existe en la tabla de colas
        if (!self::columnExists('stock_sync_queue', 'reference')) {
            return [];
        }
        
        $query = new DbQuery();
        $query->select('l.*')
            ->from('stock_sync_log', 'l')
            ->innerJoin('stock_sync_queue', 'q', 'q.id_queue = l.id_queue')
            ->where('q.reference = "' . pSQL($reference) . '"')
            ->orderBy('l.id_log DESC')
            ->limit((int) $limit);
        
        $result = Db::getInstance()->executeS($query);
        self::$cache[$cache_key] = $result;
        
        return $result;
    }
    
    /**
     * Delete old log entries
     *
     * @param int $hours Keep entries newer than this many hours
     * @return int Number of deleted entries
     */
    public static function cleanOldLogs($hours = 24)
    {
        // Verificar primero si la tabla existe
        if (!self::tableExists('stock_sync_log')) {
            return 0;
        }
        
        $date = date('Y-m-d H:i:s', strtotime('-' . (int) $hours . ' hours'));
        
        $result = Db::getInstance()->execute(
            'DELETE FROM `' . _DB_PREFIX_ . 'stock_sync_log` 
            WHERE `created_at` < "' . pSQL($date) . '"
            LIMIT 10000' // Limitar para evitar sobrecarga
        );
        
        // Invalidar caché
        self::$cache = [];
        
        return $result;
    }
    
/**
 * Get log statistics
 *
 * @param int $hours Statistics for the last X hours (0 for all time)
 * @return array
 */
public static function getStatistics($hours = 0)
{
    $stats = [
        'total' => 0,
        'info' => 0,
        'warning' => 0,
        'error' => 0,
        'conflict' => 0
    ];
    
    try {
        // Determinar el nombre exacto de la tabla
        $table_name = _DB_PREFIX_ . 'stock_sync_log';
        
        // Verificar si la tabla existe realmente
        $table_exists = Db::getInstance()->executeS("SHOW TABLES LIKE '" . $table_name . "'");
        if (empty($table_exists)) {
            // Buscar otras tablas similares
            $similar_tables = Db::getInstance()->executeS("SHOW TABLES LIKE '%" . 'stock_sync_log' . "%'");
            if (!empty($similar_tables)) {
                // Usar la primera tabla encontrada
                $table_name = reset($similar_tables)[0];
            } else {
                return $stats; // No hay tablas similares
            }
        }
        
        // OPTIMIZACIÓN: Una sola consulta para todos los conteos
        $query = "SELECT 
                    COUNT(*) as total,
                    SUM(IF(level = 'info', 1, 0)) as info,
                    SUM(IF(level = 'warning', 1, 0)) as warning,
                    SUM(IF(level = 'error', 1, 0)) as error,
                    SUM(IF(level = 'conflict', 1, 0)) as conflict
                  FROM `" . $table_name . "`";
                  
        // Si se especifica un límite de horas, añadir la condición
        if ($hours > 0) {
            $date_limit = date('Y-m-d H:i:s', strtotime('-' . (int)$hours . ' hours'));
            $query .= " WHERE created_at >= '" . pSQL($date_limit) . "'";
        }
        
        $result = Db::getInstance()->getRow($query);
        
        if ($result) {
            $stats['total'] = (int)$result['total'];
            $stats['info'] = (int)$result['info'];
            $stats['warning'] = (int)$result['warning'];
            $stats['error'] = (int)$result['error'];
            $stats['conflict'] = (int)$result['conflict'];
        }
        
        return $stats;
    } catch (Exception $e) {
        error_log('Error en getStatistics de Log: ' . $e->getMessage());
        return $stats;
    }
}
    
    /**
     * Get conflict logs
     *
     * @param int $limit Maximum number of entries to return
     * @return array
     */
    public static function getConflicts($limit = 50)
    {
        // Verificar primero si la tabla existe
        if (!self::tableExists('stock_sync_log')) {
            return [];
        }
        
        // Verificar si la columna level existe
        if (!self::columnExists('stock_sync_log', 'level')) {
            return [];
        }
        
        return self::getRecent($limit, 'conflict');
    }
    
    /**
     * Export logs to CSV
     *
     * @param string $level Filter by log level (empty for all)
     * @param string $date_from From date (Y-m-d)
     * @param string $date_to To date (Y-m-d)
     * @return string CSV content
     */
    public static function exportToCSV($level = '', $date_from = '', $date_to = '')
    {
        // Verificar primero si la tabla existe
        if (!self::tableExists('stock_sync_log')) {
            return "ID;Message;Level;Date;Reference\n";
        }
        
        $query = new DbQuery();
        $query->select('l.id_log, l.message, l.level, l.created_at, IFNULL(q.reference, "") as reference')
            ->from('stock_sync_log', 'l')
            ->leftJoin('stock_sync_queue', 'q', 'q.id_queue = l.id_queue')
            ->orderBy('l.id_log DESC')
            ->limit(10000); // Limitar para evitar archivos muy grandes
        
        $conditions = [];
        
        if ($level && self::columnExists('stock_sync_log', 'level')) {
            $levels = array_map('trim', explode(',', $level));
            $levels_sql = [];
            
            foreach ($levels as $l) {
                $levels_sql[] = "'" . pSQL($l) . "'";
            }
            
            $conditions[] = 'l.level IN (' . implode(',', $levels_sql) . ')';
        }
        
        if ($date_from) {
            $conditions[] = 'l.created_at >= "' . pSQL($date_from) . ' 00:00:00"';
        }
        
        if ($date_to) {
            $conditions[] = 'l.created_at <= "' . pSQL($date_to) . ' 23:59:59"';
        }
        
        if (!empty($conditions)) {
            $query->where(implode(' AND ', $conditions));
        }
        
        $results = Db::getInstance()->executeS($query);
        $output = "ID;Message;Level;Date;Reference\n";
        
        if (is_array($results)) {
            foreach ($results as $row) {
                $output .= $row['id_log'] . ';';
                $output .= '"' . str_replace('"', '""', $row['message']) . '";';
                $output .= $row['level'] . ';';
                $output .= $row['created_at'] . ';';
                $output .= $row['reference'] . "\n";
            }
        }
        
        return $output;
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