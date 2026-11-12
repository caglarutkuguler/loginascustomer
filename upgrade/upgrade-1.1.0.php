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
 * 1.0.5 -> 1.1.0: multistore.
 *
 * The HMAC secret becomes an installation-wide (global) configuration value, so a
 * link minted from any back-office shop context verifies on the customer's shop.
 * A secret that 1.0.x stored for one shop or shop group is promoted to the global
 * value (default shop first) instead of being replaced; only when none exists
 * anywhere is a new one generated. Old per-shop rows are left in place, unused.
 * The module is never left without a secret.
 *
 * Connect links are now bound to the customer's shop when multistore is active,
 * so links minted before the upgrade (15 minutes by default) stop working; staff
 * generate a fresh one. With a single shop nothing changes in the token format.
 *
 * @param Module $module
 *
 * @return bool
 */
function upgrade_module_1_1_0($module)
{
    $secret = LoginAsCustomerToken::ensureSecret();

    return strlen($secret) >= 32;
}
