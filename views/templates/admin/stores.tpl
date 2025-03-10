{*
* Stock Sync Module Stores Template
*
* @author    Expert PrestaShop Developer
* @copyright 2025
* @license   Commercial
*}

<div class="panel">
    <div class="panel-heading">
        <i class="icon-building"></i> {l s='Connected Stores' mod='stocksyncmodule'}
    </div>
    <div class="panel-body">
        <div class="row">
            <div class="col-md-12">
                <div class="panel">
                    <div class="panel-heading">
                        <i class="icon-bar-chart"></i> {l s='Stores Statistics' mod='stocksyncmodule'}
                    </div>
                    <div class="panel-body">
                        <div class="row">
                            <div class="col-xs-12">
                                <div class="stat-item">
                                    <span class="stat-value">{$stats.total|intval}</span>
                                    <span class="stat-label">{l s='Total Stores' mod='stocksyncmodule'}</span>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-xs-3">
                                <div class="stat-item">
                                    <span class="stat-value badge badge-success">{$stats.active|intval}</span>
                                    <span class="stat-label">{l s='Active' mod='stocksyncmodule'}</span>
                                </div>
                            </div>
                            <div class="col-xs-3">
                                <div class="stat-item">
                                    <span class="stat-value badge badge-primary">{$stats.principal|intval}</span>
                                    <span class="stat-label">{l s='Principal' mod='stocksyncmodule'}</span>
                                </div>
                            </div>
                            <div class="col-xs-3">
                                <div class="stat-item">
                                    <span class="stat-value badge badge-info">{$stats.secundaria|intval}</span>
                                    <span class="stat-label">{l s='Secondary' mod='stocksyncmodule'}</span>
                                </div>
                            </div>
                            <div class="col-xs-3">
                                <div class="stat-item">
                                    <span class="stat-value badge badge-warning">{$stats.bidirectional|intval}</span>
                                    <span class="stat-label">{l s='Bidirectional' mod='stocksyncmodule'}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="row">
            <div class="col-md-12">
                <a href="{$link->getAdminLink('AdminStockSyncStores')}&addstock_sync_stores" class="btn btn-primary">
                    <i class="icon-plus"></i> {l s='Add New Store' mod='stocksyncmodule'}
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Modal para Test de Conexión -->
<div class="modal fade" id="connectionTestModal" tabindex="-1" role="dialog" aria-labelledby="connectionTestModalLabel">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                <h4 class="modal-title" id="connectionTestModalLabel">{l s='Connection Test Result' mod='stocksyncmodule'}</h4>
            </div>
            <div class="modal-body">
                <div id="connectionTestResult">
                    <div class="alert alert-info">
                        <p><i class="icon-spinner icon-spin"></i> {l s='Testing connection...' mod='stocksyncmodule'}</p>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">{l s='Close' mod='stocksyncmodule'}</button>
            </div>
        </div>
    </div>
</div>

<script type="text/javascript">
    // Código JavaScript para prueba de conexión
    $(document).ready(function() {
        $('.test-connection').on('click', function(e) {
            e.preventDefault();
            
            var storeId = $(this).data('store-id');
            var url = $(this).attr('href');
            
            $('#connectionTestModal').modal('show');
            $('#connectionTestResult').html('<div class="alert alert-info"><p><i class="icon-spinner icon-spin"></i> {l s='Testing connection...' mod='stocksyncmodule'}</p></div>');
            
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
                    $('#connectionTestResult').html('<div class="alert alert-danger"><p><i class="icon-times"></i> {l s='An error occurred during the connection test.' mod='stocksyncmodule'}</p></div>');
                }
            });
        });
    });
</script>
