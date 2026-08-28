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
 * minted for, only while it is unexpired, and never when its signature has been
 * touched — the whole point of replacing the shop-lifetime constant the 1.x
 * module trusted.
 */

define('_PS_VERSION_', '8.1.0');
define('_COOKIE_KEY_', 'unit-test-cookie-key-0123456789ABCDEF');

class Configuration
{
    public static $store = array('LOGINASCUSTOMER_TTL' => 60);
    public static function get($k, $l = null, $s = null, $sh = null, $default = false)
    {
        return array_key_exists($k, self::$store) ? self::$store[$k] : false;
    }
}

require_once dirname(__DIR__) . '/classes/LoginAsCustomerToken.php';

/** Mirror of the class's secret + signature, so the test can forge tokens with
 *  chosen expiries (expired / far-future) that the private signer would accept. */
function test_secret()
{
    $key = defined('_COOKIE_KEY_') ? _COOKIE_KEY_ : '';
    if (defined('_COOKIE_IV_')) {
        $key .= _COOKIE_IV_;
    }
    return $key . '|loginascustomer';
}
function test_sign($idc, $ide, $exp)
{
    return hash_hmac('sha256', ((int) $idc) . '|' . ((int) $ide) . '|' . ((int) $exp), test_secret());
}
function forge($idc, $ide, $exp)
{
    return $exp . '.' . test_sign($idc, $ide, $exp);
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

$now = time();

echo "1) generate() round-trips\n";
$token = LoginAsCustomerToken::generate(42, 7);
ok(strpos($token, '.') !== false, 'token has the expiry.signature shape');
ok(LoginAsCustomerToken::isValid(42, 7, $token), 'valid for the (customer, employee) it was minted for');

echo "\n2) A token opens only its own customer / employee\n";
ok(!LoginAsCustomerToken::isValid(43, 7, $token), 'rejected for a different customer id');
ok(!LoginAsCustomerToken::isValid(42, 8, $token), 'rejected for a different employee id');

echo "\n3) Tampering with the signature or the expiry fails\n";
$parts = explode('.', $token, 2);
$badSig = $parts[0] . '.' . strrev($parts[1]);
ok(!LoginAsCustomerToken::isValid(42, 7, $badSig), 'rejected when the signature is altered');
$movedExpiry = ((int) $parts[0] + 10000) . '.' . $parts[1];
ok(!LoginAsCustomerToken::isValid(42, 7, $movedExpiry), 'rejected when the expiry is pushed out (signature no longer matches)');

echo "\n4) Malformed tokens are rejected, not fatal\n";
ok(!LoginAsCustomerToken::isValid(42, 7, ''), 'empty string');
ok(!LoginAsCustomerToken::isValid(42, 7, 'no-dot-here'), 'no separator');
ok(!LoginAsCustomerToken::isValid(42, 7, '.abc'), 'empty expiry');
ok(!LoginAsCustomerToken::isValid(42, 7, '123.'), 'empty signature');
ok(!LoginAsCustomerToken::isValid(42, 7, '0.' . test_sign(42, 7, 0)), 'zero expiry');

echo "\n5) Expiry is enforced\n";
ok(LoginAsCustomerToken::isValid(42, 7, forge(42, 7, $now + 100)), 'a correctly signed, unexpired token passes');
ok(!LoginAsCustomerToken::isValid(42, 7, forge(42, 7, $now - 10)), 'a correctly signed but expired token is rejected');
ok(!LoginAsCustomerToken::isValid(42, 7, forge(42, 7, $now + (1440 * 60) + 5000)), 'an absurd far-future expiry is rejected even with a valid signature');

echo "\n6) ttlSeconds() clamps the configured lifetime\n";
Configuration::$store['LOGINASCUSTOMER_TTL'] = 0;
ok(LoginAsCustomerToken::ttlSeconds() === 60 * 60, 'a zero/blank lifetime falls back to 60 minutes');
Configuration::$store['LOGINASCUSTOMER_TTL'] = 5;
ok(LoginAsCustomerToken::ttlSeconds() === 5 * 60, 'the low end (5 minutes) is honoured');
Configuration::$store['LOGINASCUSTOMER_TTL'] = 99999;
ok(LoginAsCustomerToken::ttlSeconds() === 1440 * 60, 'an over-large lifetime is clamped to 1440 minutes');
Configuration::$store['LOGINASCUSTOMER_TTL'] = 60;

echo "\n" . ($fail === 0 ? "OK - all passed\n" : "$fail test(s) FAILED\n");
exit($fail === 0 ? 0 : 1);
