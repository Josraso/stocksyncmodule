<?php
/**
 * Stock Sync Reference Class - FIXED VERSION
 *
 * @author    Expert PrestaShop Developer
 * @copyright 2025
 * @license   Commercial
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

class StockSyncReference
{
   /**
 * Get product by reference
 *
 * @param string $reference Product reference
 * @return array|false Product data if found, false otherwise
 */
public static function getProductByReference($reference)
{
    // OPTIMIZACIÓN: Implementar cache para referencias
    static $cache = [];
    
    if (isset($cache[$reference])) {
        return $cache[$reference];
    }
    
    // First check for combinations with this reference
    $query = new DbQuery();
    $query->select('p.id_product, pa.id_product_attribute')
        ->from('product', 'p')
        ->innerJoin('product_attribute', 'pa', 'p.id_product = pa.id_product')
        ->where('pa.reference = "' . pSQL($reference) . '"');
        // Eliminado el filtro de active para permitir productos inactivos
    
    $result = Db::getInstance()->getRow($query);
    
    if ($result) {
        $cache[$reference] = [
            'id_product' => (int) $result['id_product'],
            'id_product_attribute' => (int) $result['id_product_attribute'],
            'type' => 'combination'
        ];
        return $cache[$reference];
    }
    
    // If not found, check for simple products with this reference
    $query = new DbQuery();
    $query->select('p.id_product')
        ->from('product', 'p')
        ->where('p.reference = "' . pSQL($reference) . '"');
        // Eliminado el filtro de active para permitir productos inactivos
    
    $result = Db::getInstance()->getRow($query);
    
    if ($result) {
        $cache[$reference] = [
            'id_product' => (int) $result['id_product'],
            'id_product_attribute' => 0,
            'type' => 'product'
        ];
        return $cache[$reference];
    }
    
    // Si no se encuentra, guardar false en caché para futuras búsquedas
    $cache[$reference] = false;
    return false;
}
    
    /**
     * Get reference mapping between stores
     *
     * @param string $reference Product reference
     * @param int $source_store_id Source store ID
     * @param int $target_store_id Target store ID
     * @return array|false Mapping data if found, false otherwise
     */
    public static function getReferenceMapping($reference, $source_store_id, $target_store_id)
    {
        // Check if the references table exists first
        if (!self::tableExists(_DB_PREFIX_ . 'stock_sync_references')) {
            return false;
        }
        
        try {
            $query = new DbQuery();
            $query->select('*')
                ->from('stock_sync_references')
                ->where('reference = "' . pSQL($reference) . '"')
                ->where('id_store_source = ' . (int) $source_store_id)
                ->where('id_store_target = ' . (int) $target_store_id);
            
            return Db::getInstance()->getRow($query);
        } catch (Exception $e) {
            // Log the error but don't crash
            error_log('Error in getReferenceMapping: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Add or update reference mapping
     *
     * @param string $reference Product reference
     * @param int $id_product_source Source product ID
     * @param int $id_product_attribute_source Source product attribute ID
     * @param int $id_product_target Target product ID
     * @param int $id_product_attribute_target Target product attribute ID
     * @param int $id_store_source Source store ID
     * @param int $id_store_target Target store ID
     * @return int|bool ID of the mapping if successful, false otherwise
     */
    public static function addReferenceMapping(
        $reference,
        $id_product_source,
        $id_product_attribute_source,
        $id_product_target,
        $id_product_attribute_target,
        $id_store_source,
        $id_store_target
    ) {
        // Ensure references table exists
        if (!self::tableExists(_DB_PREFIX_ . 'stock_sync_references')) {
            return false;
        }
        
        // Permitimos mapear entre las mismas tiendas para debugging
        // y también entre tiendas con los mismos IDs de producto para tiendas clonadas
        
        try {
            // Check if mapping already exists
            $existing = self::getReferenceMapping($reference, $id_store_source, $id_store_target);
            
            if ($existing) {
                // Update existing mapping
                $result = Db::getInstance()->update(
                    'stock_sync_references',
                    [
                        'id_product_source' => (int) $id_product_source,
                        'id_product_attribute_source' => (int) $id_product_attribute_source,
                        'id_product_target' => (int) $id_product_target,
                        'id_product_attribute_target' => (int) $id_product_attribute_target,
                        'active' => 1,
                        'last_sync' => date('Y-m-d H:i:s'),
                        'updated_at' => date('Y-m-d H:i:s')
                    ],
                    'id_reference_map = ' . (int) $existing['id_reference_map']
                );
                
                if ($result) {
                    return (int) $existing['id_reference_map'];
                }
                
                return false;
            }
            
            // Insert new mapping
            $result = Db::getInstance()->insert(
                'stock_sync_references',
                [
                    'reference' => pSQL($reference),
                    'id_product_source' => (int) $id_product_source,
                    'id_product_attribute_source' => (int) $id_product_attribute_source,
                    'id_product_target' => (int) $id_product_target,
                    'id_product_attribute_target' => (int) $id_product_attribute_target,
                    'id_store_source' => (int) $id_store_source,
                    'id_store_target' => (int) $id_store_target,
                    'active' => 1,
                    'last_sync' => date('Y-m-d H:i:s'),
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s')
                ]
            );
            
            if ($result) {
                return (int) Db::getInstance()->Insert_ID();
            }
        } catch (Exception $e) {
            // Log the error but don't crash
            error_log('Error in addReferenceMapping: ' . $e->getMessage());
            return false;
        }
        
        return false;
    }
    
   /**
 * Scan and map all product references between stores
 * VERSION OPTIMIZADA para rendimiento
 *
 * @param int $source_store_id Source store ID
 * @param int $target_store_id Target store ID
 * @param bool $force Force update of existing mappings
 * @return array Results of the mapping process
 */
public static function scanAndMapReferences($source_store_id, $target_store_id, $force = false)
{
    // Activar logs detallados solo en modo debug
    $debug_mode = (bool)Configuration::get('STOCK_SYNC_DEBUG_MODE');
    if ($debug_mode) {
        error_log('INICIANDO mapeo de referencias entre tiendas: Origen=' . $source_store_id . ', Destino=' . $target_store_id);
    }
    
    $results = [
        'total_scanned' => 0,
        'new_mappings' => 0,
        'updated_mappings' => 0,
        'failed_mappings' => 0,
        'details' => []
    ];
    
    // OPTIMIZACIÓN: Verificar la existencia de la tabla de referencias una sola vez
    if (!self::tableExists(_DB_PREFIX_ . 'stock_sync_references')) {
        error_log('ERROR: La tabla de referencias no existe');
        return $results;
    }
    
    try {
        // OPTIMIZACIÓN: Obtener todos los productos con referencias en una sola consulta
        $products_query = '
            SELECT 
                p.id_product, 
                p.reference, 
                0 as id_product_attribute,
                "product" as type
            FROM ' . _DB_PREFIX_ . 'product p
            WHERE p.reference != ""
            
            UNION
            
            SELECT 
                pa.id_product, 
                pa.reference, 
                pa.id_product_attribute,
                "combination" as type
            FROM ' . _DB_PREFIX_ . 'product_attribute pa
            JOIN ' . _DB_PREFIX_ . 'product p ON p.id_product = pa.id_product
            WHERE pa.reference != ""
        ';
        
        // OPTIMIZACIÓN: Usar query nativa para mejor rendimiento
        $products = Db::getInstance()->executeS($products_query);
        
        if (!is_array($products)) {
            error_log('ERROR: No se pudo obtener la lista de productos');
            return $results;
        }
        
        $results['total_scanned'] = count($products);
        
        if ($debug_mode) {
            error_log('Se encontraron ' . count($products) . ' productos/combinaciones con referencias');
        }
        
        // OPTIMIZACIÓN: Obtener todos los mapeos existentes de una vez para evitar múltiples consultas
        $existing_mappings = [];
        $existing_query = '
            SELECT reference, id_reference_map
            FROM ' . _DB_PREFIX_ . 'stock_sync_references
            WHERE id_store_source = ' . (int)$source_store_id . '
            AND id_store_target = ' . (int)$target_store_id;
        $existing_results = Db::getInstance()->executeS($existing_query);
        
        if (is_array($existing_results)) {
            foreach ($existing_results as $mapping) {
                $existing_mappings[$mapping['reference']] = $mapping['id_reference_map'];
            }
        }
        
        // OPTIMIZACIÓN: Preparar consultas batch para inserciones y actualizaciones
        $insert_values = [];
        $update_queries = [];
        $now = date('Y-m-d H:i:s');
        
        // Procesar productos en lotes
        foreach ($products as $product) {
            if (empty($product['reference'])) {
                continue;
            }
            
            $reference = pSQL($product['reference']);
            $exists = isset($existing_mappings[$reference]);
            
            if ($exists && !$force) {
                // Ya existe mapeo y no estamos forzando actualización
                continue;
            }
            
            if ($exists) {
                // Actualizar mapeo existente
                $id_reference_map = (int)$existing_mappings[$reference];
                $update_queries[] = '
                    UPDATE `' . _DB_PREFIX_ . 'stock_sync_references`
                    SET 
                        `id_product_source` = ' . (int)$product['id_product'] . ',
                        `id_product_attribute_source` = ' . (int)$product['id_product_attribute'] . ',
                        `id_product_target` = ' . (int)$product['id_product'] . ',
                        `id_product_attribute_target` = ' . (int)$product['id_product_attribute'] . ',
                        `active` = 1,
                        `last_sync` = "' . $now . '",
                        `updated_at` = "' . $now . '"
                    WHERE `id_reference_map` = ' . $id_reference_map;
                
                $results['updated_mappings']++;
                $results['details'][] = [
                    'reference' => $product['reference'],
                    'id_product' => $product['id_product'],
                    'id_product_attribute' => $product['id_product_attribute'],
                    'status' => 'success',
                    'type' => 'updated'
                ];
            } else {
                // Crear nuevo mapeo
                $insert_values[] = '(
                    "' . $reference . '",
                    ' . (int)$product['id_product'] . ',
                    ' . (int)$product['id_product_attribute'] . ',
                    ' . (int)$product['id_product'] . ',
                    ' . (int)$product['id_product_attribute'] . ',
                    ' . (int)$source_store_id . ',
                    ' . (int)$target_store_id . ',
                    1,
                    "' . $now . '",
                    "' . $now . '",
                    "' . $now . '"
                )';
                
                $results['new_mappings']++;
                $results['details'][] = [
                    'reference' => $product['reference'],
                    'id_product' => $product['id_product'],
                    'id_product_attribute' => $product['id_product_attribute'],
                    'status' => 'success',
                    'type' => 'new'
                ];
            }
            
            // Ejecutar consultas por lotes de 100 para evitar problemas con consultas muy grandes
            if (count($insert_values) >= 100) {
                self::executeBatchInserts($insert_values);
                $insert_values = [];
            }
            
            if (count($update_queries) >= 100) {
                self::executeBatchUpdates($update_queries);
                $update_queries = [];
            }
        }
        
        // Ejecutar consultas restantes
        if (!empty($insert_values)) {
            self::executeBatchInserts($insert_values);
        }
        
        if (!empty($update_queries)) {
            self::executeBatchUpdates($update_queries);
        }
        
        if ($debug_mode) {
            error_log('Mapeo completado: ' . $results['new_mappings'] . ' nuevos, ' . 
                $results['updated_mappings'] . ' actualizados, ' . 
                $results['failed_mappings'] . ' fallidos');
        }
    } catch (Exception $e) {
        error_log('ERROR grave en scanAndMapReferences: ' . $e->getMessage() . ' | ' . $e->getTraceAsString());
        $results['failed_mappings']++;
    }
    
    return $results;
}

/**
 * Ejecuta inserciones por lotes para mejorar rendimiento
 *
 * @param array $values Arrays de valores a insertar
 * @return bool Éxito o fallo
 */
private static function executeBatchInserts($values)
{
    if (empty($values)) {
        return true;
    }
    
    $query = 'INSERT INTO `' . _DB_PREFIX_ . 'stock_sync_references` (
        `reference`, 
        `id_product_source`, 
        `id_product_attribute_source`, 
        `id_product_target`, 
        `id_product_attribute_target`, 
        `id_store_source`, 
        `id_store_target`, 
        `active`, 
        `last_sync`, 
        `created_at`, 
        `updated_at`
    ) VALUES ' . implode(',', $values);
    
    try {
        return Db::getInstance()->execute($query);
    } catch (Exception $e) {
        error_log('Error en batch insert: ' . $e->getMessage());
        return false;
    }
}

/**
 * Ejecuta actualizaciones por lotes para mejorar rendimiento
 *
 * @param array $queries Array de consultas de actualización
 * @return bool Éxito o fallo
 */
private static function executeBatchUpdates($queries)
{
    if (empty($queries)) {
        return true;
    }
    
    $success = true;
    foreach ($queries as $query) {
        try {
            $success &= Db::getInstance()->execute($query);
        } catch (Exception $e) {
            error_log('Error en batch update: ' . $e->getMessage());
            $success = false;
        }
    }
    
    return $success;
}
    
    /**
     * Helper method to find a product in target store by reference
     *
     * @param string $reference Product reference 
     * @param StockSyncStore $store Target store
     * @return array|false Product data if found, false otherwise
     */
    private static function findProductInTargetStore($reference, $store)
    {
        if (!Validate::isLoadedObject($store)) {
            return false;
        }
        
        try {
            $webservice = new StockSyncWebservice();
            
            // Try to get stock by reference (this will verify the reference exists)
            $result = $webservice->getStockByReference($store->getFields(), $reference);
            
            if ($result === false) {
                return false;
            }
            
            // For now, when we find a match, just return basic info
            // In a real implementation, we would get more details from the target store
            return [
                'id_product' => 0, // We don't know the actual ID in the target store
                'id_product_attribute' => 0, // We don't know the actual attribute ID
                'reference' => $reference,
                'exists' => true
            ];
        } catch (Exception $e) {
            error_log('Error finding product in target store: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get all product references
     *
     * @return array
     */
    public static function getAllReferences()
    {
        $references = [];
        
        try {
            // Get simple product references
            $query = new DbQuery();
            $query->select('p.id_product, p.reference')
                ->from('product', 'p')
                ->where('p.reference != ""')
               ; // Quitar el filtro de activo
          /** ->where('p.active = 1');*/
            
            $products = Db::getInstance()->executeS($query);
            
            if (is_array($products)) {
                foreach ($products as $product) {
                    $references[] = [
                        'reference' => $product['reference'],
                        'id_product' => (int) $product['id_product'],
                        'id_product_attribute' => 0,
                        'type' => 'product'
                    ];
                }
            }
            
            // Get combination references
            $query = new DbQuery();
            $query->select('pa.id_product, pa.id_product_attribute, pa.reference')
                ->from('product_attribute', 'pa')
                ->innerJoin('product', 'p', 'p.id_product = pa.id_product')
                ->where('pa.reference != ""')
; // Quitar el filtro de activo
          /** ->where('p.active = 1');*/
            
            $combinations = Db::getInstance()->executeS($query);
            
            if (is_array($combinations)) {
                foreach ($combinations as $combination) {
                    $references[] = [
                        'reference' => $combination['reference'],
                        'id_product' => (int) $combination['id_product'],
                        'id_product_attribute' => (int) $combination['id_product_attribute'],
                        'type' => 'combination'
                    ];
                }
            }
        } catch (Exception $e) {
            error_log('Error in getAllReferences: ' . $e->getMessage());
        }
        
        return $references;
    }
    
    /**
     * Check if a table exists in the database
     *
     * @param string $table Table name with prefix
     * @return bool
     */
    private static function tableExists($table)
    {
        try {
            $result = Db::getInstance()->executeS('SHOW TABLES LIKE "' . $table . '"');
            return !empty($result);
        } catch (Exception $e) {
            // Log the error
            error_log('Error checking if table exists: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Deactivate reference mapping
     *
     * @param int $id_reference_map Reference mapping ID
     * @return bool
     */
    public static function deactivateReferenceMapping($id_reference_map)
    {
        return Db::getInstance()->update(
            'stock_sync_references',
            [
                'active' => 0,
                'updated_at' => date('Y-m-d H:i:s')
            ],
            'id_reference_map = ' . (int) $id_reference_map
        );
    }
    
    /**
     * Get reference mappings
     *
     * @param int $source_store_id Source store ID (0 for all)
     * @param int $target_store_id Target store ID (0 for all)
     * @param bool $active_only Only active mappings
     * @return array
     */
    public static function getMappings($source_store_id = 0, $target_store_id = 0, $active_only = true)
    {
        $query = new DbQuery();
        $query->select('r.*, s1.store_name AS source_store_name, s2.store_name AS target_store_name')
            ->from('stock_sync_references', 'r')
            ->leftJoin('stock_sync_stores', 's1', 's1.id_store = r.id_store_source')
            ->leftJoin('stock_sync_stores', 's2', 's2.id_store = r.id_store_target')
            ->orderBy('r.reference ASC');
        
        if ($source_store_id > 0) {
            $query->where('r.id_store_source = ' . (int) $source_store_id);
        }
        
        if ($target_store_id > 0) {
            $query->where('r.id_store_target = ' . (int) $target_store_id);
        }
        
        if ($active_only) {
            $query->where('r.active = 1');
        }
        
        return Db::getInstance()->executeS($query);
    }
    
    /**
     * Check for duplicate references
     *
     * @return array List of duplicate references
     */
    public static function checkDuplicateReferences()
    {
        $duplicates = [];
        
        // Check for duplicate product references
        $query = '
            SELECT reference, COUNT(*) as count
            FROM ' . _DB_PREFIX_ . 'product
            WHERE reference != ""
            GROUP BY reference
            HAVING count > 1
        ';
        
        $results = Db::getInstance()->executeS($query);
        
        if (is_array($results)) {
            foreach ($results as $row) {
                // Get the products with this reference
                $query = new DbQuery();
                $query->select('p.id_product, pl.name')
                    ->from('product', 'p')
                    ->leftJoin('product_lang', 'pl', 'p.id_product = pl.id_product AND pl.id_lang = ' . (int) Context::getContext()->language->id)
                    ->where('p.reference = "' . pSQL($row['reference']) . '"');
                
                $products = Db::getInstance()->executeS($query);
                
                $duplicates[] = [
                    'reference' => $row['reference'],
                    'count' => $row['count'],
                    'type' => 'product',
                    'items' => $products
                ];
            }
        }
        
        // Check for duplicate combination references
        $query = '
            SELECT reference, COUNT(*) as count
            FROM ' . _DB_PREFIX_ . 'product_attribute
            WHERE reference != ""
            GROUP BY reference
            HAVING count > 1
        ';
        
        $results = Db::getInstance()->executeS($query);
        
        if (is_array($results)) {
            foreach ($results as $row) {
                // Get the combinations with this reference
                $query = new DbQuery();
                $query->select('pa.id_product, pa.id_product_attribute, pl.name')
                    ->from('product_attribute', 'pa')
                    ->innerJoin('product', 'p', 'p.id_product = pa.id_product')
                    ->leftJoin('product_lang', 'pl', 'p.id_product = pl.id_product AND pl.id_lang = ' . (int) Context::getContext()->language->id)
                    ->where('pa.reference = "' . pSQL($row['reference']) . '"');
                
                $combinations = Db::getInstance()->executeS($query);
                
                $duplicates[] = [
                    'reference' => $row['reference'],
                    'count' => $row['count'],
                    'type' => 'combination',
                    'items' => $combinations
                ];
            }
        }
        
        // Check for references that exist in both products and combinations
        $query = '
            SELECT p.reference
            FROM ' . _DB_PREFIX_ . 'product p
            INNER JOIN ' . _DB_PREFIX_ . 'product_attribute pa ON p.reference = pa.reference
            WHERE p.reference != ""
            GROUP BY p.reference
        ';
        
        $results = Db::getInstance()->executeS($query);
        
        if (is_array($results)) {
            foreach ($results as $row) {
                // Get the products with this reference
                $query = new DbQuery();
                $query->select('p.id_product, pl.name')
                    ->from('product', 'p')
                    ->leftJoin('product_lang', 'pl', 'p.id_product = pl.id_product AND pl.id_lang = ' . (int) Context::getContext()->language->id)
                    ->where('p.reference = "' . pSQL($row['reference']) . '"');
                
                $products = Db::getInstance()->executeS($query);
                
                // Get the combinations with this reference
                $query = new DbQuery();
                $query->select('pa.id_product, pa.id_product_attribute, pl.name')
                    ->from('product_attribute', 'pa')
                    ->innerJoin('product', 'p', 'p.id_product = pa.id_product')
                    ->leftJoin('product_lang', 'pl', 'p.id_product = pl.id_product AND pl.id_lang = ' . (int) Context::getContext()->language->id)
                    ->where('pa.reference = "' . pSQL($row['reference']) . '"');
                
                $combinations = Db::getInstance()->executeS($query);
                
                $duplicates[] = [
                    'reference' => $row['reference'],
                    'count' => count($products) + count($combinations),
                    'type' => 'mixed',
                    'items' => array_merge(
                        array_map(function($item) { $item['type'] = 'product'; return $item; }, $products),
                        array_map(function($item) { $item['type'] = 'combination'; return $item; }, $combinations)
                    )
                ];
            }
        }
        
        return $duplicates;
    }
    
    /**
     * Update mapping last sync date
     *
     * @param int $id_reference_map Reference mapping ID
     * @return bool
     */
    public static function updateLastSync($id_reference_map)
    {
        return Db::getInstance()->update(
            'stock_sync_references',
            [
                'last_sync' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ],
            'id_reference_map = ' . (int) $id_reference_map
        );
    }
    
 /**
 * Get mapping statistics
 *
 * @return array
 */
public static function getStatistics()
{
    $stats = [
        'total' => 0,
        'active' => 0,
        'inactive' => 0,
        'products' => 0,
        'combinations' => 0,
        'newest' => null,
        'oldest' => null
    ];
    
    try {
        // Determinar el nombre exacto de la tabla
        $table_name = _DB_PREFIX_ . 'stock_sync_references';
        
        // Verificar si la tabla existe realmente
        $table_exists = Db::getInstance()->executeS("SHOW TABLES LIKE '" . $table_name . "'");
        if (empty($table_exists)) {
            // Buscar otras tablas similares
            $similar_tables = Db::getInstance()->executeS("SHOW TABLES LIKE '%" . 'stock_sync_reference' . "%'");
            if (!empty($similar_tables)) {
                // Usar la primera tabla encontrada
                $table_name = reset($similar_tables)[0];
            } else {
                return $stats; // No hay tablas similares
            }
        }
        
        // OPTIMIZACIÓN: Una sola consulta para obtener todos los conteos
        $query = "SELECT 
                    COUNT(*) as total,
                    SUM(IF(active = 1, 1, 0)) as active,
                    SUM(IF(active = 0, 1, 0)) as inactive,
                    SUM(IF(id_product_attribute_source = 0, 1, 0)) as products,
                    SUM(IF(id_product_attribute_source > 0, 1, 0)) as combinations,
                    MAX(created_at) as newest,
                    MIN(created_at) as oldest
                  FROM `" . $table_name . "`";
        
        $result = Db::getInstance()->getRow($query);
        
        if ($result) {
            $stats['total'] = (int)$result['total'];
            $stats['active'] = (int)$result['active'];
            $stats['inactive'] = (int)$result['inactive'];
            $stats['products'] = (int)$result['products'];
            $stats['combinations'] = (int)$result['combinations'];
            $stats['newest'] = $result['newest'];
            $stats['oldest'] = $result['oldest'];
        }
        
        return $stats;
    } catch (Exception $e) {
        error_log('Error en getStatistics de Reference: ' . $e->getMessage());
        return $stats;
    }
}
}
	

