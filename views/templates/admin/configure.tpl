{*
 * Login As Customer - One-Click Support Access
 *
 * @author    MEG Venture <info@megventure.com>
 * @copyright 2019-2026 MEG Venture
 * @license   https://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 *}
{* PS 1.5/1.6 back offices carry FontAwesome but no Material Icons; 1.7 and up carry
   Material, and 9 dropped FontAwesome. mv_ms picks the set that is actually there. *}
{assign var='mv_psv' value=$smarty.const._PS_VERSION_}
{assign var='mv_ms' value=true}
{if $mv_psv[0] == '1' && $mv_psv[1] == '.' && ($mv_psv[2] == '5' || $mv_psv[2] == '6')}{assign var='mv_ms' value=false}{/if}
<style>
{literal}
/* Back-office icons. These used to be FontAwesome 4, which the back office shipped up to
   PrestaShop 8; PrestaShop 9 replaced it with Material Symbols Outlined. An icon-font class
   selects a private-use code point, so the moment the font is not there the browser has
   nothing to fall back to and draws an empty box. These now come from the set the core loads
   for its own interface, which is ligature-based: the icon name is the element's text.
   Sized down from the 24px default and set back to inheriting the text colour, so they sit
   where the FontAwesome ones did. */
.material-icons.mv-ico{font-size:18px;line-height:1;vertical-align:middle;margin-right:4px;}
.material-icons.mv-ico,.material-icons.mv-ico:hover{color:inherit;}
h3 .material-icons.mv-ico,
.panel-heading .material-icons.mv-ico{font-size:20px;}
.btn .material-icons.mv-ico{font-size:16px;margin-right:3px;}
{/literal}
</style>
<div class="panel">
    <div class="panel-heading">
        {if $mv_ms}<i class="material-icons mv-ico">login</i>{else}<i class="icon icon-sign-in"></i>{/if} {l s='Login As Customer' mod='loginascustomer'}
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
                            <span class="text-success">{if $mv_ms}<i class="material-icons mv-ico">check</i>{else}<i class="icon icon-check"></i>{/if}</span>
                            {l s='Button shown on customer pages' mod='loginascustomer'}
                        {else}
                            <span class="text-muted">{if $mv_ms}<i class="material-icons mv-ico">close</i>{else}<i class="icon icon-remove"></i>{/if}</span>
                            {l s='Button hidden on customer pages' mod='loginascustomer'}
                        {/if}
                    </li>
                    <li>
                        {if $lac_on_order}
                            <span class="text-success">{if $mv_ms}<i class="material-icons mv-ico">check</i>{else}<i class="icon icon-check"></i>{/if}</span>
                            {l s='Button shown on order pages' mod='loginascustomer'}
                        {else}
                            <span class="text-muted">{if $mv_ms}<i class="material-icons mv-ico">close</i>{else}<i class="icon icon-remove"></i>{/if}</span>
                            {l s='Button hidden on order pages' mod='loginascustomer'}
                        {/if}
                    </li>
                    <li>
                        <span class="text-info">{if $mv_ms}<i class="material-icons mv-ico">schedule</i>{else}<i class="icon icon-clock-o"></i>{/if}</span>
                        {l s='Links expire after %d minutes.' sprintf=[$lac_ttl] mod='loginascustomer'}
                    </li>
                    <li>
                        {if $lac_ssl_enabled}
                            <span class="text-success">{if $mv_ms}<i class="material-icons mv-ico">lock</i>{else}<i class="icon icon-lock"></i>{/if}</span>
                            {l s='SSL is on — connect links travel encrypted.' mod='loginascustomer'}
                        {else}
                            <span class="text-warning">{if $mv_ms}<i class="material-icons mv-ico">warning</i>{else}<i class="icon icon-warning"></i>{/if}</span>
                            {l s='SSL is off for this shop. Turn on "Enable SSL" in Shop Parameters so connect links are not sent in clear text.' mod='loginascustomer'}
                        {/if}
                    </li>
                </ul>
            </div>
        </div>
    </div>
</div>
