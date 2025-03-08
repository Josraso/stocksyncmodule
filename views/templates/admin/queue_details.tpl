{*
* Stock Sync Module Queue Details Template
*
* @author    Expert PrestaShop Developer
* @copyright 2025
* @license   Commercial
*}

<div class="panel">
    <div class="panel-heading">
        <i class="icon-refresh"></i> {l s='Queue Item Details' mod='stocksyncmodule'} #{$queue_item.id_queue}
        <div class="panel-heading-action">
            <a href="{$retry_url}" class="btn btn-default">
                <i class="icon-refresh"></i> {l s='Retry' mod='stocksyncmodule'}
            </a>
            <a href="{$dashboard_link|escape:'html':'UTF-8'}" class="btn btn-default">
                <i class="icon-arrow-left"></i> {l s='Back to Dashboard' mod='stocksyncmodule'}
            </a>
        </div>
    </div>
    <div class="form-wrapper">
        <div class="form-group">
            <label class="control-label col-lg-3">{l s='Reference' mod='stocksyncmodule'}</label>
            <div class="col-lg-9">
                <p class="form-control-static">{$queue_item.reference}</p>
            </div>
        </div>
        <div class="form-group">
            <label class="control-label col-lg-3">{l s='Operation Type' mod='stocksyncmodule'}</label>
            <div class="col-lg-9">
                <p class="form-control-static">{$queue_item.operation_type}</p>
            </div>
        </div>
        <div class="form-group">
            <label class="control-label col-lg-3">{l s='Status' mod='stocksyncmodule'}</label>
            <div class="col-lg-9">
                <p class="form-control-static">
                    <span class="badge badge-{if $queue_item.status == 'completed'}success{elseif $queue_item.status == 'pending'}warning{elseif $queue_item.status == 'processing'}info{elseif $queue_item.status == 'failed'}danger{else}default{/if}
}">
                        {$queue_item.status}
                    </span>
                </p>
            </div>
        </div>
        <div class="form-group">
            <label class="control-label col-lg-3">{l s='Stock Change' mod='stocksyncmodule'}</label>
            <div class="col-lg-9">
                <p class="form-control-static">
                    {$queue_item.old_quantity} → {$queue_item.new_quantity}
                    <span class="label label-{if $queue_item.new_quantity > $queue_item.old_quantity}success{elseif $queue_item.new_quantity < $queue_item.old_quantity}danger{else}default{/if}">
                        {if $queue_item.new_quantity > $queue_item.old_quantity}+{/if}{math equation="x-y" x=$queue_item.new_quantity y=$queue_item.old_quantity}
                    </span>
                </p>
            </div>
        </div>
        <div class="form-group">
            <label class="control-label col-lg-3">{l s='Source Store' mod='stocksyncmodule'}</label>
            <div class="col-lg-9">
                <p class="form-control-static">{$queue_item.source_store_name}</p>
            </div>
        </div>
        <div class="form-group">
            <label class="control-label col-lg-3">{l s='Target Store' mod='stocksyncmodule'}</label>
            <div class="col-lg-9">
                <p class="form-control-static">{$queue_item.target_store_name}</p>
            </div>
        </div>
        <div class="form-group">
            <label class="control-label col-lg-3">{l s='Attempts' mod='stocksyncmodule'}</label>
            <div class="col-lg-9">
                <p class="form-control-static">{$queue_item.attempts}</p>
            </div>
        </div>
        {if $queue_item.error_message}
        <div class="form-group">
            <label class="control-label col-lg-3">{l s='Error Message' mod='stocksyncmodule'}</label>
            <div class="col-lg-9">
                <p class="form-control-static text-danger">{$queue_item.error_message}</p>
            </div>
        </div>
        {/if}
        <div class="form-group">
            <label class="control-label col-lg-3">{l s='Created' mod='stocksyncmodule'}</label>
            <div class="col-lg-9">
                <p class="form-control-static">{$queue_item.created_at}</p>
            </div>
        </div>
        <div class="form-group">
            <label class="control-label col-lg-3">{l s='Last Update' mod='stocksyncmodule'}</label>
            <div class="col-lg-9">
                <p class="form-control-static">{$queue_item.updated_at}</p>
            </div>
        </div>
    </div>
    <div class="panel-footer">
        <a href="{$link->getAdminLink('AdminStockSyncQueue')|escape:'html':'UTF-8'}" class="btn btn-default">
            <i class="icon-arrow-left"></i> {l s='Back to List' mod='stocksyncmodule'}
        </a>
    </div>
</div>

{if isset($product_info) && $product_info}
<div class="panel">
    <div class="panel-heading">
        <i class="icon-archive"></i> {l s='Product Information' mod='stocksyncmodule'}
    </div>
    <div class="form-wrapper">
        <div class="form-group">
            <label class="control-label col-lg-3">{l s='Product' mod='stocksyncmodule'}</label>
            <div class="col-lg-9">
                <p class="form-control-static">
                    <a href="{$product_info.link}" target="_blank">
                        {$product_info.name} (ID: {$product_info.id})
                    </a>
                </p>
            </div>
        </div>
        {if isset($product_info.combination_id) && $product_info.combination_id}
        <div class="form-group">
            <label class="control-label col-lg-3">{l s='Combination' mod='stocksyncmodule'}</label>
            <div class="col-lg-9">
                <p class="form-control-static">
                    ID: {$product_info.combination_id}
                    {if isset($product_info.attributes) && $product_info.attributes|count > 0}
                    <br>
                    {foreach $product_info.attributes as $attribute}
                        <span class="label label-info">{$attribute}</span>
                    {/foreach}
                    {/if}
                </p>
            </div>
        </div>
        {/if}
        <div class="form-group">
            <label class="control-label col-lg-3">{l s='Reference' mod='stocksyncmodule'}</label>
            <div class="col-lg-9">
                <p class="form-control-static">
                    {if isset($product_info.combination_reference) && $product_info.combination_reference}
                        {$product_info.combination_reference}
                    {else}
                        {$product_info.reference}
                    {/if}
                </p>
            </div>
        </div>
    </div>
</div>
{/if}

{if isset($logs) && $logs|count > 0}
<div class="panel">
    <div class="panel-heading">
        <i class="icon-file-text"></i> {l s='Related Logs' mod='stocksyncmodule'}
    </div>
    <div class="panel-body">
        <div class="table-responsive">
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>{l s='Date' mod='stocksyncmodule'}</th>
                        <th>{l s='Level' mod='stocksyncmodule'}</th>
                        <th>{l s='Message' mod='stocksyncmodule'}</th>
                    </tr>
                </thead>
                <tbody>
                    {foreach from=$logs item=log}
                        <tr>
                            <td>{$log.created_at}</td>
                            <td>
                                <span class="badge badge-{if $log.level == 'info'}info{elseif $log.level == 'warning'}warning{elseif $log.level == 'error'}danger{elseif $log.level == 'conflict'}primary{else}default{/if}">
                                    {$log.level}
                                </span>
                            </td>
                            <td>{$log.message}</td>
                        </tr>
                    {/foreach}
                </tbody>
            </table>
        </div>
    </div>
</div>
{/if}