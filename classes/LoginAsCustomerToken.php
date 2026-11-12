<?php
/**
 * Login As Customer - One-Click Support Access
 *
 * @author    MEG Venture <info@megventure.com>
 * @copyright 2019-2026 MEG Venture
 * @license   https://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Signed, time-limited, single-use, customer-bound connect token.
 *
 * Token shape: "<expiry>.<jti>.<hexsignature>"
 * HMAC payload: id_customer|id_employee|expiry|jti
 *
 * 1.0.3: single-use (jti ledger), module secret, default TTL 15 minutes.
 * A leaked URL can no longer be replayed after the first successful login.
 */
class LoginAsCustomerToken
{
    /** Absolute upper bound on a token's future expiry, as a sanity guard. */
    const MAX_TTL = 1440; // minutes (24h)

    /** Fallback lifetime when the configuration value is missing or invalid. */
    const DEFAULT_TTL = 15; // minutes

    /** @var array<string, true> in-memory used set for unit tests (no Db) */
    private static $testUsed = array();

    /** @var bool|null cached result of schemaExists() for the request */
    private static $schemaExistsCache = null;

    /**
     * 1.1.0: with multistore active the token is also bound to the shop it was
     * minted for (the customer's own shop). The shop id joins the signed payload
     * only then, so a single-shop installation signs exactly what it always did.
     *
     * @param int      $idCustomer
     * @param int      $idEmployee
     * @param int      $idShop      shop the link is minted for (ignored without multistore)
     * @param int|null $idShopGroup group of that shop, for the lifetime lookup
     *
     * @return string "<expiry>.<jti>.<hexsignature>"
     */
    public static function generate($idCustomer, $idEmployee, $idShop = 0, $idShopGroup = null)
    {
        $expiry = time() + self::ttlSeconds($idShop, $idShopGroup);
        $jti = bin2hex(random_bytes(16));
        $binding = self::bindingShopId($idShop);

        return $expiry . '.' . $jti . '.' . self::sign((int) $idCustomer, (int) $idEmployee, $expiry, $jti, $binding);
    }

    /**
     * Whether more than one shop exists and multistore is switched on.
     *
     * @return bool
     */
    public static function multistoreActive()
    {
        return class_exists('Shop') && (bool) Shop::isFeatureActive();
    }

    /**
     * The shop id that goes into the signed payload: the given shop when
     * multistore is active, 0 (no binding, legacy payload) otherwise.
     *
     * @param int $idShop
     *
     * @return int
     */
    public static function bindingShopId($idShop)
    {
        if (!self::multistoreActive()) {
            return 0;
        }

        return max(0, (int) $idShop);
    }

    /**
     * Pure form of the rule core applies to a customer login (Customer::getByEmail):
     * when the shop group shares its customers, the customer must belong to the
     * same shop group; otherwise to the very shop being logged in on.
     *
     * @param int  $customerIdShop
     * @param int  $customerIdShopGroup
     * @param int  $currentIdShop
     * @param int  $currentIdShopGroup
     * @param bool $shareCustomer       share_customer flag of the current shop group
     *
     * @return bool
     */
    public static function customerMayLoginOnShop($customerIdShop, $customerIdShopGroup, $currentIdShop, $currentIdShopGroup, $shareCustomer)
    {
        if ((int) $currentIdShop <= 0) {
            return false;
        }
        if ($shareCustomer) {
            return (int) $currentIdShopGroup > 0 && (int) $customerIdShopGroup === (int) $currentIdShopGroup;
        }

        return (int) $customerIdShop === (int) $currentIdShop;
    }

    /**
     * Whether the customer may log in on the shop that is serving this request.
     * Always true without multistore.
     *
     * @param object $customer a loaded Customer
     *
     * @return bool
     */
    public static function customerBelongsToCurrentShop($customer)
    {
        if (!self::multistoreActive()) {
            return true;
        }

        $idShop = (int) Context::getContext()->shop->id;
        $group = Shop::getGroupFromShop($idShop, false);
        if (!is_array($group)) {
            return false;
        }
        $idGroup = (int) Shop::getGroupFromShop($idShop, true);

        return self::customerMayLoginOnShop(
            (int) $customer->id_shop,
            (int) $customer->id_shop_group,
            $idShop,
            $idGroup,
            !empty($group['share_customer'])
        );
    }

