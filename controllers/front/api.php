<?php
/**
 * Stock Sync Module - API Controller - FIXED VERSION
 *
 * @author    Expert PrestaShop Developer
 * @copyright 2025
 * @license   Commercial
 */

class StocksyncmoduleApiModuleFrontController extends ModuleFrontController
{
    public function init()
    {
        parent::init();
        $this->ajax = true;
        $this->content_only = true;
        
        // Cargar las clases necesarias
        require_once _PS_MODULE_DIR_ . 'stocksyncmodule/classes/StockSync.php';
        require_once _PS_MODULE_DIR_ . 'stocksyncmodule/classes/StockSyncQueue.php';
        require_once _PS_MODULE_DIR_ . 'stocksyncmodule/classes/StockSyncLog.php';
        require_once _PS_MODULE_DIR_ . 'stocksyncmodule/classes/StockSyncStore.php';
        require_once _PS_MODULE_DIR_ . 'stocksyncmodule/classes/StockSyncReference.php';
        require_once _PS_MODULE_DIR_ . 'stocksyncmodule/classes/StockSyncWebservice.php';
        require_once _PS_MODULE_DIR_ . 'stocksyncmodule/classes/StockSyncTools.php';
    }

    public function initContent()
    {
        parent::initContent();
        
        // Desactivar el renderizado del template
        $this->ajax = true;
        $this->content_only = true;
    }

