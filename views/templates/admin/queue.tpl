{*
* Stock Sync Module Queue Template
*
* @author    Expert PrestaShop Developer
* @copyright 2025
* @license   Commercial
*}

<div class="panel">
    <div class="panel-heading">
        <i class="icon-refresh"></i> {l s='Synchronization Queue' mod='stocksyncmodule'}
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
                        <i class="icon-bar-chart"></i> {l s='Queue Statistics (Last 24 Hours)' mod='stocksyncmodule'}
                    </div>
                    <div class="panel-body">
                        <div class="row">
                            <div class="col-xs-12">
                                <div class="stat-item">
                                    <span class="stat-value">{$stats.total|intval}</span>
                                    <span class="stat-label">{l s='Total Queue Items' mod='stocksyncmodule'}</span>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-xs-3">
                                <div class="stat-item">
                                    <span class="stat-value badge badge-warning">{$stats.pending|intval}</span>
                                    <span class="stat-label">{l s='Pending' mod='stocksyncmodule'}</span>
                                </div>
                            </div>
                            <div class="col-xs-3">
                                <div class="stat-item">
                                    <span class="stat-value badge badge-info">{$stats.processing|intval}</span>
                                    <span class="stat-label">{l s='Processing' mod='stocksyncmodule'}</span>
                                </div>
                            </div>
                            <div class="col-xs-3">
                                <div class="stat-item">
                                    <span class="stat-value badge badge-success">{$stats.completed|intval}</span>
                                    <span class="stat-label">{l s='Completed' mod='stocksyncmodule'}</span>
                                </div>
                            </div>
                            <div class="col-xs-3">
                                <div class="stat-item">
                                    <span class="stat-value badge badge-danger">{$stats.failed|intval}</span>
                                    <span class="stat-label">{l s='Failed' mod='stocksyncmodule'}</span>
                                </div>
                            </div>
                        </div>
                        {if $stats.avg_completion_time > 0}
                        <div class="row">
                            <div class="col-xs-12">
                                <div class="stat-item">
                                    <span class="stat-value">{$stats.avg_completion_time|round:2}</span>
                                    <span class="stat-label">{l s='Average completion time (seconds)' mod='stocksyncmodule'}</span>
                                </div>
                            </div>
                        </div>
                        {/if}
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="panel">
                    <div class="panel-heading">
                        <i class="icon-wrench"></i> {l s='Queue Actions' mod='stocksyncmodule'}
                    </div>
                    <div class="panel-body">
                        <div class="row">
                            <div class="col-xs-4">
                                <div class="form-group">
                                    <a href="{$process_url}" class="btn btn-primary btn-block">
                                        <i class="icon-cogs"></i> {l s='Process Queue Now' mod='stocksyncmodule'}
                                    </a>
                                </div>
                            </div>
                            <div class="col-xs-4">
                                <div class="form-group">
                                    <a href="{$retry_url}" class="btn btn-default btn-block">
                                        <i class="icon-refresh"></i> {l s='Retry Failed Items' mod='stocksyncmodule'}
                                    </a>
                                </div>
                            </div>
                            <div class="col-xs-4">
                                <div class="form-group">
                                    <button type="button" class="btn btn-danger btn-block" onclick="showCleanQueueModal()">
                                        <i class="icon-trash"></i> {l s='Clean Old Items' mod='stocksyncmodule'}
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal para limpiar elementos antiguos de la cola -->
<div class="modal fade" id="cleanQueueModal" tabindex="-1" role="dialog" aria-labelledby="cleanQueueModalLabel">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                <h4 class="modal-title" id="cleanQueueModalLabel">{l s='Clean Old Queue Items' mod='stocksyncmodule'}</h4>
            </div>
            <form action="{$clean_url}" method="post">
                <div class="modal-body">
                    <div class="form-group">
                        <label for="clean_queue_hours">{l s='Keep items newer than:' mod='stocksyncmodule'}</label>
                        <div class="input-group">
                            <input type="number" id="clean_queue_hours" name="hours" class="form-control" value="24" min="1" max="720">
                            <span class="input-group-addon">{l s='hours' mod='stocksyncmodule'}</span>
                        </div>
                        <p class="help-block">{l s='Completed, failed, and skipped items older than this will be permanently deleted.' mod='stocksyncmodule'}</p>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-default" data-dismiss="modal">{l s='Cancel' mod='stocksyncmodule'}</button>
                    <button type="submit" class="btn btn-danger" name="cleanQueue">{l s='Delete Old Items' mod='stocksyncmodule'}</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script type="text/javascript">
    function showCleanQueueModal() {
        $('#cleanQueueModal').modal('show');
    }
</script>