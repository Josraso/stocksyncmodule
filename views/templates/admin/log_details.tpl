{*
* Stock Sync Module Log Details Template
*
* @author    Expert PrestaShop Developer
* @copyright 2025
* @license   Commercial
*}

<div class="panel">
    <div class="panel-heading">
        <i class="icon-file-text"></i> {l s='Log Details' mod='stocksyncmodule'} #{$log.id_log}
        <div class="panel-heading-action">
            <a href="{$path}" class="btn btn-default">
                <i class="icon-arrow-left"></i> {l s='Back to Dashboard' mod='stocksyncmodule'}
            </a>
        </div>
    </div>
    <div class="form-wrapper">
        <div class="form-group">
            <label class="control-label col-lg-3">{l s='Date' mod='stocksyncmodule'}</label>
            <div class="col-lg-9">
                <p class="form-control-static">{$log.created_at}</p>
            </div>
        </div>
        <div class="form-group">
            <label class="control-label col-lg-3">{l s='Level' mod='stocksyncmodule'}</label>
            <div class="col-lg-9">
                <p class="form-control-static">
                    <span class="badge badge-{if $log.level == 'info'}info{elseif $log.level == 'warning'}warning{elseif $log.level == 'error'}danger{elseif $log.level == 'conflict'}primary{else}default{/if}">
                        {$log.level}
                    </span>
                </p>
            </div>
        </div>
        <div class="form-group">
            <label class="control-label col-lg-3">{l s='Message' mod='stocksyncmodule'}</label>
            <div class="col-lg-9">
                <p class="form-control-static">{$log.message}</p>
            </div>
        </div>
        {if $log.reference}
        <div class="form-group">
            <label class="control-label col-lg-3">{l s='Reference' mod='stocksyncmodule'}</label>
            <div class="col-lg-9">
                <p class="form-control-static">{$log.reference}</p>
            </div>
        </div>
        {/if}
        {if $log.status}
        <div class="form-group">
            <label class="control-label col-lg-3">{l s='Status' mod='stocksyncmodule'}</label>
            <div class="col-lg-9">
                <p class="form-control-static">
                    <span class="badge badge-{if $log.status == 'completed'}success{elseif $log.status == 'pending'}warning{elseif $log.status == 'processing'}info{elseif $log.status == 'failed'}danger{else}default{/if}">
                        {$log.status}
                    </span>
                </p>
            </div>
        </div>
        {/if}
        {if isset($product_info) && $product_info}
        <div class="form-group">
            <label class="control-label col-lg-3">{l s='Product' mod='stocksyncmodule'}</label>
            <div class="col-lg-9">
                <p class="form-control-static">
                    <a href="{$product_info.link}" target="_blank">
                        {$product_info.name} (ID: {$product_info.id})
                        {if isset($product_info.combination_id)} - {l s='Combination' mod='stocksyncmodule'} #{$product_info.combination_id}{/if}
                    </a>
                </p>
            </div>
        </div>
        {/if}
    </div>
    <div class="panel-footer">
        <a href="{$link->getAdminLink('AdminStockSyncLogs')|escape:'html':'UTF-8'}" class="btn btn-default">
            <i class="icon-arrow-left"></i> {l s='Back to Logs' mod='stocksyncmodule'}
        </a>
    </div>
</div>

{if isset($log.id_queue) && $log.id_queue}
<div class="panel">
    <div class="panel-heading">
        <i class="icon-retweet"></i> {l s='Related Queue Item' mod='stocksyncmodule'} #{$log.id_queue}
    </div>
    <div class="panel-body">
        <a href="{$link->getAdminLink('AdminStockSyncQueue')}&id_queue={$log.id_queue}&viewstock_sync_queue" class="btn btn-default">
            <i class="icon-eye"></i> {l s='View Queue Item Details' mod='stocksyncmodule'}
        </a>
    </div>
</div>
{/if}