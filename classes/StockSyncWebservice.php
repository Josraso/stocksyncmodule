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
    const REQUEST_TIMEOUT = 30;
    
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
            
            $data = [
                'action' => 'test',
                'token' => StockSyncTools::generateTokenForRequest($store['api_key'])
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
            
            // Try controller URL first
            $response = $this->sendRequest($controller_url, $data);
            
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
                
                $response = $this->sendRequest($direct_url, $data);
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
            
            $data = [
                'action' => 'update_stock',
                'token' => StockSyncTools::generateTokenForRequest($store['api_key']),
                'reference' => $reference,
                'quantity' => (float) $quantity,
                'queue_id' => (int) $queue_id
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
            
            // Try controller URL first
            $response = $this->sendRequest($controller_url, $data);
            
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
                
                $response = $this->sendRequest($direct_url, $data);
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
            
            $data = [
                'action' => 'get_stock',
                'token' => StockSyncTools::generateTokenForRequest($store['api_key']),
                'reference' => $reference
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
            
            // Try controller URL first
            $response = $this->sendRequest($controller_url, $data);
            
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
                
                $response = $this->sendRequest($direct_url, $data);
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
     * Send API request to another store
     *
     * @param string $url API URL
     * @param array $data Request data
     * @return array|false Response data if successful, false otherwise
     */
    private function sendRequest($url, $data)
    {
        // Ensure URL format is correct
        $url = preg_replace('#/+#', '/', $url);
        $url = str_replace(':/', '://', $url); // Fix protocol after removing duplicates
        
        // Log request URL
        if (Configuration::get('STOCK_SYNC_DEBUG_MODE')) {
            $log_file = _PS_MODULE_DIR_ . 'stocksyncmodule/logs/webservice_debug.log';
            file_put_contents(
                $log_file, 
                date('Y-m-d H:i:s') . ' - Request URL: ' . $url . "\n", 
                FILE_APPEND
            );
        }
        
        // Use cURL for the request with better error handling
        $ch = curl_init();
        
        // Set cURL options
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        curl_setopt($ch, CURLOPT_TIMEOUT, self::REQUEST_TIMEOUT);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Skip SSL verification
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false); // Skip host verification
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10); // Connection timeout
        curl_setopt($ch, CURLOPT_DNS_CACHE_TIMEOUT, 86400); // DNS cache 24 hours
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/x-www-form-urlencoded',
            'User-Agent: StockSyncModule/1.0',
            'X-Stock-Sync-Version: 1.0'
        ]);
        
        // Execute the request
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);
        $curl_errno = curl_errno($ch);
        
        // Detailed logging for debugging
        if (Configuration::get('STOCK_SYNC_DEBUG_MODE')) {
            $log_file = _PS_MODULE_DIR_ . 'stocksyncmodule/logs/webservice_debug.log';
            $info = curl_getinfo($ch);
            file_put_contents(
                $log_file, 
                date('Y-m-d H:i:s') . ' - cURL Info: ' . print_r($info, true) . "\n" .
                'Response: ' . $response . "\n" .
                'HTTP Code: ' . $http_code . "\n" .
                'Error Code: ' . $curl_errno . "\n" .
                'Error: ' . $curl_error . "\n", 
                FILE_APPEND
            );
        }
        
        // Close cURL
        curl_close($ch);
        
        // Handle specific error cases
        if ($curl_errno == CURLE_COULDNT_RESOLVE_HOST) {
            StockSyncLog::add(
                sprintf('Could not resolve host. Verify the store URL is correct: %s', $url),
                'error'
            );
            return false;
        }
        
        // Handle cURL failures
        if ($response === false) {
            StockSyncLog::add(
                sprintf('cURL Error (%d): %s', $curl_errno, $curl_error),
                'error'
            );
            return false;
        }
        
        // Handle non-200 HTTP responses
        if ($http_code != 200) {
            StockSyncLog::add(
                sprintf('HTTP Error: %d for URL: %s', $http_code, $url),
                'error'
            );
            return false;
        }
        
        // Parse JSON response
        $data = json_decode($response, true);
        
        if (json_last_error() != JSON_ERROR_NONE) {
            StockSyncLog::add(
                sprintf('JSON parse error: %s. Response: %s', json_last_error_msg(), substr($response, 0, 255)),
                'error'
            );
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