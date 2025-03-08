{*
* Stock Sync Module Reference Details Template
*
* @author    Expert PrestaShop Developer
* @copyright 2025
* @license   Commercial
*}

<div class="panel">
    <div class="panel-heading">
        <i class="icon-link"></i> {l s='Reference Mapping Details' mod='stocksyncmodule'} #{$reference_map.id_reference_map}
        <div class="panel-heading-action">
            <a href="{$update_url}&active={if $reference_map.active}0{else}1{/if}" class="btn btn-default">
                <i class="icon-{if $reference_map.active}times{else}check{/if}"></i> 
                {if $reference_map.active}
                    {l s='Deactivate' mod='stocksyncmodule'}
                {else}
                    {l s='Activate' mod='stocksyncmodule'}
                {/if}
            </a>
        </div>
    </div>
    <div class="form-wrapper">
        <div class="form-group">
            <label class="control-label col-lg-3">{l s='Reference' mod='stocksyncmodule'}</label>
            <div class="col-lg-9">
                <p class="form-control-static">{$reference_map.reference}</p>
            </div>
        </div>
        <div class="form-group">
            <label class="control-label col-lg-3">{l s='Status' mod='stocksyncmodule'}</label>
            <div class="col-lg-9">
                <p class="form-control-static">
                    <span class="badge badge-{if $reference_map.active}success{else}danger{/if}">
                        {if $reference_map.active}
                            {l s='Active' mod='stocksyncmodule'}
                        {else}
                            {l s='Inactive' mod='stocksyncmodule'}
                        {/if}
                    </span>
                </p>
            </div>
        </div>
        <div class="form-group">
            <label class="control-label col-lg-3">{l s='Source Store' mod='stocksyncmodule'}</label>
            <div class="col-lg-9">
                <p class="form-control-static">{$reference_map.source_store_name}</p>
            </div>
        </div>
        <div class="form-group">
            <label class="control-label col-lg-3">{l s='Target Store' mod='stocksyncmodule'}</label>
            <div class="col-lg-9">
                <p class="form-control-static">{$reference_map.target_store_name}</p>
            </div>
        </div>
        <div class="form-group">
            <label class="control-label col-lg-3">{l s='Last Sync' mod='stocksyncmodule'}</label>
            <div class="col-lg-9">
                <p class="form-control-static">
                    {if $reference_map.last_sync}
                        {$reference_map.last_sync}
                    {else}
                        {l s='Never' mod='stocksyncmodule'}
                    {/if}
                </p>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-lg-6">
        <div class="panel">
            <div class="panel-heading">
                <i class="icon-archive"></i> {l s='Source Product' mod='stocksyncmodule'}
            </div>
            <div class="panel-body">
                {if isset($source_product) && $source_product}
                    <div class="form-group">
                        <label class="control-label col-lg-4">{l s='Product' mod='stocksyncmodule'}</label>
                        <div class="col-lg-8">
                            <p class="form-control-static">
                                <a href="{$source_product.link}" target="_blank">
                                    {$source_product.name} (ID: {$source_product.id})
                                </a>
                            </p>
                        </div>
                    </div>
                    {if isset($source_product.combination_id) && $source_product.combination_id}
                    <div class="form-group">
                        <label class="control-label col-lg-4">{l s='Combination' mod='stocksyncmodule'}</label>
                        <div class="col-lg-8">
                            <p class="form-control-static">
                                ID: {$source_product.combination_id}
                            </p>
                        </div>
                    </div>
                    {/if}
                    <div class="form-group">
                        <label class="control-label col-lg-4">{l s='Reference' mod='stocksyncmodule'}</label>
                        <div class="col-lg-8">
                            <p class="form-control-static">
                                {if isset($source_product.combination_reference) && $source_product.combination_reference}
                                    {$source_product.combination_reference}
                                {else}
                                    {$source_product.reference}
                                {/if}
                            </p>
                        </div>
                    </div>
                {else}
                    <div class="alert alert-warning">
                        <p>{l s='Source product not found or no longer exists.' mod='stocksyncmodule'}</p>
                    </div>
                {/if}
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="panel">
            <div class="panel-heading">
                <i class="icon-archive"></i> {l s='Target Product' mod='stocksyncmodule'}
            </div>
            <div class="panel-body">
                {if isset($target_product) && $target_product}
                    <div class="form-group">
                        <label class="control-label col-lg-4">{l s='Product' mod='stocksyncmodule'}</label>
                        <div class="col-lg-8">
                            <p class="form-control-static">
                                <a href="{$target_product.link}" target="_blank">
                                    {$target_product.name} (ID: {$target_product.id})
                                </a>
                            </p>
                        </div>
                    </div>
                    {if isset($target_product.combination_id) && $target_product.combination_id}
                    <div class="form-group">
                        <label class="control-label col-lg-4">{l s='Combination' mod='stocksyncmodule'}</label>
                        <div class="col-lg-8">
                            <p class="form-control-static">
                                ID: {$target_product.combination_id}
                            </p>
                        </div>
                    </div>
                    {/if}
                    <div class="form-group">
                        <label class="control-label col-lg-4">{l s='Reference' mod='stocksyncmodule'}</label>
                        <div class="col-lg-8">
                            <p class="form-control-static">
                                {if isset($target_product.combination_reference) && $target_product.combination_reference}
                                    {$target_product.combination_reference}
                                {else}
                                    {$target_product.reference}
                                {/if}
                            </p>
                        </div>
                    </div>
                {else}
                    <div class="alert alert-warning">
                        <p>{l s='Target product not found or no longer exists.' mod='stocksyncmodule'}</p>
                    </div>
                {/if}
            </div>
        </div>
    </div>
</div>

{if isset($logs) && $logs|count > 0}
<div class="panel">
    <div class="panel-heading">
        <i class="icon-file-text"></i> {l s='Synchronization Logs' mod='stocksyncmodule'}
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
