<?php
/**
 * Stock Sync Class
 *
 * @author    Expert PrestaShop Developer
 * @copyright 2025
 * @license   Commercial
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

class StockSync
{
    /**
     * @var string API endpoint for stock synchronization
     */
    const API_ENDPOINT = 'api/stock/sync';

    /**
     * Initialize the stock synchronization
     *
     * @return bool
     */
    public static function init()
    {
        // Check if module is active
        if (!(bool) Configuration::get('STOCK_SYNC_ACTIVE')) {
            return false;
        }

        return true;
    }

    /**
 * Process the stock synchronization queue
 *
 * @param int $limit Number of queue items to process
 * @return array Results of the processing
 */
public static function processQueue($limit = 50)
{
    $results = [
        'success' => 0,
        'failed' => 0,
        'skipped' => 0,
        'details' => []
    ];

    // Get pending queue items
    $queue_items = StockSyncQueue::getPending($limit);
    
    if (empty($queue_items)) {
        return $results;
    }

    // OPTIMIZACIÓN: Inicializar el webservice una sola vez
    $webservice = new StockSyncWebservice();

    // OPTIMIZACIÓN: Cargar todas las tiendas al inicio para evitar consultas repetidas
    $stores_data = [];
    $store_ids = array_unique(array_column($queue_items, 'target_store_id'));
    
    if (!empty($store_ids)) {
        // Obtener todos los datos de tiendas en una sola consulta
        $query = new DbQuery();
        $query->select('*')
            ->from('stock_sync_stores')
            ->where('id_store IN (' . implode(',', $store_ids) . ')');
        
        $stores_result = Db::getInstance()->executeS($query);
        if (is_array($stores_result)) {
            foreach ($stores_result as $store) {
                $stores_data[(int)$store['id_store']] = $store;
            }
        }
    }

    foreach ($queue_items as $item) {
        // OPTIMIZACIÓN: Usar datos de la tienda cargados previamente
        if (!isset($stores_data[$item['target_store_id']]) || $stores_data[$item['target_store_id']]['active'] == 0) {
            StockSyncQueue::updateStatus($item['id_queue'], 'skipped', 'Target store is inactive or invalid');
            $results['skipped']++;
            $results['details'][] = [
                'id_queue' => $item['id_queue'],
                'reference' => $item['reference'],
                'status' => 'skipped',
                'message' => 'Target store is inactive or invalid'
            ];
            continue;
        }
        
        $store = $stores_data[$item['target_store_id']];

        // OPTIMIZACIÓN: Verificar intentos máximos de reintento
        $max_retries = (int) Configuration::get('STOCK_SYNC_RETRY_COUNT', 3);
        if ($item['attempts'] >= $max_retries) {
            StockSyncQueue::updateStatus($item['id_queue'], 'failed', 'Exceeded maximum retry attempts');
            $results['failed']++;
            $results['details'][] = [
                'id_queue' => $item['id_queue'],
                'reference' => $item['reference'],
                'status' => 'failed',
                'message' => 'Exceeded maximum retry attempts'
            ];
            continue;
        }

        // OPTIMIZACIÓN: Actualizar a estado processing solo si no fue saltado
        StockSyncQueue::updateStatus($item['id_queue'], 'processing');

        // OPTIMIZACIÓN: Sincronizar stock con datos completos de la tienda para evitar otra consulta
        $result = $webservice->syncStock(
            $item['id_queue'],
            $store,
            $item['reference'],
            $item['new_quantity']
        );

        if ($result) {
            StockSyncQueue::updateStatus($item['id_queue'], 'completed');
            $results['success']++;
            $results['details'][] = [
                'id_queue' => $item['id_queue'],
                'reference' => $item['reference'],
                'status' => 'completed',
                'message' => 'Successfully synchronized'
            ];
            
            // OPTIMIZACIÓN: Actualizar timestamp de última sincronización en la tienda
            if (!empty($store['id_store'])) {
                Db::getInstance()->update(
                    'stock_sync_stores',
                    [
                        'last_sync' => date('Y-m-d H:i:s'),
                        'updated_at' => date('Y-m-d H:i:s')
                    ],
                    'id_store = ' . (int) $store['id_store']
                );
            }
        } else {
            // Incrementar intentos y marcar como pendiente para reintentar
            StockSyncQueue::incrementAttempt($item['id_queue']);
            StockSyncQueue::updateStatus($item['id_queue'], 'pending', 'Synchronization failed, will retry later');
            $results['failed']++;
            $results['details'][] = [
                'id_queue' => $item['id_queue'],
                'reference' => $item['reference'],
                'status' => 'pending',
                'message' => 'Synchronization failed, will retry later'
            ];
        }
    }

    return $results;
}
/**
 * Handle incoming stock update request from another store
 *
 * @param string $reference Product reference
 * @param float $quantity New quantity
 * @param string $token Security token
 * @return bool
 */
