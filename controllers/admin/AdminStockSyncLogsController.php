<?php
/**
 * Stock Sync Logs Controller
 *
 * @author    Expert PrestaShop Developer
 * @copyright 2025
 * @license   Commercial
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

class AdminStockSyncLogsController extends ModuleAdminController
{
    public function __construct()
    {
        $this->bootstrap = true;
        $this->table = 'stock_sync_log';
        // $this->className = 'StockSyncLog'; // Esta clase no es un ObjectModel
        $this->lang = false;
        $this->addRowAction('view');
        $this->explicitSelect = true;
        $this->allow_export = true;
        $this->deleted = false;
        $this->context = Context::getContext();
        
        // Definir título sin usar l() en el constructor
        $this->meta_title = 'Stock Sync Logs';
        $this->list_title = 'Stock Sync Logs';
        
        // Importante: establecer el identificador correcto
        $this->identifier = 'id_log';
        
        parent::__construct();
        
        if (!$this->module->active) {
            Tools::redirectAdmin($this->context->link->getAdminLink('AdminModules'));
        }
        
        // Definir campos de la tabla
        $this->fields_list = [
            'id_log' => [
                'title' => $this->l('ID'),
                'align' => 'center',
                'class' => 'fixed-width-xs'
            ],
            'level' => [
                'title' => $this->l('Level'),
                'align' => 'center',
                'callback' => 'getLogLevelBadge',
                'search' => true,
                'type' => 'select',
                'list' => [
                    'info' => $this->l('Info'),
                    'warning' => $this->l('Warning'),
                    'error' => $this->l('Error'),
                    'conflict' => $this->l('Conflict')
                ],
                'filter_key' => 'a!level'
            ],
            'message' => [
                'title' => $this->l('Message'),
                'align' => 'left',
                'maxlength' => 100,
                'search' => true
            ],

            'created_at' => [
                'title' => $this->l('Date'),
                'align' => 'center',
                'type' => 'datetime',
                'filter_key' => 'a!created_at'
            ]
        ];
        
        $this->bulk_actions = [
            'delete' => [
                'text' => $this->l('Delete selected'),
                'confirm' => $this->l('Delete selected items?'),
                'icon' => 'icon-trash'
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
    if (!$this->tableExists(_DB_PREFIX_ . 'stock_sync_log')) {
        $this->_list = [];
        return false;
    }
    
    $this->_select = 'a.*';
    $this->_orderBy = 'a.id_log';
    $this->_orderWay = 'DESC';
    
    return parent::getListQuery();
}
    
    /**
     * Método de callback para mostrar badges de nivel
     */
    public function getLogLevelBadge($level, $row)
    {
        $badge_class = 'badge badge-';
        
        switch ($level) {
            case 'info':
                $badge_class .= 'info';
                break;
            case 'warning':
                $badge_class .= 'warning';
                break;
            case 'error':
                $badge_class .= 'danger';
                break;
            case 'conflict':
                $badge_class .= 'primary';
                break;
            default:
                $badge_class .= 'default';
                break;
        }
        
        return '<span class="' . $badge_class . '">' . $level . '</span>';
    }
    
    /**
     * Procesar acciones de mantenimiento de logs
	 
	 */
    public function postProcess()
    {
        parent::postProcess();
        
        // Exportar logs
        if (Tools::isSubmit('exportLogs')) {
            $level = Tools::getValue('export_level', '');
            $date_from = Tools::getValue('export_date_from', '');
            $date_to = Tools::getValue('export_date_to', '');
            
            $csv_content = StockSyncLog::exportToCSV($level, $date_from, $date_to);
            
            header('Content-type: text/csv');
            header('Content-Disposition: attachment; filename=stock_sync_logs_' . date('Y-m-d') . '.csv');
            header('Pragma: no-cache');
            header('Expires: 0');
            
            echo $csv_content;
            exit;
        }
        
        // Limpiar logs antiguos
        if (Tools::isSubmit('cleanLogs')) {
            $hours = (int) Tools::getValue('hours', 24);
            $count = StockSyncLog::cleanOldLogs($hours);
            
            $this->confirmations[] = sprintf($this->l('%d log entries have been deleted.'), $count);
            
            Tools::redirectAdmin($this->context->link->getAdminLink('AdminStockSyncLogs'));
        }
        
        // Mostrar logs por referencia
        if (Tools::isSubmit('viewByReference')) {
            $reference = Tools::getValue('reference', '');
            
            if ($reference) {
                $this->_where = ' AND q.reference = "' . pSQL($reference) . '"';
                $this->_orderBy = 'a.id_log';
                $this->_orderWay = 'DESC';
            }
        }
    }
    
    /**
     * Renderizar formulario de exportación
     */
    public function renderForm()
    {
        $this->fields_form = [
            'legend' => [
                'title' => $this->l('Export Logs'),
                'icon' => 'icon-download'
            ],
            'input' => [
                [
                    'type' => 'select',
                    'label' => $this->l('Log Level'),
                    'name' => 'export_level',
                    'options' => [
                        'query' => [
                            ['id' => '', 'name' => $this->l('All')],
                            ['id' => 'info', 'name' => $this->l('Info')],
                            ['id' => 'warning', 'name' => $this->l('Warning')],
                            ['id' => 'error', 'name' => $this->l('Error')],
                            ['id' => 'conflict', 'name' => $this->l('Conflict')]
                        ],
                        'id' => 'id',
                        'name' => 'name'
                    ]
                ],
                [
                    'type' => 'date',
                    'label' => $this->l('From Date'),
                    'name' => 'export_date_from',
                    'size' => 10
                ],
                [
                    'type' => 'date',
                    'label' => $this->l('To Date'),
                    'name' => 'export_date_to',
                    'size' => 10
                ]
            ],
            'submit' => [
                'title' => $this->l('Export'),
                'icon' => 'process-icon-download'
            ],
            'buttons' => [
                'cancel' => [
                    'title' => $this->l('Cancel'),
                    'href' => $this->context->link->getAdminLink('AdminStockSyncDashboard'),
                    'icon' => 'process-icon-cancel'
                ]
            ]
        ];
        
        return parent::renderForm();
    }
    
    /**
     * Renderizar vista
     */
    public function renderView()
    {
        $id_log = (int) Tools::getValue('id_log');
        
        // Verificar primero si la tabla existe
        if (!$this->tableExists(_DB_PREFIX_ . 'stock_sync_log')) {
            $this->errors[] = $this->l('The log table does not exist');
            return $this->renderList();
        }
        
        // Obtener información relacionada
        $log_details = Db::getInstance()->getRow(
            'SELECT l.* FROM `' . _DB_PREFIX_ . 'stock_sync_log` l WHERE l.id_log = ' . (int) $id_log
        );
        
        if (!$log_details) {
            $this->errors[] = $this->l('Log not found');
            return $this->renderList();
        }
        
        // Intentar obtener información adicional de la cola si existe
        $queue_info = [];
        if (isset($log_details['id_queue']) && $log_details['id_queue'] && $this->tableExists(_DB_PREFIX_ . 'stock_sync_queue')) {
            $queue_info = Db::getInstance()->getRow(
                'SELECT q.reference, q.id_product, q.id_product_attribute, q.operation_type, q.status
                FROM `' . _DB_PREFIX_ . 'stock_sync_queue` q 
                WHERE q.id_queue = ' . (int) $log_details['id_queue']
            );
            
            if ($queue_info) {
                $log_details = array_merge($log_details, $queue_info);
            }
        }
        
        // Información del producto si existe
        $product_info = [];
        
        if (!empty($log_details['id_product'])) {
            $product = new Product($log_details['id_product']);
            
            if (Validate::isLoadedObject($product)) {
                $product_info = [
                    'id' => $product->id,
                    'name' => $product->name[$this->context->language->id],
                    'reference' => $product->reference,
                    'link' => $this->context->link->getAdminLink('AdminProducts') . '&id_product=' . $product->id . '&updateproduct',
                    'combination_id' => isset($log_details['id_product_attribute']) ? $log_details['id_product_attribute'] : 0,
                    'combination_reference' => ''
                ];
                
                if (!empty($product_info['combination_id'])) {
                    $combination = new Combination($product_info['combination_id']);
                    
                    if (Validate::isLoadedObject($combination)) {
                        $product_info['combination_reference'] = $combination->reference;
                    }
                }
            }
        }
        
        // Asignar variables a la vista
        $this->context->smarty->assign([
            'log' => $log_details,
            'product_info' => $product_info,
            'path' => $this->context->link->getAdminLink('AdminStockSyncDashboard')
        ]);
        
        return $this->createTemplate('log_details.tpl')->fetch();
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
                    'stock_sync_log',
                    'id_log = ' . (int)$id
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
    $id = (int)Tools::getValue('id_log');
    
    if ($id) {
        try {
            $success = Db::getInstance()->delete(
                'stock_sync_log',
                'id_log = ' . $id
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
    /**
     * Añadir enlaces en la cabecera
     */
    public function initPageHeaderToolbar()
    {
        if (empty($this->display)) {
            $this->page_header_toolbar_btn['export_logs'] = [
                'href' => self::$currentIndex . '&add' . $this->table . '&token=' . $this->token,
                'desc' => $this->l('Export Logs'),
                'icon' => 'process-icon-download'
            ];
            
            $this->page_header_toolbar_btn['clean_logs'] = [
                'href' => '#',
                'desc' => $this->l('Clean Old Logs'),
                'icon' => 'process-icon-eraser',
                'js' => 'showCleanLogsModal()'
            ];
            
            $this->page_header_toolbar_btn['back_to_dashboard'] = [
                'href' => $this->context->link->getAdminLink('AdminStockSyncDashboard'),
                'desc' => $this->l('Back to Dashboard'),
                'icon' => 'process-icon-back'
            ];
        } elseif ($this->display == 'view' || $this->display == 'edit' || $this->display == 'add') {
            $this->page_header_toolbar_btn['back_to_list'] = [
                'href' => $this->context->link->getAdminLink('AdminStockSyncLogs'),
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
     * Renderizar contenido - CLAVE PARA ARREGLAR EL PROBLEMA DE VISUALIZACIÓN
     */
    public function renderList()
    {
        // Verifica si la tabla existe antes de continuar
        if (!$this->tableExists(_DB_PREFIX_ . 'stock_sync_log')) {
            $this->displayWarning($this->l('La tabla de logs no existe. Por favor, reinstale el módulo o compruebe su base de datos.'));
            return parent::renderList();
        }
        
        try {
            // CORRECCIÓN: Aseguramos que siempre tenemos una template disponible
            if (file_exists(_PS_MODULE_DIR_ . $this->module->name . '/views/templates/admin/logs.tpl')) {
                $tpl = $this->createTemplate('logs.tpl');
                $tpl->assign([
                    'log_levels' => [
                        'info' => $this->l('Info'),
                        'warning' => $this->l('Warning'),
                        'error' => $this->l('Error'),
						'conflict' => $this->l('Conflict')
                    ],
                    'stats' => StockSyncLog::getStatistics(24), // Usar 24 horas para las estadísticas
                    'recent_logs' => StockSyncLog::getRecent(5),
                    'conflicts' => StockSyncLog::getConflicts(5),
                    'clean_logs_action' => $this->context->link->getAdminLink('AdminStockSyncLogs') . '&cleanLogs=1',
                    'export_logs_action' => $this->context->link->getAdminLink('AdminStockSyncLogs') . '&exportLogs=1',
                    'use_hours' => true, // Usar horas en vez de días
                    'dashboard_link' => $this->context->link->getAdminLink('AdminStockSyncDashboard')
                ]);
            
                return $tpl->fetch() . parent::renderList();
            } else {
                // Si no existe la plantilla, mostrar una advertencia
                $this->displayWarning($this->l('No se encontró la plantilla logs.tpl. Se mostrará solo la lista estándar.'));
                return parent::renderList();
            }
        } catch (Exception $e) {
            $this->displayWarning($this->l('Error al renderizar la plantilla: ') . $e->getMessage());
            return parent::renderList();
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
}