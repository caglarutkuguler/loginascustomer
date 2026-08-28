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
 * Signed, time-limited, customer-bound connect token.
 *
 * The original Team Ever module authorised the "log in as customer" front
 * controller with Tools::encrypt('everpscustomerconnect/everlogin') — a value
 * that is CONSTANT for the whole life of the shop and was printed into every
 * back-office order and customer page. Anyone who ever saw it could silently
 * log in as ANY customer just by changing the id in the URL.
 *
 * This replaces it with an HMAC over (id_customer, id_employee, expiry) keyed
 * on the shop secret (_COOKIE_KEY_), which is never exposed. A token therefore:
 *   - only opens the one customer it was minted for (id is inside the signature);
 *   - expires after a configurable window (default 60 minutes);
 *   - cannot be forged or guessed without the shop secret;
 *   - records which employee minted it, for the audit log.
 *
 * It stores nothing: the token is self-contained and verified by recomputation.
 */
class LoginAsCustomerToken
{
    /** Absolute upper bound on a token's future expiry, as a sanity guard. */
    const MAX_TTL = 1440; // minutes (24h)

    /** Fallback lifetime when the configuration value is missing or invalid. */
    const DEFAULT_TTL = 60; // minutes

    /**
     * @param int $idCustomer
     * @param int $idEmployee
     *
     * @return string "<expiry>.<hexsignature>"
     */
    public static function generate($idCustomer, $idEmployee)
    {
        $expiry = time() + self::ttlSeconds();

        return $expiry . '.' . self::sign((int) $idCustomer, (int) $idEmployee, $expiry);
    }

    /**
     * @param int    $idCustomer
     * @param int    $idEmployee
     * @param string $token
     *
     * @return bool
     */
    public static function isValid($idCustomer, $idEmployee, $token)
    {
        $token = (string) $token;
        $dot = strpos($token, '.');
        if ($dot === false) {
            return false;
        }

        $expiry = (int) substr($token, 0, $dot);
        $signature = substr($token, $dot + 1);
        if ($expiry <= 0 || $signature === '') {
            return false;
        }

        $now = time();
        if ($expiry < $now) {
            return false; // expired
        }
        if ($expiry > $now + self::MAX_TTL * 60) {
            return false; // absurd future value — reject even though the signature would fail anyway
        }

        return self::hashEquals(self::sign((int) $idCustomer, (int) $idEmployee, $expiry), $signature);
    }

    /**
     * Configured link lifetime in seconds, clamped to a sane range. Kept in one
     * place so the module and the front controller can never disagree.
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
     * @param int $idCustomer
     * @param int $idEmployee
     * @param int $expiry
     *
     * @return string
     */
    private static function sign($idCustomer, $idEmployee, $expiry)
    {
        $payload = $idCustomer . '|' . $idEmployee . '|' . $expiry;

        return hash_hmac('sha256', $payload, self::secret());
    }

    /**
     * The shop's own secret, never printed anywhere. Falls back gracefully if a
     * constant is missing on a very old core.
     *
     * @return string
     */
    private static function secret()
    {
        $key = defined('_COOKIE_KEY_') ? _COOKIE_KEY_ : '';
        if (defined('_COOKIE_IV_')) {
            $key .= _COOKIE_IV_;
        }

        return $key . '|loginascustomer';
    }

    /**
     * Constant-time comparison with a fallback for PHP < 5.6 (PrestaShop 1.6 can
     * run on such versions), so token verification never leaks timing.
     *
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