public static function handleIncomingUpdate($reference, $quantity, $token)
{
    // OPTIMIZACIÓN: Comprobar aceptación incondicional de tokens
    $accept_any_token = (bool)Configuration::get('STOCK_SYNC_ACCEPT_ANY_TOKEN', false);
    
    if (!$accept_any_token) {
        // Validar el token solo si no está habilitada la aceptación incondicional
        $valid_token = StockSyncTools::validateToken($token);
        if (!$valid_token) {
            StockSyncLog::add('Token inválido recibido para actualización de stock', 'error');
            return false;
        }
    }
    
    // Log de depuración si está activado
    if (Configuration::get('STOCK_SYNC_DEBUG_MODE')) {
        $log_file = _PS_MODULE_DIR_ . 'stocksyncmodule/logs/sync_debug.log';
        if (!file_exists($log_file)) {
            file_put_contents($log_file, "# Stock Sync Debug Log - handleIncomingUpdate\n");
        }
        file_put_contents(
            $log_file, 
            date('Y-m-d H:i:s') . ' - Recibida actualización para: ' . $reference . ' cantidad: ' . $quantity . "\n", 
            FILE_APPEND
        );
    }

    // OPTIMIZACIÓN: Buscar producto por referencia con caché
    $product_data = StockSyncReference::getProductByReference($reference);
    
    if (!$product_data) {
        StockSyncLog::add(
            sprintf('No se encontró producto con referencia %s', $reference),
            'warning'
        );
        return false;
    }

    $id_product = (int) $product_data['id_product'];
    $id_product_attribute = (int) $product_data['id_product_attribute'];

    // OPTIMIZACIÓN: Obtener cantidad actual una sola vez
    $current_quantity = StockAvailable::getQuantityAvailableByProduct($id_product, $id_product_attribute);

    // OPTIMIZACIÓN: Verificar conflictos solo si hay operaciones pendientes
    $pending_updates = StockSyncQueue::getByReference($reference, 'pending,processing');
    
    if (!empty($pending_updates)) {
        // Obtener estrategia de resolución de conflictos
        $strategy = Configuration::get('STOCK_SYNC_CONFLICT_STRATEGY', 'last_update_wins');
        
        // OPTIMIZACIÓN: Manejar estrategia según configuración
        switch ($strategy) {
            case 'last_update_wins':
                // Simplemente continuar con la actualización
                break;
                
            case 'source_priority':
                // Verificar prioridad de la tienda origen
                $source_store_id = (int) $pending_updates[0]['source_store_id'];
                $source_store = new StockSyncStore($source_store_id);
                
                if (Validate::isLoadedObject($source_store) && $source_store->priority > 0) {
                    // Rechazar esta actualización si la tienda origen tiene prioridad
                    StockSyncLog::add(
                        sprintf(
                            'Conflicto resuelto por prioridad para referencia %s. Manteniendo cola pendiente.',
                            $reference
                        ),
                        'conflict'
                    );
                    return false;
                }
                break;
                
            case 'manual_resolution':
                // Crear registro de conflicto y requerir resolución manual
                StockSyncLog::add(
                    sprintf(
                        'Resolución manual requerida para referencia %s. Actual: %f, Entrante: %f',
                        $reference,
                        $current_quantity,
                        $quantity
                    ),
                    'conflict'
                );
                // Continuar con la actualización pero registrar el conflicto
                break;
                
            default:
                // Por defecto, "last_update_wins"
                break;
        }
    }

    // OPTIMIZACIÓN: Intentar la actualización directamente, sin condiciones adicionales
    $result = StockAvailable::setQuantity($id_product, $id_product_attribute, $quantity);
    
    if ($result) {
        // Registrar solo si fue exitoso
        StockSyncLog::add(
            sprintf(
                'Stock actualizado para referencia %s: %f -> %f',
                $reference,
                $current_quantity,
                $quantity
            ),
            'info'
        );
        return true;
    }

    // Registrar error si falló
    StockSyncLog::add(
        sprintf(
            'Falló la actualización de stock para referencia %s',
            $reference
        ),
        'error'
    );
    return false;
}
    /**
     * Handle conflicts in bidirectional synchronization
     *
     * @param string $reference Product reference
     * @param float $current_quantity Current stock quantity
     * @param float $incoming_quantity Incoming stock quantity
     * @param array $pending_updates Pending updates for this reference
     * @return bool Whether the conflict was resolved
     */
    public static function handleConflict($reference, $current_quantity, $incoming_quantity, $pending_updates)
    {
        // Get conflict resolution strategy
        $strategy = Configuration::get('STOCK_SYNC_CONFLICT_STRATEGY', 'last_update_wins');
        
        switch ($strategy) {
            case 'last_update_wins':
                // Just apply the incoming update
                return true;
                
            case 'source_priority':
                // Check if the source store has priority
                $source_store_id = (int) $pending_updates[0]['source_store_id'];
                $source_store = new StockSyncStore($source_store_id);
                
                if ($source_store->priority > 0) {
                    // Cancel the incoming update and keep our pending update
                    return false;
                }
                
                // Apply the incoming update
                return true;
                
            case 'manual_resolution':
                // Create a record in the conflict table and require manual resolution
                // For this example, we'll just log the conflict and proceed with the update
                StockSyncLog::add(
                    sprintf(
                        'Manual conflict resolution required for reference %s. Current: %f, Incoming: %f',
                        $reference,
                        $current_quantity,
                        $incoming_quantity
                    ),
                    'conflict'
                );
                return true;
                
            default:
                // Default to last update wins
                return true;
        }
    }

    /**
     * Check for stock discrepancies between stores
     *
     * @return array List of discrepancies found
     */
    public static function checkDiscrepancies()
    {
        $discrepancies = [];
        $stores = StockSyncStore::getActiveStores();
        
        if (count($stores) < 2) {
            return $discrepancies;
        }
        
        // Get all mapped references
        $references = StockSyncReference::getAllReferences();
        
        foreach ($references as $reference) {
            $quantities = [];
            $has_discrepancy = false;
            
            foreach ($stores as $store) {
                $webservice = new StockSyncWebservice();
                $store_quantity = $webservice->getStockByReference($store, $reference['reference']);
                
                if ($store_quantity !== false) {
                    $quantities[$store['id_store']] = [
                        'store_name' => $store['store_name'],
                        'quantity' => $store_quantity
                    ];
                    
                    // Check if this quantity differs from others
                    if (!empty($quantities)) {
                        $first_quantity = reset($quantities)['quantity'];
                        if ($store_quantity != $first_quantity) {
                            $has_discrepancy = true;
                        }
                    }
                }
            }
            
            if ($has_discrepancy) {
                $discrepancies[] = [
                    'reference' => $reference['reference'],
                    'id_product' => $reference['id_product'],
                    'id_product_attribute' => $reference['id_product_attribute'],
                    'quantities' => $quantities
                ];
            }
        }
        
        return $discrepancies;
    }
}