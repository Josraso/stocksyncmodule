/**
 * Stock Sync Module - Admin Stores JavaScript
 */

// Función para generar una API key aleatoria
function generateApiKey() {
    // Generar una clave aleatoria de 32 caracteres
    var charset = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    var apiKey = '';
    
    for (var i = 0; i < 32; i++) {
        var randomIndex = Math.floor(Math.random() * charset.length);
        apiKey += charset.charAt(randomIndex);
    }
    
    // Establecer el valor en el campo
    document.getElementsByName('api_key')[0].value = apiKey;
}

// Agregar botón para copiar API key
document.addEventListener('DOMContentLoaded', function() {
    // Buscar el campo de API key
    var apiKeyField = document.getElementsByName('api_key')[0];
    
    if (apiKeyField) {
        // Crear botón de copiar
        var copyButton = document.createElement('button');
        copyButton.innerText = 'Copiar';
        copyButton.className = 'btn btn-default';
        copyButton.type = 'button';
        copyButton.style.marginLeft = '10px';
        copyButton.onclick = function() {
            apiKeyField.select();
            document.execCommand('copy');
            alert('API key copiada al portapapeles');
        };
        
        // Insertar el botón después del campo
        apiKeyField.parentNode.insertBefore(copyButton, apiKeyField.nextSibling);
    }
});