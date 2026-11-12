<?php
/**
 * Login As Customer - One-Click Support Access
 *
 * @author    MEG Venture <info@megventure.com>
 * @copyright 2019-2026 MEG Venture
 * @license   https://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 *
 * Guards the multistore rules. Runs on plain PHP: `php tests/MultistoreTest.php`.
 *
 * With multistore a connect token must open only on the shop it was minted for,
 * a customer must be refused on a shop core would not let them log in on, the
 * secret must be installation-wide, and with ONE shop nothing may change.
 */

if (PHP_SAPI === 'cli' && !defined('_PS_VERSION_')) {
    define('_PS_VERSION_', '8.1.0'); // standalone run outside PrestaShop
}
if (!defined('_PS_VERSION_')) {
    exit;
}
define('_COOKIE_KEY_', 'unit-test-cookie-key-0123456789ABCDEF');

class Configuration
{
    /** @var array global values */
    public static $global = array(
        'LOGINASCUSTOMER_TTL' => 15,
        'LOGINASCUSTOMER_SECRET' => '0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef',
    );
    /** @var array per shop values: [idShop][key] */
    public static $shop = array();

    public static function get($k, $l = null, $g = null, $s = null)
    {
        if ($s && isset(self::$shop[$s]) && array_key_exists($k, self::$shop[$s])) {
            return self::$shop[$s][$k];
        }

        return array_key_exists($k, self::$global) ? self::$global[$k] : false;
    }

    public static function getGlobalValue($k, $l = null)
    {
        return array_key_exists($k, self::$global) ? self::$global[$k] : false;
    }

    public static function updateGlobalValue($k, $v)
    {
        self::$global[$k] = $v;

        return true;
    }

    public static function updateValue($k, $v)
    {
        self::$global[$k] = $v;

        return true;
    }
}

class Shop
{
    public static $active = false;
    public static $groups = array(1 => array('share_customer' => 0), 2 => array('share_customer' => 1));
    public static $shopGroup = array(1 => 1, 2 => 1, 3 => 2, 4 => 2);

    public static function isFeatureActive()
    {
        return self::$active;
    }

    public static function getGroupFromShop($idShop, $asId = true)
    {
        if (!isset(self::$shopGroup[$idShop])) {
            return false;
        }

        return $asId ? self::$shopGroup[$idShop] : self::$groups[self::$shopGroup[$idShop]];
    }
}

class Context
{
    public static $instance;

    public static function getContext()
    {
        if (self::$instance === null) {
            self::$instance = new stdClass();
            self::$instance->shop = new stdClass();
            self::$instance->shop->id = 1;
        }

        return self::$instance;
    }
}

/** Mirrors Employee::hasAuthOnShop() of 1.6.1 to 9.1: super admin or associated shop. */
class Employee
{
    public $id = 0;
    public $id_profile = 2;
    public $associated_shops = array();

    public function __construct($id, $profile, array $shops)
    {
        $this->id = $id;
        $this->id_profile = $profile;
        $this->associated_shops = $shops;
    }

    public function isSuperAdmin()
    {
        return $this->id_profile == 1;
    }

    public function hasAuthOnShop($idShop)
    {
        return $this->isSuperAdmin() || in_array($idShop, $this->associated_shops);
    }
}

require_once dirname(__DIR__) . '/classes/LoginAsCustomerToken.php';

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

function customer($idShop, $idGroup)
{
    $c = new stdClass();
    $c->id_shop = $idShop;
    $c->id_shop_group = $idGroup;

    return $c;
}

function at_shop($idShop)
{
    Context::getContext()->shop->id = $idShop;
}

LoginAsCustomerToken::resetTestLedger();

