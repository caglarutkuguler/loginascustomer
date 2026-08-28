{*
 * Login As Customer - One-Click Support Access
 *
 * @author    MEG Venture <info@megventure.com>
 * @copyright 2019-2026 MEG Venture
 * @license   https://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 *}
<div class="panel">
    <div class="panel-heading">
        <i class="icon icon-sign-in"></i> {l s='Login As Customer' mod='loginascustomer'}
    </div>
    <div class="row">
        <div class="col-lg-7">
            <p>
                <strong>{l s='See your shop the way your customers see it.' mod='loginascustomer'}</strong>
            </p>
            <p>{l s='This module adds a "Log in as customer" button to the back office.' mod='loginascustomer'}</p>
            <h4>{l s='How it works' mod='loginascustomer'}</h4>
            <ol>
                <li>{l s='Open any customer page (Customers) or any order (Orders) in the back office.' mod='loginascustomer'}</li>
                <li>{l s='Click the blue "Log in as customer" button.' mod='loginascustomer'}</li>
                <li>{l s='The storefront opens in a session signed in as that customer, so you can reproduce what they report, check prices, vouchers, group access and cart, then close the tab.' mod='loginascustomer'}</li>
            </ol>
            <p class="text-muted">
                {l s='Each button builds a fresh, signed link that only opens that one customer and expires by itself. Every connection is written to the back-office logs (who connected as whom).' mod='loginascustomer'}
            </p>
        </div>
        <div class="col-lg-5">
            <div class="panel">
                <div class="panel-heading">{l s='Status' mod='loginascustomer'}</div>
                <ul class="list-unstyled">
                    <li>
                        {if $lac_on_customer}
                            <span class="text-success"><i class="icon icon-check"></i></span>
                            {l s='Button shown on customer pages' mod='loginascustomer'}
                        {else}
                            <span class="text-muted"><i class="icon icon-remove"></i></span>
                            {l s='Button hidden on customer pages' mod='loginascustomer'}
                        {/if}
                    </li>
                    <li>
                        {if $lac_on_order}
                            <span class="text-success"><i class="icon icon-check"></i></span>
                            {l s='Button shown on order pages' mod='loginascustomer'}
                        {else}
                            <span class="text-muted"><i class="icon icon-remove"></i></span>
                            {l s='Button hidden on order pages' mod='loginascustomer'}
                        {/if}
                    </li>
                    <li>
                        <span class="text-info"><i class="icon icon-clock-o"></i></span>
                        {l s='Links expire after %d minutes.' sprintf=[$lac_ttl] mod='loginascustomer'}
                    </li>
                    <li>
                        {if $lac_ssl_enabled}
                            <span class="text-success"><i class="icon icon-lock"></i></span>
                            {l s='SSL is on — connect links travel encrypted.' mod='loginascustomer'}
                        {else}
                            <span class="text-warning"><i class="icon icon-warning"></i></span>
                            {l s='SSL is off for this shop. Turn on "Enable SSL" in Shop Parameters so connect links are not sent in clear text.' mod='loginascustomer'}
                        {/if}
                    </li>
                </ul>
            </div>
        </div>
    </div>
</div>
