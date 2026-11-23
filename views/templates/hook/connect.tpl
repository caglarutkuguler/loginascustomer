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
.panel-heading .material-icons.mv-ico,
.card-header .material-icons.mv-ico{font-size:20px;}
.btn .material-icons.mv-ico{font-size:16px;margin-right:3px;}
{/literal}
</style>
{* PrestaShop 9 dropped the .panel rules the back office carried up to 8, so a block
   using them lost its frame. These re-create just enough of it, scoped to this module. *}
{if $mv_psv[0] != '1' && $mv_psv[0] != '8'}
<style>
{literal}
.lac-panel{background:#fff;border:1px solid #d6dbe0;border-radius:4px;margin-bottom:20px;}
.lac-panel>.panel-heading{padding:14px 20px;border-bottom:1px solid #d6dbe0;font-size:15px;font-weight:600;color:#363a41;}
.lac-panel>.panel-body{padding:20px;}
.lac-panel>.row{padding:20px;margin-left:0;margin-right:0;}
.lac-panel>.list-unstyled{padding:16px 20px;margin-bottom:0;}
{/literal}
</style>
{/if}
<div class="panel lac-panel" id="loginascustomer-panel">
    <div class="panel-heading">
        {if $mv_ms}<i class="material-icons mv-ico">login</i>{else}<i class="icon icon-sign-in"></i>{/if} {l s='Login as customer' mod='loginascustomer'}
    </div>
    <div class="panel-body">
        {if $lac_link}
            <p>
                <a href="{$lac_link|escape:'html':'UTF-8'}"
                   class="btn btn-primary btn-lg"
                   {if $lac_newtab}target="_blank" rel="noopener"{/if}>
                    {if $mv_ms}<i class="material-icons mv-ico">login</i>{else}<i class="icon icon-sign-in"></i>{/if}
                    {l s='Log in as' mod='loginascustomer'}
                    {if $lac_customer_name}{$lac_customer_name|escape:'html':'UTF-8'}{else}{$lac_customer_email|escape:'html':'UTF-8'}{/if}
                </a>
            </p>
            <p class="help-block">
                {l s='Opens the storefront in a session signed in as this customer, so you can see exactly what they see.' mod='loginascustomer'}
                {l s='The link works once and expires after %d minutes.' sprintf=[$lac_ttl] mod='loginascustomer'}
            </p>
        {/if}
    </div>
</div>
