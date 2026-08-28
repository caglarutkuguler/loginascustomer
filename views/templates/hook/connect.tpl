{*
 * Login As Customer - One-Click Support Access
 *
 * @author    MEG Venture <info@megventure.com>
 * @copyright 2019-2026 MEG Venture
 * @license   https://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 *}
<div class="panel" id="loginascustomer-panel">
    <div class="panel-heading">
        <i class="icon icon-sign-in"></i> {l s='Login as customer' mod='loginascustomer'}
    </div>
    <div class="panel-body">
        {if $lac_link}
            <p>
                <a href="{$lac_link|escape:'html':'UTF-8'}"
                   class="btn btn-primary btn-lg"
                   {if $lac_newtab}target="_blank" rel="noopener"{/if}>
                    <i class="icon icon-sign-in"></i>
                    {l s='Log in as' mod='loginascustomer'}
                    {if $lac_customer_name}{$lac_customer_name|escape:'html':'UTF-8'}{else}{$lac_customer_email|escape:'html':'UTF-8'}{/if}
                </a>
            </p>
            <p class="help-block">
                {l s='Opens the storefront in a session signed in as this customer, so you can see exactly what they see.' mod='loginascustomer'}
                {l s='The link is single-purpose and expires after %d minutes.' sprintf=[$lac_ttl] mod='loginascustomer'}
            </p>
        {/if}
    </div>
</div>
