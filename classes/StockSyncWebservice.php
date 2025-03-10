<?php
/**
 * Stock Sync Webservice Class - FIXED VERSION
 *
 * @author    Expert PrestaShop Developer
 * @copyright 2025
 * @license   Commercial
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

class StockSyncWebservice
{
    /**
     * @var string API endpoint for stock synchronization
     */
    const API_ENDPOINT = 'modules/stocksyncmodule/api/sync.php';
    
    /**
     * @var int Request timeout in seconds
     */
    const REQUEST_TIMEOUT = 10; // Reducido de 30 a 10 segundos
    
    /**
     * Test connection to a store
     *
     * @param array $store Store data
     * @return array Result of the connection test
     */
    public function testConnection($store)
    {
        $result = [
            'success' => false,
            'message' => '',
            'response' => '',
            'time' => 0
        ];
        
        $start_time = microtime(true);
        
        try {
            // Make sure the store URL has the correct format
            $store['store_url'] = $this->sanitizeUrl($store['store_url']);
            
            // Check DNS resolution
            if (!$this->checkDnsResolution($store['store_url'])) {
                $result['message'] = 'DNS resolution failed for host: ' . parse_url($store['store_url'], PHP_URL_HOST);
                $result['time'] = microtime(true) - $start_time;
                
                // Log DNS error
                if (Configuration::get('STOCK_SYNC_DEBUG_MODE')) {
                    $log_file = _PS_MODULE_DIR_ . 'stocksyncmodule/logs/webservice_debug.log';
                    file_put_contents(
                        $log_file, 
                        date('Y-m-d H:i:s') . ' - DNS Error: ' . $result['message'] . "\n", 
                        FILE_APPEND
                    );
                }
                return $result;
            }
            
            // Try both the controller URL and direct API URL
            // First attempt with front controller
            $controller_url = rtrim($store['store_url'], '/') . '/index.php?fc=module&module=stocksyncmodule&controller=api';
            
            // SOLUCIÓN: Enviar directamente la API key como token
            $data = [
                'action' => 'test',
                'token' => $store['api_key'],
                // También enviar como api_key para compatibilidad
                'api_key' => $store['api_key']
            ];
            
            // Log test request
            if (Configuration::get('STOCK_SYNC_DEBUG_MODE')) {
                $log_file = _PS_MODULE_DIR_ . 'stocksyncmodule/logs/webservice_debug.log';
                file_put_contents(
                    $log_file, 
                    date('Y-m-d H:i:s') . ' - Test Request to ' . $controller_url . ': ' . print_r($data, true) . "\n", 
                    FILE_APPEND
                );
            }
            
            // Try controller URL first with retry
            $response = $this->sendRequestWithRetry($controller_url, $data, 2); // Usar 2 reintentos para tests
            
            // If controller URL fails, try direct API URL as fallback
            if ($response === false) {
                $direct_url = rtrim($store['store_url'], '/') . '/' . self::API_ENDPOINT;
                
                if (Configuration::get('STOCK_SYNC_DEBUG_MODE')) {
                    $log_file = _PS_MODULE_DIR_ . 'stocksyncmodule/logs/webservice_debug.log';
                    file_put_contents(
                        $log_file, 
                        date('Y-m-d H:i:s') . ' - Trying direct API URL: ' . $direct_url . "\n", 
                        FILE_APPEND
                    );
                }
                
                $response = $this->sendRequestWithRetry($direct_url, $data, 2);
            }
            
            $result['time'] = microtime(true) - $start_time;
            
            if ($response && isset($response['success']) && $response['success']) {
                $result['success'] = true;
                $result['message'] = 'Connection successful';
                $result['response'] = $response;
            } else {
                $result['message'] = $response && isset($response['message']) ? $response['message'] : 'Invalid response from server';
                $result['response'] = $response;
            }
            
            // Log test response
            if (Configuration::get('STOCK_SYNC_DEBUG_MODE')) {
                $log_file = _PS_MODULE_DIR_ . 'stocksyncmodule/logs/webservice_debug.log';
                file_put_contents(
                    $log_file, 
                    date('Y-m-d H:i:s') . ' - Test Response: ' . print_r($result, true) . "\n", 
                    FILE_APPEND
                );
            }
        } catch (Exception $e) {
            $result['message'] = $e->getMessage();
            $result['time'] = microtime(true) - $start_time;
            
            // Log exception
            if (Configuration::get('STOCK_SYNC_DEBUG_MODE')) {
                $log_file = _PS_MODULE_DIR_ . 'stocksyncmodule/logs/webservice_debug.log';
                file_put_contents(
                    $log_file, 
                    date('Y-m-d H:i:s') . ' - Test Exception: ' . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n", 
                    FILE_APPEND
                );
            }
        }
        
        return $result;
    }
    
    /**
     * Synchronize stock to another store
     *
     * @param int $queue_id Queue ID
     * @param array $store Target store data
     * @param string $reference Product reference
     * @param float $quantity New stock quantity
     * @return bool
     */
    public function syncStock($queue_id, $store, $reference, $quantity)
    {
        try {
            // Make sure the store URL has the correct format
            $store['store_url'] = $this->sanitizeUrl($store['store_url']);
            
            // Check DNS resolution
            if (!$this->checkDnsResolution($store['store_url'])) {
                $error_message = 'DNS resolution failed for host: ' . parse_url($store['store_url'], PHP_URL_HOST);
                StockSyncLog::add(
                    sprintf('DNS Error: %s', $error_message),
                    'error',
                    $queue_id
                );
                return false;
            }
            
            // Try both controller URL and direct API URL
            $controller_url = rtrim($store['store_url'], '/') . '/index.php?fc=module&module=stocksyncmodule&controller=api';
            $direct_url = rtrim($store['store_url'], '/') . '/' . self::API_ENDPOINT;
            
            // SOLUCIÓN: Enviar directamente la API key como token
            $data = [
                'action' => 'update_stock',
                'token' => $store['api_key'],
                'reference' => $reference,
                'quantity' => (float) $quantity,
                'queue_id' => (int) $queue_id,
                // También enviar como api_key para compatibilidad
                'api_key' => $store['api_key']
            ];
            
            // Log sync request
            if (Configuration::get('STOCK_SYNC_DEBUG_MODE')) {
                $log_file = _PS_MODULE_DIR_ . 'stocksyncmodule/logs/webservice_debug.log';
                file_put_contents(
                    $log_file, 
                    date('Y-m-d H:i:s') . ' - Sync Request to ' . $controller_url . ': ' . print_r($data, true) . "\n", 
                    FILE_APPEND
                );
            }
            
            // Try controller URL first with retry
            $response = $this->sendRequestWithRetry($controller_url, $data);
            
            // If controller URL fails, try direct API URL as fallback
            if ($response === false) {
                if (Configuration::get('STOCK_SYNC_DEBUG_MODE')) {
                    $log_file = _PS_MODULE_DIR_ . 'stocksyncmodule/logs/webservice_debug.log';
                    file_put_contents(
                        $log_file, 
                        date('Y-m-d H:i:s') . ' - Controller URL failed, trying direct API: ' . $direct_url . "\n", 
                        FILE_APPEND
                    );
                }
                
                $response = $this->sendRequestWithRetry($direct_url, $data);
            }
            
            // Log sync response
            if (Configuration::get('STOCK_SYNC_DEBUG_MODE')) {
                $log_file = _PS_MODULE_DIR_ . 'stocksyncmodule/logs/webservice_debug.log';
                file_put_contents(
                    $log_file, 
                    date('Y-m-d H:i:s') . ' - Sync Response: ' . print_r($response, true) . "\n", 
                    FILE_APPEND
                );
            }
            
            if ($response && isset($response['success']) && $response['success']) {
                // Update last sync date for the store
                Db::getInstance()->update(
                    'stock_sync_stores',
                    [
                        'last_sync' => date('Y-m-d H:i:s'),
                        'updated_at' => date('Y-m-d H:i:s')
                    ],
                    'id_store = ' . (int) $store['id_store']
                );
                
                return true;
            }
            
            // Log the error
            StockSyncLog::add(
                sprintf(
                    'Failed to sync stock for reference %s to store %s. Error: %s',
                    $reference,
                    $store['store_name'],
                    $response && isset($response['message']) ? $response['message'] : 'Unknown error'
                ),
                'error',
                $queue_id
            );
            
            return false;
        } catch (Exception $e) {
            // Log the exception
            StockSyncLog::add(
                sprintf(
                    'Exception when syncing stock for reference %s to store %s: %s',
                    $reference,
                    $store['store_name'],
                    $e->getMessage()
                ),
                'error',
                $queue_id
            );
            
            // Log exception if debug mode is enabled
            if (Configuration::get('STOCK_SYNC_DEBUG_MODE')) {
                $log_file = _PS_MODULE_DIR_ . 'stocksyncmodule/logs/webservice_debug.log';
                file_put_contents(
                    $log_file, 
                    date('Y-m-d H:i:s') . ' - Sync Exception: ' . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n", 
                    FILE_APPEND
                );
            }
            
            return false;
        }
    }
    
    /**
     * Get stock by reference from another store
     *
     * @param array $store Source store data
     * @param string $reference Product reference
     * @return float|bool Stock quantity if successful, false otherwise
     */
    public function getStockByReference($store, $reference)
    {
        try {
            // Make sure the store URL has the correct format
            $store['store_url'] = $this->sanitizeUrl($store['store_url']);
            
            // Check DNS resolution
            if (!$this->checkDnsResolution($store['store_url'])) {
                StockSyncLog::add(
                    sprintf('DNS Error when getting stock: Could not resolve %s', parse_url($store['store_url'], PHP_URL_HOST)),
                    'error'
                );
                return false;
            }
            
            // Try both controller URL and direct API URL
            $controller_url = rtrim($store['store_url'], '/') . '/index.php?fc=module&module=stocksyncmodule&controller=api';
            $direct_url = rtrim($store['store_url'], '/') . '/' . self::API_ENDPOINT;
            
            // SOLUCIÓN: Enviar directamente la API key como token
            $data = [
                'action' => 'get_stock',
                'token' => $store['api_key'],
                'reference' => $reference,
                // También enviar como api_key para compatibilidad
                'api_key' => $store['api_key']
            ];
            
            // Log request in debug mode
            if (Configuration::get('STOCK_SYNC_DEBUG_MODE')) {
                $log_file = _PS_MODULE_DIR_ . 'stocksyncmodule/logs/webservice_debug.log';
                file_put_contents(
                    $log_file, 
                    date('Y-m-d H:i:s') . ' - Get Stock Request: ' . print_r($data, true) . "\n", 
                    FILE_APPEND
                );
            }
            
            // Try controller URL first with retry
            $response = $this->sendRequestWithRetry($controller_url, $data);
            
            // If controller URL fails, try direct API URL as fallback
            if ($response === false) {
                if (Configuration::get('STOCK_SYNC_DEBUG_MODE')) {
                    $log_file = _PS_MODULE_DIR_ . 'stocksyncmodule/logs/webservice_debug.log';
                    file_put_contents(
                        $log_file, 
                        date('Y-m-d H:i:s') . ' - Controller URL failed, trying direct API: ' . $direct_url . "\n", 
                        FILE_APPEND
                    );
                }
                
                $response = $this->sendRequestWithRetry($direct_url, $data);
            }
            
            // Log response in debug mode
            if (Configuration::get('STOCK_SYNC_DEBUG_MODE')) {
                $log_file = _PS_MODULE_DIR_ . 'stocksyncmodule/logs/webservice_debug.log';
                file_put_contents(
                    $log_file, 
                    date('Y-m-d H:i:s') . ' - Get Stock Response: ' . print_r($response, true) . "\n", 
                    FILE_APPEND
                );
            }
            
            if ($response && isset($response['success']) && $response['success'] && isset($response['quantity'])) {
                return (float) $response['quantity'];
            }
            
            return false;
        } catch (Exception $e) {
            StockSyncLog::add(
                sprintf('Exception when getting stock: %s', $e->getMessage()),
                'error'
            );
            
            // Log exception in debug mode
            if (Configuration::get('STOCK_SYNC_DEBUG_MODE')) {
                $log_file = _PS_MODULE_DIR_ . 'stocksyncmodule/logs/webservice_debug.log';
                file_put_contents(
                   $log_file, 
                    date('Y-m-d H:i:s') . ' - Get Stock Exception: ' . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n", 
                    FILE_APPEND
                );
            }
            
            return false;
        }
    }
    
    /**
     * Enviar solicitud con reintentos y backoff exponencial
     * 
     * @param string $url URL a la que enviar la solicitud
     * @param array $data Datos a enviar
     * @param int $max_retries Número máximo de reintentos
     * @param int $base_timeout Timeout base en segundos
     * @return array|false Respuesta JSON decodificada o false
     */
    private function sendRequestWithRetry($url, $data, $max_retries = 3, $base_timeout = 5)
    {
        $attempt = 0;
        
        while ($attempt < $max_retries) {
            // Aumentar el timeout exponencialmente con cada intento
            $current_timeout = $base_timeout * pow(2, $attempt);
            
            // Configurar cURL con este timeout
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
            curl_setopt($ch, CURLOPT_TIMEOUT, $current_timeout);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, min(5, $current_timeout));
            curl_setopt($ch, CURLOPT_DNS_CACHE_TIMEOUT, 86400); // Caché DNS por 24 horas
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/x-www-form-urlencoded',
                'User-Agent: StockSyncModule/1.0'
            ]);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
            curl_setopt($ch, CURLOPT_HEADER, false);
            
            $response = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curl_error = curl_error($ch);
            $curl_errno = curl_errno($ch);
            
            // Log detallado solo en modo debug
            if (Configuration::get('STOCK_SYNC_DEBUG_MODE')) {
                $log_file = _PS_MODULE_DIR_ . 'stocksyncmodule/logs/webservice_debug.log';
                $info = curl_getinfo($ch);
                file_put_contents(
                    $log_file, 
                    date('Y-m-d H:i:s') . ' - Intento #' . ($attempt + 1) . ' a ' . $url . "\n" .
                    'Timeout: ' . $current_timeout . "s\n" .
                    'HTTP Code: ' . $http_code . "\n" .
                    'Error Code: ' . $curl_errno . "\n" .
                    'Error: ' . $curl_error . "\n", 
                    FILE_APPEND
                );
            }
            
            curl_close($ch);
            
            // Si fue exitoso, procesarlo y retornar
            if ($response !== false && $http_code == 200) {
                $result = json_decode($response, true);
                if (json_last_error() == JSON_ERROR_NONE) {
                    return $result;
                }
            }
            
            // Si el error fue de conexión o timeout, reintentamos
            if (in_array($curl_errno, [CURLE_OPERATION_TIMEDOUT, CURLE_COULDNT_CONNECT, CURLE_COULDNT_RESOLVE_HOST])) {
                $attempt++;
                
                // Log del reintento
                if (Configuration::get('STOCK_SYNC_DEBUG_MODE')) {
                    $log_file = _PS_MODULE_DIR_ . 'stocksyncmodule/logs/webservice_debug.log';
                    file_put_contents(
                        $log_file, 
                        date('Y-m-d H:i:s') . ' - Reintento #' . $attempt . ' para URL ' . $url . 
                        ' después de error: ' . $curl_error . "\n", 
                        FILE_APPEND
                    );
                }
                
                // Esperar un tiempo incremental antes de reintentar
                usleep(200000 * $attempt); // 200ms, 400ms, 600ms, etc.
                continue;
            }
            
            // Si el error no es de timeout o conexión, no tiene sentido reintentar
            break;
        }
        
        // Si llegamos aquí, todos los intentos fallaron
        if (Configuration::get('STOCK_SYNC_DEBUG_MODE')) {
            $log_file = _PS_MODULE_DIR_ . 'stocksyncmodule/logs/webservice_debug.log';
            file_put_contents(
                $log_file, 
                date('Y-m-d H:i:s') . ' - Fallaron todos los intentos para URL ' . $url . "\n", 
                FILE_APPEND
            );
        }
        
        return false;
    }
    
    /**
     * Send API request to another store
     *
     * @param string $url API URL
     * @param array $data Request data
     * @return array|false Response data if successful, false otherwise
     */
    private function sendRequest($url, $data)
    {
        // Asegurar que tenemos una URL válida
        if (empty($url)) {
            StockSyncLog::add('URL vacía en sendRequest', 'error');
            return false;
        }

        // OPTIMIZACIÓN: Eliminar análisis innecesario de la URL
        $url = rtrim($url, '/');
        
        // Log request URL solo en modo debug
        if (Configuration::get('STOCK_SYNC_DEBUG_MODE')) {
            $log_file = _PS_MODULE_DIR_ . 'stocksyncmodule/logs/webservice_debug.log';
            file_put_contents(
                $log_file, 
                date('Y-m-d H:i:s') . ' - Request URL: ' . $url . "\n", 
                FILE_APPEND
            );
        }
        
        // MEJORA: Asegurar que el token y api_key estén en la petición
        if (!isset($data['token']) && isset($data['api_key'])) {
            $data['token'] = $data['api_key'];
        }
        if (!isset($data['api_key']) && isset($data['token'])) {
            $data['api_key'] = $data['token'];
        }
        
        // OPTIMIZACIÓN: Usar cURL con opciones optimizadas para rendimiento
        $ch = curl_init();
        
        // Configurar opciones básicas de cURL
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        
        // OPTIMIZACIÓN: Reducir timeout para mejorar rendimiento general
        curl_setopt($ch, CURLOPT_TIMEOUT, 10); // Reducido de 30 a 10 segundos
        
        // OPTIMIZACIÓN: Omitir verificación SSL para mejorar velocidad
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        
        // OPTIMIZACIÓN: Reducir tiempo de espera para conexión
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5); // Reducido de 10 a 5 segundos
        
        // OPTIMIZACIÓN: Configurar caché DNS para mejorar rendimiento
        curl_setopt($ch, CURLOPT_DNS_CACHE_TIMEOUT, 86400); // Caché DNS por 24 horas
        
        // OPTIMIZACIÓN: Establecer cabeceras minimalistas
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/x-www-form-urlencoded',
            'User-Agent: StockSyncModule/1.0'
        ]);
        
        // OPTIMIZACIÓN: Deshabilitar opciones no necesarias
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false); // No seguir redirecciones automáticamente
        curl_setopt($ch, CURLOPT_HEADER, false); // No incluir cabeceras en la respuesta
        
        // Ejecutar la petición
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);
        $curl_errno = curl_errno($ch);
        
        // Registrar detalles de debug solo si está habilitado
        if (Configuration::get('STOCK_SYNC_DEBUG_MODE')) {
            $log_file = _PS_MODULE_DIR_ . 'stocksyncmodule/logs/webservice_debug.log';
            $info = curl_getinfo($ch);
            file_put_contents(
                $log_file, 
                date('Y-m-d H:i:s') . ' - cURL Info: ' . print_r($info, true) . "\n" .
                'HTTP Code: ' . $http_code . "\n" .
                'Error Code: ' . $curl_errno . "\n" .
                'Error: ' . $curl_error . "\n", 
                FILE_APPEND
            );
        }
        
        // Cerrar cURL
        curl_close($ch);
        
        // OPTIMIZACIÓN: Manejar errores críticos de forma rápida
        if ($curl_errno == CURLE_COULDNT_RESOLVE_HOST) {
            StockSyncLog::add(
                sprintf('No se pudo resolver el host: %s', $url),
                'error'
            );
            return false;
        }
        
        // OPTIMIZACIÓN: Manejar fallos de cURL
        if ($response === false) {
            // Registrar error solo si es necesario
            if ($curl_errno != CURLE_OPERATION_TIMEDOUT) { // Ignorar timeouts comunes
                StockSyncLog::add(
                    sprintf('Error cURL (%d): %s', $curl_errno, $curl_error),
                    'error'
                );
            }
            return false;
        }
        
        // OPTIMIZACIÓN: Validación HTTP simplificada
        if ($http_code >= 400) {
            StockSyncLog::add(
                sprintf('Error HTTP %d para URL: %s', $http_code, $url),
                'error'
            );
            return false;
        }
        
        // OPTIMIZACIÓN: Parseo JSON con manejo de errores
        $data = @json_decode($response, true);
        
        if (json_last_error() != JSON_ERROR_NONE) {
            // Solo registrar si es un error JSON real, no un error de conexión
            if (!empty($response)) {
                StockSyncLog::add(
                    sprintf('Error de parseo JSON: %s', json_last_error_msg()),
                    'error'
                );
            }
            return false;
        }
        
        return $data;
    }
    
    /**
     * Sanitize a URL to ensure correct format
     *
     * @param string $url URL to sanitize
     * @return string Sanitized URL
     */
    private function sanitizeUrl($url)
    {
        // Remove whitespace
        $url = trim($url);
        
        // Fix malformed protocols (https:/ or http:/)
        $url = preg_replace('#^https?:\/([^\/])#', 'https://$1', $url);
        
        // Ensure URL has a protocol
        if (strpos($url, 'http://') !== 0 && strpos($url, 'https://') !== 0) {
            $url = 'https://' . $url;
        }
        
        // Remove trailing slashes
        $url = rtrim($url, '/');
        
        return $url;
    }
    
    /**
     * Check DNS resolution for a URL
     *
     * @param string $url URL to check
     * @return bool true if resolution successful, false otherwise
     */
    private function checkDnsResolution($url)
    {
        $host = parse_url($url, PHP_URL_HOST);
        if (!$host) {
            return false;
        }
        
        // Try to resolve hostname
        $ip = gethostbyname($host);
        
        // If gethostbyname fails, it returns the hostname
        return ($ip !== $host);
    }
    
    /**
     * Translation helper
     *
     * @param string $text Text to translate
     * @return string Translated text
     */
    private function l($text)
    {
        return Translate::getModuleTranslation('stocksyncmodule', $text, 'stocksyncwebservice');
    }
}