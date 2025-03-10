<?php
/**
 * Script para optimizar índices de las tablas del módulo Stock Sync
 *
 * Este script debe ejecutarse una vez para añadir índices a las tablas
 * que mejoran significativamente el rendimiento del módulo.
 */

// Incluir configuración PrestaShop
include(dirname(__FILE__).'/../../config/config.inc.php');
include(dirname(__FILE__).'/../../init.php');

// Verificar seguridad - Requerir que el usuario sea administrador
$cookie = new Cookie('psAdmin');
if (!$cookie->id_employee) {
    echo 'Debe iniciar sesión como administrador';
    exit;
}

// Arreglo con los índices a agregar por tabla
$indexes = [
    'stock_sync_queue' => [
        ['fields' => 'reference', 'name' => 'reference', 'type' => 'INDEX'],
        ['fields' => 'status', 'name' => 'status', 'type' => 'INDEX'],
        ['fields' => 'status, updated_at', 'name' => 'status_date', 'type' => 'INDEX'],
        ['fields' => 'reference, status', 'name' => 'reference_status', 'type' => 'INDEX']
    ],
    'stock_sync_log' => [
        ['fields' => 'level', 'name' => 'level', 'type' => 'INDEX'],
        ['fields' => 'created_at', 'name' => 'created_at', 'type' => 'INDEX'],
        ['fields' => 'level, created_at', 'name' => 'level_date', 'type' => 'INDEX']
    ],
    'stock_sync_references' => [
        ['fields' => 'reference, id_store_source, id_store_target', 'name' => 'unique_mapping', 'type' => 'UNIQUE'],
        ['fields' => 'reference', 'name' => 'reference', 'type' => 'INDEX'],
        ['fields' => 'active', 'name' => 'active', 'type' => 'INDEX'],
        ['fields' => 'id_product_source, id_product_attribute_source', 'name' => 'source_product', 'type' => 'INDEX'],
        ['fields' => 'id_product_target, id_product_attribute_target', 'name' => 'target_product', 'type' => 'INDEX'],
        ['fields' => 'id_store_source, id_store_target', 'name' => 'stores', 'type' => 'INDEX']
    ],
    'stock_sync_stores' => [
        ['fields' => 'store_url', 'name' => 'store_url', 'type' => 'UNIQUE'],
        ['fields' => 'sync_type', 'name' => 'sync_type', 'type' => 'INDEX'],
        ['fields' => 'active', 'name' => 'active', 'type' => 'INDEX']
    ]
];

// Iniciar la salida HTML
header('Content-Type: text/html; charset=utf-8');
echo '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Optimización de índices - Stock Sync Module</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .success { color: green; }
        .error { color: red; }
        .warning { color: orange; }
        pre { background: #f5f5f5; padding: 10px; border: 1px solid #ddd; overflow: auto; }
        .container { max-width: 800px; margin: 0 auto; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Optimización de índices para Stock Sync Module</h1>';

// Número de índices creados/actualizados
$created_count = 0;
$already_exists_count = 0;
$error_count = 0;

// Procesar cada tabla e índice
foreach ($indexes as $table => $table_indexes) {
    $table_name = _DB_PREFIX_ . $table;
    
    echo "<h2>Procesando tabla: {$table_name}</h2>";
    
    // Verificar si la tabla existe
    $table_exists = Db::getInstance()->executeS("SHOW TABLES LIKE '{$table_name}'");
    
    if (empty($table_exists)) {
        echo "<p class='error'>La tabla {$table_name} no existe. Omitiendo...</p>";
        continue;
    }
    
    // Obtener índices existentes
    $existing_indexes = [];
    $indexes_result = Db::getInstance()->executeS("SHOW INDEX FROM `{$table_name}`");
    
    if (is_array($indexes_result)) {
        foreach ($indexes_result as $idx) {
            if (!isset($existing_indexes[$idx['Key_name']])) {
                $existing_indexes[$idx['Key_name']] = [];
            }
            $existing_indexes[$idx['Key_name']][] = $idx['Column_name'];
        }
    }
    
    // Procesar cada índice para esta tabla
    foreach ($table_indexes as $index) {
        $index_name = $index['name'];
        $index_type = $index['type'];
        $fields = $index['fields'];
        
        echo "<h3>Índice: {$index_name}</h3>";
        
        // Verificar si el índice ya existe
        if (isset($existing_indexes[$index_name])) {
            echo "<p class='warning'>El índice ya existe. No se requieren cambios.</p>";
            $already_exists_count++;
            continue;
        }
        
        // Crear el índice
        $sql = "ALTER TABLE `{$table_name}` ADD {$index_type} `{$index_name}` ({$fields})";
        
        echo "<pre>{$sql}</pre>";
        
        try {
            $result = Db::getInstance()->execute($sql);
            
            if ($result) {
                echo "<p class='success'>Índice creado correctamente.</p>";
                $created_count++;
            } else {
                echo "<p class='error'>Error al crear el índice.</p>";
                $error_count++;
            }
        } catch (Exception $e) {
            echo "<p class='error'>Excepción: " . htmlspecialchars($e->getMessage()) . "</p>";
            $error_count++;
        }
    }
}

// Mostrar resumen
echo "<h2>Resumen</h2>
<p>Índices creados: <strong>{$created_count}</strong></p>
<p>Índices existentes: <strong>{$already_exists_count}</strong></p>
<p>Errores: <strong>{$error_count}</strong></p>

<p><a href='../../index.php?controller=AdminModules&configure=stocksyncmodule'>Volver al módulo</a></p>

</div>
</body>
</html>";
