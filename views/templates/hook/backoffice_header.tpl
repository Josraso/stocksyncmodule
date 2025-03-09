{*
* Stock Sync Module Backoffice Header Template
*
* @author    Expert PrestaShop Developer
* @copyright 2025
* @license   Commercial
*}

<script type="text/javascript">
    // Variables globales del módulo
    var stockSyncPath = '{$stock_sync_path}';
    var stockSyncToken = '{$stock_sync_token}';
    
    // Inicializar comportamientos cuando el DOM esté listo
    $(document).ready(function() {
        // Código específico para las páginas del módulo
        if ($('body').attr('id').indexOf('AdminStockSync') !== -1) {
            // Aquí puedes añadir funcionalidades globales para todas las páginas del módulo
            console.log('Stock Sync Module - BackOffice Initialized');
        }
    });
</script>

{* Estilos globales para todas las páginas del módulo *}
<style type="text/css">
    .icon-AdminStockSync:before {
        content: "\f1b3"; /* Icono de cubo/caja */
    }
    
    .badge.badge-info {
        background-color: #5bc0de;
    }
    
    .badge.badge-warning {
        background-color: #f0ad4e;
    }
    
    .badge.badge-danger {
        background-color: #d9534f;
    }
    
    .badge.badge-success {
        background-color: #5cb85c;
    }
    
    .badge.badge-primary {
        background-color: #337ab7;
    }
    
    /* Estilo para las estadísticas */
    .stat-item {
        text-align: center;
        margin-bottom: 10px;
    }
    
    .stat-value {
        font-size: 20px;
        font-weight: bold;
    }
    
    .stat-label {
        font-size: 12px;
        color: #777;
    }
</style>
