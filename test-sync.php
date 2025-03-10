// Corregir la consulta en el script test-sync.php

// La parte del código que debe corregir se encuentra en el caso de procesamiento de stock:
if (isset($_POST['test_update']) && $_POST['test_update'] == '1') {
    echo '<h2>6. Prueba de Actualización de Stock</h2>';
    
    $reference = Tools::getValue('reference');
    $quantity = (float)Tools::getValue('quantity');
    $store_id = (int)Tools::getValue('store_id');
    
    if (empty($reference)) {
        echo '<p style="color:red">Error: Referencia no especificada.</p>';
    } else {
        // Primero verificamos si el producto existe en la tienda local
        echo "<h3>Verificando producto local con referencia: $reference</h3>";
        
        // CORREGIDO: Eliminar el LIMIT 1 duplicado
        $product_query = 'SELECT p.id_product, p.reference, pa.id_product_attribute, pa.reference as attribute_reference 
                         FROM '._DB_PREFIX_.'product p 
                         LEFT JOIN '._DB_PREFIX_.'product_attribute pa ON p.id_product = pa.id_product
                         WHERE p.reference = "'.pSQL($reference).'" OR pa.reference = "'.pSQL($reference).'"';
                         
        $product = Db::getInstance()->getRow($product_query);
