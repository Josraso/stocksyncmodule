<?php
/**
 * Stock Sync References Controller
 *
 * @author    Expert PrestaShop Developer
 * @copyright 2025
 * @license   Commercial
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

class AdminStockSyncReferencesController extends ModuleAdminController
{
    public function __construct()
    {
        $this->bootstrap = true;
        $this->table = 'stock_sync_references';
        $this->className = 'StockSyncReference';
        $this->lang = false;
        $this->addRowAction('view');
        $this->addRowAction('delete');
        $this->explicitSelect = true;
        $this->allow_export = true;
        $this->deleted = false;
        $this->context = Context::getContext();
        
        // Definir título sin usar l() en el constructor
        $this->meta_title = 'Stock Sync References';
        $this->list_title = 'Reference Mappings';
        
        parent::__construct();
        
        if (!$this->module->active) {
            Tools::redirectAdmin($this->context->link->getAdminLink('AdminModules'));
        }
        
        // Definir campos de la tabla
        $this->fields_list = [
            'id_reference_map' => [
                'title' => $this->l('ID'),
                'align' => 'center',
                'class' => 'fixed-width-xs'
            ],
            'reference' => [
                'title' => $this->l('Reference'),
                'align' => 'left',
                'search' => true
            ],
            'source_store_name' => [
                'title' => $this->l('Source Store'),
                'align' => 'left',
                'search' => true,
                'filter_key' => 's1!store_name'
            ],
            'target_store_name' => [
                'title' => $this->l('Target Store'),
                'align' => 'left',
                'search' => true,
                'filter_key' => 's2!store_name'
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
     * Sobrescribir para usar nuestra propia query
     */
    public function getListQuery()
    {
        $this->_select = 'a.*, s1.store_name AS source_store_name, s2.store_name AS target_store_name';
        $this->_join = '
            LEFT JOIN `' . _DB_PREFIX_ . 'stock_sync_stores` s1 ON (a.id_store_source = s1.id_store)
            LEFT JOIN `' . _DB_PREFIX_ . 'stock_sync_stores` s2 ON (a.id_store_target = s2.id_store)
        ';
        $this->_orderBy = 'id_reference_map';
        $this->_orderWay = 'DESC';
        
        return parent::getListQuery();
    }
    
/**
 * Procesar acciones
 */
