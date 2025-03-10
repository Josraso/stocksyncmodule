<?php
/**
 * Stock Sync Module
 *
 * @author    Expert PrestaShop Developer
 * @copyright 2025
 * @license   Commercial
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

require_once dirname(__FILE__) . '/classes/StockSync.php';
require_once dirname(__FILE__) . '/classes/StockSyncQueue.php';
require_once dirname(__FILE__) . '/classes/StockSyncLog.php';
require_once dirname(__FILE__) . '/classes/StockSyncStore.php';
require_once dirname(__FILE__) . '/classes/StockSyncReference.php';
require_once dirname(__FILE__) . '/classes/StockSyncWebservice.php';
require_once dirname(__FILE__) . '/classes/StockSyncTools.php';

class StockSyncModule extends Module
{
    /**
     * Module constructor
     */
    public function __construct()
    {
        $this->name = 'stocksyncmodule';
        $this->tab = 'administration';
        $this->version = '1.0.0';
        $this->author = 'Expert PrestaShop Developer';
        $this->need_instance = 0;
        $this->bootstrap = true;
        $this->module_key = 'stocksyncmodule'; // Añadido para evitar el error "Undefined array key 'module_key'"
        $this->ps_versions_compliancy = [
            'min' => '1.7.0.0',
            'max' => _PS_VERSION_
        ];

        parent::__construct();

        $this->displayName = $this->l('Stock Synchronization');
        $this->description = $this->l('Synchronize stock between multiple PrestaShop stores in real-time');
        $this->confirmUninstall = $this->l('Are you sure you want to uninstall this module? All synchronization data will be lost.');
    }

    /**
     * Module installation
     *
     * @return bool
     */
    public function install()
    {
        // Asegurarse de que los directorios necesarios existen
        $dirs = [
            'logs',
            'backups',
            'views/templates/admin',
            'views/templates/hook',
            'views/templates/helpers/list',
        ];
        
        foreach ($dirs as $dir) {
            $full_dir = dirname(__FILE__) . '/' . $dir;
            if (!is_dir($full_dir)) {
                if (!mkdir($full_dir, 0755, true)) {
                    $this->_errors[] = $this->l('Could not create directory:') . ' ' . $dir;
                    return false;
                }
            }
            
            // Crear archivo index.php en cada directorio para prevenir listado
            if (!file_exists($full_dir . '/index.php')) {
                @file_put_contents(
                    $full_dir . '/index.php',
                    '<?php\n\nheader("Pragma: no-cache");\nheader("Cache-Control: no-store, no-cache, must-revalidate, post-check=0, pre-check=0");\nheader("Expires: 0");\nheader("Location: ../");\nexit;\n'
                );
            }
        }
        
        // Crear archivo debug para registros
        $debug_file = dirname(__FILE__) . '/logs/webservice_debug.log';
        if (!file_exists($debug_file)) {
            @file_put_contents($debug_file, '# Stock Sync Module Debug Log\n' . date('Y-m-d H:i:s') . ' - Module installed\n');
        }
        
        // Check if multishop is active
        if (Shop::isFeatureActive()) {
            Shop::setContext(Shop::CONTEXT_ALL);
        }

        return parent::install() &&
            $this->registerHooks() &&
            $this->installDb() &&
            $this->installTabs() &&
            $this->installConfiguration();
    }

    /**
     * Module uninstallation
     *
     * @return bool
     */
    public function uninstall()
    {
        return parent::uninstall() &&
            $this->uninstallDb() &&
            $this->uninstallTabs() &&
            $this->uninstallConfiguration();
    }

    /**
     * Register module hooks
     *
     * @return bool
     */
    private function registerHooks()
    {
        return $this->registerHook('actionUpdateQuantity') &&
            $this->registerHook('actionObjectProductUpdateAfter') &&
            $this->registerHook('actionObjectCombinationUpdateAfter') &&
            $this->registerHook('actionValidateOrder') &&
            $this->registerHook('actionAdminControllerSetMedia') &&
            $this->registerHook('displayBackOfficeHeader');
    }

    /**
     * Install module database tables
     *
     * @return bool
     */
    private function installDb()
    {
        $result = true;
        $sql_files = [
            dirname(__FILE__) . '/sql/install/stock_sync_stores.sql',
            dirname(__FILE__) . '/sql/install/stock_sync_queue.sql',
            dirname(__FILE__) . '/sql/install/stock_sync_log.sql',
            dirname(__FILE__) . '/sql/install/stock_sync_references.sql',
        ];

        // Primero creamos el directorio sql/install si no existe
        $install_dir = dirname(__FILE__) . '/sql/install';
        if (!is_dir($install_dir)) {
            if (!mkdir($install_dir, 0755, true)) {
                $this->_errors[] = $this->l('Could not create SQL install directory');
                return false;
            }
        }

        foreach ($sql_files as $sql_file) {
            // Verificar si el archivo existe, si no, intentar crearlo
            if (!file_exists($sql_file)) {
                // Crear los archivos SQL si no existen
                $this->createSqlFiles();
            }
            
            if (file_exists($sql_file)) {
                $sql = file_get_contents($sql_file);
                if (!empty($sql)) {
                    $sql = str_replace('PREFIX_', _DB_PREFIX_, $sql);
                    try {
                        $result &= Db::getInstance()->execute($sql);
                    } catch (Exception $e) {
                        $this->_errors[] = $this->l('SQL Error: ') . $e->getMessage();
                        error_log('Stock Sync Module - SQL Error: ' . $e->getMessage());
                        $result = false;
                    }
                }
            } else {
                $this->_errors[] = $this->l('SQL file not found: ') . $sql_file;
                $result = false;
            }
        }

        return $result;
    }

    /**
     * Crear archivos SQL si no existen
     */
    private function createSqlFiles()
    {
        // Directorio para los archivos SQL
        $install_dir = dirname(__FILE__) . '/sql/install';
        if (!is_dir($install_dir)) {
            mkdir($install_dir, 0755, true);
        }

        // Definición de las tablas
        $sql_stores = "CREATE TABLE IF NOT EXISTS `PREFIX_stock_sync_stores` (
  `id_store` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `store_name` varchar(128) NOT NULL,
  `store_url` varchar(255) NOT NULL,
  `api_key` varchar(128) NOT NULL,
  `sync_type` varchar(32) NOT NULL,
  `active` tinyint(1) unsigned NOT NULL DEFAULT 1,
  `priority` int(10) unsigned NOT NULL DEFAULT 0,
  `last_sync` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  PRIMARY KEY (`id_store`),
  UNIQUE KEY `store_url` (`store_url`),
  KEY `sync_type` (`sync_type`),
  KEY `active` (`active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

        $sql_queue = "CREATE TABLE IF NOT EXISTS `PREFIX_stock_sync_queue` (
  `id_queue` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `id_product` int(10) unsigned NOT NULL,
  `id_product_attribute` int(10) unsigned NOT NULL DEFAULT 0,
  `reference` varchar(64) NOT NULL,
  `old_quantity` decimal(20,6) NOT NULL DEFAULT 0,
  `new_quantity` decimal(20,6) NOT NULL DEFAULT 0,
  `operation_type` varchar(32) NOT NULL,
  `status` varchar(32) NOT NULL,
  `source_store_id` int(10) unsigned NOT NULL,
  `target_store_id` int(10) unsigned NOT NULL,
  `attempts` int(10) unsigned NOT NULL DEFAULT 0,
  `error_message` text DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  PRIMARY KEY (`id_queue`),
  KEY `reference` (`reference`),
  KEY `status` (`status`),
  KEY `source_target` (`source_store_id`, `target_store_id`),
  KEY `product` (`id_product`, `id_product_attribute`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

        $sql_log = "CREATE TABLE IF NOT EXISTS `PREFIX_stock_sync_log` (
  `id_log` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `id_queue` int(10) unsigned DEFAULT NULL,
  `message` text NOT NULL,
  `level` varchar(32) NOT NULL,
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`id_log`),
  KEY `id_queue` (`id_queue`),
  KEY `level` (`level`),
  KEY `created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

        $sql_references = "CREATE TABLE IF NOT EXISTS `PREFIX_stock_sync_references` (
  `id_reference_map` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `reference` varchar(64) NOT NULL,
  `id_product_source` int(10) unsigned NOT NULL,
  `id_product_attribute_source` int(10) unsigned NOT NULL DEFAULT 0,
  `id_product_target` int(10) unsigned NOT NULL,
  `id_product_attribute_target` int(10) unsigned NOT NULL DEFAULT 0,
  `id_store_source` int(10) unsigned NOT NULL,
  `id_store_target` int(10) unsigned NOT NULL,
  `active` tinyint(1) unsigned NOT NULL DEFAULT 1,
  `last_sync` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  PRIMARY KEY (`id_reference_map`),
  UNIQUE KEY `unique_mapping` (`reference`, `id_store_source`, `id_store_target`),
  KEY `reference` (`reference`),
  KEY `active` (`active`),
  KEY `source_product` (`id_product_source`, `id_product_attribute_source`),
  KEY `target_product` (`id_product_target`, `id_product_attribute_target`),
  KEY `stores` (`id_store_source`, `id_store_target`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

        // Guardar los archivos SQL
        @file_put_contents($install_dir . '/stock_sync_stores.sql', $sql_stores);
        @file_put_contents($install_dir . '/stock_sync_queue.sql', $sql_queue);
        @file_put_contents($install_dir . '/stock_sync_log.sql', $sql_log);
        @file_put_contents($install_dir . '/stock_sync_references.sql', $sql_references);
    }

    /**
     * Uninstall module database tables
     *
     * @return bool
     */
    private function uninstallDb()
    {
        $result = true;
        $tables = [
            'stock_sync_stores',
            'stock_sync_queue',
            'stock_sync_log',
            'stock_sync_references',
        ];

        foreach ($tables as $table) {
            try {
                $result &= Db::getInstance()->execute('DROP TABLE IF EXISTS `' . _DB_PREFIX_ . $table . '`');
            } catch (Exception $e) {
                error_log('Stock Sync Module - Error al desinstalar tabla ' . $table . ': ' . $e->getMessage());
                // Continuar con las siguientes tablas
            }
        }

        return $result;
    }

    /**
     * Install admin tabs
     *
     * @return bool
     */
    private function installTabs()
    {
        $result = true;
        $parent_tab = $this->addTab('Stock Sync', 'AdminStockSync', 0);

        if ($parent_tab) {
            $tabs = [
                ['name' => 'Dashboard', 'class_name' => 'AdminStockSyncDashboard', 'parent_id' => $parent_tab],
                ['name' => 'Stores', 'class_name' => 'AdminStockSyncStores', 'parent_id' => $parent_tab],
                ['name' => 'References', 'class_name' => 'AdminStockSyncReferences', 'parent_id' => $parent_tab],
                ['name' => 'Queue', 'class_name' => 'AdminStockSyncQueue', 'parent_id' => $parent_tab],
                ['name' => 'Logs', 'class_name' => 'AdminStockSyncLogs', 'parent_id' => $parent_tab],
            ];

            foreach ($tabs as $tab) {
                $result &= $this->addTab($tab['name'], $tab['class_name'], $tab['parent_id']);
            }
        }

        return $result;
    }

    /**
     * Add admin tab
     *
     * @param string $name
     * @param string $class_name
     * @param int $parent_id
     * @return int|bool
     */
    private function addTab($name, $class_name, $parent_id)
    {
        $tab = new Tab();
        $tab->active = 1;
        $tab->class_name = $class_name;
        $tab->name = [];
        foreach (Language::getLanguages(true) as $lang) {
            $tab->name[$lang['id_lang']] = $name;
        }
        $tab->id_parent = $parent_id;
        $tab->module = $this->name;

        return $tab->add();
    }

    /**
     * Uninstall admin tabs
     *
     * @return bool
     */
    private function uninstallTabs()
    {
        $result = true;
        $tabs = [
            'AdminStockSync',
            'AdminStockSyncDashboard',
            'AdminStockSyncStores',
            'AdminStockSyncReferences',
            'AdminStockSyncQueue',
            'AdminStockSyncLogs',
        ];

        foreach ($tabs as $class_name) {
            $id_tab = (int) Tab::getIdFromClassName($class_name);
            if ($id_tab) {
                $tab = new Tab($id_tab);
                $result &= $tab->delete();
            }
        }

        return $result;
    }

/**
 * Install default configuration
 *
 * @return bool
 */
private function installConfiguration()
{
    return Configuration::updateValue('STOCK_SYNC_ACTIVE', 0) &&
        Configuration::updateValue('STOCK_SYNC_ROLE', 'bidirectional') &&
        Configuration::updateValue('STOCK_SYNC_RETRY_COUNT', 3) &&
        Configuration::updateValue('STOCK_SYNC_RETRY_DELAY', 300) &&
        Configuration::updateValue('STOCK_SYNC_TOKEN_EXPIRY', 86400) &&
        Configuration::updateValue('STOCK_SYNC_CONFLICT_STRATEGY', 'last_update_wins') &&
        Configuration::updateValue('STOCK_SYNC_DEBUG_MODE', 0) &&
        Configuration::updateValue('STOCK_SYNC_ALLOW_ALL_SYNC', 1) && 
        Configuration::updateValue('STOCK_SYNC_ACCEPT_ANY_TOKEN', 0); // Nueva opción para aceptar cualquier token
}

    /**
     * Uninstall configuration
     *
     * @return bool
     */
    private function uninstallConfiguration()
    {
        return Configuration::deleteByName('STOCK_SYNC_ACTIVE') &&
            Configuration::deleteByName('STOCK_SYNC_ROLE') &&
            Configuration::deleteByName('STOCK_SYNC_RETRY_COUNT') &&
            Configuration::deleteByName('STOCK_SYNC_RETRY_DELAY') &&
            Configuration::deleteByName('STOCK_SYNC_TOKEN_EXPIRY') &&
            Configuration::deleteByName('STOCK_SYNC_CONFLICT_STRATEGY') &&
            Configuration::deleteByName('STOCK_SYNC_DEBUG_MODE');
    }

    /**
     * Module configuration page
     *
     * @return string
     */
    public function getContent()
    {
        Tools::redirectAdmin($this->context->link->getAdminLink('AdminStockSyncDashboard'));
    }

    /**
     * Hook: actionUpdateQuantity
     * Triggered when product quantity is updated
     *
     * @param array $params
     */
    public function hookActionUpdateQuantity($params)
    {
        if (!$this->isModuleActive()) {
            return;
        }

        // Extract parameters
        $id_product = (int) $params['id_product'];
        $id_product_attribute = (int) $params['id_product_attribute'];
        $old_quantity = (float) $params['old_quantity'];
        $new_quantity = (float) $params['quantity'];

        // Get product reference
        $product = new Product($id_product);
        
        // If it's a combination, get the combination reference instead
        $reference = $product->reference;
        if ($id_product_attribute > 0) {
            $combination = new Combination($id_product_attribute);
            if (Validate::isLoadedObject($combination) && $combination->reference) {
                $reference = $combination->reference;
            }
        }

        // Log the stock change
        StockSyncLog::add(
            sprintf(
                'Stock updated for product %s (ID: %d, ID Attribute: %d): %f -> %f',
                $reference,
                $id_product,
                $id_product_attribute,
                $old_quantity,
                $new_quantity
            ),
            'info'
        );

        // Process stock synchronization
        $this->syncStockChange($id_product, $id_product_attribute, $reference, $old_quantity, $new_quantity, 'update');
    }

    /**
     * Hook: actionObjectProductUpdateAfter
     * Triggered after a product is updated
     *
     * @param array $params
     */
    public function hookActionObjectProductUpdateAfter($params)
    {
        if (!$this->isModuleActive()) {
            return;
        }

        $product = $params['object'];
        
        // Only process if the product has a reference and the quantity has changed
        if ($product->reference) {
            // Get the old quantities from cache if available
            $old_quantity = StockSyncTools::getOldProductQuantity($product->id);
            $new_quantity = StockAvailable::getQuantityAvailableByProduct($product->id);
            
            // Only sync if the quantity has changed
            if ($old_quantity !== null && $old_quantity != $new_quantity) {
                $this->syncStockChange(
                    $product->id, 
                    0, 
                    $product->reference, 
                    $old_quantity, 
                    $new_quantity, 
                    'product_update'
                );
            }
        }
    }

    /**
     * Hook: actionObjectCombinationUpdateAfter
     * Triggered after a combination is updated
     *
     * @param array $params
     */
    public function hookActionObjectCombinationUpdateAfter($params)
    {
        if (!$this->isModuleActive()) {
            return;
        }

        $combination = $params['object'];
        
        // Only process if the combination has a reference
        if (Validate::isLoadedObject($combination)) {
            $product = new Product($combination->id_product);
            $old_quantity = StockSyncTools::getOldCombinationQuantity($combination->id_product, $combination->id);
            $new_quantity = StockAvailable::getQuantityAvailableByProduct($combination->id_product, $combination->id);
            
            // Only sync if the quantity has changed
            if ($old_quantity !== null && $old_quantity != $new_quantity) {
                $this->syncStockChange(
                    $combination->id_product, 
                    $combination->id, 
                    $combination->reference ? $combination->reference : $product->reference, 
                    $old_quantity, 
                    $new_quantity, 
                    'combination_update'
                );
            }
        }
    }

    /**
     * Hook: actionValidateOrder
     * Triggered when an order is validated
     *
     * @param array $params
     */
    public function hookActionValidateOrder($params)
    {
        if (!$this->isModuleActive()) {
            return;
        }

        $order = $params['order'];
        $products = $order->getProducts();
        
        foreach ($products as $product) {
            $id_product = (int) $product['product_id'];
            $id_product_attribute = (int) $product['product_attribute_id'];
            $quantity = (float) $product['product_quantity'];
            
            // Get the product object to retrieve the reference
            $product_obj = new Product($id_product);
            $reference = $product_obj->reference;
            
            // If it's a combination, get the combination reference
            if ($id_product_attribute > 0) {
                $combination = new Combination($id_product_attribute);
                if (Validate::isLoadedObject($combination) && $combination->reference) {
                    $reference = $combination->reference;
                }
            }
            
            // Get current stock quantity
            $current_quantity = StockAvailable::getQuantityAvailableByProduct($id_product, $id_product_attribute);
            $old_quantity = $current_quantity + $quantity; // The quantity before the order
            
            $this->syncStockChange(
                $id_product, 
                $id_product_attribute, 
                $reference, 
                $old_quantity, 
                $current_quantity, 
                'order'
            );
        }
    }

    /**
     * Hook: actionAdminControllerSetMedia
     * Add CSS and JS files to the admin pages
     */
    public function hookActionAdminControllerSetMedia()
    {
        $controller = Tools::getValue('controller');
        
        if (strpos($controller, 'AdminStockSync') !== false) {
            $this->context->controller->addCSS($this->_path . 'views/css/admin.css');
            $this->context->controller->addJS($this->_path . 'views/js/admin.js');
        }
    }

    /**
     * Hook: displayBackOfficeHeader
     * Add additional elements to the back office header
     */
    public function hookDisplayBackOfficeHeader()
    {
        $controller = Tools::getValue('controller');
        
        if (strpos($controller, 'AdminStockSync') !== false) {
            $this->context->smarty->assign([
                'stock_sync_path' => $this->_path,
                'stock_sync_token' => $this->getWebserviceToken()
            ]);
            
            return $this->display(__FILE__, 'views/templates/hook/backoffice_header.tpl');
        }
        
        return '';
    }

    /**
     * Process stock synchronization
     *
     * @param int $id_product
     * @param int $id_product_attribute
     * @param string $reference
     * @param float $old_quantity
     * @param float $new_quantity
     * @param string $operation_type
     * @return bool
     */
    public function syncStockChange($id_product, $id_product_attribute, $reference, $old_quantity, $new_quantity, $operation_type)
    {
        // Check if module is active
        if (!$this->isModuleActive()) {
            return false;
        }
        
        try {
            // Get all active stores
            $stores = StockSyncStore::getActiveStores();
            if (empty($stores)) {
                return false;
            }
            
            $sync_role = Configuration::get('STOCK_SYNC_ROLE');
            $current_store_id = StockSyncStore::getCurrentStoreId();
            
            foreach ($stores as $store) {
                // Skip self
                if ($store['id_store'] == $current_store_id) {
                    continue;
                }
                
                // Determine if we should sync with this store based on roles
                $should_sync = false;
                
                if ($sync_role == 'principal' && $store['sync_type'] == 'secundaria') {
                    $should_sync = true;
                } elseif ($sync_role == 'secundaria' && $store['sync_type'] == 'principal') {
                    $should_sync = true;
                } elseif ($sync_role == 'bidirectional' || $store['sync_type'] == 'bidirectional') {
                    $should_sync = true;
                }
                
                if ($should_sync) {
                    // Add to sync queue
                    $queue_id = StockSyncQueue::add(
                        $id_product,
                        $id_product_attribute,
                        $reference,
                        $old_quantity,
                        $new_quantity,
                        $operation_type,
                        $current_store_id,
                        $store['id_store']
                    );
                    
                    if ($queue_id) {
                        // Process immediately for real-time sync
                        $webservice = new StockSyncWebservice();
                        $result = $webservice->syncStock(
                            $queue_id,
                            $store,
                            $reference,
                            $new_quantity
                        );
                        
                        // Update queue status based on result
                        if ($result) {
                            StockSyncQueue::updateStatus($queue_id, 'completed');
                            StockSyncLog::add(
                                sprintf(
                                    'Successfully synchronized stock for reference %s to store %s',
                                    $reference,
                                    $store['store_name']
                                ),
                                'info'
                            );
                        } else {
                            StockSyncQueue::updateStatus($queue_id, 'failed', 'Failed to synchronize stock');
                            StockSyncLog::add(
                                sprintf(
                                    'Failed to synchronize stock for reference %s to store %s',
                                    $reference,
                                    $store['store_name']
                                ),
                                'error'
                            );
                        }
                    }
                }
            }
            
            return true;
        } catch (Exception $e) {
            // Registro del error
            if (Configuration::get('STOCK_SYNC_DEBUG_MODE')) {
                $log_file = dirname(__FILE__) . '/logs/sync_errors.log';
                file_put_contents(
                    $log_file, 
                    date('Y-m-d H:i:s') . ' - Error: ' . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n", 
                    FILE_APPEND
                );
            }
            
            return false;
        }
    }

    /**
     * Check if module is active
     *
     * @return bool
     */
    public function isModuleActive()
    {
        return (bool) Configuration::get('STOCK_SYNC_ACTIVE');
    }

    /**
     * Get webservice token
     *
     * @return string
     */
    public function getWebserviceToken()
    {
        // Generate a secure token for API communication
        $token = Configuration::get('STOCK_SYNC_TOKEN');
        
        if (!$token || (time() - (int) Configuration::get('STOCK_SYNC_TOKEN_GENERATED') > (int) Configuration::get('STOCK_SYNC_TOKEN_EXPIRY'))) {
            $token = StockSyncTools::generateSecureToken();
            Configuration::updateValue('STOCK_SYNC_TOKEN', $token);
            Configuration::updateValue('STOCK_SYNC_TOKEN_GENERATED', time());
        }
        
        return $token;
    }
}