    /**
     * Rewrites a plain-http URL of the given shop to its SSL domain. Link::getModuleLink()
     * decides http/https from the back-office context shop; when the customer's shop has
     * SSL but the context shop does not, the bearer link must still not go out in clear.
     *
     * @param string $url
     * @param string $domain    shop domain
     * @param string $domainSsl shop SSL domain
     *
     * @return string
     */
    public static function forceShopHttps($url, $domain, $domainSsl)
    {
        $url = (string) $url;
        $prefix = 'http://' . $domain;
        if ($domainSsl !== '' && $domain !== '' && strpos($url, $prefix) === 0) {
            return 'https://' . $domainSsl . substr($url, strlen($prefix));
        }

        return $url;
    }

    /**
     * Cryptographic + expiry check only (does not consume). Prefer consume().
     *
     * @param int    $idCustomer
     * @param int    $idEmployee
     * @param string $token
     * @param int    $idShop  shop redeeming the token (ignored without multistore)
     *
     * @return bool
     */
    public static function isValid($idCustomer, $idEmployee, $token, $idShop = 0)
    {
        return self::parseAndVerify($idCustomer, $idEmployee, $token, true, $idShop) !== null;
    }

    /**
     * Validate and mark the jti as used. Returns false if invalid or already used.
     *
     * @param int    $idCustomer
     * @param int    $idEmployee
     * @param string $token
     * @param int    $idShop  shop redeeming the token (ignored without multistore)
     *
     * @return bool
     */
    public static function consume($idCustomer, $idEmployee, $token, $idShop = 0)
    {
        $result = self::redeem($idCustomer, $idEmployee, $token, $idShop);

        return $result['status'] === 'consumed';
    }

    /**
     * Redeem a connect token with a typed outcome (for audit logging).
     *
     * Status values:
     * - consumed       — first use; session may be opened
     * - already_used   — HMAC valid but jti already claimed (replay)
     * - schema_missing — ledger table absent; fail closed
     * - invalid        — bad/expired/tampered token
     * - failed         — unexpected DB error after a valid token
     *
     * @param int    $idCustomer
     * @param int    $idEmployee
     * @param string $token
     * @param int    $idShop  shop redeeming the token (ignored without multistore)
     *
     * @return array{status: string, jti: string|null}
     */
    public static function redeem($idCustomer, $idEmployee, $token, $idShop = 0)
    {
        // Skip ledger read here — markUsed's INSERT is the atomic claim.
        $parsed = self::parseAndVerify($idCustomer, $idEmployee, $token, false, $idShop);
        if ($parsed === null) {
            return array('status' => 'invalid', 'jti' => null);
        }

        $jti = $parsed['jti'];
        $mark = self::markUsed($jti, (int) $idCustomer, (int) $idEmployee);
        if ($mark === true) {
            return array('status' => 'consumed', 'jti' => $jti);
        }
        if ($mark === 'schema_missing') {
            return array('status' => 'schema_missing', 'jti' => $jti);
        }
        if ($mark === 'already_used') {
            return array('status' => 'already_used', 'jti' => $jti);
        }

        return array('status' => 'failed', 'jti' => $jti);
    }

    /**
     * @param int    $idCustomer
     * @param int    $idEmployee
     * @param string $token
     * @param bool   $checkUsed
     * @param int    $idShop
     *
     * @return array{expiry: int, jti: string}|null
     */
    private static function parseAndVerify($idCustomer, $idEmployee, $token, $checkUsed = true, $idShop = 0)
    {
        $token = (string) $token;
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            return null;
        }

        $expiry = (int) $parts[0];
        $jti = (string) $parts[1];
        $signature = (string) $parts[2];
        if ($expiry <= 0 || $signature === '' || !preg_match('/^[a-f0-9]{32}$/', $jti)) {
            return null;
        }

        $now = time();
        if ($expiry < $now) {
            return null;
        }
        if ($expiry > $now + self::MAX_TTL * 60) {
            return null;
        }

        $binding = self::bindingShopId($idShop);
        if (!self::hashEquals(self::sign((int) $idCustomer, (int) $idEmployee, $expiry, $jti, $binding), $signature)) {
            return null;
        }

        if ($checkUsed && self::isUsed($jti)) {
            return null;
        }