echo "1) One shop: the token is byte-for-byte what 1.0.x signed\n";
Shop::$active = false;
$t = LoginAsCustomerToken::generate(42, 7, 3, 1);
$p = explode('.', $t);
$legacySig = hash_hmac('sha256', '42|7|' . $p[0] . '|' . $p[1], Configuration::$global['LOGINASCUSTOMER_SECRET'] . '|loginascustomer');
ok($p[2] === $legacySig, 'payload has no shop segment when multistore is off, even if a shop id is passed');
ok(LoginAsCustomerToken::bindingShopId(3) === 0, 'bindingShopId() is 0 without multistore');
ok(LoginAsCustomerToken::isValid(42, 7, $t), 'redeems with no shop argument');
ok(LoginAsCustomerToken::isValid(42, 7, $t, 9), 'redeems whatever shop id the redeemer reports');
ok(LoginAsCustomerToken::customerBelongsToCurrentShop(customer(99, 99)), 'every customer may log in (no shop check)');

echo "\n2) Multistore: the token is bound to the shop it was minted for\n";
Shop::$active = true;
$t2 = LoginAsCustomerToken::generate(42, 7, 2, 1);
$p2 = explode('.', $t2);
ok($p2[2] !== $legacySig, 'signature differs from the unbound one');
ok(LoginAsCustomerToken::isValid(42, 7, $t2, 2), 'opens on the shop it was minted for');
ok(!LoginAsCustomerToken::isValid(42, 7, $t2, 1), 'refused on another shop');
ok(!LoginAsCustomerToken::isValid(42, 7, $t2, 0), 'refused when the redeemer reports no shop');
ok(!LoginAsCustomerToken::isValid(42, 7, $t2), 'refused when the redeemer passes no shop argument');
ok(!LoginAsCustomerToken::isValid(43, 7, $t2, 2), 'still customer-bound');
ok(!LoginAsCustomerToken::isValid(42, 8, $t2, 2), 'still employee-bound');
$unbound = $p[0] . '.' . $p[1] . '.' . $p[2];
ok(!LoginAsCustomerToken::isValid(42, 7, $unbound, 1), 'a token minted before multistore was switched on is refused');
$stripped = $p2[0] . '.' . $p2[1] . '.' . $legacySig;
ok(!LoginAsCustomerToken::isValid(42, 7, $stripped, 2), 'a bound token cannot be downgraded by swapping in an unbound signature');

echo "\n3) Multistore: single use and expiry still apply\n";
LoginAsCustomerToken::resetTestLedger();
$t3 = LoginAsCustomerToken::generate(10, 2, 4, 2);
ok(LoginAsCustomerToken::redeem(10, 2, $t3, 3)['status'] === 'invalid', 'redeem on the wrong shop is invalid and does not claim the jti');
ok(LoginAsCustomerToken::redeem(10, 2, $t3, 4)['status'] === 'consumed', 'redeem on the right shop succeeds afterwards (jti was not burned)');
ok(LoginAsCustomerToken::redeem(10, 2, $t3, 4)['status'] === 'already_used', 'second redeem is a replay');
$exp = time() - 5;
$jti = bin2hex(random_bytes(16));
$expired = $exp . '.' . $jti . '.' . hash_hmac('sha256', '10|2|' . $exp . '|' . $jti . '|4', Configuration::$global['LOGINASCUSTOMER_SECRET'] . '|loginascustomer');
ok(!LoginAsCustomerToken::isValid(10, 2, $expired, 4), 'an expired bound token is refused');

echo "\n4) Customer / shop rule (same as Customer::getByEmail)\n";
$may = array('LoginAsCustomerToken', 'customerMayLoginOnShop');
ok(call_user_func($may, 1, 1, 1, 1, false), 'own shop, no sharing: allowed');
ok(!call_user_func($may, 2, 1, 1, 1, false), 'other shop of the same group, no sharing: refused');
ok(!call_user_func($may, 3, 2, 1, 1, false), 'shop of another group, no sharing: refused');
ok(call_user_func($may, 3, 2, 4, 2, true), 'other shop of the same group, sharing on: allowed');
ok(!call_user_func($may, 1, 1, 4, 2, true), 'customer of another group, sharing on: refused');
ok(!call_user_func($may, 1, 1, 0, 0, false), 'no current shop: refused');
ok(!call_user_func($may, 0, 0, 1, 1, false), 'customer without a shop: refused');
ok(!call_user_func($may, 1, 0, 1, 0, true), 'sharing on but no group known: refused');

