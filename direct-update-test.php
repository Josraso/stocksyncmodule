<?php
/**
 * Prueba directa de actualización de stock
 *
 * Este script prueba la actualización directa de stock sin usar la lógica del módulo.
 * Coloca este archivo en la raíz de tu tienda y llámalo direct-update.php
 */

// Incluir configuración de PrestaShop
include(dirname(__FILE__).'/config/config.inc.php');
include(dirname(__FILE__).'/init.php');

// Verificar que estamos en modo desarrollo/debug
if (!Configuration::get('STOCK_SYNC_DEBUG_MODE')) {
    die('Este script solo funciona en modo debug. Actívalo en la configuración del módulo.');
}

// Función para imprimir mensajes
function printMsg($msg, $type = 'info') {
    $color = 'black';
    switch ($type) {
        case 'success': $color = 'green'; break;
        case 'error': $color = 'red'; break;
        case 'warning': $color = 'orange'; break;
    }
    echo '<div style="color:'.$color.';margin:5px 0;">'.$msg.'</div>';
}

// Función para probar actualización directa de stock usando StockAvailable
function testDirectStockUpdate($reference, $quantity) {
    // Buscar el producto primero
    $product_id = null;
    $product_attribute_id = 0;
    
    // CORREGIDO: Eliminar el LIMIT 1 duplicado
    // Intentar buscar como combinación primero
    $combination_query = 'SELECT pa.id_product_attribute, pa.id_product 
                        FROM '._DB_PREFIX_.'product_attribute pa 
                        WHERE pa.reference = "'.pSQL($reference).'"';
    $combination = Db::getInstance()->getRow($combination_query);
    
    if ($combination) {
        $product_id = (int)$combination['id_product'];
        $product_attribute_id = (int)$combination['id_product_attribute'];
        printMsg("Encontrada combinación con ID: {$product_id}, ID_ATTRIBUTE: {$product_attribute_id}", 'success');
    } else {
        // Si no es combinación, buscar como producto
        $product_query = 'SELECT p.id_product 
                        FROM '._DB_PREFIX_.'product p 
                        WHERE p.reference = "'.pSQL($reference).'"';
        $product = Db::getInstance()->getRow($product_query);
        
        if ($product) {
            $product_id = (int)$product['id_product'];
            printMsg("Encontrado producto con ID: {$product_id}", 'success');
        } else {
            printMsg("No se encontró ningún producto con referencia: {$reference}", 'error');
            return false;
        }
    }
    
    // Si hemos llegado aquí, tenemos un producto para actualizar
    $current_quantity = StockAvailable::getQuantityAvailableByProduct($product_id, $product_attribute_id);
    printMsg("Cantidad actual: {$current_quantity}", 'info');
    
    try {
        // Intentar actualizar el stock directamente
        $result = StockAvailable::setQuantity($product_id, $product_attribute_id, $quantity);
        
        if ($result) {
            $new_quantity = StockAvailable::getQuantityAvailableByProduct($product_id, $product_attribute_id);
            printMsg("¡Stock actualizado con éxito! Nueva cantidad: {$new_quantity}", 'success');
            return true;
        } else {
            printMsg("Falló la actualización de stock. setQuantity devolvió false.", 'error');
            return false;
        }
    } catch (Exception $e) {
        printMsg("Error al actualizar el stock: " . $e->getMessage(), 'error');
        return false;
    }
}

// Función para enviar actualización directa vía cURL a otra tienda
function testRemoteStockUpdate($url, $api_key, $reference, $quantity) {
    $url = rtrim($url, '/') . '/modules/stocksyncmodule/api/sync.php';
    printMsg("Enviando actualización a: " . $url, 'info');
    
    // Preparar datos
    $data = [
        'action' => 'update_stock',
        'token' => $api_key,
        'api_key' => $api_key,
        'reference' => $reference,
        'quantity' => (float)$quantity
    ];
    
    // Inicializar cURL
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_VERBOSE, true);
    
    // Capturar el output verbose para diagnóstico
    $verbose = fopen('php://temp', 'w+');
    curl_setopt($ch, CURLOPT_STDERR, $verbose);
    
    // Ejecutar petición
    $response = curl_exec($ch);
    $info = curl_getinfo($ch);
    $error = curl_error($ch);
    
    // Obtener log verbose
    rewind($verbose);
    $verbose_log = stream_get_contents($verbose);
    fclose($verbose);
    
    if ($response === false) {
        printMsg("Error cURL: " . $error, 'error');
        printMsg("Info cURL: " . print_r($info, true), 'info');
        printMsg("Log verbose: " . $verbose_log, 'info');
        return false;
    }
    
    // Intentar decodificar la respuesta JSON
    $result = json_decode($response, true);
    
    if ($result === null) {
        printMsg("Error al decodificar respuesta JSON.", 'error');
        printMsg("Respuesta: " . $response, 'info');
        return false;
    }
    
    if (isset($result['success']) && $result['success']) {
        printMsg("¡Stock actualizado en tienda remota con éxito!", 'success');
        printMsg("Respuesta: " . print_r($result, true), 'info');
        return true;
    } else {
        printMsg("Error al actualizar stock en tienda remota: " . (isset($result['message']) ? $result['message'] : 'Desconocido'), 'error');
        printMsg("Respuesta completa: " . print_r($result, true), 'info');
        return false;
    }
}

