<?php
/**
 * @author    MEG Venture <info@megventure.com>
 * @copyright 2007-2026 MEG Venture
 * @license   All rights reserved
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

require_once dirname(__DIR__) . '/classes/LoginAsCustomerToken.php';

/**
 * 1.0.2 -> 1.0.4: single-use connect tokens + module HMAC secret.
 *
 * - Creates `loginascustomer_used` jti ledger.
 * - Ensures LOGINASCUSTOMER_SECRET (random 64 hex).
 * - Caps default TTL to 15 minutes when still at the old 60-minute default.
 *
 * Existing in-flight connect links (two-part tokens) stop working after upgrade;
 * staff generate a fresh link from the customer/order page.
 *
 * @param Module $module
 *
 * @return bool
 */
function upgrade_module_1_0_4($module)
{
    LoginAsCustomerToken::ensureSecret();

    if (!LoginAsCustomerToken::installSchema()) {
        return false;
    }

    $ttl = (int) Configuration::get('LOGINASCUSTOMER_TTL');
    if ($ttl === 60 || $ttl <= 0) {
        Configuration::updateValue('LOGINASCUSTOMER_TTL', 15);
    }

    if (method_exists('Tools', 'clearSmartyCache')) {
        Tools::clearSmartyCache();
    }

    return true;
}