echo "\n5) customerBelongsToCurrentShop() reads the request's shop and its group flag\n";
Shop::$active = true;
at_shop(1);
ok(LoginAsCustomerToken::customerBelongsToCurrentShop(customer(1, 1)), 'shop 1 customer on shop 1');
ok(!LoginAsCustomerToken::customerBelongsToCurrentShop(customer(2, 1)), 'shop 2 customer on shop 1 (group does not share)');
at_shop(4);
ok(LoginAsCustomerToken::customerBelongsToCurrentShop(customer(3, 2)), 'shop 3 customer on shop 4 (group shares customers)');
ok(!LoginAsCustomerToken::customerBelongsToCurrentShop(customer(1, 1)), 'shop 1 customer on shop 4');
at_shop(77);
ok(!LoginAsCustomerToken::customerBelongsToCurrentShop(customer(1, 1)), 'unknown current shop: refused');
at_shop(1);

echo "\n6) Lifetime is read from the customer's shop with multistore\n";
Configuration::$shop[2] = array('LOGINASCUSTOMER_TTL' => 30);
Shop::$active = true;
ok(LoginAsCustomerToken::ttlSeconds(2, 1) === 30 * 60, 'shop 2 value used');
ok(LoginAsCustomerToken::ttlSeconds(3, 2) === 15 * 60, 'shop without its own value falls back to the global one');
$tt = LoginAsCustomerToken::generate(5, 1, 2, 1);
ok(((int) explode('.', $tt)[0]) - time() >= 30 * 60 - 2, 'a token minted for shop 2 expires after shop 2 lifetime');
Shop::$active = false;
ok(LoginAsCustomerToken::ttlSeconds(2, 1) === 15 * 60, 'one shop: the shop argument is ignored');
Shop::$active = true;

echo "\n7) The secret is the global value\n";
Configuration::$shop[2]['LOGINASCUSTOMER_SECRET'] = 'shop-two-secret-shop-two-secret-shop-two-secret!!';
$g = Configuration::$global['LOGINASCUSTOMER_SECRET'];
ok(LoginAsCustomerToken::readSecret() === $g, 'readSecret() ignores a per-shop row');
ok(LoginAsCustomerToken::ensureSecret() === $g, 'ensureSecret() keeps an existing global secret');
$saved = Configuration::$global['LOGINASCUSTOMER_SECRET'];
unset(Configuration::$global['LOGINASCUSTOMER_SECRET']);
$new = LoginAsCustomerToken::ensureSecret();
ok(strlen($new) >= 32 && Configuration::$global['LOGINASCUSTOMER_SECRET'] === $new, 'a missing secret is recreated as a global value');
ok(LoginAsCustomerToken::readSecret() === $new, 'and read back from there');
Configuration::$global['LOGINASCUSTOMER_SECRET'] = $saved;

echo "\n8) forceShopHttps() never leaves a bearer link in clear\n";
$u = 'http://b.example.com/en/module/loginascustomer/login?x=1';
ok(LoginAsCustomerToken::forceShopHttps($u, 'b.example.com', 'secure.b.example.com') === 'https://secure.b.example.com/en/module/loginascustomer/login?x=1', 'http link of the shop becomes https on its SSL domain');
ok(LoginAsCustomerToken::forceShopHttps($u, 'b.example.com', '') === $u, 'no SSL domain: unchanged');
ok(LoginAsCustomerToken::forceShopHttps('https://b.example.com/x', 'b.example.com', 'b.example.com') === 'https://b.example.com/x', 'already https: unchanged');
ok(LoginAsCustomerToken::forceShopHttps($u, 'a.example.com', 'a.example.com') === $u, 'another shop domain: unchanged');