        return array('expiry' => $expiry, 'jti' => $jti);
    }

    /**
     * Configured link lifetime in seconds, clamped to a sane range. With multistore
     * the value is read for the given shop (the customer's), not the back-office context.
     *
     * @param int|null $idShop
     * @param int|null $idShopGroup
     *
     * @return int
     */
    public static function ttlSeconds($idShop = null, $idShopGroup = null)
    {
        if (self::multistoreActive() && (int) $idShop > 0) {
            $minutes = (int) Configuration::get('LOGINASCUSTOMER_TTL', null, $idShopGroup === null ? null : (int) $idShopGroup, (int) $idShop);
        } else {
            $minutes = (int) Configuration::get('LOGINASCUSTOMER_TTL');
        }
        if ($minutes < 5) {
            $minutes = self::DEFAULT_TTL;
        } elseif ($minutes > self::MAX_TTL) {
            $minutes = self::MAX_TTL;
        }

        return $minutes * 60;
    }

    /**
     * The installation-wide HMAC secret (global configuration value), or ''.
     *
     * The secret is global on purpose: a link is minted in the back office
     * (any shop context) and redeemed on another shop's domain, so both ends
     * must read the same value.
     *
     * @return string
     */
    public static function readSecret()
    {
        if (!class_exists('Configuration', false)) {
            return '';
        }
        if (method_exists('Configuration', 'getGlobalValue')) {
            return (string) Configuration::getGlobalValue('LOGINASCUSTOMER_SECRET');
        }

        return (string) Configuration::get('LOGINASCUSTOMER_SECRET');
    }

    /**
     * Ensure the global HMAC secret exists (install / upgrade). A secret that an
     * earlier version stored for one shop or shop group is promoted to the global
     * value (default shop first) instead of being replaced; a new one is only
     * generated when there is none anywhere. The old per-shop rows are left alone.
     *
     * @return string
     */
    public static function ensureSecret()
    {
        $secret = self::readSecret();
        if ($secret === '' || strlen($secret) < 32) {
            $secret = self::legacyScopedSecret();
            if ($secret === '') {
                $secret = bin2hex(random_bytes(32));
            }
            if (method_exists('Configuration', 'updateGlobalValue')) {
                Configuration::updateGlobalValue('LOGINASCUSTOMER_SECRET', $secret);
            } else {
                Configuration::updateValue('LOGINASCUSTOMER_SECRET', $secret);
            }
        }

        return $secret;
    }

    /**
     * A usable secret stored by 1.0.x for a single shop or shop group, preferring
     * the default shop. '' when none (or when there is no database, as in tests).
     *
     * @return string
     */
    private static function legacyScopedSecret()
    {
        if (!class_exists('Db', false) || !defined('_DB_PREFIX_')) {
            return '';
        }

        $default = (int) Configuration::get('PS_SHOP_DEFAULT');
        $value = Db::getInstance()->getValue(
            'SELECT `value` FROM `' . _DB_PREFIX_ . 'configuration`
            WHERE `name` = \'LOGINASCUSTOMER_SECRET\'
            AND (`id_shop` IS NOT NULL OR `id_shop_group` IS NOT NULL)
            AND CHAR_LENGTH(`value`) >= 32
            ORDER BY (`id_shop` = ' . $default . ') DESC, `id_configuration` ASC'
        );

        return is_string($value) ? $value : '';
    }

    /**
     * @return bool
     */
    public static function installSchema()
    {
        $engine = defined('_MYSQL_ENGINE_') ? _MYSQL_ENGINE_ : 'InnoDB';
        $sql = 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'loginascustomer_used` (
            `jti` CHAR(32) NOT NULL,
            `id_customer` INT UNSIGNED NOT NULL DEFAULT 0,
            `id_employee` INT UNSIGNED NOT NULL DEFAULT 0,
            `date_add` DATETIME NOT NULL,
            PRIMARY KEY (`jti`),
            KEY `date_add` (`date_add`)
        ) ENGINE=' . $engine . ' DEFAULT CHARSET=utf8mb4';

        $ok = (bool) Db::getInstance()->execute($sql);
        self::clearSchemaCache();

        return $ok;
    }

    /**
     * @return bool
     */
    public static function uninstallSchema()
    {
        $ok = (bool) Db::getInstance()->execute(
            'DROP TABLE IF EXISTS `' . _DB_PREFIX_ . 'loginascustomer_used`'
        );
        self::clearSchemaCache();

        return $ok;
    }

    /**
     * Whether the single-use jti ledger table exists.
     *
     * In the unit-test harness (no Db), the in-memory ledger is always available.
     *
     * @return bool
     */
    public static function schemaExists()
    {
        if (!class_exists('Db', false) || !defined('_DB_PREFIX_')) {
            return true;
        }

        if (self::$schemaExistsCache !== null) {
            return self::$schemaExistsCache;
        }

        $rows = Db::getInstance()->executeS(
            'SHOW TABLES LIKE \'' . pSQL(_DB_PREFIX_ . 'loginascustomer_used') . '\''
        );
        self::$schemaExistsCache = !empty($rows);

        return self::$schemaExistsCache;
    }

    /**
     * Drop the cached schemaExists() result (after install/uninstall/repair).
     */
    public static function clearSchemaCache()
    {
        self::$schemaExistsCache = null;
    }

    /**
     * Unit-test helper: clear the in-memory used set.
     */
    public static function resetTestLedger()
    {
        self::$testUsed = array();
    }

    /**
     * @param int    $idCustomer
     * @param int    $idEmployee
     * @param int    $expiry
     * @param string $jti
     * @param int    $binding shop id bound into the payload, 0 for none
     *
     * @return string
     */
    private static function sign($idCustomer, $idEmployee, $expiry, $jti, $binding = 0)
    {
        $payload = $idCustomer . '|' . $idEmployee . '|' . $expiry . '|' . $jti;
        if ((int) $binding > 0) {
            $payload .= '|' . (int) $binding;
        }

        return hash_hmac('sha256', $payload, self::secret());
    }

    /**
     * Prefer module Configuration secret; fall back to shop cookie material
     * only when the secret row is missing (pre-upgrade tokens already invalid
     * after format change anyway).
     *
     * @return string
     */
    private static function secret()
    {
        $configured = self::readSecret();
        if ($configured !== '' && strlen($configured) >= 32) {
            return $configured . '|loginascustomer';
        }

        $key = defined('_COOKIE_KEY_') ? _COOKIE_KEY_ : '';
        if (defined('_COOKIE_IV_')) {
            $key .= _COOKIE_IV_;
        }

        return $key . '|loginascustomer';
    }

    /**
     * @param string $jti
     *
     * @return bool
     */
    private static function isUsed($jti)
    {
        if (!class_exists('Db', false) || !defined('_DB_PREFIX_')) {
            return isset(self::$testUsed[$jti]);
        }

        $id = Db::getInstance()->getValue(
            'SELECT 1 FROM `' . _DB_PREFIX_ . 'loginascustomer_used` WHERE `jti` = \'' . pSQL($jti) . '\''
        );

        return (bool) $id;
    }

    /**
     * @param string $jti
     * @param int    $idCustomer
     * @param int    $idEmployee
     *
     * @return true|string true on first claim; 'already_used' | 'schema_missing' | 'failed' otherwise
     */
    private static function markUsed($jti, $idCustomer, $idEmployee)
    {
        if (!class_exists('Db', false) || !defined('_DB_PREFIX_')) {
            if (isset(self::$testUsed[$jti])) {
                return 'already_used';
            }
            self::$testUsed[$jti] = true;

            return true;
        }

        if (!self::schemaExists()) {
            return 'schema_missing';
        }

        // Plain INSERT: duplicate primary key fails → second consumer loses the race.
        $ok = Db::getInstance()->execute(
            'INSERT INTO `' . _DB_PREFIX_ . 'loginascustomer_used`
            (`jti`, `id_customer`, `id_employee`, `date_add`)
            VALUES (\'' . pSQL($jti) . '\', ' . (int) $idCustomer . ', ' . (int) $idEmployee . ', \'' . pSQL(date('Y-m-d H:i:s')) . '\')'
        );
        if (!$ok) {
            // Distinguish replay (row present) from an unexpected write failure.
            if (self::isUsed($jti)) {
                return 'already_used';
            }

            return 'failed';
        }

        // Opportunistic purge of expired ledger rows (beyond max TTL).
        Db::getInstance()->execute(
            'DELETE FROM `' . _DB_PREFIX_ . 'loginascustomer_used`
            WHERE `date_add` < \'' . pSQL(date('Y-m-d H:i:s', time() - self::MAX_TTL * 60)) . '\'
            LIMIT 500'
        );

        return true;
    }

    /**
     * @param string $known
     * @param string $user
     *
     * @return bool
     */
    private static function hashEquals($known, $user)
    {
        if (function_exists('hash_equals')) {
            return hash_equals($known, $user);
        }

        $known = (string) $known;
        $user = (string) $user;
        if (strlen($known) !== strlen($user)) {
            return false;
        }

        $result = 0;
        for ($i = 0, $len = strlen($known); $i < $len; $i++) {
            $result |= ord($known[$i]) ^ ord($user[$i]);
        }

        return $result === 0;
    }
}