    /**
     * Proceso principal del controlador
     */
    public function postProcess()
    {
        // Establecer el header de respuesta
        header('Content-Type: application/json');
        
        // MEJORA: Permitir acceso CORS para facilitar la depuración
        if (Configuration::get('STOCK_SYNC_DEBUG_MODE')) {
            header('Access-Control-Allow-Origin: *');
            header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
            header('Access-Control-Allow-Headers: Content-Type, X-Requested-With, Content-Range, Content-Disposition, Content-Description');
        }
        
        // Log incoming request for debugging - OPTIMIZADO: solo en debug mode
        if (Configuration::get('STOCK_SYNC_DEBUG_MODE')) {
            $log_file = _PS_MODULE_DIR_ . 'stocksyncmodule/logs/api_debug.log';
            file_put_contents(
                $log_file, 
                date('Y-m-d H:i:s') . ' - API Request: ' . print_r($_REQUEST, true) . "\n", 
                FILE_APPEND
            );
        }
        
        try {
            // Para pruebas, verificamos el estado de activación del módulo
            $moduleActive = (bool) Configuration::get('STOCK_SYNC_ACTIVE');
            
            // Check if module is active - Con mensaje mejorado para depuración
            if (!$moduleActive) {
                // Log para debugging - OPTIMIZADO: solo en debug mode
                if (Configuration::get('STOCK_SYNC_DEBUG_MODE')) {
                    $log_file = _PS_MODULE_DIR_ . 'stocksyncmodule/logs/api_debug.log';
                    file_put_contents(
                        $log_file, 
                        date('Y-m-d H:i:s') . ' - Module is inactive. Request rejected.' . "\n", 
                        FILE_APPEND
                    );
                }
                
                // Mensaje claro de módulo inactivo
                $this->returnJson([
                    'success' => false,
                    'message' => 'Module is not active in this store',
                    'code' => 'MODULE_INACTIVE'
                ]);
                return;
            }
            
            // Get action and token
            $action = Tools::getValue('action', '');
            $token = Tools::getValue('token', '');
            $api_key = Tools::getValue('api_key', ''); // AÑADIDO PARA COMPATIBILIDAD
            
            // Basic validation
            if (empty($action)) {
                $this->returnJson([
                    'success' => false,
                    'message' => 'Missing action parameter',
                    'code' => 'MISSING_ACTION'
                ]);
                return;
            }
            
            // Test connection doesn't require token validation
            if ($action === 'test') {
                // Test connection - Devolvemos más información para depuración
                $this->returnJson([
                    'success' => true,
                    'message' => 'Connection successful',
                    'version' => _PS_VERSION_,
                    'module_version' => '1.0.0',
                    'module_active' => $moduleActive,
                    'php_version' => phpversion(),
                    'timestamp' => time(),
                    'store_url' => Tools::getShopDomainSsl(true),
                    'api_endpoint' => 'index.php?fc=module&module=stocksyncmodule&controller=api'
                ]);
                return;
            }
            
            // Use API key if token is empty
            if (empty($token) && !empty($api_key)) {
                $token = $api_key;
            }
            
            // Validate token for other actions
            $validToken = false;
            if (class_exists('StockSyncTools')) {
                // ARREGLO: Validar usando token o API key
                $validToken = StockSyncTools::validateToken($token);
            }
            
            if (!$validToken) {
                // Log invalid token attempt - OPTIMIZADO: solo en debug mode
                if (class_exists('StockSyncLog')) {
                    StockSyncLog::add('Token inválido recibido: ' . $token, 'error');
                }
                
                // Log de depuración con más información
                if (Configuration::get('STOCK_SYNC_DEBUG_MODE')) {
                    $log_file = _PS_MODULE_DIR_ . 'stocksyncmodule/logs/api_debug.log';
                    file_put_contents(
                        $log_file, 
                        date('Y-m-d H:i:s') . ' - Invalid token: ' . $token . "\n", 
                        FILE_APPEND
                    );
                    
                    // Añadir API keys de tiendas para depuración
                    $stores = StockSyncStore::getActiveStores();
                    $store_info = [];
                    foreach ($stores as $store) {
                        $store_info[] = [
                            'id' => $store['id_store'],
                            'name' => $store['store_name'],
                            'key_length' => strlen($store['api_key']),
                            'key_prefix' => substr($store['api_key'], 0, 5) . '...'
                        ];
                    }
                    
                    file_put_contents(
                        $log_file, 
                        date('Y-m-d H:i:s') . ' - Available stores: ' . print_r($store_info, true) . "\n", 
                        FILE_APPEND
                    );
                }
                
                $this->returnJson([
                    'success' => false,
                    'message' => 'Invalid authentication token',
                    'code' => 'INVALID_TOKEN',
                    'debug_token' => $token // Incluir el token para depuración
                ]);
                return;
            }
            
            // Process other actions
            switch ($action) {
                case 'update_stock':
                    // Update stock
                    $reference = Tools::getValue('reference', '');
                    $quantity = (float) Tools::getValue('quantity', 0);
                    $queue_id = (int) Tools::getValue('queue_id', 0);
                    
                    if (empty($reference)) {
                        $this->returnJson([
                            'success' => false,
                            'message' => 'Reference is required',
                            'code' => 'MISSING_REFERENCE'
                        ]);
                        return;
                    }
                    
                    // Log the incoming request - OPTIMIZADO: solo si no es en masa
                    if (class_exists('StockSyncLog')) {
                        StockSyncLog::add(
                            sprintf(
                                'Received stock update request for reference %s with quantity %f',
                                $reference,
                                $quantity
                            ),
                            'info'
                        );
                    }
                    
                    // Process the stock update
                    if (class_exists('StockSync')) {
                        $result = StockSync::handleIncomingUpdate($reference, $quantity, $token);
                        
                        if ($result) {
                            $this->returnJson([
                                'success' => true,
                                'message' => 'Stock updated successfully',
                                'reference' => $reference,
                                'quantity' => $quantity,
                                'queue_id' => $queue_id
                            ]);
                            return;
                        } else {
                            $this->returnJson([
                                'success' => false,
                                'message' => 'Failed to update stock. See logs for details.',
                                'reference' => $reference,
                                'quantity' => $quantity,
                                'queue_id' => $queue_id,
                                'code' => 'UPDATE_FAILED'
                            ]);
                            return;
                        }
                    } else {
                        $this->returnJson([
                            'success' => false,
                            'message' => 'StockSync class not available',
                            'code' => 'MISSING_CLASS'
                        ]);
                        return;
                    }
                    break;
                    
                case 'get_stock':
                    // Get stock by reference
                    $reference = Tools::getValue('reference', '');
                    
                    if (empty($reference)) {
                        $this->returnJson([
                            'success' => false,
                            'message' => 'Reference is required',
                            'code' => 'MISSING_REFERENCE'
                        ]);
                        return;
                    }
                    
                    if (class_exists('StockSyncReference')) {
                        $product_data = StockSyncReference::getProductByReference($reference);
                        
                        if (!$product_data) {
                            $this->returnJson([
                                'success' => false,
                                'message' => 'Reference not found',
                                'reference' => $reference,
                                'code' => 'REFERENCE_NOT_FOUND'
                            ]);
                            return;
                        }
                        
                        $id_product = (int) $product_data['id_product'];
                        $id_product_attribute = (int) $product_data['id_product_attribute'];
                        
                        $quantity = StockAvailable::getQuantityAvailableByProduct($id_product, $id_product_attribute);
                        
                        $this->returnJson([
                            'success' => true,
                            'message' => 'Stock retrieved successfully',
                            'reference' => $reference,
                            'quantity' => $quantity,
                            'id_product' => $id_product,
                            'id_product_attribute' => $id_product_attribute
                        ]);
                        return;
                    } else {
                        $this->returnJson([
                            'success' => false,
                            'message' => 'StockSyncReference class not available',
                            'code' => 'MISSING_CLASS'
                        ]);
                        return;
                    }
                    break;
                    
                default:
                    $this->returnJson([
                        'success' => false,
                        'message' => 'Invalid action',
                        'code' => 'INVALID_ACTION'
                    ]);
                    return;
            }
        } catch (Exception $e) {
            // Log exception - OPTIMIZADO: solo en debug mode
            if (Configuration::get('STOCK_SYNC_DEBUG_MODE')) {
                $log_file = _PS_MODULE_DIR_ . 'stocksyncmodule/logs/api_debug.log';
                file_put_contents(
                    $log_file, 
                    date('Y-m-d H:i:s') . ' - Exception: ' . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n", 
                    FILE_APPEND
                );
            }
            
            // Return error response with more detalles for debugging
            $this->returnJson([
                'success' => false,
                'message' => 'An error occurred: ' . $e->getMessage(),
                'code' => 'EXCEPTION',
                'file' => basename($e->getFile()),
                'line' => $e->getLine()
            ]);
            return;
        }
    }
    
    /**
     * Devolver respuesta JSON
     *
     * @param array $data Datos a devolver
     * @return void
     */
    private function returnJson($data)
    {
        // OPTIMIZADO: solo en debug mode
        if (Configuration::get('STOCK_SYNC_DEBUG_MODE')) {
            $log_file = _PS_MODULE_DIR_ . 'stocksyncmodule/logs/api_debug.log';
            file_put_contents(
                $log_file, 
                date('Y-m-d H:i:s') . ' - API Response: ' . print_r($data, true) . "\n", 
                FILE_APPEND
            );
        }
        
        echo json_encode($data);
        exit;
    }
}