echo "\n9) Source assertions\n";
$main = file_get_contents(dirname(__DIR__) . '/loginascustomer.php');
$ctrl = file_get_contents(dirname(__DIR__) . '/controllers/front/login.php');
$cls = file_get_contents(dirname(__DIR__) . '/classes/LoginAsCustomerToken.php');
$main = str_replace("\r\n", "\n", $main);
$ctrl = str_replace("\r\n", "\n", $ctrl);
$cls = str_replace("\r\n", "\n", $cls);
ok(strpos($main, "'login',\n            array(\n                'id_customer' => \$idCustomer,\n                'id_employee' => \$idEmployee,\n                'lac_token' => \$token,\n            ),\n            \$ssl,\n            \$idLang > 0 ? \$idLang : null,\n            \$idShop\n        )") !== false, 'the multistore link passes the customer shop id (and its language) to getModuleLink');
ok(strpos($main, 'LoginAsCustomerToken::generate($idCustomer, $idEmployee, $idShop, $idGroup)') !== false, 'the multistore token is minted for the customer shop');
ok(strpos($main, "Configuration::get('PS_SSL_ENABLED', null, \$idGroup, \$idShop)") !== false, 'SSL is read for the customer shop');
ok(strpos($main, 'if (LoginAsCustomerToken::multistoreActive()) {' . "\n" . '            return $this->buildMultistoreConnectLink') !== false, 'the multistore branch is gated on the feature');
ok(strpos($main, 'if (!Shop::isFeatureActive() || Shop::getContext() == Shop::CONTEXT_SHOP) {' . "\n" . "            return '';") !== false, 'the back-office notice is gated on multistore + non-shop context');
ok(strpos($main, 'alert alert-warning') !== false, 'the notice uses the back-office alert class');
ok(strpos($main, "\$this->version = '1.1.1'") !== false, 'module version is 1.1.1');
ok(strpos($ctrl, 'LoginAsCustomerToken::customerBelongsToCurrentShop($target)') !== false, 'the front controller checks the customer against the current shop');
ok(strpos($ctrl, 'LoginAsCustomerToken::redeem($idCustomer, $idEmployee, $token, $idShop)') !== false, 'the front controller redeems with the current shop id');
ok(strpos($ctrl, '(int) $this->context->shop->id') !== false, 'the redeeming shop id comes from the request context');
ok(strpos($ctrl, 'Employee($idEmployee)') !== false && strpos($ctrl, '$employee->active') !== false, 'employee check is still there');
ok(strpos($ctrl, 'refused cross-shop connect') !== false, 'a cross-shop refusal is written to the log');
$posShop = strpos($ctrl, 'customerBelongsToCurrentShop');
$posRedeem = strpos($ctrl, 'LoginAsCustomerToken::redeem(');
ok($posShop !== false && $posRedeem !== false && $posShop < $posRedeem, 'the shop check runs before the jti is claimed');
ok(strpos($cls, 'Configuration::updateGlobalValue(\'LOGINASCUSTOMER_SECRET\'') !== false, 'the secret is written as a global value');
ok(strpos($cls, 'Configuration::getGlobalValue(\'LOGINASCUSTOMER_SECRET\')') !== false, 'the secret is read as a global value');
ok(strpos($cls, "(`id_shop` IS NOT NULL OR `id_shop_group` IS NOT NULL)") !== false, 'the upgrade promotes an existing per-shop secret');
ok(preg_match('/getValue\(\s*\'SELECT[^;]*?LIMIT/s', $cls) === 0, 'no LIMIT inside a getValue() query');
$up = file_get_contents(dirname(__DIR__) . '/upgrade/upgrade-1.1.0.php');
ok(strpos($up, 'function upgrade_module_1_1_0(') !== false && strpos($up, 'ensureSecret()') !== false, 'upgrade-1.1.0.php migrates the secret via ensureSecret()');
ok(strpos($up, 'deleteByName') === false && stripos($up, 'DELETE ') === false, 'the upgrade deletes nothing');

