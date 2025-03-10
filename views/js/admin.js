/**
 * Stock Sync Module Admin JavaScript
 *
 * @author    Expert PrestaShop Developer
 * @copyright 2025
 * @license   Commercial
 */

// Funciones para mostrar modales de confirmación
function showCleanLogsModal() {
    $('#cleanLogsModal').modal('show');
}

function showCleanQueueModal() {
    $('#cleanQueueModal').modal('show');
}

// Funciones para prueba de conexión
function testConnection(storeId, url) {
    $('#connectionTestModal').modal('show');
    $('#connectionTestResult').html('<div class="alert alert-info"><p><i class="icon-spinner icon-spin"></i> Testing connection...</p></div>');
    
    $.ajax({
        url: url,
        type: 'GET',
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                $('#connectionTestResult').html('<div class="alert alert-success"><p><i class="icon-check"></i> ' + response.message + '</p></div>');
            } else {
                $('#connectionTestResult').html('<div class="alert alert-danger"><p><i class="icon-times"></i> ' + response.message + '</p></div>');
            }
        },
        error: function() {
            $('#connectionTestResult').html('<div class="alert alert-danger"><p><i class="icon-times"></i> An error occurred during the connection test.</p></div>');
        }
    });
}

// Función para actualizar el panel de control en tiempo real
function refreshDashboardData() {
    if ($('#dashboard-stats-container').length) {
        $.ajax({
            url: stockSyncAjaxUrl,
            type: 'POST',
            dataType: 'json',
            data: {
                ajax: 1,
                action: 'dashboardData',
                subaction: 'get_stats'
            },
            success: function(response) {
                // Actualizar estadísticas
                updateStats(response);
            }
        });
    }
}

// Actualizar estadísticas del dashboard
function updateStats(data) {
    if (data.queue) {
        $('#queue-total').text(data.queue.total);
        $('#queue-pending').text(data.queue.pending);
        $('#queue-completed').text(data.queue.completed);
        $('#queue-failed').text(data.queue.failed);
    }
    
    if (data.logs) {
        $('#logs-total').text(data.logs.total);
        $('#logs-info').text(data.logs.info);
        $('#logs-warning').text(data.logs.warning);
        $('#logs-error').text(data.logs.error);
        $('#logs-conflict').text(data.logs.conflict);
    }
}

// Inicializar comportamientos al cargar la página
$(document).ready(function() {
    // Inicializar datepickers
    if ($.fn.datepicker) {
        $('.datepicker').datepicker({
            dateFormat: 'yy-mm-dd'
        });
    }
    
    // Mostrar/ocultar detalles de duplicaciones
    $('.show-duplicate-details').on('click', function(e) {
        e.preventDefault();
        var reference = $(this).data('reference');
        $('#details-' + reference).toggle();
    });
    
    // Actualizar dashboard periódicamente
    if ($('#dashboard-stats-container').length) {
        setInterval(refreshDashboardData, 60000); // Cada minuto
    }
    
    // Inicializar eventos de conexión
    $('.test-connection').on('click', function(e) {
        if (typeof testConnection === 'function') {
            e.preventDefault();
            var storeId = $(this).data('store-id');
            var url = $(this).attr('href');
            testConnection(storeId, url);
        }
    });
    
    // IMPORTANTE: Comentar o eliminar cualquier validación que impida seleccionar la misma tienda de origen y destino
    // Por ejemplo, algo como:
    /*
    $('select[name="target_store_id"]').on('change', function() {
        var sourceStoreId = $('select[name="source_store_id"]').val();
        var targetStoreId = $(this).val();
        
        if (sourceStoreId === targetStoreId) {
            alert('No se pueden asignar referencias de la tienda a sí misma.');
            $(this).val('');
        }
    });
    */
    
    // En su lugar, podríamos añadir un código que advierta pero permita continuar:
    $('select[name="target_store_id"]').on('change', function() {
        var sourceStoreId = $('select[name="source_store_id"]').val();
        var targetStoreId = $(this).val();
        
        if (sourceStoreId === targetStoreId) {
            if (!confirm('Está seleccionando la misma tienda como origen y destino. Esto puede ser útil para tiendas clonadas. ¿Desea continuar?')) {
                $(this).val('');
            }
        }
    });
});