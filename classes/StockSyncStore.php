<?php
/**
 * Stock Sync Store Class
 *
 * @author    Expert PrestaShop Developer
 * @copyright 2025
 * @license   Commercial
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

class StockSyncStore extends ObjectModel
{
    /**
     * @var int Store ID
     */
    public $id_store;
    
    /**
     * @var string Store name
     */
    public $store_name;
    
    /**
     * @var string Store URL
     */
    public $store_url;
    
    /**
     * @var string API key for WebService access
     */
    public $api_key;
    
    /**
     * @var string Synchronization type (principal, secundaria, bidirectional)
     */
    public $sync_type;
    
    /**
     * @var bool Whether the store is active
     */
    public $active;
    
    /**
     * @var int Priority for conflict resolution (higher number = higher priority)
     */
    public $priority;
    
    /**
     * @var string Date of last synchronization
     */
    public $last_sync;
    
    /**
     * @var string Creation date
     */
    public $created_at;
    
    /**
     * @var string Last update date
     */
    public $updated_at;
    
    /**
     * @see ObjectModel::$definition
     */
    public static $definition = [
        'table' => 'stock_sync_stores',
        'primary' => 'id_store',
        'fields' => [
            'store_name' => ['type' => self::TYPE_STRING, 'validate' => 'isGenericName', 'required' => true, 'size' => 128],
            'store_url' => ['type' => self::TYPE_STRING, 'validate' => 'isUrl', 'required' => true, 'size' => 255],
            'api_key' => ['type' => self::TYPE_STRING, 'validate' => 'isString', 'required' => true, 'size' => 128],
            'sync_type' => ['type' => self::TYPE_STRING, 'validate' => 'isString', 'required' => true, 'size' => 32],
            'active' => ['type' => self::TYPE_BOOL, 'validate' => 'isBool', 'required' => true],
            'priority' => ['type' => self::TYPE_INT, 'validate' => 'isUnsignedInt', 'required' => true],
            'last_sync' => ['type' => self::TYPE_DATE, 'validate' => 'isDateFormat', 'required' => false],
            'created_at' => ['type' => self::TYPE_DATE, 'validate' => 'isDateFormat', 'required' => true],
            'updated_at' => ['type' => self::TYPE_DATE, 'validate' => 'isDateFormat', 'required' => true]
        ]
    ];
    
    /**
     * Constructor
     *
     * @param int $id Store ID
     * @param int $id_lang Language ID
     * @param int $id_shop Shop ID
     */
    public function __construct($id = null, $id_lang = null, $id_shop = null)
    {
        parent::__construct($id, $id_lang, $id_shop);
        
        // Set default values for new stores
        if (!$id) {
            $this->active = true;
            $this->priority = 0;
            $this->sync_type = 'bidirectional';
            $this->created_at = date('Y-m-d H:i:s');
            $this->updated_at = date('Y-m-d H:i:s');
        }
        
        // Sanitize URL to ensure it has a protocol
        if (!empty($this->store_url)) {
            $this->store_url = self::sanitizeUrl($this->store_url);
        }
    }
    
    /**
     * Save the store
     *
     * @param bool $null_values Whether to allow null values
     * @param bool $auto_date Whether to automatically update date fields
     * @return bool
     */
    public function save($null_values = false, $auto_date = true)
    {
        // Sanitize URL before saving
        if (!empty($this->store_url)) {
            $this->store_url = self::sanitizeUrl($this->store_url);
        }
        
        $this->updated_at = date('Y-m-d H:i:s');
        return parent::save($null_values, $auto_date);
    }
    
    /**
     * Get all active stores
     *
     * @return array
     */
    public static function getActiveStores()
    {
        // Verificar primero si la tabla existe
        if (!self::tableExists(_DB_PREFIX_ . 'stock_sync_stores')) {
            return [];
        }
        
        $query = new DbQuery();
        $query->select('*')
            ->from('stock_sync_stores')
            ->where('active = 1')
            ->orderBy('store_name ASC');
        
        return Db::getInstance()->executeS($query);
    }
    
    /**
     * Get all stores (active and inactive)
     *
     * @return array
     */
    public static function getAllStores()
    {
        // Verificar primero si la tabla existe
        if (!self::tableExists(_DB_PREFIX_ . 'stock_sync_stores')) {
            return [];
        }
        
        $query = new DbQuery();
        $query->select('*')
            ->from('stock_sync_stores')
            ->orderBy('store_name ASC');
        
        return Db::getInstance()->executeS($query);
    }
    
    /**
     * Get store by URL
     *
     * @param string $url Store URL
     * @return array|false
     */
    public static function getByUrl($url)
    {
        // Verificar primero si la tabla existe
        if (!self::tableExists(_DB_PREFIX_ . 'stock_sync_stores')) {
            return false;
        }
        
        // Sanitize URL
        $url = self::sanitizeUrl($url);
        
        $query = new DbQuery();
        $query->select('*')
            ->from('stock_sync_stores')
            ->where('store_url = "' . pSQL($url) . '"');
        
        return Db::getInstance()->getRow($query);
    }
    
    /**
     * Get current store ID
     * This determines the ID of the store for the current installation
     *
     * @return int|null
     */
    public static function getCurrentStoreId()
    {
        // Verificar primero si la tabla existe
        if (!self::tableExists(_DB_PREFIX_ . 'stock_sync_stores')) {
            return null;
        }
        
        // Get the current shop domain
        $current_url = Tools::getShopDomainSsl(true);
        
        // Try to find a store with this URL
        $store = self::getByUrl($current_url);
        
        if ($store) {
            return (int) $store['id_store'];
        }
        
        // MODIFICADO: Ya no creamos automáticamente una tienda
        // Devolvemos null para que el administrador deba crear la tienda manualmente
        return null;
    }
    
    /**
     * Generate an API key for WebService access
     *
     * @return string
     */
    public static function generateApiKey()
    {
        if (class_exists('StockSyncTools')) {
            return StockSyncTools::generateSecureToken(32);
        }
        
        // Si la clase StockSyncTools no está disponible, usar una alternativa
        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $key = '';
        
        for ($i = 0; $i < 32; $i++) {
            $key .= $characters[rand(0, strlen($characters) - 1)];
        }
        
        return $key;
    }
    
    /**
     * Sanitiza una URL para asegurar formato correcto
     *
     * @param string $url URL a sanitizar
     * @return string URL sanitizada
     */
    public static function sanitizeUrl($url)
    {
        // Eliminar espacios en blanco
        $url = trim($url);
        
        // Asegurar que la URL tiene un protocolo
        if (strpos($url, 'http://') !== 0 && strpos($url, 'https://') !== 0) {
            $url = 'https://' . $url;
        }
        
        // Eliminar barras finales
        $url = rtrim($url, '/');
        
        return $url;
    }
    
    /**
     * Verifica la resolución DNS de una URL
     *
     * @param string $url URL a verificar
     * @return bool true si se pudo resolver, false si no
     */
    public static function checkDnsResolution($url)
    {
        $host = parse_url($url, PHP_URL_HOST);
        if (!$host) {
            return false;
        }
        
        // Intentar resolver el nombre de host
        $ip = gethostbyname($host);
        
        // Si gethostbyname falla, devuelve el nombre de host
        return ($ip !== $host);
    }
    
    /**
     * Test connection to a store
     *
     * @return array Result of the connection test
     */
    public function testConnection()
    {
        // Verificar primero la resolución DNS
        if (!self::checkDnsResolution($this->store_url)) {
            return [
                'success' => false,
                'message' => 'No se pudo resolver el nombre de host: ' . parse_url($this->store_url, PHP_URL_HOST),
                'response' => '',
                'time' => 0
            ];
        }
        
        if (class_exists('StockSyncWebservice')) {
            $webservice = new StockSyncWebservice();
            return $webservice->testConnection($this->getFields());
        }
        
        return [
            'success' => false,
            'message' => 'WebService class not available',
            'response' => '',
            'time' => 0
        ];
    }
    
    /**
     * Get store fields as an array
     *
     * @return array
     */
    public function getFields()
    {
        return [
            'id_store' => $this->id,
            'store_name' => $this->store_name,
            'store_url' => $this->store_url,
            'api_key' => $this->api_key,
            'sync_type' => $this->sync_type,
            'active' => $this->active,
            'priority' => $this->priority,
            'last_sync' => $this->last_sync
        ];
    }
    
    /**
     * Update last sync date
     *
     * @return bool
     */
    public function updateLastSync()
    {
        $this->last_sync = date('Y-m-d H:i:s');
        return $this->save();
    }
    
    /**
     * Get stores by sync type
     *
     * @param string $type Sync type (principal, secundaria, bidirectional)
     * @return array
     */
    public static function getByType($type)
    {
        // Verificar primero si la tabla existe
        if (!self::tableExists(_DB_PREFIX_ . 'stock_sync_stores')) {
            return [];
        }
        
        $query = new DbQuery();
        $query->select('*')
            ->from('stock_sync_stores')
            ->where('sync_type = "' . pSQL($type) . '"')
            ->where('active = 1')
            ->orderBy('store_name ASC');
        
        return Db::getInstance()->executeS($query);
    }
    
    /**
     * Validate if the synchronization is allowed based on the store roles
     *
     * @param int $source_store_id Source store ID
     * @param int $target_store_id Target store ID
     * @return bool
     */
    public static function validateSyncAllowed($source_store_id, $target_store_id)
    {
        // Comprobar si se permite cualquier sincronización (nueva configuración)
        if (Configuration::get('STOCK_SYNC_ALLOW_ALL_SYNC')) {
            return true;
        }
        
        // Permitir explícitamente la sincronización incluso si source_store_id == target_store_id
        if ($source_store_id == $target_store_id) {
            return true;
        }
        
        $source = new StockSyncStore($source_store_id);
        $target = new StockSyncStore($target_store_id);
        
        if (!Validate::isLoadedObject($source) || !Validate::isLoadedObject($target)) {
            return false;
        }
        
        if (!$source->active || !$target->active) {
            return false;
        }
        
        // Check sync roles
        if ($source->sync_type == 'principal' && $target->sync_type == 'principal') {
            return false; // Two principal stores can't sync with each other
        }
        
        if ($source->sync_type == 'secundaria' && $target->sync_type == 'secundaria') {
            return false; // Two secondary stores can't sync with each other
        }
        
        if ($source->sync_type == 'secundaria' && $target->sync_type == 'principal') {
            return true; // Secondary can send to principal
        }
        
        if ($source->sync_type == 'principal' && $target->sync_type == 'secundaria') {
            return true; // Principal can send to secondary
        }
        
        if ($source->sync_type == 'bidirectional' || $target->sync_type == 'bidirectional') {
            return true; // Bidirectional can sync with any type
        }
        
        return false;
    }
    
