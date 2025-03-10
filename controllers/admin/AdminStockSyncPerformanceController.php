<?php
/**
 * Stock Sync Performance Configuration Controller
 *
 * @author    Expert PrestaShop Developer
 * @copyright 2025
 * @license   Commercial
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

class AdminStockSyncPerformanceController extends ModuleAdminController
{
    public function __construct()
    {
        $this->bootstrap = true;
        $this->display = 'view';
        
        // Asignar el título sin usar l() en el constructor
        $this->meta_title = 'Stock Sync Performance Configuration';
        
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
     * Render view
     */
    public function renderView()
    {
        // Obtener los valores de configuración actuales
        $async_processing = (bool) Configuration::get('STOCK_SYNC_ASYNC_PROCESSING', 0);
        $batch_size = (int) Configuration::get('STOCK_SYNC_CRON_BATCH_SIZE', 50);
        $log_retention = (int) Configuration::get('STOCK_SYNC_LOG_RETENTION', 7);
        $queue_retention = (int) Configuration::get('STOCK_SYNC_QUEUE_RETENTION', 2);
        $retry_count = (int) Configuration::get('STOCK_SYNC_RETRY_COUNT', 3);
        $debug_mode = (bool) Configuration::get('STOCK_SYNC_DEBUG_MODE', 0);
        
        // Assign variables to template
        $this->context->smarty->assign([
            'async_processing' => $async_processing,
            'batch_size' => $batch_size,
            'log_retention' => $log_retention,
            'queue_retention' => $queue_retention,
            'retry_count' => $retry_count,
            'debug_mode' => $debug_mode,
            'shop_root' => _PS_ROOT_DIR_,
            'module_path' => _PS_MODULE_DIR_ . $this->module->name,
            'module_uri' => $this->context->link->getBaseLink() . 'modules/' . $this->module->name,
            'admin_token' => Tools::getAdminTokenLite('AdminStockSyncPerformance'),
            'admin_dashboard_link' => $this->context->link->getAdminLink('AdminStockSyncDashboard'),
            'admin_stores_link' => $this->context->link->getAdminLink('AdminStockSyncStores'),
            'admin_references_link' => $this->context->link->getAdminLink('AdminStockSyncReferences'),
            'admin_queue_link' => $this->context->link->getAdminLink('AdminStockSyncQueue'),
            'admin_logs_link' => $this->context->link->getAdminLink('AdminStockSyncLogs')
        ]);
        
        return $this->createTemplate('performance_config.tpl')->fetch();
    }
    
    /**
     * Process actions
     */
    public function postProcess()
    {
        parent::postProcess();
        
        // Procesar configuración de rendimiento
        if (Tools::isSubmit('submitPerformanceConfig')) {
            $async_processing = (bool) Tools::getValue('STOCK_SYNC_ASYNC_PROCESSING', 0);
            $debug_mode = (bool) Tools::getValue('STOCK_SYNC_DEBUG_MODE', 0);
            $batch_size = (int) Tools::getValue('STOCK_SYNC_CRON_BATCH_SIZE', 50);
            $log_retention = (int) Tools::getValue('STOCK_SYNC_LOG_RETENTION', 7);
            $queue_retention = (int) Tools::getValue('STOCK_SYNC_QUEUE_RETENTION', 2);
            $retry_count = (int) Tools::getValue('STOCK_SYNC_RETRY_COUNT', 3);
            
            // Validar valores
            $batch_size = max(10, min(500, $batch_size));
            $log_retention = max(1, min(90, $log_retention));
            $queue_retention = max(1, min(30, $queue_retention));
            $retry_count = max(1, min(10, $retry_count));
            
            // Actualizar configuración
            Configuration::updateValue('STOCK_SYNC_ASYNC_PROCESSING', $async_processing);
            Configuration::updateValue('STOCK_SYNC_DEBUG_MODE', $debug_mode);
            Configuration::updateValue('STOCK_SYNC_CRON_BATCH_SIZE', $batch_size);
            Configuration::updateValue('STOCK_SYNC_LOG_RETENTION', $log_retention);
            Configuration::updateValue('STOCK_SYNC_QUEUE_RETENTION', $queue_retention);
            Configuration::updateValue('STOCK_SYNC_RETRY_COUNT', $retry_count);
            
            $this->confirmations[] = $this->l('Performance settings updated successfully.');
            
            Tools::redirectAdmin($this->context->link->getAdminLink('AdminStockSyncPerformance'));
        }
    }
    
    /**
     * Añadir enlaces en la cabecera
     */
    public function initPageHeaderToolbar()
    {
        $this->page_header_toolbar_btn['back_to_dashboard'] = [
            'href' => $this->context->link->getAdminLink('AdminStockSyncDashboard'),
            'desc' => $this->l('Back to Dashboard'),
            'icon' => 'process-icon-back'
        ];
        
        parent::initPageHeaderToolbar();
    }
}