{*
* Stock Sync Module Logs Template
*
* @author    Expert PrestaShop Developer
* @copyright 2025
* @license   Commercial
*}

<div class="panel">
    <div class="panel-heading">
        <i class="icon-file-text"></i> {l s='Synchronization Logs' mod='stocksyncmodule'}
        <div class="panel-heading-action">
            <a href="{$dashboard_link|escape:'html':'UTF-8'}" class="btn btn-default">
                <i class="icon-arrow-left"></i> {l s='Back to Dashboard' mod='stocksyncmodule'}
            </a>
        </div>
    </div>
    <div class="panel-body">
        <div class="row">
            <div class="col-md-6">
                <div class="panel">
                    <div class="panel-heading">
                        <i class="icon-bar-chart"></i> {l s='Log Statistics (Last 24 Hours)' mod='stocksyncmodule'}
                    </div>
                    <div class="panel-body">
                        <div class="row">
                            <div class="col-xs-12">
                                <div class="stat-item">
                                    <span class="stat-value">{$stats.total|intval}</span>
                                    <span class="stat-label">{l s='Total Log Entries' mod='stocksyncmodule'}</span>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-xs-3">
                                <div class="stat-item">
                                    <span class="stat-value badge badge-info">{$stats.info|intval}</span>
                                    <span class="stat-label">{l s='Info Messages' mod='stocksyncmodule'}</span>
                                </div>
                            </div>
                            <div class="col-xs-3">
                                <div class="stat-item">
                                    <span class="stat-value badge badge-warning">{$stats.warning|intval}</span>
                                    <span class="stat-label">{l s='Warnings' mod='stocksyncmodule'}</span>
                                </div>
                            </div>
                            <div class="col-xs-3">
                                <div class="stat-item">
                                    <span class="stat-value badge badge-danger">{$stats.error|intval}</span>
                                    <span class="stat-label">{l s='Errors' mod='stocksyncmodule'}</span>
                                </div>
                            </div>
                            <div class="col-xs-3">
                                <div class="stat-item">
                                    <span class="stat-value badge badge-primary">{$stats.conflict|intval}</span>
                                    <span class="stat-label">{l s='Conflicts' mod='stocksyncmodule'}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="panel">
                    <div class="panel-heading">
                        <i class="icon-wrench"></i> {l s='Log Maintenance' mod='stocksyncmodule'}
                    </div>
                    <div class="panel-body">
                        <div class="row">
                            <div class="col-xs-6">
                                <div class="form-group">
                                    <a href="{$export_logs_action}" class="btn btn-default btn-block">
                                        <i class="icon-download"></i> {l s='Export Logs to CSV' mod='stocksyncmodule'}
                                    </a>
                                </div>
                            </div>
                            <div class="col-xs-6">
                                <div class="form-group">
                                    <button type="button" class="btn btn-danger btn-block" onclick="showCleanLogsModal()">
                                        <i class="icon-trash"></i> {l s='Clean Old Logs' mod='stocksyncmodule'}
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        {if isset($conflicts) && $conflicts|count > 0}
        <div class="panel">
            <div class="panel-heading">
                <i class="icon-warning"></i> {l s='Recent Conflicts' mod='stocksyncmodule'}
            </div>
            <div class="panel-body">
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>{l s='Date' mod='stocksyncmodule'}</th>
                                <th>{l s='Reference' mod='stocksyncmodule'}</th>
                                <th>{l s='Message' mod='stocksyncmodule'}</th>
                            </tr>
                        </thead>
                        <tbody>
                            {foreach from=$conflicts item=conflict}
                                <tr>
                                    <td>{$conflict.created_at}</td>
                                    <td>{$conflict.reference}</td>
                                    <td>{$conflict.message}</td>
                                </tr>
                            {/foreach}
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        {/if}
    </div>
</div>

<!-- Modal para limpiar logs antiguos -->
<div class="modal fade" id="cleanLogsModal" tabindex="-1" role="dialog" aria-labelledby="cleanLogsModalLabel">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                <h4 class="modal-title" id="cleanLogsModalLabel">{l s='Clean Old Logs' mod='stocksyncmodule'}</h4>
            </div>
            <form action="{$clean_logs_action}" method="post">
                <div class="modal-body">
                    <div class="form-group">
                        <label for="clean_logs_hours">{if $use_hours}{l s='Keep logs newer than:' mod='stocksyncmodule'}{else}{l s='Keep logs newer than:' mod='stocksyncmodule'}{/if}</label>
                        <div class="input-group">
                            <input type="number" id="clean_logs_hours" name="hours" class="form-control" value="24" min="1" max="720">
                            <span class="input-group-addon">{if $use_hours}{l s='hours' mod='stocksyncmodule'}{else}{l s='days' mod='stocksyncmodule'}{/if}</span>
                        </div>
                        <p class="help-block">{l s='Logs older than this will be permanently deleted.' mod='stocksyncmodule'}</p>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-default" data-dismiss="modal">{l s='Cancel' mod='stocksyncmodule'}</button>
                    <button type="submit" class="btn btn-danger" name="cleanLogs">{l s='Delete Old Logs' mod='stocksyncmodule'}</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script type="text/javascript">
    function showCleanLogsModal() {
        $('#cleanLogsModal').modal('show');
    }
</script>