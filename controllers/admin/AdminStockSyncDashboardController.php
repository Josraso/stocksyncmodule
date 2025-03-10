<?php
/**
 * Stock Sync Dashboard Controller
 *
 * @author    Expert PrestaShop Developer
 * @copyright 2025
 * @license   Commercial
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

class AdminStockSyncDashboardController extends ModuleAdminController
{
    public function __construct()
    {
        $this->bootstrap = true;
        $this->display = 'view';
        
        // Asignar el título sin usar l() en el constructor
        $this->meta_title = 'Stock Sync Dashboard';
        
        parent::__construct();
        
        if (!$this->module->active) {
            Tools::redirectAdmin($this->context->link->getAdminLink('AdminModules'));
        }
    }
    
    /**
     * Set media
     */
    public function setMedia($isNewTheme = false)
    {
        parent::setMedia($isNewTheme);
        
        $this->addJqueryUI('ui.datepicker');
        $this->addJS(_PS_MODULE_DIR_ . $this->module->name . '/views/js/admin.js');
        $this->addCSS(_PS_MODULE_DIR_ . $this->module->name . '/views/css/admin.css');
    }
    
    /**
     * Render view - OPTIMIZADO para mejorar rendimiento
     */
    public function renderView()
    {
        // Definir valores predeterminados para las estadísticas
        $default_queue_stats = [
            'total' => 0,
            'pending' => 0,
            'processing' => 0,
            'completed' => 0,
            'failed' => 0,
            'skipped' => 0,
            'avg_completion_time' => 0
        ];

        $default_log_stats = [
            'total' => 0,
            'info' => 0,
            'warning' => 0,
            'error' => 0,
            'conflict' => 0
        ];

        $default_store_stats = [
            'total' => 0,
            'active' => 0,
            'inactive' => 0,
            'principal' => 0,
            'secundaria' => 0,
            'bidirectional' => 0,
            'newest' => null,
            'oldest' => null
        ];

        $default_reference_stats = [
            'total' => 0,
            'active' => 0,
            'inactive' => 0,
            'products' => 0,
            'combinations' => 0,
            'newest' => null,
            'oldest' => null
        ];

        // OPTIMIZACIÓN: Utilizamos caché para estadísticas (30 minutos)
        $cache_id = 'stocksync_dashboard_stats_' . (int)$this->context->shop->id;
        $cached_stats = Cache::retrieve($cache_id);

        if ($cached_stats === false) {
            // Si no hay caché, calculamos las estadísticas
            // Verificar que las tablas y métodos existen antes de llamarlos
            if ($this->tableExists(_DB_PREFIX_ . 'stock_sync_queue') && method_exists('StockSyncQueue', 'getStatistics')) {
                $queue_stats = StockSyncQueue::getStatistics(30);
            } else {
                $queue_stats = $default_queue_stats;
            }
            
            if ($this->tableExists(_DB_PREFIX_ . 'stock_sync_log') && method_exists('StockSyncLog', 'getStatistics')) {
                $log_stats = StockSyncLog::getStatistics(30);
            } else {
                $log_stats = $default_log_stats;
            }
            
            if ($this->tableExists(_DB_PREFIX_ . 'stock_sync_stores') && method_exists('StockSyncStore', 'getStatistics')) {
                $store_stats = StockSyncStore::getStatistics();
            } else {
                $store_stats = $default_store_stats;
            }
            
            if ($this->tableExists(_DB_PREFIX_ . 'stock_sync_references') && method_exists('StockSyncReference', 'getStatistics')) {
                $reference_stats = StockSyncReference::getStatistics();
            } else {
                $reference_stats = $default_reference_stats;
            }
            
            // Asegurarse de que todos los campos necesarios estén en las estadísticas
            $queue_stats = array_merge($default_queue_stats, is_array($queue_stats) ? $queue_stats : []);
            $log_stats = array_merge($default_log_stats, is_array($log_stats) ? $log_stats : []);
            $store_stats = array_merge($default_store_stats, is_array($store_stats) ? $store_stats : []);
            $reference_stats = array_merge($default_reference_stats, is_array($reference_stats) ? $reference_stats : []);
            
            // Guardamos en caché (1800 segundos = 30 minutos)
            $cached_stats = [
                'queue_stats' => $queue_stats,
                'log_stats' => $log_stats,
                'store_stats' => $store_stats,
                'reference_stats' => $reference_stats
            ];
            Cache::store($cache_id, $cached_stats, 1800);
        } else {
            // Usamos los datos de la caché pero aseguramos que todos los campos necesarios estén presentes
            $queue_stats = array_merge($default_queue_stats, is_array($cached_stats['queue_stats']) ? $cached_stats['queue_stats'] : []);
            $log_stats = array_merge($default_log_stats, is_array($cached_stats['log_stats']) ? $cached_stats['log_stats'] : []);
            $store_stats = array_merge($default_store_stats, is_array($cached_stats['store_stats']) ? $cached_stats['store_stats'] : []);
            $reference_stats = array_merge($default_reference_stats, is_array($cached_stats['reference_stats']) ? $cached_stats['reference_stats'] : []);
        }
                
        // Recent activity - Siempre obtener en tiempo real
        $recent_logs = StockSyncLog::getRecent(10);
        $recent_queue = StockSyncQueue::getRecent(10);
        
        // Check for conflicts - Siempre obtener en tiempo real
        $conflicts = StockSyncLog::getConflicts(5);
        
        // OPTIMIZACIÓN: No verificamos discrepancias automáticamente
        $discrepancies = []; // Ahora se cargará bajo demanda con AJAX
        
        // OPTIMIZACIÓN: Ya no hacemos test de conexión automático
        $stores = StockSyncStore::getActiveStores();
        $connection_status = [];
        
        // En vez de probar la conexión, solo mostramos un estado "desconocido"
        foreach ($stores as $store) {
            $connection_status[$store['id_store']] = [
                'store_name' => $store['store_name'],
                'success' => null, // null significa "estado desconocido"
                'message' => $this->l('Connection status unknown. Click "Test Connection" to verify.'),
                'time' => 0
            ];
        }
        
        // NUEVO: Añadir aviso si no hay tiendas configuradas
        if (count($stores) == 0) {
            $this->warnings[] = $this->l('¡Importante! No hay tiendas configuradas. Debe configurar manualmente todas las tiendas que desee conectar, incluyendo la tienda actual. Vaya a la sección "Tiendas" y haga clic en "Añadir Nueva Tienda".');
        }
        
        // Check for duplicate references - No lo hacemos automáticamente
        $duplicate_references = []; // Ahora se cargará bajo demanda
        
        // Module status
        $module_active = (bool) Configuration::get('STOCK_SYNC_ACTIVE');
        $sync_role = Configuration::get('STOCK_SYNC_ROLE', 'bidirectional');
        
        // Asegurar que las estadísticas tienen todos los índices necesarios
        $queue_stats = array_merge([
            'total' => 0,
            'pending' => 0,
            'processing' => 0,
            'completed' => 0,
            'failed' => 0,
            'skipped' => 0,
            'avg_completion_time' => 0
        ], is_array($queue_stats) ? $queue_stats : []);

        $log_stats = array_merge([
            'total' => 0,
            'info' => 0,
            'warning' => 0,
            'error' => 0,
            'conflict' => 0
        ], is_array($log_stats) ? $log_stats : []);

        $store_stats = array_merge([
            'total' => 0,
            'active' => 0,
            'inactive' => 0,
            'principal' => 0,
            'secundaria' => 0,
            'bidirectional' => 0
        ], is_array($store_stats) ? $store_stats : []);

        $reference_stats = array_merge([
            'total' => 0,
            'active' => 0,
            'inactive' => 0,
            'products' => 0,
            'combinations' => 0
        ], is_array($reference_stats) ? $reference_stats : []);

        // Assign variables to template
        $this->context->smarty->assign([
            'queue_stats' => $queue_stats,
            'log_stats' => $log_stats,
            'store_stats' => $store_stats,
            'reference_stats' => $reference_stats,
            'recent_logs' => $recent_logs,
            'recent_queue' => $recent_queue,
            'conflicts' => $conflicts,
            'discrepancies' => $discrepancies,
            'connection_status' => $connection_status,
            'duplicate_references' => $duplicate_references,
            'module_active' => $module_active,
            'sync_role' => $sync_role,
            'stores' => $stores,
            'current_store_id' => StockSyncStore::getCurrentStoreId(),
            'module_path' => _PS_MODULE_DIR_ . $this->module->name,
            'module_uri' => $this->context->link->getBaseLink() . 'modules/' . $this->module->name,
            'admin_token' => Tools::getAdminTokenLite('AdminStockSyncDashboard'),
            'admin_stores_link' => $this->context->link->getAdminLink('AdminStockSyncStores'),
            'admin_references_link' => $this->context->link->getAdminLink('AdminStockSyncReferences'),
            'admin_queue_link' => $this->context->link->getAdminLink('AdminStockSyncQueue'),
            'admin_logs_link' => $this->context->link->getAdminLink('AdminStockSyncLogs')
        ]);

        return $this->renderTemplate();
    }
    
    /**
     * Process actions
     */
    public function postProcess()
    {
        parent::postProcess();
        
        // Limpiar caché cuando se realizan acciones
        $cache_id = 'stocksync_dashboard_stats_' . (int)$this->context->shop->id;
        if (Cache::isStored($cache_id)) {
            Cache::delete($cache_id);
        }
        
        // Toggle module active status
        if (Tools::isSubmit('toggleActive')) {
            $active = (bool) Configuration::get('STOCK_SYNC_ACTIVE');
            Configuration::updateValue('STOCK_SYNC_ACTIVE', !$active);
            
            $this->confirmations[] = $this->l('Module status updated successfully.');
            
            Tools::redirectAdmin($this->context->link->getAdminLink('AdminStockSyncDashboard'));
        }
        
        // Change sync role
        if (Tools::isSubmit('changeSyncRole')) {
            $role = Tools::getValue('sync_role');
            
            if (in_array($role, ['principal', 'secundaria', 'bidirectional'])) {
                Configuration::updateValue('STOCK_SYNC_ROLE', $role);
                
                $this->confirmations[] = $this->l('Synchronization role updated successfully.');
            }
            
            Tools::redirectAdmin($this->context->link->getAdminLink('AdminStockSyncDashboard'));
        }
        
        // Clean old logs
        if (Tools::isSubmit('cleanLogs')) {
            $days = (int) Tools::getValue('days', 30);
            $count = StockSyncLog::cleanOldLogs($days);
            
            $this->confirmations[] = sprintf($this->l('%d log entries have been deleted.'), $count);
            
            Tools::redirectAdmin($this->context->link->getAdminLink('AdminStockSyncDashboard'));
        }
        
        // Clean old queue items
        if (Tools::isSubmit('cleanQueue')) {
            $days = (int) Tools::getValue('days', 30);
            $count = StockSyncQueue::cleanOldItems($days);
            
            $this->confirmations[] = sprintf($this->l('%d queue items have been deleted.'), $count);
            
            Tools::redirectAdmin($this->context->link->getAdminLink('AdminStockSyncDashboard'));
        }
        
        // Retry failed queue items
        if (Tools::isSubmit('retryFailed')) {
            $count = StockSyncQueue::retryFailed();
            
            if ($count > 0) {
                $this->confirmations[] = sprintf($this->l('%d failed items have been set for retry.'), $count);
            } else {
                $this->warnings[] = $this->l('No failed items found to retry.');
            }
            
            Tools::redirectAdmin($this->context->link->getAdminLink('AdminStockSyncDashboard'));
        }
        
        // Process queue manually
        if (Tools::isSubmit('processQueue')) {
            $limit = (int) Tools::getValue('limit', 50);
            $results = StockSync::processQueue($limit);
            
            $this->confirmations[] = sprintf(
                $this->l('Queue processing completed: %d successful, %d failed, %d skipped.'),
                $results['success'],
                $results['failed'],
                $results['skipped']
            );
            
            Tools::redirectAdmin($this->context->link->getAdminLink('AdminStockSyncDashboard'));
        }
        
        // Create backup
        if (Tools::isSubmit('createBackup')) {
            $filename = StockSyncTools::createBackup();
            
            if ($filename) {
                $this->confirmations[] = sprintf($this->l('Backup created successfully: %s'), basename($filename));
            } else {
                $this->errors[] = $this->l('Failed to create backup.');
            }
            
            Tools::redirectAdmin($this->context->link->getAdminLink('AdminStockSyncDashboard'));
        }
        
        // NUEVO: Comprobar todas las conexiones
        if (Tools::isSubmit('checkAllConnections')) {
            $stores = StockSyncStore::getActiveStores();
            $success_count = 0;
            $failed_count = 0;
            
            foreach ($stores as $store) {
                $webservice = new StockSyncWebservice();
                $test = $webservice->testConnection($store);
                
                if ($test['success']) {
                    $success_count++;
                } else {
                    $failed_count++;
                    $this->warnings[] = sprintf(
                        $this->l('Connection failed to store "%s": %s'),
                        $store['store_name'],
                        $test['message']
                    );
                }
            }
            
            if ($success_count > 0) {
                $this->confirmations[] = sprintf(
                    $this->l('Connection test completed: %d successful, %d failed.'),
                    $success_count,
                    $failed_count
                );
            }
            
            Tools::redirectAdmin($this->context->link->getAdminLink('AdminStockSyncDashboard'));
        }
        
        // NUEVO: Comprobar discrepancias
        if (Tools::isSubmit('checkDiscrepancies')) {
            $discrepancies = StockSync::checkDiscrepancies();
            
            if (count($discrepancies) > 0) {
                $this->warnings[] = sprintf(
                    $this->l('Found %d products with stock discrepancies between stores.'),
                    count($discrepancies)
                );
                
                // Guardamos en sesión para mostrar en el template
                $this->context->cookie->discrepancies = json_encode($discrepancies);
                $this->context->cookie->write();
            } else {
                $this->confirmations[] = $this->l('No stock discrepancies found between stores.');
            }
            
            Tools::redirectAdmin($this->context->link->getAdminLink('AdminStockSyncDashboard'));
        }
    }
    
    /**
     * AJAX actions - Mejorado para soportar nuevas funcionalidades
     */
    public function ajaxProcessDashboardData()
    {
        $subaction = Tools::getValue('subaction');
        $response = [];
        
        switch ($subaction) {
            case 'test_connection':
                $id_store = (int) Tools::getValue('id_store');
                $store = new StockSyncStore($id_store);
                
                if (Validate::isLoadedObject($store)) {
                    $webservice = new StockSyncWebservice();
                    $test = $webservice->testConnection($store->getFields());
                    
                    $response = [
                        'success' => $test['success'],
                        'message' => $test['message'],
                        'time' => $test['time']
                    ];
                } else {
                    $response = [
                        'success' => false,
                        'message' => $this->l('Invalid store ID.')
                    ];
                }
                break;
                
            case 'test_all_connections':
                $stores = StockSyncStore::getActiveStores();
                $results = [];
                
                foreach ($stores as $store) {
                    $webservice = new StockSyncWebservice();
                    $test = $webservice->testConnection($store);
                    
                    $results[$store['id_store']] = [
                        'store_name' => $store['store_name'],
                        'success' => $test['success'],
                        'message' => $test['message'],
                        'time' => $test['time']
                    ];
                }
                
                $response = [
                    'results' => $results
                ];
                break;
                
            case 'get_stats':
                $response = [
                    'queue' => StockSyncQueue::getStatistics(30),
                    'logs' => StockSyncLog::getStatistics(30),
                    'stores' => StockSyncStore::getStatistics(),
                    'references' => StockSyncReference::getStatistics()
                ];
                break;
                
            case 'get_recent_logs':
                $response = [
                    'logs' => StockSyncLog::getRecent(20)
                ];
                break;
                
            case 'get_recent_queue':
                $response = [
                    'queue' => StockSyncQueue::getRecent(20)
                ];
                break;
                
            case 'check_discrepancies':
                $response = [
                    'discrepancies' => StockSync::checkDiscrepancies()
                ];
                break;
        }
        
        die(json_encode($response));
    }
    
    /**
     * Render template
     */
    private function renderTemplate()
    {
        $tpl = $this->createTemplate('dashboard.tpl');
        
        // Si hay discrepancias en la sesión, las mostramos
        if (isset($this->context->cookie->discrepancies)) {
            $discrepancies = json_decode($this->context->cookie->discrepancies, true);
            if (is_array($discrepancies)) {
                $tpl->assign('discrepancies', $discrepancies);
            }
            // Limpiamos la cookie
            unset($this->context->cookie->discrepancies);
            $this->context->cookie->write();
        }
        
        return $tpl->fetch();
    }
    
    /**
     * Comprueba si una tabla existe en la base de datos
     * 
     * @param string $table Nombre completo de la tabla con prefijo
     * @return bool
     */
    private function tableExists($table)
    {
        try {
            $result = Db::getInstance()->executeS('SHOW TABLES LIKE "' . $table . '"');
            return !empty($result);
        } catch (Exception $e) {
            // Registra el error en el archivo de registro
            error_log('Error al verificar existencia de tabla ' . $table . ': ' . $e->getMessage());
            return false;
        }
    }
}