echo "\n10) An employee may only mint a link for a customer of a shop they can manage\n";
$superAdmin = new Employee(1, 1, array());
$shopAOnly = new Employee(2, 2, array(1));
$allShops = new Employee(3, 2, array(1, 2, 3, 4));
$nobody = new Employee(4, 2, array());
Shop::$active = false;
ok(LoginAsCustomerToken::employeeMayAccessShop($shopAOnly, 2), 'one shop: unchanged, always allowed');
ok(LoginAsCustomerToken::employeeMayAccessShop(null, 2), 'one shop: not even an employee object is needed');
Shop::$active = true;
ok(LoginAsCustomerToken::employeeMayAccessShop($superAdmin, 2), 'multistore: super administrator may act on any shop');
ok(LoginAsCustomerToken::employeeMayAccessShop($allShops, 4), 'multistore: employee associated with every shop may act on any shop');
ok(LoginAsCustomerToken::employeeMayAccessShop($shopAOnly, 1), 'multistore: employee restricted to shop 1 may act on shop 1');
ok(!LoginAsCustomerToken::employeeMayAccessShop($shopAOnly, 2), 'multistore: employee restricted to shop 1 is refused for a shop 2 customer');
ok(!LoginAsCustomerToken::employeeMayAccessShop($nobody, 1), 'multistore: employee with no shop association is refused');
ok(!LoginAsCustomerToken::employeeMayAccessShop($superAdmin, 0), 'multistore: a customer without a shop is refused');
ok(!LoginAsCustomerToken::employeeMayAccessShop(null, 1), 'multistore: no employee is refused');
ok(!LoginAsCustomerToken::employeeMayAccessShop(new Employee(0, 1, array()), 1), 'multistore: an unloaded employee is refused');
ok(!LoginAsCustomerToken::employeeMayAccessShop(new stdClass(), 1), 'multistore: an object without hasAuthOnShop() is refused');
Shop::$active = false;

ok(strpos($main, 'LoginAsCustomerToken::employeeMayAccessShop(') !== false, 'the module checks employee shop access');
$posMint = strpos($main, 'private function buildMultistoreConnectLink');
$posCheck = strpos($main, '$this->employeeMayConnect($customer)', $posMint);
$posGen = strpos($main, 'LoginAsCustomerToken::generate($idCustomer, $idEmployee, $idShop, $idGroup)');
ok($posMint !== false && $posCheck !== false && $posGen !== false && $posCheck < $posGen, 'the employee check runs before the multistore token is minted');
ok(strpos($main, 'multistoreActive() && !$this->employeeMayConnect($customer)') !== false, 'the connect panel is replaced by a notice for a refused employee');
ok(strpos($main, 'has no access to shop #%d of customer #%d') !== false, 'a refused mint is written to the log');
ok(strpos($ctrl, 'LoginAsCustomerToken::employeeMayAccessShop($employee, (int) $target->id_shop)') !== false, 'the front controller re-checks the employee against the customer shop');
$posEmp = strpos($ctrl, 'employeeMayAccessShop');
ok($posEmp !== false && $posEmp < $posRedeem, 'that check runs before the jti is claimed');
ok(strpos($cls, 'hasAuthOnShop((int) $idShop)') !== false, 'the helper defers to Employee::hasAuthOnShop()');
foreach (glob(dirname(__DIR__) . '/translations/*.php') as $tf) {
    if (basename($tf) === 'index.php') {
        continue;
    }
    $tx = file_get_contents($tf);
    ok(strpos($tx, '>loginascustomer_' . md5('You cannot log in as this customer: the customer belongs to a shop that your employee account is not allowed to manage.') . "'") !== false, 'refusal message translated in ' . basename($tf));
}

echo "\n" . ($fail === 0 ? "OK - all passed\n" : "$fail test(s) FAILED\n");
exit($fail === 0 ? 0 : 1);
