<?php
/**
 * Stock Sync Queue Controller
 *
 * @author    Expert PrestaShop Developer
 * @copyright 2025
 * @license   Commercial
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

class AdminStockSyncQueueController extends ModuleAdminController
{
    public function __construct()
    {
        $this->bootstrap = true;
        $this->table = 'stock_sync_queue';
        
        // SOLUCIÓN: No establecer className para evitar que PrestaShop intente
        // tratar StockSyncQueue como un ObjectModel
        // $this->className = 'StockSyncQueue'; - Eliminar esta línea
        
        $this->lang = false;
        $this->addRowAction('view');
        $this->addRowAction('retry');
        $this->addRowAction('delete');
        $this->explicitSelect = true;
        $this->allow_export = true;
        $this->deleted = false;
        $this->context = Context::getContext();
        
        // Definir título sin usar l() en el constructor
        $this->meta_title = 'Stock Sync Queue';
        $this->list_title = 'Synchronization Queue';
        
        // Establecer el identificador correcto
        $this->identifier = 'id_queue';
        
        parent::__construct();
        
        if (!$this->module->active) {
            Tools::redirectAdmin($this->context->link->getAdminLink('AdminModules'));
        }
        
        // Definir campos de la tabla
        $this->fields_list = [
            'id_queue' => [
                'title' => $this->l('ID'),
                'align' => 'center',
                'class' => 'fixed-width-xs'
            ],
            'reference' => [
                'title' => $this->l('Reference'),
                'align' => 'left',
                'search' => true
            ],
            'old_quantity' => [
                'title' => $this->l('Old Qty'),
                'align' => 'right',
                'search' => false
            ],
            'new_quantity' => [
                'title' => $this->l('New Qty'),
                'align' => 'right',
                'search' => false
            ],
            'operation_type' => [
                'title' => $this->l('Operation'),
                'align' => 'center',
                'search' => true,
                'type' => 'select',
                'list' => [
                    'update' => $this->l('Update'),
                    'order' => $this->l('Order'),
                    'import' => $this->l('Import'),
                    'product_update' => $this->l('Product Update'),
                    'combination_update' => $this->l('Combination Update')
                ],
                'filter_key' => 'a!operation_type'
            ],
            'status' => [
                'title' => $this->l('Status'),
                'align' => 'center',
                'callback' => 'getStatusBadge',
                'search' => true,
                'type' => 'select',
                'list' => [
                    'pending' => $this->l('Pending'),
                    'processing' => $this->l('Processing'),
                    'completed' => $this->l('Completed'),
                    'failed' => $this->l('Failed'),
                    'skipped' => $this->l('Skipped')
                ],
                'filter_key' => 'a!status'
            ],
            'attempts' => [
                'title' => $this->l('Attempts'),
                'align' => 'center',
                'search' => false
            ],
            'updated_at' => [
                'title' => $this->l('Last Update'),
                'align' => 'center',
                'type' => 'datetime',
                'filter_key' => 'a!updated_at'
            ]
        ];
        
        $this->bulk_actions = [
            'delete' => [
                'text' => $this->l('Delete selected'),
                'confirm' => $this->l('Delete selected items?'),
                'icon' => 'icon-trash'
            ],
            'retry' => [
                'text' => $this->l('Retry selected'),
                'icon' => 'icon-refresh'
            ]
        ];
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
     * Sobrescribir para usar nuestra propia query
     */
    public function getListQuery()
    {
        // Verificar primero si la tabla existe
        if (!$this->tableExists(_DB_PREFIX_ . 'stock_sync_queue')) {
            $this->_list = [];
            return false;
        }
        
        $this->_select = 'a.*';
        
        // Comprobar si las tablas de tiendas existen antes de hacer el join
        if ($this->tableExists(_DB_PREFIX_ . 'stock_sync_stores')) {
            $this->_select .= ', IFNULL(s1.store_name, "") AS source_store_name, IFNULL(s2.store_name, "") AS target_store_name';
            $this->_join = '
                LEFT JOIN `' . _DB_PREFIX_ . 'stock_sync_stores` s1 ON (a.source_store_id = s1.id_store)
                LEFT JOIN `' . _DB_PREFIX_ . 'stock_sync_stores` s2 ON (a.target_store_id = s2.id_store)
            ';
        } else {
            // Si las tablas no existen, usar valores vacíos
            $this->_select .= ', "" AS source_store_name, "" AS target_store_name';
        }
        
        $this->_orderBy = 'id_queue';
        $this->_orderWay = 'DESC';
        
        // Filtrar por referencia si se especifica
        $reference = Tools::getValue('reference', '');
        if (!empty($reference)) {
            $this->_where = ' AND a.reference = "' . pSQL($reference) . '"';
        }
        
        return parent::getListQuery();
    }
    
    /**
     * Método de callback para mostrar badges de estado
     */
    public function getStatusBadge($status, $row)
    {
        $badge_class = 'badge badge-';
        
        switch ($status) {
            case 'pending':
                $badge_class .= 'warning';
                break;
            case 'processing':
                $badge_class .= 'info';
                break;
            case 'completed':
                $badge_class .= 'success';
                break;
            case 'failed':
                $badge_class .= 'danger';
                break;
            case 'skipped':
                $badge_class .= 'default';
                break;
            default:
                $badge_class .= 'default';
                break;
        }
        
        return '<span class="' . $badge_class . '">' . $status . '</span>';
    }
    
    /**
     * Procesar acciones
     */
    public function postProcess()
    {
        // Procesar la cola manualmente
        if (Tools::isSubmit('processQueue')) {
            $limit = (int) Tools::getValue('limit', 50);
            
            if (!class_exists('StockSync')) {
                $this->errors[] = $this->l('StockSync class not found. Please check your module installation.');
            } else {
                $results = StockSync::processQueue($limit);
                
                $this->confirmations[] = sprintf(
                    $this->l('Queue processing completed: %d successful, %d failed, %d skipped.'),
                    $results['success'],
                    $results['failed'],
                    $results['skipped']
                );
            }
        }
        
        // Reintentar elementos fallidos
        if (Tools::isSubmit('retryFailed') || Tools::isSubmit('submitBulkretrystock_sync_queue')) {
            if (Tools::isSubmit('submitBulkretrystock_sync_queue') && isset($this->boxes) && is_array($this->boxes)) {
                $count = 0;
                
                foreach ($this->boxes as $id) {
                    // Verificar que la tabla existe
                    if (!$this->tableExists(_DB_PREFIX_ . 'stock_sync_queue')) {
                        $this->errors[] = $this->l('The queue table does not exist.');
                        break;
                    }
                    
                    $queue_item = Db::getInstance()->getRow('
                        SELECT *
                        FROM `' . _DB_PREFIX_ . 'stock_sync_queue`
                        WHERE id_queue = ' . (int) $id
                    );
                    
                    if ($queue_item && in_array($queue_item['status'], ['failed', 'skipped'])) {
                        Db::getInstance()->update(
                            'stock_sync_queue',
                            [
                                'status' => 'pending',
                                'attempts' => 0,
                                'error_message' => 'Retry after failure or skip',
                                'updated_at' => date('Y-m-d H:i:s')
                            ],
                            'id_queue = ' . (int) $id
                        );
                        
                        $count++;
                    }
                }
                
                if ($count > 0) {
                    $this->confirmations[] = sprintf($this->l('%d items have been set for retry.'), $count);
                } else {
                    $this->warnings[] = $this->l('No items to retry.');
                }
            } else {
                // Usar método estático de StockSyncQueue directamente
                if (class_exists('StockSyncQueue')) {
                    $count = StockSyncQueue::retryFailed(24); // Usar 24 horas como valor por defecto
                    
                    if ($count > 0) {
                        $this->confirmations[] = sprintf($this->l('%d failed items have been set for retry.'), $count);
                    } else {
                        $this->warnings[] = $this->l('No failed items found to retry.');
                    }
                } else {
                    $this->errors[] = $this->l('StockSyncQueue class not found. Please check your module installation.');
                }
            }
        }
        
        // Reintentar un elemento específico
        if (Tools::getValue('action') == 'retry') {
            $id_queue = (int) Tools::getValue('id_queue');
            
            // Verificar que la tabla existe
            if (!$this->tableExists(_DB_PREFIX_ . 'stock_sync_queue')) {
                $this->errors[] = $this->l('The queue table does not exist.');
            } else {
                $result = Db::getInstance()->update(
                    'stock_sync_queue',
                    [
                        'status' => 'pending',
                        'attempts' => 0,
                        'error_message' => 'Manual retry',
                        'updated_at' => date('Y-m-d H:i:s')
                    ],
                    'id_queue = ' . $id_queue
                );
                
                if ($result) {
                    $this->confirmations[] = $this->l('Queue item has been set for retry.');
                } else {
                    $this->errors[] = $this->l('Failed to retry queue item.');
                }
                
                Tools::redirectAdmin($this->context->link->getAdminLink('AdminStockSyncQueue'));
            }
        }
        
        // Limpiar elementos antiguos
        if (Tools::isSubmit('cleanQueue')) {
            $hours = (int) Tools::getValue('hours', 24);
            
            // Usar método estático de StockSyncQueue directamente
            if (class_exists('StockSyncQueue')) {
                $count = StockSyncQueue::cleanOldItems($hours);
                
                $this->confirmations[] = sprintf($this->l('%d queue items have been deleted.'), $count);
            } else {
                $this->errors[] = $this->l('StockSyncQueue class not found. Please check your module installation.');
            }
        }
        
        parent::postProcess();
    }
    
    /**
     * Renderizar vista
     */
    public function renderView()
    {
        $id_queue = (int) Tools::getValue('id_queue');
        
        // Verificar que las tablas existen
        if (!$this->tableExists(_DB_PREFIX_ . 'stock_sync_queue')) {
            $this->errors[] = $this->l('The queue table does not exist.');
            return $this->renderList();
        }
        
        // Obtener información básica del elemento de cola
        $queue_item = Db::getInstance()->getRow(
            'SELECT * FROM `' . _DB_PREFIX_ . 'stock_sync_queue` WHERE id_queue = ' . (int) $id_queue
        );
        
        if (!$queue_item) {
            $this->errors[] = $this->l('Queue item not found');
            return $this->renderList();
        }
        
        // Intentar obtener información adicional de las tiendas si existen
        $store_info = [];
        if ($this->tableExists(_DB_PREFIX_ . 'stock_sync_stores')) {
            $source_store = Db::getInstance()->getRow(
                'SELECT store_name FROM `' . _DB_PREFIX_ . 'stock_sync_stores` WHERE id_store = ' . (int) $queue_item['source_store_id']
            );
            
            $target_store = Db::getInstance()->getRow(
                'SELECT store_name FROM `' . _DB_PREFIX_ . 'stock_sync_stores` WHERE id_store = ' . (int) $queue_item['target_store_id']
            );
            
            if ($source_store) {
                $queue_item['source_store_name'] = $source_store['store_name'];
            } else {
                $queue_item['source_store_name'] = 'Unknown Store #' . $queue_item['source_store_id'];
            }
            
            if ($target_store) {
                $queue_item['target_store_name'] = $target_store['store_name'];
            } else {
                $queue_item['target_store_name'] = 'Unknown Store #' . $queue_item['target_store_id'];
            }
        } else {
            $queue_item['source_store_name'] = 'Store #' . $queue_item['source_store_id'];
            $queue_item['target_store_name'] = 'Store #' . $queue_item['target_store_id'];
        }
        
        // Obtener logs relacionados - usando acceso directo a la BD
        $logs = [];
        if ($this->tableExists(_DB_PREFIX_ . 'stock_sync_log')) {
            $logs = Db::getInstance()->executeS(
                'SELECT * FROM `' . _DB_PREFIX_ . 'stock_sync_log` WHERE id_queue = ' . (int) $id_queue . ' ORDER BY id_log DESC'
            );
        }
        
        // Información del producto
        $product_info = [];
        
        if ($queue_item['id_product'] > 0) {
            try {
                $product = new Product($queue_item['id_product']);
                
                if (Validate::isLoadedObject($product)) {
                    $product_info = [
                        'id' => $product->id,
                        'name' => isset($product->name[$this->context->language->id]) ? $product->name[$this->context->language->id] : 'Product #' . $product->id,
                        'reference' => $product->reference,
                        'link' => $this->context->link->getAdminLink('AdminProducts') . '&id_product=' . $product->id . '&updateproduct'
                    ];
                    
                    if ($queue_item['id_product_attribute'] > 0) {
                        $combination = new Combination($queue_item['id_product_attribute']);
                        
                        if (Validate::isLoadedObject($combination)) {
                            $product_info['combination_id'] = $combination->id;
                            $product_info['combination_reference'] = $combination->reference;
                            // Añadir atributos de la combinación
                            $product_info['attributes'] = [];
                            
                            try {
                                $attributes = $product->getAttributesParams($product->id, $combination->id);
                                
                                foreach ($attributes as $attribute) {
                                    $product_info['attributes'][] = $attribute['group_name'] . ': ' . $attribute['name'];
                                }
                            } catch (Exception $e) {
                                // Si hay un error al obtener los atributos, lo registramos pero continuamos
                                $product_info['attributes'][] = 'Error al cargar atributos: ' . $e->getMessage();
                            }
                        }
                    }
                }
            } catch (Exception $e) {
                // Si hay un error al cargar el producto, simplemente continuamos sin la información
                $product_info = [
                    'id' => $queue_item['id_product'],
                    'name' => 'Product #' . $queue_item['id_product'] . ' (Error: ' . $e->getMessage() . ')',
                    'reference' => $queue_item['reference']
                ];
            }
        }
        
        // Asignar variables a la vista
        $this->context->smarty->assign([
            'queue_item' => $queue_item,
            'product_info' => $product_info,
            'logs' => $logs,
            'retry_url' => $this->context->link->getAdminLink('AdminStockSyncQueue') . '&action=retry&id_queue=' . $id_queue,
            'dashboard_link' => $this->context->link->getAdminLink('AdminStockSyncDashboard')
        ]);
        
        return $this->createTemplate('queue_details.tpl')->fetch();
    }
    
    /**
     * Añadir enlaces en la cabecera
     */
    public function initPageHeaderToolbar()
    {
        if (empty($this->display)) {
            $this->page_header_toolbar_btn['process_queue'] = [
                'href' => self::$currentIndex . '&processQueue&token=' . $this->token,
                'desc' => $this->l('Process Queue Now'),
                'icon' => 'process-icon-cogs'
            ];
            
            $this->page_header_toolbar_btn['retry_failed'] = [
                'href' => self::$currentIndex . '&retryFailed&token=' . $this->token,
                'desc' => $this->l('Retry Failed Items'),
                'icon' => 'process-icon-refresh'
            ];
            
            $this->page_header_toolbar_btn['clean_queue'] = [
                'href' => '#',
                'desc' => $this->l('Clean Old Items'),
                'icon' => 'process-icon-eraser',
                'js' => 'showCleanQueueModal()'
            ];
            
            $this->page_header_toolbar_btn['back_to_dashboard'] = [
                'href' => $this->context->link->getAdminLink('AdminStockSyncDashboard'),
                'desc' => $this->l('Back to Dashboard'),
                'icon' => 'process-icon-back'
            ];
        } elseif ($this->display == 'view' || $this->display == 'edit' || $this->display == 'add') {
            $this->page_header_toolbar_btn['back_to_list'] = [
                'href' => $this->context->link->getAdminLink('AdminStockSyncQueue'),
                'desc' => $this->l('Back to List'),
                'icon' => 'process-icon-back'
            ];
            
            $this->page_header_toolbar_btn['back_to_dashboard'] = [
                'href' => $this->context->link->getAdminLink('AdminStockSyncDashboard'),
                'desc' => $this->l('Back to Dashboard'),
                'icon' => 'process-icon-back'
            ];
        }
        
        parent::initPageHeaderToolbar();
    }
    
    /**
     * Renderizar contenido
     */
    public function renderList()
    {
        // Verifica si la tabla existe antes de continuar
        if (!$this->tableExists(_DB_PREFIX_ . 'stock_sync_queue')) {
            $this->displayWarning($this->l('La tabla de cola no existe. Por favor, reinstale el módulo o compruebe su base de datos.'));
            return parent::renderList();
        }
        
        try {
            $tpl = $this->createTemplate('queue.tpl');
            
            // Obtener estadísticas utilizando método estático
            $stats = [
                'total' => 0,
                'pending' => 0,
                'processing' => 0,
                'completed' => 0,
                'failed' => 0,
                'skipped' => 0,
                'avg_completion_time' => 0
            ];
            
            if (class_exists('StockSyncQueue')) {
                $stats = StockSyncQueue::getStatistics(24); // 24 horas
            }
            
            $tpl->assign([
                'stats' => $stats,
                'process_url' => $this->context->link->getAdminLink('AdminStockSyncQueue') . '&processQueue=1',
                'retry_url' => $this->context->link->getAdminLink('AdminStockSyncQueue') . '&retryFailed=1',
                'clean_url' => $this->context->link->getAdminLink('AdminStockSyncQueue') . '&cleanQueue=1',
                'dashboard_link' => $this->context->link->getAdminLink('AdminStockSyncDashboard')
            ]);
            
            return $tpl->fetch() . parent::renderList();
        } catch (Exception $e) {
            $this->displayWarning($this->l('Error al renderizar la plantilla: ') . $e->getMessage());
            return parent::renderList();
        }
    }
    
    /**
     * Añadir un botón para reintentar en la lista de cola
     */
    public function displayRetryLink($token, $id)
    {
        if (!array_key_exists('retry', self::$cache_lang)) {
            self::$cache_lang['retry'] = $this->l('Retry');
        }
        
        $this->context->smarty->assign([
            'href' => self::$currentIndex . '&' . $this->identifier . '=' . $id . '&action=retry&token=' . ($token != null ? $token : $this->token),
            'action' => self::$cache_lang['retry'],
            'id' => $id
        ]);
        
        try {
            return $this->context->smarty->fetch('helpers/list/list_action_retry.tpl');
        } catch (Exception $e) {
            return '<a href="' . self::$currentIndex . '&' . $this->identifier . '=' . $id . '&action=retry&token=' . ($token != null ? $token : $this->token) . '" title="' . self::$cache_lang['retry'] . '">
                <i class="icon-refresh"></i> ' . self::$cache_lang['retry'] . '
            </a>';
        }
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
    
    /**
     * Sobrescribe el método processBulkDelete para manejar la eliminación sin ObjectModel
     */
    public function processBulkDelete()
    {
        if (is_array($this->boxes) && !empty($this->boxes)) {
            $success = true;
            
            foreach ($this->boxes as $id) {
                try {
                    $success &= Db::getInstance()->delete(
                        'stock_sync_queue',
                        'id_queue = ' . (int)$id
                    );
                } catch (Exception $e) {
                    $this->errors[] = $this->l('Error deleting item #') . $id . ': ' . $e->getMessage();
                    $success = false;
                }
            }
            
            if ($success) {
                $this->confirmations[] = $this->l('Selected items have been deleted successfully.');
            }
        }
    }
    
    /**
     * Sobrescribe el método processDelete para manejar la eliminación sin ObjectModel
     */
    public function processDelete()
    {
        $id = (int)Tools::getValue('id_queue');
        
        if ($id) {
            try {
                $success = Db::getInstance()->delete(
                    'stock_sync_queue',
                    'id_queue = ' . $id
                );
                
                if ($success) {
                    $this->confirmations[] = $this->l('Item deleted successfully.');
                } else {
                    $this->errors[] = $this->l('Error deleting item.');
                }
            } catch (Exception $e) {
                $this->errors[] = $this->l('Error deleting item: ') . $e->getMessage();
            }
        }
        
        return true;
    }
}