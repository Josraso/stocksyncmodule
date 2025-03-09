{*
* Stock Sync Module Duplicates Template
*
* @author    Expert PrestaShop Developer
* @copyright 2025
* @license   Commercial
*}

<div class="panel">
    <div class="panel-heading">
        <i class="icon-warning"></i> {l s='Duplicate References Detection Results' mod='stocksyncmodule'}
    </div>
    <div class="panel-body">
        {if $duplicates|count > 0}
            <div class="alert alert-warning">
                <p>{l s='Found' mod='stocksyncmodule'} <strong>{$duplicates|count}</strong> {l s='duplicate references. This can cause synchronization issues.' mod='stocksyncmodule'}</p>
            </div>
            
            <div class="table-responsive">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>{l s='Reference' mod='stocksyncmodule'}</th>
                            <th>{l s='Type' mod='stocksyncmodule'}</th>
                            <th>{l s='Count' mod='stocksyncmodule'}</th>
                            <th>{l s='Details' mod='stocksyncmodule'}</th>
                        </tr>
                    </thead>
                    <tbody>
                        {foreach from=$duplicates item=duplicate}
                            <tr>
                                <td><strong>{$duplicate.reference}</strong></td>
                                <td>
                                    {if $duplicate.type == 'product'}
                                        <span class="badge badge-info">{l s='Products' mod='stocksyncmodule'}</span>
                                    {elseif $duplicate.type == 'combination'}
                                        <span class="badge badge-warning">{l s='Combinations' mod='stocksyncmodule'}</span>
                                    {elseif $duplicate.type == 'mixed'}
                                        <span class="badge badge-danger">{l s='Mixed (Products & Combinations)' mod='stocksyncmodule'}</span>
                                    {/if}
                                </td>
                                <td>{$duplicate.count}</td>
                                <td>
                                    <a href="#" class="btn btn-default btn-xs show-duplicate-details" data-reference="{$duplicate.reference}">
                                        <i class="icon-eye"></i> {l s='Show Details' mod='stocksyncmodule'}
                                    </a>
                                </td>
                            </tr>
                            <tr class="duplicate-details" id="details-{$duplicate.reference}" style="display: none;">
                                <td colspan="4">
                                    <div class="table-responsive">
                                        <table class="table">
                                            <thead>
                                                <tr>
                                                    <th>{l s='ID' mod='stocksyncmodule'}</th>
                                                    <th>{l s='Type' mod='stocksyncmodule'}</th>
                                                    <th>{l s='Name' mod='stocksyncmodule'}</th>
                                                    <th>{l s='Actions' mod='stocksyncmodule'}</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                {foreach from=$duplicate.items item=item}
                                                    <tr>
                                                        <td>
                                                            {if isset($item.id_product_attribute)}
                                                                {$item.id_product} / {$item.id_product_attribute}
                                                            {else}
                                                                {$item.id_product}
                                                            {/if}
                                                        </td>
                                                        <td>
                                                            {if isset($item.type) && $item.type == 'combination'}
                                                                <span class="badge badge-warning">{l s='Combination' mod='stocksyncmodule'}</span>
                                                            {else}
                                                                <span class="badge badge-info">{l s='Product' mod='stocksyncmodule'}</span>
                                                            {/if}
                                                        </td>
                                                        <td>{$item.name}</td>
                                                        <td>
                                                            <a href="{$link->getAdminLink('AdminProducts')}&id_product={$item.id_product}&updateproduct" class="btn btn-default btn-xs" target="_blank">
                                                                <i class="icon-edit"></i> {l s='Edit' mod='stocksyncmodule'}
                                                            </a>
                                                        </td>
                                                    </tr>
                                                {/foreach}
                                            </tbody>
                                        </table>
                                    </div>
                                </td>
                            </tr>
                        {/foreach}
                    </tbody>
                </table>
            </div>
            
            <div class="panel-footer">
                <p><strong>{l s='Recommendation:' mod='stocksyncmodule'}</strong> {l s='To avoid synchronization issues, make sure each product and combination has a unique reference.' mod='stocksyncmodule'}</p>
            </div>
        {else}
            <div class="alert alert-success">
                <p><i class="icon-check"></i> {l s='No duplicate references found. All products and combinations have unique references.' mod='stocksyncmodule'}</p>
            </div>
        {/if}
    </div>
</div>

<script type="text/javascript">
    $(document).ready(function() {
        $('.show-duplicate-details').click(function(e) {
            e.preventDefault();
            var reference = $(this).data('reference');
            $('#details-' + reference).toggle();
        });
    });
</script>
