<?php
/**
 * Script para procesar la cola de StockSync desde un cron
 * 
 * Este script debe ejecutarse periódicamente a través de un cron job
 * para procesar los elementos pendientes en la cola sin ralentizar la tienda.
 * 
 * Ejemplo de configuración cron:
 * */5 * * * * php /ruta/a/tu/prestashop/process-queue.php TOKEN > /dev/null 2>&1
 */

// Comprobación de seguridad - asegúrate de reemplazar TOKEN por algo único
if (!isset($argv[1]) || $argv[1] != 'TOKEN_SECRETO_AQUI') {
    die("Acceso no autorizado\n");
}

// Determinar la ruta a PrestaShop
$ps_root = dirname(__FILE__);

// Incluir archivos de configuración de PrestaShop
require_once($ps_root . '/config/config.inc.php');
require_once($ps_root . '/init.php');

// Cargar las clases del módulo
require_once($ps_root . '/modules/stocksyncmodule/classes/StockSync.php');
require_once($ps_root . '/modules/stocksyncmodule/classes/StockSyncQueue.php');
require_once($ps_root . '/modules/stocksyncmodule/classes/StockSyncLog.php');
require_once($ps_root . '/modules/stocksyncmodule/classes/StockSyncStore.php');
require_once($ps_root . '/modules/stocksyncmodule/classes/StockSyncReference.php');
require_once($ps_root . '/modules/stocksyncmodule/classes/StockSyncWebservice.php');
require_once($ps_root . '/modules/stocksyncmodule/classes/StockSyncTools.php');

// Verificar si el módulo está activo
if (!(bool) Configuration::get('STOCK_SYNC_ACTIVE')) {
    echo "El módulo StockSync no está activo. Abortando...\n";
    exit(0);
}

// Establecer un límite de tiempo de ejecución más largo
ini_set('max_execution_time', 300); // 5 minutos

try {
    // Obtener el número de elementos a procesar
    $limit = (int) Configuration::get('STOCK_SYNC_CRON_BATCH_SIZE', 50);
    
    // Procesar la cola
    $results = StockSync::processQueue($limit);
    
    // Mostrar resultados
    echo "Procesamiento completado: \n";
    echo "- Exitosos: " . $results['success'] . "\n";
    echo "- Fallidos: " . $results['failed'] . "\n";
    echo "- Omitidos: " . $results['skipped'] . "\n";
    
    // Limpiar registros antiguos (logs y elementos de cola completados)
    // Solo hacer limpieza si el procesamiento fue exitoso
    if ($results['success'] > 0) {
        // Limpiar logs antiguos (más de 7 días)
        $log_days = (int) Configuration::get('STOCK_SYNC_LOG_RETENTION', 7);
        $logs_cleaned = StockSyncLog::cleanOldLogs($log_days * 24);
        echo "- Logs antiguos eliminados: " . $logs_cleaned . "\n";
        
        // Limpiar elementos de cola antiguos (más de 2 días)
        $queue_days = (int) Configuration::get('STOCK_SYNC_QUEUE_RETENTION', 2);
        $queue_cleaned = StockSyncQueue::cleanOldItems($queue_days * 24);
        echo "- Elementos de cola antiguos eliminados: " . $queue_cleaned . "\n";
    }
    
    // Salir con éxito
    exit(0);
} catch (Exception $e) {
    // Registrar error
    error_log("Error en process-queue.php: " . $e->getMessage());
    echo "Error: " . $e->getMessage() . "\n";
    
    // Salir con error
    exit(1);
}