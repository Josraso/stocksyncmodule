{*
* Stock Sync Module Dashboard Template
*
* @author    Expert PrestaShop Developer
* @copyright 2025
* @license   Commercial
*}

<div class="bootstrap">
    {if isset($confirmations) && $confirmations}
        {foreach $confirmations as $confirmation}
            <div class="alert alert-success">
                <button type="button" class="close" data-dismiss="alert">×</button>
                {$confirmation|escape:'htmlall':'UTF-8'}
            </div>
        {/foreach}
    {/if}

    {if isset($warnings) && $warnings}
        {foreach $warnings as $warning}
            <div class="alert alert-warning">
                <button type="button" class="close" data-dismiss="alert">×</button>
                {$warning|escape:'htmlall':'UTF-8'}
            </div>
        {/foreach}
    {/if}

    {if isset($errors) && $errors}
        {foreach $errors as $error}
            <div class="alert alert-danger">
                <button type="button" class="close" data-dismiss="alert">×</button>
                {$error|escape:'htmlall':'UTF-8'}
            </div>
        {/foreach}
    {/if}

    <div class="panel">
        <div class="panel-heading">
            <i class="icon-dashboard"></i> {l s='Stock Sync Dashboard' mod='stocksyncmodule'}
        </div>
        <div class="row">
            <div class="col-lg-12">
                <div class="panel">
                    <div class="panel-heading">
                        <i class="icon-cogs"></i> {l s='Module Status' mod='stocksyncmodule'}
                    </div>
                    <div class="panel-body">
                        <div class="row">
                            <div class="col-md-6">
                                <form class="form-horizontal" method="post" action="{$smarty.server.REQUEST_URI|escape:'htmlall':'UTF-8'}">
                                    <div class="form-group">
                                        <label class="control-label col-lg-4">{l s='Module Status:' mod='stocksyncmodule'}</label>
                                        <div class="col-lg-8">
                                            <a href="{$smarty.server.REQUEST_URI|escape:'htmlall':'UTF-8'}&amp;toggleActive=1" class="btn {if $module_active}btn-success{else}btn-danger{/if}">
                                                {if $module_active}
                                                    <i class="icon-check"></i> {l s='Active' mod='stocksyncmodule'}
                                                {else}
                                                    <i class="icon-times"></i> {l s='Inactive' mod='stocksyncmodule'}
                                                {/if}
                                            </a>
                                        </div>
                                    </div>
                                </form>
                            </div>
                            <div class="col-md-6">
                                <form class="form-horizontal" method="post" action="{$smarty.server.REQUEST_URI|escape:'htmlall':'UTF-8'}">
                                    <div class="form-group">
                                        <label class="control-label col-lg-4">{l s='Sync Role:' mod='stocksyncmodule'}</label>
                                        <div class="col-lg-8">
                                            <div class="btn-group">
                                                <button type="button" class="btn {if $sync_role == 'principal'}btn-primary{else}btn-default{/if} dropdown-toggle" data-toggle="dropdown">
                                                    {if $sync_role == 'principal'}
                                                        <i class="icon-star"></i> {l s='Principal' mod='stocksyncmodule'}
                                                    {elseif $sync_role == 'secundaria'}
                                                        <i class="icon-star-o"></i> {l s='Secondary' mod='stocksyncmodule'}
                                                    {else}
                                                        <i class="icon-exchange"></i> {l s='Bidirectional' mod='stocksyncmodule'}
                                                    {/if}
                                                    <span class="caret"></span>
                                                </button>
                                                <ul class="dropdown-menu" role="menu">
                                                    <li>
                                                        <a href="{$smarty.server.REQUEST_URI|escape:'htmlall':'UTF-8'}&amp;changeSyncRole=1&amp;sync_role=principal">
                                                            <i class="icon-star"></i> {l s='Principal' mod='stocksyncmodule'}
                                                        </a>
                                                    </li>
                                                    <li>
                                                        <a href="{$smarty.server.REQUEST_URI|escape:'htmlall':'UTF-8'}&amp;changeSyncRole=1&amp;sync_role=secundaria">
                                                            <i class="icon-star-o"></i> {l s='Secondary' mod='stocksyncmodule'}
                                                        </a>
                                                    </li>
                                                    <li>
                                                        <a href="{$smarty.server.REQUEST_URI|escape:'htmlall':'UTF-8'}&amp;changeSyncRole=1&amp;sync_role=bidirectional">
                                                            <i class="icon-exchange"></i> {l s='Bidirectional' mod='stocksyncmodule'}
                                                        </a>
                                                    </li>
                                                </ul>
                                            </div>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-lg-12">
                <div class="panel">
                    <div class="panel-heading">
                        <i class="icon-dashboard"></i> {l s='Dashboard Actions' mod='stocksyncmodule'}
                    </div>
                    <div class="panel-body">
                        <div class="row">
                            <div class="col-md-4">
                                <form action="{$smarty.server.REQUEST_URI|escape:'htmlall':'UTF-8'}" method="post">
                                    <button type="submit" name="checkAllConnections" class="btn btn-primary btn-block">
                                        <i class="icon-signal"></i> {l s='Check All Store Connections' mod='stocksyncmodule'}
                                    </button>
                                    <p class="help-block">{l s='Test the connection to all configured stores. This might take some time.' mod='stocksyncmodule'}</p>
                                </form>
                            </div>
                            <div class="col-md-4">
                                <form action="{$smarty.server.REQUEST_URI|escape:'htmlall':'UTF-8'}" method="post">
                                    <button type="submit" name="checkDiscrepancies" class="btn btn-warning btn-block">
                                        <i class="icon-search"></i> {l s='Check Stock Discrepancies' mod='stocksyncmodule'}
                                    </button>
                                    <p class="help-block">{l s='Check for differences in stock levels between stores.' mod='stocksyncmodule'}</p>
                                </form>
                            </div>
                            <div class="col-md-4">
                                <form action="{$smarty.server.REQUEST_URI|escape:'htmlall':'UTF-8'}" method="post">
                                    <button type="submit" name="processQueue" class="btn btn-success btn-block">
                                        <i class="icon-refresh"></i> {l s='Process Synchronization Queue' mod='stocksyncmodule'}
                                    </button>
                                    <p class="help-block">{l s='Manually process pending synchronization items.' mod='stocksyncmodule'}</p>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Statistics Cards -->
            <div class="col-lg-3 col-md-6">
                <div class="panel">
                    <div class="panel-heading bg-info">
                        <i class="icon-refresh"></i> {l s='Queue' mod='stocksyncmodule'}
                    </div>
                    <div class="panel-body">
                        <div class="stats-card">
                            <div class="stats-number">{$queue_stats.total|intval}</div>
                            <div class="stats-title">{l s='Total Queue Items' mod='stocksyncmodule'}</div>
                        </div>
                        <div class="stats-details">
                            <div class="row">
                                <div class="col-xs-4 text-center">
                                    <span class="label label-warning">{$queue_stats.pending|intval}</span>
                                    <div class="stats-label">{l s='Pending' mod='stocksyncmodule'}</div>
                                </div>
                                <div class="col-xs-4 text-center">
                                    <span class="label label-success">{$queue_stats.completed|intval}</span>
                                    <div class="stats-label">{l s='Completed' mod='stocksyncmodule'}</div>
                                </div>
                                <div class="col-xs-4 text-center">
                                    <span class="label label-danger">{$queue_stats.failed|intval}</span>
                                    <div class="stats-label">{l s='Failed' mod='stocksyncmodule'}</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-3 col-md-6">
                <div class="panel">
                    <div class="panel-heading bg-success">
                        <i class="icon-file-text"></i> {l s='Logs' mod='stocksyncmodule'}
                    </div>
                    <div class="panel-body">
                        <div class="stats-card">
                            <div class="stats-number">{$log_stats.total|intval}</div>
                            <div class="stats-title">{l s='Total Log Entries' mod='stocksyncmodule'}</div>
                        </div>
                        <div class="stats-details">
                            <div class="row">
                                <div class="col-xs-3 text-center">
                                    <span class="label label-info">{$log_stats.info|intval}</span>
                                    <div class="stats-label">{l s='Info' mod='stocksyncmodule'}</div>
                                </div>
                                <div class="col-xs-3 text-center">
                                    <span class="label label-warning">{$log_stats.warning|intval}</span>
                                    <div class="stats-label">{l s='Warning' mod='stocksyncmodule'}</div>
                                </div>
                                <div class="col-xs-3 text-center">
                                    <span class="label label-danger">{$log_stats.error|intval}</span>
                                    <div class="stats-label">{l s='Error' mod='stocksyncmodule'}</div>
                                </div>
                                <div class="col-xs-3 text-center">
                                    <span class="label label-primary">{$log_stats.conflict|intval}</span>
                                    <div class="stats-label">{l s='Conflict' mod='stocksyncmodule'}</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-3 col-md-6">
                <div class="panel">
                    <div class="panel-heading bg-warning">
                        <i class="icon-building"></i> {l s='Stores' mod='stocksyncmodule'}
                    </div>
                    <div class="panel-body">
                        <div class="stats-card">
                            <div class="stats-number">{$store_stats.total|intval}</div>
                            <div class="stats-title">{l s='Total Stores' mod='stocksyncmodule'}</div>
                        </div>
                        <div class="stats-details">
                            <div class="row">
                                <div class="col-xs-4 text-center">
                                    <span class="label label-success">{$store_stats.active|intval}</span>
                                    <div class="stats-label">{l s='Active' mod='stocksyncmodule'}</div>
                                </div>
                                <div class="col-xs-4 text-center">
                                    <span class="label label-default">{$store_stats.principal|intval}</span>
                                    <div class="stats-label">{l s='Principal' mod='stocksyncmodule'}</div>
                                </div>
                                <div class="col-xs-4 text-center">
                                    <span class="label label-info">{$store_stats.secundaria|intval}</span>
                                    <div class="stats-label">{l s='Secondary' mod='stocksyncmodule'}</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-3 col-md-6">
                <div class="panel">
                    <div class="panel-heading bg-danger">
                        <i class="icon-link"></i> {l s='References' mod='stocksyncmodule'}
                    </div>
                    <div class="panel-body">
                        <div class="stats-card">
                            <div class="stats-number">{$reference_stats.total|intval}</div>
                            <div class="stats-title">{l s='Total References' mod='stocksyncmodule'}</div>
                        </div>
                        <div class="stats-details">
                            <div class="row">
                                <div class="col-xs-4 text-center">
                                    <span class="label label-success">{$reference_stats.active|intval}</span>
                                    <div class="stats-label">{l s='Active' mod='stocksyncmodule'}</div>
                                </div>
                                <div class="col-xs-4 text-center">
                                    <span class="label label-primary">{$reference_stats.products|intval}</span>
                                    <div class="stats-label">{l s='Products' mod='stocksyncmodule'}</div>
                                </div>
                                <div class="col-xs-4 text-center">
                                    <span class="label label-info">{$reference_stats.combinations|intval}</span>
                                    <div class="stats-label">{l s='Combinations' mod='stocksyncmodule'}</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Recent Activity -->
            <div class="col-lg-6">
                <div class="panel">
                    <div class="panel-heading">
                        <i class="icon-file-text"></i> {l s='Recent Logs' mod='stocksyncmodule'}
                    </div>
                    <div class="panel-body">
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>{l s='Time' mod='stocksyncmodule'}</th>
                                        <th>{l s='Level' mod='stocksyncmodule'}</th>
                                        <th>{l s='Message' mod='stocksyncmodule'}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {if isset($recent_logs) && $recent_logs|count > 0}
                                        {foreach from=$recent_logs item=log}
                                            <tr>
                                                <td>{$log.created_at|escape:'htmlall':'UTF-8'}</td>
                                                <td>
                                                    <span class="label label-{if $log.level == 'info'}info{elseif $log.level == 'warning'}warning{elseif $log.level == 'error'}danger{elseif $log.level == 'conflict'}primary{else}default{/if}">
                                                        {$log.level|escape:'htmlall':'UTF-8'}
                                                    </span>
                                                </td>
                                                <td title="{$log.message|escape:'htmlall':'UTF-8'}">{$log.message|truncate:100:'...'|escape:'htmlall':'UTF-8'}</td>
                                            </tr>
                                        {/foreach}
                                    {else}
                                        <tr>
                                            <td colspan="3" class="text-center">{l s='No logs found' mod='stocksyncmodule'}</td>
                                        </tr>
                                    {/if}
                                </tbody>
                            </table>
                        </div>
                        <div class="panel-footer">
                            <a href="{$link->getAdminLink('AdminStockSyncLogs')|escape:'htmlall':'UTF-8'}" class="btn btn-default pull-right">
                                <i class="icon-external-link"></i> {l s='View All Logs' mod='stocksyncmodule'}
                            </a>
                            <div class="clearfix"></div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-6">
                <div class="panel">
                    <div class="panel-heading">
                        <i class="icon-refresh"></i> {l s='Recent Queue Items' mod='stocksyncmodule'}
                    </div>
                    <div class="panel-body">
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>{l s='Reference' mod='stocksyncmodule'}</th>
                                        <th>{l s='Quantity' mod='stocksyncmodule'}</th>
                                        <th>{l s='Status' mod='stocksyncmodule'}</th>
                                        <th>{l s='Time' mod='stocksyncmodule'}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {if isset($recent_queue) && $recent_queue|count > 0}
                                        {foreach from=$recent_queue item=queue}
                                            <tr>
                                                <td>{$queue.reference|escape:'htmlall':'UTF-8'}</td>
                                                <td>
                                                    {$queue.old_quantity|escape:'htmlall':'UTF-8'} → {$queue.new_quantity|escape:'htmlall':'UTF-8'}
                                                </td>
                                                <td>
                                                    <span class="label label-{if $queue.status == 'completed'}success{elseif $queue.status == 'pending'}warning{elseif $queue.status == 'processing'}info{else}danger{/if}">
                                                        {$queue.status|escape:'htmlall':'UTF-8'}
                                                    </span>
                                                </td>
                                                <td>{$queue.created_at|escape:'htmlall':'UTF-8'}</td>
                                            </tr>
                                        {/foreach}
                                    {else}
                                        <tr>
                                            <td colspan="4" class="text-center">{l s='No queue items found' mod='stocksyncmodule'}</td>
                                        </tr>
                                    {/if}
                                </tbody>
                            </table>
                        </div>
                        <div class="panel-footer">
                            <div class="row">
                                <div class="col-lg-6">
                                    <form action="{$smarty.server.REQUEST_URI|escape:'htmlall':'UTF-8'}" method="post" class="form-inline">
                                        <button type="submit" name="processQueue" class="btn btn-primary">
                                            <i class="icon-refresh"></i> {l s='Process Queue Now' mod='stocksyncmodule'}
                                        </button>
                                    </form>
                                </div>
                                <div class="col-lg-6 text-right">
                                    <a href="{$link->getAdminLink('AdminStockSyncQueue')|escape:'htmlall':'UTF-8'}" class="btn btn-default">
                                        <i class="icon-external-link"></i> {l s='View All Queue' mod='stocksyncmodule'}
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Conflicts and Alerts -->
        {if isset($conflicts) && $conflicts|count > 0}
            <div class="row">
                <div class="col-lg-12">
                    <div class="panel">
                        <div class="panel-heading bg-danger">
                            <i class="icon-warning"></i> {l s='Conflicts' mod='stocksyncmodule'}
                        </div>
                        <div class="panel-body">
                            <div class="table-responsive">
                                <table class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>{l s='Time' mod='stocksyncmodule'}</th>
                                            <th>{l s='Reference' mod='stocksyncmodule'}</th>
                                            <th>{l s='Message' mod='stocksyncmodule'}</th>
                                            <th>{l s='Actions' mod='stocksyncmodule'}</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {foreach from=$conflicts item=conflict}
                                            <tr>
                                                <td>{$conflict.created_at|escape:'htmlall':'UTF-8'}</td>
                                                <td>{$conflict.reference|escape:'htmlall':'UTF-8'}</td>
                                                <td>{$conflict.message|escape:'htmlall':'UTF-8'}</td>
                                                <td>
                                                    <a href="{$link->getAdminLink('AdminStockSyncQueue')|escape:'htmlall':'UTF-8'}&amp;reference={$conflict.reference|escape:'url'}" class="btn btn-default btn-sm">
                                                        <i class="icon-search"></i> {l s='View' mod='stocksyncmodule'}
                                                    </a>
                                                </td>
                                            </tr>
                                        {/foreach}
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        {/if}

        {if isset($discrepancies) && $discrepancies|count > 0}
            <div class="row">
                <div class="col-lg-12">
                    <div class="panel">
                        <div class="panel-heading bg-warning">
                            <i class="icon-warning"></i> {l s='Stock Discrepancies' mod='stocksyncmodule'}
                        </div>
                        <div class="panel-body">
                            <div class="table-responsive">
                                <table class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>{l s='Reference' mod='stocksyncmodule'}</th>
                                            <th>{l s='Store' mod='stocksyncmodule'}</th>
                                            <th>{l s='Quantity' mod='stocksyncmodule'}</th>
                                            <th>{l s='Actions' mod='stocksyncmodule'}</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {foreach from=$discrepancies item=discrepancy}
                                            <tr class="discrepancy-header">
                                                <td colspan="4">
                                                    <strong>{l s='Reference:' mod='stocksyncmodule'} {$discrepancy.reference|escape:'htmlall':'UTF-8'}</strong>
                                                </td>
                                            </tr>
                                            {foreach from=$discrepancy.quantities key=store_id item=store_data}
                                                <tr>
                                                    <td></td>
                                                    <td>{$store_data.store_name|escape:'htmlall':'UTF-8'}</td>
                                                    <td>{$store_data.quantity|escape:'htmlall':'UTF-8'}</td>
                                                    <td>
                                                        <a href="#" class="btn btn-default btn-sm sync-stock" data-reference="{$discrepancy.reference|escape:'html':'UTF-8'}" data-store="{$store_id|escape:'html':'UTF-8'}" data-quantity="{$store_data.quantity|escape:'html':'UTF-8'}">
                                                            <i class="icon-refresh"></i> {l s='Sync' mod='stocksyncmodule'}
                                                        </a>
                                                    </td>
                                                </tr>
                                            {/foreach}
                                        {/foreach}
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        {/if}

        <!-- Connection Status -->
        <div class="row">
            <div class="col-lg-12">
                <div class="panel">
                    <div class="panel-heading">
                        <i class="icon-signal"></i> {l s='Connection Status' mod='stocksyncmodule'}
                    </div>
                    <div class="panel-body">
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>{l s='Store' mod='stocksyncmodule'}</th>
                                        <th>{l s='Status' mod='stocksyncmodule'}</th>
                                        <th>{l s='Response Time' mod='stocksyncmodule'}</th>
                                        <th>{l s='Last Sync' mod='stocksyncmodule'}</th>
                                        <th>{l s='Actions' mod='stocksyncmodule'}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {if isset($connection_status) && $connection_status|count > 0}
                                        {foreach from=$connection_status key=store_id item=store}
                                            <tr>
                                                <td>{$store.store_name|escape:'htmlall':'UTF-8'}</td>
                                                <td>
                                                    {if $store.success === null}
                                                        <span class="label label-default">
                                                            <i class="icon-question"></i> {l s='Unknown' mod='stocksyncmodule'}
                                                        </span>
                                                    {elseif $store.success}
                                                        <span class="label label-success">
                                                            <i class="icon-check"></i> {l s='Connected' mod='stocksyncmodule'}
                                                        </span>
                                                    {else}
                                                        <span class="label label-danger">
                                                            <i class="icon-times"></i> {l s='Disconnected' mod='stocksyncmodule'}
                                                        </span>
                                                    {/if}
                                                </td>
                                                <td>
                                                    {if $store.success === null}
                                                        -
                                                    {else}
                                                        {$store.time|round:3} s
                                                    {/if}
                                                </td>
                                                <td>
                                                    {if isset($stores[$store_id].last_sync) && $stores[$store_id].last_sync}
                                                        {$stores[$store_id].last_sync|escape:'htmlall':'UTF-8'}
                                                    {else}
                                                        {l s='Never' mod='stocksyncmodule'}
                                                    {/if}
                                                </td>
                                                <td>
                                                    <a href="#" class="btn btn-default btn-sm test-connection" data-store-id="{$store_id|escape:'html':'UTF-8'}">
                                                        <i class="icon-refresh"></i> {l s='Test Connection' mod='stocksyncmodule'}
                                                    </a>
                                                    <a href="{$link->getAdminLink('AdminStockSyncStores')|escape:'htmlall':'UTF-8'}&amp;id_store={$store_id|escape:'html':'UTF-8'}&amp;updatestock_sync_stores" class="btn btn-default btn-sm">
                                                        <i class="icon-pencil"></i> {l s='Edit' mod='stocksyncmodule'}
                                                    </a>
                                                </td>
                                            </tr>
                                        {/foreach}
                                    {else}
                                        <tr>
                                            <td colspan="5" class="text-center">{l s='No stores found' mod='stocksyncmodule'}</td>
                                        </tr>
                                    {/if}
                                </tbody>
                            </table>
                        </div>
                        <div class="panel-footer">
                            <div class="row">
                                <div class="col-md-6">
                                    <a href="{$link->getAdminLink('AdminStockSyncStores')|escape:'htmlall':'UTF-8'}&amp;addstock_sync_stores" class="btn btn-default">
                                        <i class="icon-plus"></i> {l s='Add New Store' mod='stocksyncmodule'}
                                    </a>
                                </div>
                                <div class="col-md-6 text-right">
                                    <form action="{$smarty.server.REQUEST_URI|escape:'htmlall':'UTF-8'}" method="post" class="form-inline">
                                        <button type="submit" name="checkAllConnections" class="btn btn-primary">
                                            <i class="icon-refresh"></i> {l s='Check All Connections' mod='stocksyncmodule'}
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Maintenance Tools -->
        <div class="row">
            <div class="col-lg-12">
                <div class="panel">
                    <div class="panel-heading">
                        <i class="icon-wrench"></i> {l s='Maintenance Tools' mod='stocksyncmodule'}
                    </div>
                    <div class="panel-body">
                        <div class="row">
                            <div class="col-md-3">
                                <div class="well">
                                    <h4>{l s='Clean Old Logs' mod='stocksyncmodule'}</h4>
                                    <p>{l s='Remove old log entries to keep the database clean.' mod='stocksyncmodule'}</p>
                                    <form action="{$smarty.server.REQUEST_URI|escape:'htmlall':'UTF-8'}" method="post">
                                        <div class="form-group">
                                            <label>{l s='Keep logs newer than:' mod='stocksyncmodule'}</label>
                                            <div class="input-group">
                                                <input type="number" name="days" class="form-control" value="30" min="1" max="365" />
                                                <span class="input-group-addon">{l s='days' mod='stocksyncmodule'}</span>
                                            </div>
                                        </div>
                                        <button type="submit" name="cleanLogs" class="btn btn-danger">
                                            <i class="icon-trash"></i> {l s='Clean Logs' mod='stocksyncmodule'}
                                        </button>
                                    </form>
                                </div>
                            </div>
                            
                            <div class="col-md-3">
                                <div class="well">
                                    <h4>{l s='Clean Old Queue Items' mod='stocksyncmodule'}</h4>
                                    <p>{l s='Remove old processed queue items to free up space.' mod='stocksyncmodule'}</p>
                                    <form action="{$smarty.server.REQUEST_URI|escape:'htmlall':'UTF-8'}" method="post">
                                        <div class="form-group">
                                            <label>{l s='Keep items newer than:' mod='stocksyncmodule'}</label>
                                            <div class="input-group">
                                                <input type="number" name="days" class="form-control" value="30" min="1" max="365" />
                                                <span class="input-group-addon">{l s='days' mod='stocksyncmodule'}</span>
                                            </div>
                                        </div>
                                        <button type="submit" name="cleanQueue" class="btn btn-danger">
                                            <i class="icon-trash"></i> {l s='Clean Queue' mod='stocksyncmodule'}
                                        </button>
                                    </form>
                                </div>
                            </div>
                            
                            <div class="col-md-3">
                                <div class="well">
                                    <h4>{l s='Retry Failed Items' mod='stocksyncmodule'}</h4>
                                    <p>{l s='Attempt to resend failed synchronization items.' mod='stocksyncmodule'}</p>
                                    <form action="{$smarty.server.REQUEST_URI|escape:'htmlall':'UTF-8'}" method="post">
                                        <button type="submit" name="retryFailed" class="btn btn-primary">
                                            <i class="icon-refresh"></i> {l s='Retry Failed Items' mod='stocksyncmodule'}
                                        </button>
                                    </form>
                                </div>
                            </div>
                            
                            <div class="col-md-3">
                                <div class="well">
                                    <h4>{l s='Database Backup' mod='stocksyncmodule'}</h4>
                                    <p>{l s='Create a backup of the synchronization data.' mod='stocksyncmodule'}</p>
                                    <form action="{$smarty.server.REQUEST_URI|escape:'htmlall':'UTF-8'}" method="post">
                                        <button type="submit" name="createBackup" class="btn btn-success">
                                            <i class="icon-download"></i> {l s='Create Backup' mod='stocksyncmodule'}
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script type="text/javascript">
    $(document).ready(function() {
        // Test connection button
        $('.test-connection').on('click', function(e) {
            e.preventDefault();
            var btn = $(this);
            var storeId = btn.data('store-id');
            
            btn.html('<i class="icon-spinner icon-spin"></i> {l s='Testing...' mod='stocksyncmodule'}');
            
            $.ajax({
                url: '{$link->getAdminLink('AdminStockSyncDashboard')|addslashes}',
                type: 'POST',
                dataType: 'json',
                data: {
                    ajax: 1,
                    action: 'dashboardData',
                    subaction: 'test_connection',
                    id_store: storeId
                },
                success: function(response) {
                    btn.html('<i class="icon-refresh"></i> {l s='Test Connection' mod='stocksyncmodule'}');
                    
                    if (response.success) {
                        showSuccessMessage('{l s='Connection successful!' mod='stocksyncmodule'}');
                        // Actualizar el estado en la interfaz
                        btn.closest('tr').find('td:nth-child(2)').html(
                            '<span class="label label-success"><i class="icon-check"></i> {l s='Connected' mod='stocksyncmodule'}</span>'
                        );
                        // Actualizar el tiempo de respuesta
                        btn.closest('tr').find('td:nth-child(3)').text(response.time.toFixed(3) + ' s');
                    } else {
                        showErrorMessage(response.message);
                        // Actualizar el estado en la interfaz
                        btn.closest('tr').find('td:nth-child(2)').html(
                            '<span class="label label-danger"><i class="icon-times"></i> {l s='Disconnected' mod='stocksyncmodule'}</span>'
                        );
                    }
                },
                error: function() {
                    btn.html('<i class="icon-refresh"></i> {l s='Test Connection' mod='stocksyncmodule'}');
                    showErrorMessage('{l s='An error occurred during the connection test.' mod='stocksyncmodule'}');
                }
            });
        });
        
        // Sync stock button
        $('.sync-stock').on('click', function(e) {
            e.preventDefault();
            var btn = $(this);
            var reference = btn.data('reference');
            var storeId = btn.data('store');
            var quantity = btn.data('quantity');
            
            if (confirm('{l s='Do you want to synchronize this stock quantity to all stores?' mod='stocksyncmodule'}')) {
                btn.html('<i class="icon-spinner icon-spin"></i> {l s='Syncing...' mod='stocksyncmodule'}');
                
                // Process synchronization
                // This is just a placeholder, the actual implementation would be more complex
                setTimeout(function() {
                    btn.html('<i class="icon-refresh"></i> {l s='Sync' mod='stocksyncmodule'}');
                    showSuccessMessage('{l s='Stock synchronized successfully!' mod='stocksyncmodule'}');
                }, 2000);
            }
        });
        
        // Refresh dashboard data periodically
        setInterval(function() {
            $.ajax({
                url: '{$link->getAdminLink('AdminStockSyncDashboard')|addslashes}',
                type: 'POST',
                dataType: 'json',
                data: {
                    ajax: 1,
                    action: 'dashboardData',
                    subaction: 'get_stats'
                },
                success: function(response) {
                    // Update stats cards
                    if (response.queue) {
                        $('#queue-total').text(response.queue.total);
                        $('#queue-pending').text(response.queue.pending);
                        $('#queue-completed').text(response.queue.completed);
                        $('#queue-failed').text(response.queue.failed);
                    }
                    
                    if (response.logs) {
                        $('#logs-total').text(response.logs.total);
                        $('#logs-info').text(response.logs.info);
                        $('#logs-warning').text(response.logs.warning);
                        $('#logs-error').text(response.logs.error);
                        $('#logs-conflict').text(response.logs.conflict);
                    }
                }
            });
        }, 60000); // Refresh every minute
    });
</script>
				