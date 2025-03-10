{*
* Stock Sync Module References Template
*
* @author    Expert PrestaShop Developer
* @copyright 2025
* @license   Commercial
*}

<div class="panel">
    <div class="panel-heading">
        <i class="icon-link"></i> {l s='Product References Mapping' mod='stocksyncmodule'}
    </div>
    <div class="panel-body">
        <div class="row">
            <div class="col-md-6">
                <div class="panel">
                    <div class="panel-heading">
                        <i class="icon-bar-chart"></i> {l s='References Statistics' mod='stocksyncmodule'}
                    </div>
                    <div class="panel-body">
                        <div class="row">
                            <div class="col-xs-12">
                                <div class="stat-item">
                                    <span class="stat-value">{$stats.total|intval}</span>
                                    <span class="stat-label">{l s='Total References Mapped' mod='stocksyncmodule'}</span>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-xs-4">
                                <div class="stat-item">
                                    <span class="stat-value badge badge-success">{$stats.active|intval}</span>
                                    <span class="stat-label">{l s='Active' mod='stocksyncmodule'}</span>
                                </div>
                            </div>
                            <div class="col-xs-4">
                                <div class="stat-item">
                                    <span class="stat-value badge badge-primary">{$stats.products|intval}</span>
                                    <span class="stat-label">{l s='Products' mod='stocksyncmodule'}</span>
                                </div>
                            </div>
                            <div class="col-xs-4">
                                <div class="stat-item">
                                    <span class="stat-value badge badge-info">{$stats.combinations|intval}</span>
                                    <span class="stat-label">{l s='Combinations' mod='stocksyncmodule'}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="panel">
                    <div class="panel-heading">
                        <i class="icon-wrench"></i> {l s='Reference Tools' mod='stocksyncmodule'}
                    </div>
                    <div class="panel-body">
                        <div class="row">
                            <div class="col-xs-6">
                                <div class="form-group">
                                    <a href="{$scan_url}" class="btn btn-primary btn-block">
                                        <i class="icon-cogs"></i> {l s='Scan & Map References' mod='stocksyncmodule'}
                                    </a>
                                </div>
                            </div>
                            <div class="col-xs-6">
                                <div class="form-group">
                                    <a href="{$check_url}" class="btn btn-warning btn-block">
                                        <i class="icon-search"></i> {l s='Check Duplicate References' mod='stocksyncmodule'}
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
