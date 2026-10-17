<?php
/**
 * Login As Customer - One-Click Support Access
 *
 * @author    MEG Venture <info@megventure.com>
 * @copyright 2019-2026 MEG Venture
 * @license   https://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 *
 * Guards the connect-token crypto. Runs on plain PHP: `php tests/TokenTest.php`.
 *
 * What it is here to catch: a token must open only the one customer it was
 * minted for, only while it is unexpired, only once (single-use jti), and never
 * when its signature has been touched.
 */

define('_PS_VERSION_', '8.1.0');
define('_COOKIE_KEY_', 'unit-test-cookie-key-0123456789ABCDEF');

class Configuration
{
    public static $store = array(
        'LOGINASCUSTOMER_TTL' => 15,
        'LOGINASCUSTOMER_SECRET' => '0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef',
    );

    public static function get($k, $l = null, $s = null, $sh = null, $default = false)
    {
        return array_key_exists($k, self::$store) ? self::$store[$k] : false;
    }

    public static function updateValue($k, $v)
    {
        self::$store[$k] = $v;

        return true;
    }
}

require_once dirname(__DIR__) . '/classes/LoginAsCustomerToken.php';

/** Mirror of the class's secret + signature for forged tokens. */
function test_secret()
{
    $configured = (string) Configuration::get('LOGINASCUSTOMER_SECRET');
    if ($configured !== '' && strlen($configured) >= 32) {
        return $configured . '|loginascustomer';
    }
    $key = defined('_COOKIE_KEY_') ? _COOKIE_KEY_ : '';
    if (defined('_COOKIE_IV_')) {
        $key .= _COOKIE_IV_;
    }

    return $key . '|loginascustomer';
}

function test_sign($idc, $ide, $exp, $jti)
{
    return hash_hmac('sha256', ((int) $idc) . '|' . ((int) $ide) . '|' . ((int) $exp) . '|' . $jti, test_secret());
}

function forge($idc, $ide, $exp, $jti = null)
{
    if ($jti === null) {
        $jti = bin2hex(random_bytes(16));
    }

    return $exp . '.' . $jti . '.' . test_sign($idc, $ide, $exp, $jti);
}

$fail = 0;
function ok($cond, $label)
{
    global $fail;
    if ($cond) {
        echo "  ok   $label\n";
    } else {
        echo "  FAIL $label\n";
        $fail++;
    }
}

LoginAsCustomerToken::resetTestLedger();
$now = time();

echo "1) generate() round-trips (expiry.jti.sig)\n";
$token = LoginAsCustomerToken::generate(42, 7);
$parts = explode('.', $token);
ok(count($parts) === 3, 'token has the expiry.jti.signature shape');
ok(preg_match('/^[a-f0-9]{32}$/', $parts[1]), 'jti is 32 hex chars');
ok(LoginAsCustomerToken::isValid(42, 7, $token), 'valid for the (customer, employee) it was minted for');

echo "\n2) A token opens only its own customer / employee\n";
ok(!LoginAsCustomerToken::isValid(43, 7, $token), 'rejected for a different customer id');
ok(!LoginAsCustomerToken::isValid(42, 8, $token), 'rejected for a different employee id');

echo "\n3) Tampering with the signature, expiry or jti fails\n";
$badSig = $parts[0] . '.' . $parts[1] . '.' . strrev($parts[2]);
ok(!LoginAsCustomerToken::isValid(42, 7, $badSig), 'rejected when the signature is altered');
$movedExpiry = ((int) $parts[0] + 10000) . '.' . $parts[1] . '.' . $parts[2];
ok(!LoginAsCustomerToken::isValid(42, 7, $movedExpiry), 'rejected when the expiry is pushed out');
$movedJti = $parts[0] . '.' . str_repeat('a', 32) . '.' . $parts[2];
ok(!LoginAsCustomerToken::isValid(42, 7, $movedJti), 'rejected when the jti is swapped');

echo "\n4) Malformed tokens are rejected, not fatal\n";
ok(!LoginAsCustomerToken::isValid(42, 7, ''), 'empty string');
ok(!LoginAsCustomerToken::isValid(42, 7, 'no-dot-here'), 'no separator');
ok(!LoginAsCustomerToken::isValid(42, 7, '.abc'), 'empty expiry');
ok(!LoginAsCustomerToken::isValid(42, 7, '123.'), 'two-part legacy shape');
ok(!LoginAsCustomerToken::isValid(42, 7, '0.' . str_repeat('b', 32) . '.' . test_sign(42, 7, 0, str_repeat('b', 32))), 'zero expiry');

echo "\n5) Expiry is enforced\n";
ok(LoginAsCustomerToken::isValid(42, 7, forge(42, 7, $now + 100)), 'a correctly signed, unexpired token passes');
ok(!LoginAsCustomerToken::isValid(42, 7, forge(42, 7, $now - 10)), 'a correctly signed but expired token is rejected');
ok(!LoginAsCustomerToken::isValid(42, 7, forge(42, 7, $now + (1440 * 60) + 5000)), 'an absurd far-future expiry is rejected');

echo "\n6) ttlSeconds() clamps the configured lifetime (default 15)\n";
Configuration::$store['LOGINASCUSTOMER_TTL'] = 0;
ok(LoginAsCustomerToken::ttlSeconds() === 15 * 60, 'a zero/blank lifetime falls back to 15 minutes');
Configuration::$store['LOGINASCUSTOMER_TTL'] = 5;
ok(LoginAsCustomerToken::ttlSeconds() === 5 * 60, 'the low end (5 minutes) is honoured');
Configuration::$store['LOGINASCUSTOMER_TTL'] = 99999;
ok(LoginAsCustomerToken::ttlSeconds() === 1440 * 60, 'an over-large lifetime is clamped to 1440 minutes');
Configuration::$store['LOGINASCUSTOMER_TTL'] = 15;

echo "\n7) consume() is single-use\n";
LoginAsCustomerToken::resetTestLedger();
$once = LoginAsCustomerToken::generate(99, 3);
ok(LoginAsCustomerToken::consume(99, 3, $once), 'first consume succeeds');
ok(!LoginAsCustomerToken::consume(99, 3, $once), 'second consume of the same token fails');
ok(!LoginAsCustomerToken::isValid(99, 3, $once), 'isValid rejects a used token');

echo "\n8) redeem() returns jti and distinguishes replay\n";
LoginAsCustomerToken::resetTestLedger();
$once2 = LoginAsCustomerToken::generate(55, 2);
$parts2 = explode('.', $once2);
$first = LoginAsCustomerToken::redeem(55, 2, $once2);
ok($first['status'] === 'consumed', 'redeem status is consumed on first use');
ok($first['jti'] === $parts2[1], 'redeem returns the token jti');
$second = LoginAsCustomerToken::redeem(55, 2, $once2);
ok($second['status'] === 'already_used', 'redeem status is already_used on replay');
ok($second['jti'] === $parts2[1], 'replay redeem returns the same jti');
$bad = LoginAsCustomerToken::redeem(55, 2, 'not-a-token');
ok($bad['status'] === 'invalid' && $bad['jti'] === null, 'redeem status is invalid for garbage');

echo "\n9) Legacy two-part tokens are rejected after 1.0.3\n";
$legacy = ($now + 100) . '.' . hash_hmac('sha256', '42|7|' . ($now + 100), test_secret());
ok(!LoginAsCustomerToken::isValid(42, 7, $legacy), 'pre-1.0.3 expiry.sig shape fails');

echo "\n" . ($fail === 0 ? "OK - all passed\n" : "$fail test(s) FAILED\n");
exit($fail === 0 ? 0 : 1);
