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
     * @param int $idCustomer
     * @param int $idEmployee
     *
     * @return string "<expiry>.<jti>.<hexsignature>"
     */
    public static function generate($idCustomer, $idEmployee)
    {
        $expiry = time() + self::ttlSeconds();
        $jti = bin2hex(random_bytes(16));

        return $expiry . '.' . $jti . '.' . self::sign((int) $idCustomer, (int) $idEmployee, $expiry, $jti);
    }

    /**
     * Cryptographic + expiry check only (does not consume). Prefer consume().
     *
     * @param int    $idCustomer
     * @param int    $idEmployee
     * @param string $token
     *
     * @return bool
     */
    public static function isValid($idCustomer, $idEmployee, $token)
    {
        return self::parseAndVerify($idCustomer, $idEmployee, $token) !== null;
    }

    /**
     * Validate and mark the jti as used. Returns false if invalid or already used.
     *
     * @param int    $idCustomer
     * @param int    $idEmployee
     * @param string $token
     *
     * @return bool
     */
    public static function consume($idCustomer, $idEmployee, $token)
    {
        $result = self::redeem($idCustomer, $idEmployee, $token);

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
     *
     * @return array{status: string, jti: string|null}
     */
    public static function redeem($idCustomer, $idEmployee, $token)
    {
        // Skip ledger read here — markUsed's INSERT is the atomic claim.
        $parsed = self::parseAndVerify($idCustomer, $idEmployee, $token, false);
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
     *
     * @return array{expiry: int, jti: string}|null
     */
    private static function parseAndVerify($idCustomer, $idEmployee, $token, $checkUsed = true)
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

        if (!self::hashEquals(self::sign((int) $idCustomer, (int) $idEmployee, $expiry, $jti), $signature)) {
            return null;
        }

        if ($checkUsed && self::isUsed($jti)) {
            return null;
        }

        return array('expiry' => $expiry, 'jti' => $jti);
    }

    /**
     * Configured link lifetime in seconds, clamped to a sane range.
     *
     * @return int
     */
    public static function ttlSeconds()
    {
        $minutes = (int) Configuration::get('LOGINASCUSTOMER_TTL');
        if ($minutes < 5) {
            $minutes = self::DEFAULT_TTL;
        } elseif ($minutes > self::MAX_TTL) {
            $minutes = self::MAX_TTL;
        }

        return $minutes * 60;
    }

    /**
     * Ensure the HMAC secret exists (install / upgrade).
     *
     * @return string
     */
    public static function ensureSecret()
    {
        $secret = (string) Configuration::get('LOGINASCUSTOMER_SECRET');
        if ($secret === '' || strlen($secret) < 32) {
            $secret = bin2hex(random_bytes(32));
            Configuration::updateValue('LOGINASCUSTOMER_SECRET', $secret);
        }

        return $secret;
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
     *
     * @return string
     */
    private static function sign($idCustomer, $idEmployee, $expiry, $jti)
    {
        $payload = $idCustomer . '|' . $idEmployee . '|' . $expiry . '|' . $jti;

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
        $configured = '';
        if (class_exists('Configuration', false)) {
            $configured = (string) Configuration::get('LOGINASCUSTOMER_SECRET');
        }
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
