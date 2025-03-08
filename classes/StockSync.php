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

        $webservice = new StockSyncWebservice();

        foreach ($queue_items as $item) {
            // Get target store
            $store = new StockSyncStore($item['target_store_id']);
            
            if (!Validate::isLoadedObject($store) || !$store->active) {
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

            // Check if we've exceeded the retry count
            if ($item['attempts'] >= (int) Configuration::get('STOCK_SYNC_RETRY_COUNT')) {
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

            // Update to processing status
            StockSyncQueue::updateStatus($item['id_queue'], 'processing');

            // Try to sync the stock
            $result = $webservice->syncStock(
                $item['id_queue'],
                $store->getFields(),
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
            } else {
                // Increment attempt count
                StockSyncQueue::incrementAttempt($item['id_queue']);
                
                // Update status to pending for retry later
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
        // Validate token
        if (!StockSyncTools::validateToken($token)) {
            StockSyncLog::add('Invalid token received for stock update', 'error');
            return false;
        }

        // Find product by reference
        $product_data = StockSyncReference::getProductByReference($reference);
        
        if (!$product_data) {
            StockSyncLog::add(
                sprintf('No product found with reference %s', $reference),
                'warning'
            );
            return false;
        }

        $id_product = (int) $product_data['id_product'];
        $id_product_attribute = (int) $product_data['id_product_attribute'];

        // Get current quantity
        $current_quantity = StockAvailable::getQuantityAvailableByProduct($id_product, $id_product_attribute);

        // Check for conflict (if both stores modified the stock at almost the same time)
        $pending_updates = StockSyncQueue::getByReference($reference, 'pending,processing');
        
        if (!empty($pending_updates)) {
            // Handle conflict based on configuration
            $conflict_resolved = self::handleConflict($reference, $current_quantity, $quantity, $pending_updates);
            
            if (!$conflict_resolved) {
                StockSyncLog::add(
                    sprintf(
                        'Conflict detected for reference %s. Current: %f, Incoming: %f',
                        $reference,
                        $current_quantity,
                        $quantity
                    ),
                    'conflict'
                );
                return false;
            }
        }

        // Update stock
        $result = StockAvailable::setQuantity($id_product, $id_product_attribute, $quantity);
        
        if ($result) {
            StockSyncLog::add(
                sprintf(
                    'Stock updated for reference %s: %f -> %f',
                    $reference,
                    $current_quantity,
                    $quantity
                ),
                'info'
            );
            return true;
        }

        StockSyncLog::add(
            sprintf(
                'Failed to update stock for reference %s',
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