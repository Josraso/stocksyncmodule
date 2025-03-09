<?php
/**
 * Stock Sync Stores Controller
 *
 * @author    Expert PrestaShop Developer
 * @copyright 2025
 * @license   Commercial
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

class AdminStockSyncStoresController extends ModuleAdminController
{
    public function __construct()
    {
        $this->bootstrap = true;
        $this->table = 'stock_sync_stores';
        $this->className = 'StockSyncStore';
        $this->lang = false;
        $this->addRowAction('edit');
        $this->addRowAction('delete');
        $this->addRowAction('test');
        $this->explicitSelect = true;
        $this->allow_export = true;
        $this->deleted = false;
        $this->context = Context::getContext();
        
        // Definir título sin usar l() en el constructor
        $this->meta_title = 'Stock Sync Stores';
        $this->list_title = 'Stock Sync Stores';
        
        // ¡IMPORTANTE! Establecer el identificador correcto
        $this->identifier = 'id_store';
        
        parent::__construct();
        
        if (!$this->module->active) {
            Tools::redirectAdmin($this->context->link->getAdminLink('AdminModules'));
        }
        
        // Definir campos de la tabla
        $this->fields_list = [
            'id_store' => [
                'title' => $this->l('ID'),
                'align' => 'center',
                'class' => 'fixed-width-xs'
            ],
            'store_name' => [
                'title' => $this->l('Store Name'),
                'align' => 'left',
                'search' => true
            ],
            'store_url' => [
                'title' => $this->l('URL'),
                'align' => 'left',
                'search' => true
            ],
            'sync_type' => [
                'title' => $this->l('Sync Type'),
                'align' => 'center',
                'callback' => 'getSyncTypeBadge',
                'search' => true,
                'type' => 'select',
                'list' => [
                    'principal' => $this->l('Principal'),
                    'secundaria' => $this->l('Secondary'),
                    'bidirectional' => $this->l('Bidirectional')
                ],
                'filter_key' => 'a!sync_type'
            ],
            'active' => [
                'title' => $this->l('Status'),
                'active' => 'status',
                'type' => 'bool',
                'align' => 'center',
                'ajax' => true,
                'search' => true,
                'orderby' => false
            ],
            'last_sync' => [
                'title' => $this->l('Last Sync'),
                'align' => 'center',
                'type' => 'datetime',
                'search' => true,
                'filter_key' => 'a!last_sync'
            ]
        ];
        
        $this->bulk_actions = [
            'delete' => [
                'text' => $this->l('Delete selected'),
                'confirm' => $this->l('Delete selected items?'),
                'icon' => 'icon-trash'
            ],
            'enable' => [
                'text' => $this->l('Enable selected'),
                'icon' => 'icon-check'
            ],
            'disable' => [
                'text' => $this->l('Disable selected'),
                'icon' => 'icon-times'
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
     * Sobrescribir para usar nuestra propia query y evitar el error de ordenación
     */
    public function getListQuery()
    {
        // Establecer explícitamente el campo de ordenación
        if (!isset($this->_orderBy) || empty($this->_orderBy)) {
            $this->_orderBy = 'id_store';
        }
        
        // Evitar prefijo 'a.' para el campo por el que se ordena
        if ($this->_orderBy == 'id_store') {
            $this->_orderBy = 'a.id_store';
        }
        
        return parent::getListQuery();
    }
    
    /**
     * Método de callback para mostrar badges de tipo de sincronización
     */
    public function getSyncTypeBadge($sync_type, $row)
    {
        $badge_class = 'badge badge-';
        $label = '';
        
        switch ($sync_type) {
            case 'principal':
                $badge_class .= 'primary';
                $label = $this->l('Principal');
                break;
            case 'secundaria':
                $badge_class .= 'info';
                $label = $this->l('Secondary');
                break;
            case 'bidirectional':
                $badge_class .= 'success';
                $label = $this->l('Bidirectional');
                break;
            default:
                $badge_class .= 'default';
                $label = $sync_type;
                break;
        }
        
        return '<span class="' . $badge_class . '">' . $label . '</span>';
    }
    
    /**
     * Procesar acciones
     */
    public function postProcess()
    {
        // Generar nueva API key
        if (Tools::isSubmit('regenerateKey')) {
            $id_store = (int) Tools::getValue('id_store');
            $store = new StockSyncStore($id_store);
            
            if (Validate::isLoadedObject($store)) {
                $store->api_key = StockSyncStore::generateApiKey();
                
                if ($store->save()) {
                    $this->confirmations[] = $this->l('API Key regenerated successfully.');
                } else {
                    $this->errors[] = $this->l('Failed to regenerate API Key.');
                }
            } else {
                $this->errors[] = $this->l('Store not found.');
            }
        }
        
        // Prueba de conexión
        if (Tools::isSubmit('testConnection') || Tools::getValue('action') == 'test') {
            $id_store = (int) Tools::getValue('id_store');
            $store = new StockSyncStore($id_store);
            
            if (Validate::isLoadedObject($store)) {
                $test_result = $store->testConnection();
                
                if ($test_result['success']) {
                    $this->confirmations[] = sprintf(
                        $this->l('Connection successful to store "%s" (Response time: %s seconds)'),
                        $store->store_name,
                        number_format($test_result['time'], 3)
                    );
                } else {
                    $this->errors[] = sprintf(
                        $this->l('Connection failed to store "%s": %s'),
                        $store->store_name,
                        $test_result['message']
                    );
                }
                
                if (Tools::getValue('action') == 'test') {
                    Tools::redirectAdmin($this->context->link->getAdminLink('AdminStockSyncStores'));
                }
            } else {
                $this->errors[] = $this->l('Store not found.');
            }
        }
        
        // Escanear referencias
        if (Tools::isSubmit('scanReferences')) {
            $id_store = (int) Tools::getValue('id_store');
            $store = new StockSyncStore($id_store);
            
            if (Validate::isLoadedObject($store)) {
                $current_store_id = StockSyncStore::getCurrentStoreId();
                
                if ($current_store_id && $current_store_id != $id_store) {
                    $force = (bool) Tools::getValue('force_update', false);
                    $result = StockSyncReference::scanAndMapReferences($current_store_id, $id_store, $force);
                    
                    $this->confirmations[] = sprintf(
                        $this->l('Reference mapping completed: %d new mappings, %d updated mappings, %d failed mappings.'),
                        $result['new_mappings'],
                        $result['updated_mappings'],
                        $result['failed_mappings']
                    );
                } else {
                    $this->errors[] = $this->l('Cannot map references from store to itself.');
                }
            } else {
                $this->errors[] = $this->l('Store not found.');
            }
        }
        
        parent::postProcess();
    }
    
    /**
     * Renderizar formulario
     */
    public function renderForm()
    {
        $this->fields_form = [
            'legend' => [
                'title' => $this->l('Store Information'),
                'icon' => 'icon-building'
            ],
            'input' => [
                [
                    'type' => 'text',
                    'label' => $this->l('Store Name'),
                    'name' => 'store_name',
                    'required' => true,
                    'class' => 'fixed-width-xl'
                ],
                [
                    'type' => 'text',
                    'label' => $this->l('Store URL'),
                    'name' => 'store_url',
                    'desc' => $this->l('Enter the full URL of the store, e.g. https://www.yourstore.com/'),
                    'required' => true,
                    'class' => 'fixed-width-xxl'
                ],
                [
                    'type' => 'text',
                    'label' => $this->l('API Key'),
                    'name' => 'api_key',
                    'required' => true,
                    'readonly' => false,
                    'class' => 'fixed-width-xxl',
                    'desc' => $this->l('This key is used for authentication between stores.')
                ],
                [
                    'type' => 'select',
                    'label' => $this->l('Synchronization Type'),
                    'name' => 'sync_type',
                    'required' => true,
                    'options' => [
                        'query' => [
                            [
                                'id' => 'principal',
                                'name' => $this->l('Principal (sends stock to secondary stores)')
                            ],
                            [
                                'id' => 'secundaria',
                                'name' => $this->l('Secondary (receives stock from principal store)')
                            ],
                            [
                                'id' => 'bidirectional',
                                'name' => $this->l('Bidirectional (sends and receives stock)')
                            ]
                        ],
                        'id' => 'id',
                        'name' => 'name'
                    ]
                ],
                [
                    'type' => 'switch',
                    'label' => $this->l('Active'),
                    'name' => 'active',
                    'required' => false,
                    'is_bool' => true,
                    'values' => [
                        [
                            'id' => 'active_on',
                            'value' => 1,
                            'label' => $this->l('Yes')
                        ],
                        [
                            'id' => 'active_off',
                            'value' => 0,
                            'label' => $this->l('No')
                        ]
                    ]
                ],
                [
                    'type' => 'text',
                    'label' => $this->l('Priority'),
                    'name' => 'priority',
                    'desc' => $this->l('Higher priority stores will take precedence in conflict resolution.'),
                    'required' => true,
                    'class' => 'fixed-width-sm'
                ]
            ],
            'buttons' => [
                'regenerate_key' => [
                    'title' => $this->l('Regenerate API Key'),
                    'icon' => 'process-icon-refresh',
                    'href' => self::$currentIndex . '&regenerateKey&id_store=' . Tools::getValue('id_store') . '&token=' . $this->token,
                    'js' => 'if (confirm(\'' . $this->l('Are you sure you want to regenerate the API key? This will break any existing connection.') . '\')) { return true; } else { event.preventDefault(); }',
                    'name' => 'regenerateKey'
                ],
                'test_connection' => [
                    'title' => $this->l('Test Connection'),
                    'icon' => 'process-icon-refresh',
                    'href' => self::$currentIndex . '&testConnection&id_store=' . Tools::getValue('id_store') . '&token=' . $this->token,
                    'name' => 'testConnection'
                ]
            ],
            'submit' => [
                'title' => $this->l('Save')
            ]
        ];
        
        if (Tools::isSubmit('addstock_sync_stores')) {
            // Para nueva tienda, generar API key
            $this->fields_value = [
                'api_key' => StockSyncStore::generateApiKey(),
                'active' => 1,
                'priority' => 0,
                'sync_type' => 'bidirectional'
            ];
        } elseif (Tools::isSubmit('updatestock_sync_stores')) {
            // Para tienda existente, mostrar botones adicionales
            $id_store = (int) Tools::getValue('id_store');
            
            if ($id_store > 0) {
                $this->fields_form['buttons']['scan_references'] = [
                    'title' => $this->l('Scan & Map References'),
                    'icon' => 'process-icon-cogs',
                    'href' => self::$currentIndex . '&scanReferences&id_store=' . $id_store . '&token=' . $this->token,
                    'js' => 'if (confirm(\'' . $this->l('Do you want to scan and map all product references with this store?') . '\')) { return true; } else { event.preventDefault(); }',
                    'name' => 'scanReferences'
                ];
            }
        }
        
        return parent::renderForm();
    }
    
    /**
     * Añadir enlaces en la cabecera
     */
    public function initPageHeaderToolbar()
    {
        if (empty($this->display)) {
            $this->page_header_toolbar_btn['new_store'] = [
                'href' => self::$currentIndex . '&addstock_sync_stores&token=' . $this->token,
                'desc' => $this->l('Add New Store'),
                'icon' => 'process-icon-new'
            ];
            
            // Botón para volver al panel de control
            $this->page_header_toolbar_btn['back_to_dashboard'] = [
                'href' => $this->context->link->getAdminLink('AdminStockSyncDashboard'),
                'desc' => $this->l('Back to Dashboard'),
                'icon' => 'process-icon-back'
            ];
        } elseif ($this->display == 'view' || $this->display == 'edit' || $this->display == 'add') {
            $this->page_header_toolbar_btn['back_to_list'] = [
                'href' => $this->context->link->getAdminLink('AdminStockSyncStores'),
                'desc' => $this->l('Back to List'),
                'icon' => 'process-icon-back'
            ];
            
            // Botón para volver al panel de control
            $this->page_header_toolbar_btn['back_to_dashboard'] = [
                'href' => $this->context->link->getAdminLink('AdminStockSyncDashboard'),
                'desc' => $this->l('Back to Dashboard'),
                'icon' => 'process-icon-back'
            ];
        }
        
        parent::initPageHeaderToolbar();
    }
    
    /**
     * Añadir un botón para probar la conexión en la lista de tiendas
     */
    public function displayTestLink($token, $id)
    {
        if (!array_key_exists('test', self::$cache_lang)) {
            self::$cache_lang['test'] = $this->l('Test');
        }
        
        $this->context->smarty->assign([
            'href' => self::$currentIndex . '&' . $this->identifier . '=' . $id . '&action=test&token=' . ($token != null ? $token : $this->token),
            'action' => self::$cache_lang['test'],
            'id' => $id
        ]);
        
        // Corregido: Usar el método de módulo para renderizar la plantilla
        return $this->module->display(_PS_MODULE_DIR_ . $this->module->name . '/controllers/admin/AdminStockSyncStoresController.php', 'helpers/list/list_action_test.tpl');
    }
}