/**
 * Get store statistics
 *
 * @return array
 */
public static function getStatistics()
{
    $stats = [
        'total' => 0,
        'active' => 0,
        'inactive' => 0,
        'principal' => 0,
        'secundaria' => 0,
        'bidirectional' => 0,
        'newest' => null,
        'oldest' => null
    ];
    
    try {
        // Determinar el nombre exacto de la tabla
        $table_name = _DB_PREFIX_ . 'stock_sync_stores';
        
        // Verificar si la tabla existe realmente
        $table_exists = Db::getInstance()->executeS("SHOW TABLES LIKE '" . $table_name . "'");
        if (empty($table_exists)) {
            // Buscar otras tablas similares
            $similar_tables = Db::getInstance()->executeS("SHOW TABLES LIKE '%" . 'stock_sync_store' . "%'");
            if (!empty($similar_tables)) {
                // Usar la primera tabla encontrada
                $table_name = reset($similar_tables)[0];
            } else {
                return $stats; // No hay tablas similares
            }
        }
        
        // OPTIMIZACIÓN: Una única consulta para obtener todos los conteos
        $query = "SELECT 
                    COUNT(*) as total,
                    SUM(IF(active = 1, 1, 0)) as active,
                    SUM(IF(active = 0, 1, 0)) as inactive,
                    SUM(IF(sync_type = 'principal', 1, 0)) as principal,
                    SUM(IF(sync_type = 'secundaria', 1, 0)) as secundaria,
                    SUM(IF(sync_type = 'bidirectional', 1, 0)) as bidirectional,
                    MAX(created_at) as newest,
                    MIN(created_at) as oldest
                  FROM `" . $table_name . "`";
        
        $result = Db::getInstance()->getRow($query);
        
        if ($result) {
            $stats['total'] = (int)$result['total'];
            $stats['active'] = (int)$result['active'];
            $stats['inactive'] = (int)$result['inactive'];
            $stats['principal'] = (int)$result['principal'];
            $stats['secundaria'] = (int)$result['secundaria'];
            $stats['bidirectional'] = (int)$result['bidirectional'];
            $stats['newest'] = $result['newest'];
            $stats['oldest'] = $result['oldest'];
        }
        
        return $stats;
    } catch (Exception $e) {
        error_log('Error en getStatistics de Store: ' . $e->getMessage());
        return $stats;
    }
}
    
    /**
     * Check if a table exists in the database
     *
     * @param string $table Table name with prefix
     * @return bool
     */
    private static function tableExists($table)
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