// HTML para la interfaz
header('Content-Type: text/html; charset=utf-8');
echo '<!DOCTYPE html><html><head><title>Prueba Directa de Stock</title>';
echo '<style>
body { font-family: Arial, sans-serif; margin: 20px; line-height: 1.5; }
h1, h2 { color: #333; }
pre { background: #f5f5f5; padding: 10px; border: 1px solid #ccc; }
.container { max-width: 800px; margin: 0 auto; }
.panel { border: 1px solid #ddd; margin-bottom: 20px; border-radius: 4px; overflow: hidden; }
.panel-header { background: #f5f5f5; padding: 10px 15px; border-bottom: 1px solid #ddd; }
.panel-body { padding: 15px; }
.form-group { margin-bottom: 15px; }
label { display: block; margin-bottom: 5px; font-weight: bold; }
input, select { padding: 6px 10px; width: 100%; box-sizing: border-box; }
button { background: #4CAF50; color: white; border: none; padding: 10px 15px; cursor: pointer; }
.result { margin-top: 20px; padding: 15px; background: #f9f9f9; border: 1px solid #ddd; }
</style>';
echo '</head><body>';
echo '<div class="container">';
echo '<h1>Prueba Directa de Actualización de Stock</h1>';

// Obtener tiendas
$stores = [];
try {
    $stores_query = 'SELECT id_store, store_name, store_url, api_key, active FROM '._DB_PREFIX_.'stock_sync_stores';
    $stores = Db::getInstance()->executeS($stores_query);
} catch (Exception $e) {
    printMsg("Error al obtener tiendas: " . $e->getMessage(), 'error');
}

// Procesar actualización local
if (isset($_POST['update_local'])) {
    $reference = Tools::getValue('reference_local');
    $quantity = (float)Tools::getValue('quantity_local');
    
    echo '<div class="result">';
    echo '<h3>Resultado de actualización local:</h3>';
    
    if (empty($reference)) {
        printMsg("La referencia no puede estar vacía", 'error');
    } else {
        testDirectStockUpdate($reference, $quantity);
    }
    
    echo '</div>';
}

// Procesar actualización remota
if (isset($_POST['update_remote'])) {
    $store_id = (int)Tools::getValue('store_id');
    $reference = Tools::getValue('reference_remote');
    $quantity = (float)Tools::getValue('quantity_remote');
    
    echo '<div class="result">';
    echo '<h3>Resultado de actualización remota:</h3>';
    
    if (empty($reference)) {
        printMsg("La referencia no puede estar vacía", 'error');
    } elseif ($store_id <= 0) {
        printMsg("Debes seleccionar una tienda", 'error');
    } else {
        // Buscar la tienda seleccionada
        $selected_store = null;
        foreach ($stores as $store) {
            if ((int)$store['id_store'] === $store_id) {
                $selected_store = $store;
                break;
            }
        }
        
        if ($selected_store) {
            testRemoteStockUpdate($selected_store['store_url'], $selected_store['api_key'], $reference, $quantity);
        } else {
            printMsg("Tienda no encontrada", 'error');
        }
    }
    
    echo '</div>';
}

// Formulario para actualización local
echo '<div class="panel">';
echo '<div class="panel-header"><h2>1. Actualizar Stock Localmente</h2></div>';
echo '<div class="panel-body">';
echo '<form method="post" action="">';
echo '<div class="form-group">';
echo '<label for="reference_local">Referencia del producto:</label>';
echo '<input type="text" id="reference_local" name="reference_local" required>';
echo '</div>';
echo '<div class="form-group">';
echo '<label for="quantity_local">Nueva cantidad:</label>';
echo '<input type="number" id="quantity_local" name="quantity_local" value="10" min="0" step="1" required>';
echo '</div>';
echo '<button type="submit" name="update_local">Actualizar Stock Localmente</button>';
echo '</form>';
echo '</div></div>';

// Formulario para actualización remota
if (!empty($stores)) {
    echo '<div class="panel">';
    echo '<div class="panel-header"><h2>2. Actualizar Stock en Tienda Remota</h2></div>';
    echo '<div class="panel-body">';
    echo '<form method="post" action="">';
    echo '<div class="form-group">';
    echo '<label for="store_id">Selecciona tienda destino:</label>';
    echo '<select id="store_id" name="store_id" required>';
    echo '<option value="">-- Selecciona una tienda --</option>';
    
    foreach ($stores as $store) {
        $active_text = $store['active'] ? '' : ' (Inactiva)';
        echo '<option value="'.$store['id_store'].'">'.$store['store_name'].$active_text.'</option>';
    }
    
    echo '</select>';
    echo '</div>';
    echo '<div class="form-group">';
    echo '<label for="reference_remote">Referencia del producto:</label>';
    echo '<input type="text" id="reference_remote" name="reference_remote" required>';
    echo '</div>';
    echo '<div class="form-group">';
    echo '<label for="quantity_remote">Nueva cantidad:</label>';
    echo '<input type="number" id="quantity_remote" name="quantity_remote" value="10" min="0" step="1" required>';
    echo '</div>';
    echo '<button type="submit" name="update_remote">Enviar a Tienda Remota</button>';
    echo '</form>';
    echo '</div></div>';
} else {
    echo '<div class="panel">';
    echo '<div class="panel-header"><h2>2. Actualizar Stock en Tienda Remota</h2></div>';
    echo '<div class="panel-body">';
    echo '<p style="color:red;">No hay tiendas configuradas para actualización remota.</p>';
    echo '</div></div>';
}

echo '</div>'; // Fin del container
echo '</body></html>';