public function postProcess()
{
    // Escanear y mapear referencias
    if (Tools::isSubmit('scanAll')) {
        $source_store_id = (int) Tools::getValue('source_store_id');
        $target_store_id = (int) Tools::getValue('target_store_id');
        $force = (bool) Tools::getValue('force_update', false);
        
        // Permitir explícitamente el mapeo incluso si source_store_id == target_store_id
        if ($source_store_id > 0 && $target_store_id > 0) {
            $result = StockSyncReference::scanAndMapReferences($source_store_id, $target_store_id, $force);
            
            $this->confirmations[] = sprintf(
                $this->l('Reference mapping completed: %d new mappings, %d updated mappings, %d failed mappings.'),
                $result['new_mappings'],
                $result['updated_mappings'],
                $result['failed_mappings']
            );
        } else {
            $this->errors[] = $this->l('Invalid source or target store.');
        }
    }
    
 

        
        // Comprobar si hay referencias duplicadas
        if (Tools::isSubmit('checkDuplicates')) {
            $duplicates = StockSyncReference::checkDuplicateReferences();
            
            if (count($duplicates) > 0) {
                $this->warnings[] = sprintf(
                    $this->l('Found %d duplicate references. Please check and fix them.'),
                    count($duplicates)
                );
                
                $this->context->smarty->assign([
                    'duplicates' => $duplicates
                ]);
            } else {
                $this->confirmations[] = $this->l('No duplicate references found.');
            }
        }
        
        // Actualizar manualmente una referencia
        if (Tools::isSubmit('updateMapping')) {
            $id_reference_map = (int) Tools::getValue('id_reference_map');
            $active = (int) Tools::getValue('active');
            
            $result = Db::getInstance()->update(
                'stock_sync_references',
                [
                    'active' => $active,
                    'updated_at' => date('Y-m-d H:i:s')
                ],
                'id_reference_map = ' . $id_reference_map
            );
            
            if ($result) {
                $this->confirmations[] = $this->l('Reference mapping updated successfully.');
            } else {
                $this->errors[] = $this->l('Failed to update reference mapping.');
            }
        }
        
        parent::postProcess();
    }
    
    /**
     * Renderizar formulario para escanear referencias
     */
    public function renderScanForm()
    {
        $stores = StockSyncStore::getActiveStores();
        $store_options = [];
        
        foreach ($stores as $store) {
            $store_options[] = [
                'id' => $store['id_store'],
                'name' => $store['store_name'] . ' (' . $store['store_url'] . ')'
            ];
        }
        
        $fields_form = [
            'legend' => [
                'title' => $this->l('Scan and Map References'),
                'icon' => 'icon-cogs'
            ],
            'input' => [
                [
                    'type' => 'select',
                    'label' => $this->l('Source Store'),
                    'name' => 'source_store_id',
                    'required' => true,
                    'options' => [
                        'query' => $store_options,
                        'id' => 'id',
                        'name' => 'name'
                    ]
                ],
                [
                    'type' => 'select',
                    'label' => $this->l('Target Store'),
                    'name' => 'target_store_id',
                    'required' => true,
                    'options' => [
                        'query' => $store_options,
                        'id' => 'id',
                        'name' => 'name'
                    ]
                ],
                [
                    'type' => 'switch',
                    'label' => $this->l('Force Update Existing'),
                    'name' => 'force_update',
                    'required' => false,
                    'is_bool' => true,
                    'desc' => $this->l('If enabled, existing mappings will be updated.'),
                    'values' => [
                        [
                            'id' => 'force_on',
                            'value' => 1,
                            'label' => $this->l('Yes')
                        ],
                        [
                            'id' => 'force_off',
                            'value' => 0,
                            'label' => $this->l('No')
                        ]
                    ]
                ]
            ],
            'submit' => [
                'title' => $this->l('Scan and Map'),
                'icon' => 'process-icon-cogs',
                'name' => 'scanAll'
            ]
        ];
        
        $helper = new HelperForm();
        $helper->show_toolbar = false;
        $helper->table = $this->table;
        $helper->module = $this->module;
        $helper->default_form_language = $this->context->language->id;
        $helper->allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG', 0);
        
        $helper->identifier = $this->identifier;
        $helper->submit_action = 'scanAll';
        $helper->currentIndex = $this->context->link->getAdminLink('AdminStockSyncReferences');
        $helper->token = Tools::getAdminTokenLite('AdminStockSyncReferences');
        
        $helper->tpl_vars = [
            'fields_value' => [
                'source_store_id' => StockSyncStore::getCurrentStoreId(),
                'target_store_id' => 0,
                'force_update' => false
            ],
            'languages' => $this->context->controller->getLanguages(),
            'id_language' => $this->context->language->id
        ];
        
        return $helper->generateForm([['form' => $fields_form]]);
    }
    
    /**
     * Renderizar vista
     */
    public function renderView()
    {
        $id_reference_map = (int) Tools::getValue('id_reference_map');
        
        $reference_map = Db::getInstance()->getRow(
            'SELECT r.*, s1.store_name AS source_store_name, s2.store_name AS target_store_name
            FROM `' . _DB_PREFIX_ . 'stock_sync_references` r
            LEFT JOIN `' . _DB_PREFIX_ . 'stock_sync_stores` s1 ON (r.id_store_source = s1.id_store)
            LEFT JOIN `' . _DB_PREFIX_ . 'stock_sync_stores` s2 ON (r.id_store_target = s2.id_store)
            WHERE r.id_reference_map = ' . $id_reference_map
        );
        
        if (!$reference_map) {
            $this->errors[] = $this->l('Reference mapping not found');
            
            return $this->renderList();
        }
        
        // Obtener información de producto origen
        $source_product = [];
        
        if ($reference_map['id_product_source'] > 0) {
            $product = new Product($reference_map['id_product_source']);
            
            if (Validate::isLoadedObject($product)) {
                $source_product = [
                    'id' => $product->id,
                    'name' => $product->name[$this->context->language->id],
                    'reference' => $product->reference,
                    'link' => $this->context->link->getAdminLink('AdminProducts') . '&id_product=' . $product->id . '&updateproduct'
                ];
                
                if ($reference_map['id_product_attribute_source'] > 0) {
                    $combination = new Combination($reference_map['id_product_attribute_source']);
                    
                    if (Validate::isLoadedObject($combination)) {
                        $source_product['combination_id'] = $combination->id;
                        $source_product['combination_reference'] = $combination->reference;
                    }
                }
            }
        }
        
        // Obtener información de producto destino
        $target_product = [];
        
        if ($reference_map['id_product_target'] > 0) {
            $product = new Product($reference_map['id_product_target']);
            
            if (Validate::isLoadedObject($product)) {
                $target_product = [
                    'id' => $product->id,
                    'name' => $product->name[$this->context->language->id],
                    'reference' => $product->reference,
                    'link' => $this->context->link->getAdminLink('AdminProducts') . '&id_product=' . $product->id . '&updateproduct'
                ];
                
                if ($reference_map['id_product_attribute_target'] > 0) {
                    $combination = new Combination($reference_map['id_product_attribute_target']);
                    
                    if (Validate::isLoadedObject($combination)) {
                        $target_product['combination_id'] = $combination->id;
                        $target_product['combination_reference'] = $combination->reference;
                    }
                }
            }
        }
        
        // Obtener logs de sincronización para esta referencia
        $logs = StockSyncLog::getByReference($reference_map['reference'], 10);
        
        // Asignar variables a la vista
        $this->context->smarty->assign([
            'reference_map' => $reference_map,
            'source_product' => $source_product,
            'target_product' => $target_product,
            'logs' => $logs,
            'update_url' => $this->context->link->getAdminLink('AdminStockSyncReferences') . '&updateMapping=1&id_reference_map=' . $id_reference_map
        ]);
        
        return $this->createTemplate('reference_details.tpl')->fetch();
    }
    
    /**
     * Añadir enlaces en la cabecera
     */
    public function initPageHeaderToolbar()
    {
        if (empty($this->display)) {
            $this->page_header_toolbar_btn['scan_references'] = [
                'href' => self::$currentIndex . '&scanForm&token=' . $this->token,
                'desc' => $this->l('Scan & Map References'),
                'icon' => 'process-icon-cogs'
            ];
            
            $this->page_header_toolbar_btn['check_duplicates'] = [
                'href' => self::$currentIndex . '&checkDuplicates&token=' . $this->token,
                'desc' => $this->l('Check Duplicates'),
                'icon' => 'process-icon-search'
            ];
        }
        
        parent::initPageHeaderToolbar();
    }
    
    /**
     * Renderizar contenido
     */
    public function renderList()
    {
        // Si estamos mostrando el formulario de escaneo
        if (Tools::isSubmit('scanForm')) {
            return $this->renderScanForm();
        }
        
        // Mostrar la lista de duplicados si se ha realizado la comprobación
        $duplicates_html = '';
        
        if (isset($this->context->smarty->tpl_vars['duplicates']) && is_array($this->context->smarty->tpl_vars['duplicates']->value) && count($this->context->smarty->tpl_vars['duplicates']->value) > 0) {
            $tpl = $this->createTemplate('duplicates.tpl');
            $tpl->assign([
                'duplicates' => $this->context->smarty->tpl_vars['duplicates']->value
            ]);
            
            $duplicates_html = $tpl->fetch();
        }
        
        // Mostrar estadísticas
        $tpl = $this->createTemplate('references.tpl');
        $tpl->assign([
            'stats' => StockSyncReference::getStatistics(),
            'scan_url' => $this->context->link->getAdminLink('AdminStockSyncReferences') . '&scanForm',
            'check_url' => $this->context->link->getAdminLink('AdminStockSyncReferences') . '&checkDuplicates'
        ]);
        
        return $tpl->fetch() . $duplicates_html . parent::renderList();
    }
}